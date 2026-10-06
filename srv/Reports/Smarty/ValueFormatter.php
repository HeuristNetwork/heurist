<?php
/**
* ValueFormatter.php - The {wrap} and {out} functions of report templates
*
* {out lbl= var=}: label and value in a div (ReportExecute::printLabelValuePair).
* {wrap var= dt=url|file|geo|date mode= lbl= style= width= height= limit= fancybox=
*       calendar= info= auto_play= show_artwork=}: links, media players/images,
*       map links, dates and CMS content (ReportExecute::printProcessedValue,
*       processFieldFile, prepareCMScontent; recordFile.php fileGetPlayerTag,
*       getPlayerURL, detect3D_byExt).
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

use Heurist\Utilities\Temporal;

/** Formats values for {wrap} and {out}. */
final class ValueFormatter
{
    private const MT_YOUTUBE = 'video/youtube';
    private const MT_VIMEO = 'video/vimeo';
    private const MT_SOUNDCLOUD = 'audio/soundcloud';
    private const ULF_IIIF = '_iiif';
    private const ULF_REMOTE = '_remote';
    private const FILE_INFO_TYPES = array('caption', 'copyright', 'copyowner', 'description');
    private const VIEWERS_3D = array('obj', '3ds', 'stl', 'ply', 'gltf', 'glb', 'off', '3dm', 'fbx', 'dae',
        'wrl', '3mf', 'ifc', 'brep', 'step', 'iges', 'fcstd', 'bim');

    private ReportEnvironment $environment;

    /** @param ReportEnvironment $environment Installation values. */
    public function __construct(ReportEnvironment $environment)
    {
        $this->environment = $environment;
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
                $value = $params['var'];
                if(is_array($value) && !empty($value['wkt']) && class_exists('\geoPHP')){
                    $geometry = \geoPHP::load($value['wkt'], 'wkt');
                    if($geometry && !$geometry->isEmpty()){
                        $point = $geometry->centroid();
                        return '<a href="https://maps.google.com/maps?z=18&q='.$point->y().','.$point->x()
                            .'" target="_blank" rel="noopener">'.($label === '' ? 'on map' : $label).'</a>';
                    }
                }
                return '';
            case 'date':
                $value = $params['var'];
                if(is_array($value) && array_key_exists(0, $value)){
                    $value = $value[0];
                }
                $content = Temporal::toHumanReadable($value, true, $mode ?? 1, '|', $params['calendar'] ?? null);
                return ($label !== '' ? $label.': ' : '').$content.'<br>';
            default:
                $content = is_string($params['var']) ? json_decode($params['var'], true) : $params['var'];
                $content = $this->cmsContent(is_array($content) ? $content : $params['var']);
                return ($label !== '' ? $label.': ' : '').$content.'<br>';
        }
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
            $thumbUrl = $this->environment->thumbnailUrl($nonce);
            $fileUrl = $this->environment->fileUrl($nonce);
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
                $result .= $this->playerTag($nonce, $mimeType, $params, $external, $size, $style);
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

    /** Player, image or link of one file (recordFile.php fileGetPlayerTag). */
    private function playerTag(string $nonce, string $mimeType, array $params, string $external, string $size, string $style): string
    {
        $first = is_array($params['var'] ?? null) && isset($params['var'][0]) && is_array($params['var'][0])
            ? $params['var'][0] : (is_array($params['var'] ?? null) ? $params['var'] : array());
        $isVideo = strpos($mimeType, 'video/') === 0;
        $isAudio = strpos($mimeType, 'audio/') === 0;
        $isImage = strpos($mimeType, 'image/') === 0;
        $isIiif = strpos((string)($first['ulf_OrigFileName'] ?? ''), self::ULF_IIIF) === 0
            || strpos((string)($first['ulf_PreferredSource'] ?? ''), 'iiif') === 0;
        $db = $this->environment->databaseName;

        $filePath = $external !== '' && strpos($external, 'http://') !== 0
            ? $external
            : $this->environment->fileUrl($nonce, true).'&fancybox=1';
        $thumbUrl = $this->environment->thumbnailUrl($nonce, true);
        $viewer3d = self::viewer3d((string)($first['ulf_MimeExt'] ?? ''));

        if($viewer3d !== ''){
            $playerUrl = $this->environment->baseUrl.'hclient/widgets/viewers/'.$viewer3d.'Viewer.php?db='.$db.'&file='.$nonce;
            $result = '<a href="'.$playerUrl.'" target="_blank"><img src="'.$thumbUrl.'" '.$style.'/></a>';
        }elseif($isVideo){
            if($size === '' && $style === ''){ $size = 'width="640px" height="480px"'; }
            if($mimeType === self::MT_YOUTUBE || $mimeType === self::MT_VIMEO
                || strpos($external, 'vimeo.com') > 0 || strpos($external, 'youtu.be') > 0 || strpos($external, 'youtube.com') > 0){
                $playerUrl = $this->playerUrl($mimeType, $external, $params);
                $result = "<iframe $size $style src=\"$playerUrl\" "
                    .' frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';
            }else{
                $autoplay = !empty($params['auto_play']) ? ' autoplay="autoplay"  loop="" muted="" ' : '';
                $fileId = $external !== '' ? '' : $nonce;
                $result = "<video $autoplay $size $style controls=\"controls\">\n"
                    ."    <source type=\"$mimeType\" src=\"$filePath\" data-id=\"$fileId\"/>\n"
                    ."    <img src=\"$thumbUrl\" width=\"320\" height=\"240\" title=\"No video playback capabilities\" />\n"
                    .'</video>';
            }
        }elseif($isAudio){
            if($mimeType === self::MT_SOUNDCLOUD || strpos($external, 'soundcloud.com') > 0){
                $playerUrl = $this->playerUrl($mimeType, $external, $params);
                $result = "<iframe $size $style src=\"$playerUrl\" frameborder=\"0\"></iframe>";
            }else{
                $autoplay = !empty($params['auto_play']) ? ' autoplay="autoplay"' : '';
                $fileId = $external !== '' ? '' : $nonce;
                $result = "<audio controls=\"controls\" $autoplay>\n"
                    ."    <source type=\"$mimeType\" src=\"$filePath\" data-id=\"$fileId\"/>\n"
                    ."    Your browser does not support the audio element\n"
                    .'</audio>';
            }
        }elseif($isIiif){
            if($size === '' && $style === ''){ $size = ' height="640" width="800" '; }
            $recordId = intval($first['rec_ID'] ?? 0);
            $viewer = $this->environment->baseUrl.'hclient/widgets/viewers/miradorViewer.php?db='.$db
                .'&id='.($recordId > 0 ? $recordId : $nonce);
            $result = "<iframe $size $style src=\"$viewer\" frameborder=\"0\"></iframe>";
        }elseif($isImage){
            if($size === '' && $style === ''){ $size = 'width="300"'; }
            $fancybox = !empty($params['fancybox'])
                ? ' class="fancybox-thumb" data-id="'.$nonce.'" '
                : ($external === '' ? ' data-id="'.$nonce.'" ' : '');
            $result = '<img '.$size.$style.' src="'.$filePath.'"'.$fancybox.'/>';
        }elseif($mimeType === 'application/pdf'){
            $fileId = $external !== '' ? '' : $nonce;
            $result = '<embed width="100%" height="100%" name="plugin" src="'.$filePath.'&embedplayer=1" data-id="'.$fileId
                .'" type="application/pdf" internalinstanceid="9">';
        }else{
            $result = '<a href="'.$filePath.'" target="_blank"><img src="'.$thumbUrl.'" '.$style.'/></a>';
        }

        if(!empty($params['fancybox'])){
            $result = '<div style="width:80%;height:90%">'.$result.'</div>';
        }
        return $result;
    }

    /** Embed URL of YouTube, Vimeo and SoundCloud (recordFile.php getPlayerURL). */
    private function playerUrl(string $mimeType, string $url, array $params): string
    {
        if($mimeType === self::MT_YOUTUBE || strpos($url, 'youtu.be') > 0 || strpos($url, 'youtube.com') > 0){
            preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $url, $matches);
            return 'https://www.youtube.com/embed/'.($matches[1] ?? '');
        }
        if($mimeType === self::MT_VIMEO || strpos($url, 'viemo.com') > 0){
            $context = stream_context_create(array('http' => array('timeout' => 5)));
            $json = @file_get_contents('https://vimeo.com/api/oembed.json?url='.rawurlencode($url), false, $context);
            $hash = is_string($json) ? json_decode($json, true) : null;
            $videoId = intval($hash['video_id'] ?? 0);
            return $videoId > 0 ? 'https://player.vimeo.com/video/'.$videoId : $url;
        }
        if($mimeType === self::MT_SOUNDCLOUD || strpos($url, 'soundcloud.com') > 0){
            $autoplay = '&amp;auto_play='.(!empty($params['auto_play']) ? 'true' : 'false');
            $artwork = '&amp;show_artwork='.(isset($params['show_artwork']) && $params['show_artwork'] == 0 ? 'false' : 'true');
            return 'https://w.soundcloud.com/player/?url='.$url.$autoplay
                .'&amp;hide_related=false&amp;show_comments=false&amp;show_user=false&amp;'
                .'show_reposts=false&amp;show_teaser=false&amp;visual=true'.$artwork;
        }
        return $url;
    }

    /** 3D viewer of a file extension: '3dhop', '3d' or ''. */
    public static function viewer3d(string $extension): string
    {
        if($extension === 'nxz' || $extension === 'nxs'){ return '3dhop'; }
        return in_array($extension, self::VIEWERS_3D, true) ? '3d' : '';
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
