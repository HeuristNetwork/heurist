<?php
/**
* ValueFormatter.php - The {wrap} and {out} functions of report templates
*
* {out lbl= var=}: label and value in a div (ReportExecute::printLabelValuePair).
* {wrap var= dt=url|file|geo|date mode= lbl= style= width= height= limit= fancybox=
*       calendar= info= auto_play= show_artwork=}: links, media players/images,
*       map links, dates and CMS content (ReportExecute::printProcessedValue,
*       processFieldFile, prepareCMScontent). The player of a file: Files\FilePlayerHtml
*       (recordFile.php fileGetPlayerTag; file endpoints since plan 34).
*
* Difference from the legacy engine: a numeric width/height gets "px" (the
* legacy test `is_numeric($width)<0` was always false).
*
* @project     Heurist academic knowledge management system
* @package     Reports\Smarty
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Reports\Smarty;

use Heurist\Files\FilePlayerHtml;
use Heurist\Files\FileUrls;
use Heurist\Utilities\GeoText;
use Heurist\Utilities\Temporal;

/** Formats values for {wrap} and {out}. */
final class ValueFormatter
{
    private const ULF_IIIF = '_iiif';
    private const ULF_REMOTE = '_remote';
    private const FILE_INFO_TYPES = array('caption', 'copyright', 'copyowner', 'description');

    private ReportEnvironment $environment;

    /** Report language (3 letters): the language of date texts. */
    private string $language;

    /**
     * @param ReportEnvironment $environment Installation values.
     * @param string $language Report language (3 letters).
     */
    public function __construct(ReportEnvironment $environment, string $language = 'ENG')
    {
        $this->environment = $environment;
        $this->language = $language;
    }

    /**
     * {out lbl= var=}
     *
     * @param array $params
     * @param mixed $template Smarty template (unused).
     * @return string
     */
    public function out($params, $template = null): string
    {
        return !empty($params['var'])
            ? '<div><div class="tlbl">'.($params['lbl'] ?? '').': </div><b>'.$params['var'].'</b></div>'
            : '';
    }

    /**
     * {wrap var= dt= ...}
     *
     * @param array $params
     * @param mixed $template Smarty template (unused).
     * @return string
     */
    public function wrap($params, $template = null): string
    {
        if(!isset($params['var'])){ return ''; }
        $dt = $params['dt'] ?? null;
        $mode = $params['mode'] ?? null;
        $label = isset($params['lbl']) && $params['lbl'] !== '' ? (string)$params['lbl'] : '';
        $info = $params['info'] ?? null;
        $style = '';
        $size = '';

        if(isset($params['style']) && $params['style'] !== ''){
            $style = ' style="'.$params['style'].'"';
        }else{
            $width = isset($params['width']) && $params['width'] !== '' ? (string)$params['width'] : '';
            $height = isset($params['height']) && $params['height'] !== '' ? (string)$params['height'] : '';
            if(is_numeric($width)){ $width .= 'px'; }
            if(is_numeric($height)){ $height .= 'px'; }
            if($width === '' && $height === ''){
                if($mode !== 'thumbnail'){
                    $size = 'width='.($dt === 'geo' ? '200px' : "'300px'");
                }
            }else{
                if($width !== ''){ $size = "width='".$width."'"; }
                if($height !== ''){ $size .= " height='".$height."'"; }
            }
        }

        switch($dt){
            case 'url':
                return "<a href='{$params['var']}' target=_blank rel=noopener $style>{$params['var']}</a>";
            case 'file':
                return $this->files($params, $mode, $style, $size, $info);
            case 'geo':
                return $this->geo($params['var'], $mode, $label);
            case 'date':
                $value = $params['var'];
                if(is_array($value) && array_key_exists(0, $value)){
                    $value = $value[0];
                }
                $content = Temporal::toHumanReadable($value, true, $mode ?? 1, '|', $params['calendar'] ?? null, $this->language);
                return ($label !== '' ? $label.': ' : '').$content.'<br>';
            default:
                $content = is_string($params['var']) ? json_decode($params['var'], true) : $params['var'];
                $content = $this->cmsContent(is_array($content) ? $content : $params['var']);
                return ($label !== '' ? $label.': ' : '').$content.'<br>';
        }
    }

    /**
     * Geo values: their readable text (GeoText, the same text as the client shows), one per
     * line, after the label; mode="link" gives a map link at the centroid (label: the link
     * text, "on map" by default), which was the only output of the legacy engine.
     *
     * @param mixed $value WKT text, a geo value ['wkt'=>...] or a list of them (fN_originalvalue).
     * @param mixed $mode Null or "link".
     * @param string $label Label.
     * @return string
     */
    private function geo($value, $mode, string $label): string
    {
        $values = is_array($value) && array_key_exists(0, $value) ? $value : array($value);
        $result = array();
        foreach($values as $geo){
            $wkt = is_array($geo) ? (string)($geo['wkt'] ?? '') : (is_string($geo) ? $geo : '');
            if($wkt === ''){ continue; }
            if($mode !== 'link'){
                $result[] = htmlspecialchars(GeoText::summary($wkt));
                continue;
            }
            if(class_exists('\geoPHP')){
                $geometry = \geoPHP::load($wkt, 'wkt');
                if($geometry && !$geometry->isEmpty()){
                    $point = $geometry->centroid();
                    $result[] = '<a href="https://maps.google.com/maps?z=18&q='.$point->y().','.$point->x()
                        .'" target="_blank" rel="noopener">'.($label === '' ? 'on map' : $label).'</a>';
                }
            }
        }
        if(empty($result)){ return ''; }
        return ($mode !== 'link' && $label !== '' ? $label.': ' : '').implode('<br>', $result);
    }

    /** Images, players or links of uploaded files (ReportExecute::processFieldFile). */
    private function files(array $params, $mode, string $style, string $size, $info): string
    {
        $values = $params['var'];
        $limit = intval($params['limit'] ?? 0);
        if(is_string($info)){ $info = explode(',', $info); }
        if(!is_array($values) || !array_key_exists(0, $values)){ $values = array($values); }

        $result = '';
        foreach($values as $index => $file){
            if($limit > 0 && $index >= $limit){ break; }
            if(!is_array($file)){ continue; }
            $external = (string)($file['ulf_ExternalFileReference'] ?? '');
            $name = (string)($file['ulf_OrigFileName'] ?? '');
            $nonce = (string)($file['ulf_ObfuscatedFileID'] ?? '');
            $description = htmlspecialchars(strip_tags((string)($file['ulf_Description'] ?? '')));
            $mimeType = (string)($file['fxm_MimeType'] ?? '');
            // the file endpoints (plan 34): a link opens the file itself
            $thumbUrl = $this->environment->thumbnailUrl($nonce);
            $fileUrl = $this->environment->fileUrl($nonce, false, 'asset');
            $fancybox = !empty($params['fancybox']);

            if($mode === 'link'){
                $shown = ($name === '' || $name === self::ULF_REMOTE || strpos($name, self::ULF_IIIF) === 0) ? $external : $name;
                $result .= $fancybox
                    ? "<a class=\"fancybox-thumb\" data-id=\"$nonce\" href='".$fileUrl."' target=_blank rel=noopener title='".$description."' $style>$shown</a>"
                    : "<a href='$fileUrl' target=_blank rel=noopener title='$description' $style>$shown</a>";
            }elseif($mode === 'thumbnail'){
                $result .= $fancybox
                    ? "<img class=\"fancybox-thumb\" data-id=\"$nonce\" src=\"".$thumbUrl."\" title=\"".$description."\" $size $style/></a>"
                    : "<a href='$fileUrl' target=_blank rel=noopener><img class=\"\" src=\"".$thumbUrl."\" title=\"".$description."\" $size $style/></a>";
            }else{
                $result .= $this->player()->tag($nonce, $mimeType, $params, $external, $size, $style);
                if(is_array($info)){
                    foreach($info as $type){
                        if(in_array($type, self::FILE_INFO_TYPES, true)){
                            $text = htmlspecialchars(strip_tags((string)($file['ulf_'.ucfirst($type)] ?? '')), ENT_QUOTES, 'UTF-8');
                            if($text !== ''){
                                $result .= '<span class="file-info">'.$text.'</span>';
                            }
                        }
                    }
                }
            }

            if($fancybox && $this->environment->javaScriptAllowed){
                $result .= '<script>if(rec_Files)rec_Files.push({'
                    .'rec_ID:'.intval($file['rec_ID'] ?? 0)
                    .',id:"'.$nonce
                    .'",mimeType:"'.$mimeType
                    .'",mode_3d_viewer:"'.self::viewer3d((string)($file['ulf_MimeExt'] ?? ''))
                    .'",filename:"'.htmlspecialchars($name)
                    .'",external:"'.htmlspecialchars($external).'"});</script>';
            }
        }
        return $result;
    }

    /** 3D viewer of a file extension: '3dhop', '3d' or ''. */
    public static function viewer3d(string $extension): string
    {
        return FilePlayerHtml::viewer3d($extension);
    }

    /** The server player of files (plan 34): the file endpoints of the production URL, the viewers of Heurist. */
    private function player(): FilePlayerHtml
    {
        return new FilePlayerHtml($this->environment->databaseName, $this->environment->baseUrlPro ?: $this->environment->baseUrl,
            $this->environment->baseUrl);
    }

    /** HTML of CMS content (a JSON text element or group) with absolute links. */
    private function cmsContent($content)
    {
        $convertLinks = true;
        $text = '';
        if(is_array($content)){
            if(($content['type'] ?? null) === 'group' && is_array($content['children'] ?? null)){
                $convertLinks = false;
                $text = $this->cmsGroup($content['children']);
            }elseif(($content['type'] ?? null) === 'text'){
                $text = $content['content'] ?? '';
            }else{
                $convertLinks = false;
                $text = $this->cmsGroup($content);
            }
        }else{
            $text = $content;
        }
        if($convertLinks && $text !== null && is_string($text)){
            // file URLs as the absolute file endpoints (plan 34), other relative Heurist links absolute
            $text = FileUrls::resolveHtml($text, $this->environment->databaseName, $this->environment->baseUrl);
            $text = str_replace('./?db=', $this->environment->baseUrl.'?db=', $text);
        }
        return $text;
    }

    private function cmsGroup(array $content): string
    {
        $text = '';
        foreach($content as $element){
            $part = $this->cmsContent($element);
            if($part){
                $text .= '<br>'.(is_array($part) ? '' : $part);
            }
        }
        return $text;
    }
}
