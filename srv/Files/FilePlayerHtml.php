<?php
/**
* FilePlayerHtml.php - The server player of a registered file (plan 34)
*
* HTML of one file as the legacy fileGetPlayerTag (recordFile.php) made it, for report templates
* (`{wrap var=... dt="file" mode="player"}`, Custom reports and CMS Record cards): a YouTube / Vimeo / SoundCloud
* iframe, a video or audio player, an image, a PDF embed, a link to the 3D viewer, a Mirador iframe, or the
* thumbnail with a link. Moved from Reports\Smarty\ValueFormatter. Local files use the file endpoints
* (FileUrls): an image = `/web` (as legacy `?file=` gave it), video, audio, PDF and links = `/asset`, the
* thumbnail = `/thumbnail`; the viewers stay legacy pages. The client builds the same choice
* (heurist-explorer shared/src/site/cmsMedia.js, mediaTag.js; fixture shared/test/fixtures/file-players.json).
*
* @project     Heurist academic knowledge management system
* @package     Files
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Files;

/** Player, image or link of one registered file. */
final class FilePlayerHtml
{
    public const MT_YOUTUBE = 'video/youtube';
    public const MT_VIMEO = 'video/vimeo';
    public const MT_SOUNDCLOUD = 'audio/soundcloud';
    public const ULF_IIIF = '_iiif';

    private const VIEWERS_3D = array('obj', '3ds', 'stl', 'ply', 'gltf', 'glb', 'off', '3dm', 'fbx', 'dae',
        'wrl', '3mf', 'ifc', 'brep', 'step', 'iges', 'fcstd', 'bim');

    private string $database;

    /** Base URL of the file endpoints (the production URL of a report). */
    private string $fileBaseUrl;

    /** Base URL of the viewer pages. */
    private string $viewerBaseUrl;

    public function __construct(string $database, string $fileBaseUrl, string $viewerBaseUrl)
    {
        $this->database = $database;
        $this->fileBaseUrl = $fileBaseUrl;
        $this->viewerBaseUrl = $viewerBaseUrl;
    }

    /** 3D viewer of a file extension: '3dhop', '3d' or ''. */
    public static function viewer3d(string $extension): string
    {
        if($extension === 'nxz' || $extension === 'nxs'){ return '3dhop'; }
        return in_array($extension, self::VIEWERS_3D, true) ? '3d' : '';
    }

    /** URL of a file: asset | web | thumbnail | download (FileUrls::url, absolute). */
    public function url(string $key, string $kind): string
    {
        return FileUrls::url($key, $kind, $this->database, $this->fileBaseUrl);
    }

    /**
     * Player, image or link of one file (the legacy fileGetPlayerTag).
     *
     * @param array $params `var` (file rows: ulf_OrigFileName, ulf_PreferredSource, ulf_MimeExt, rec_ID),
     *        `auto_play`, `show_artwork`, `fancybox`.
     * @param string $size HTML size attributes; $style HTML style attribute.
     */
    public function tag(string $nonce, string $mimeType, array $params, string $external, string $size, string $style): string
    {
        $first = is_array($params['var'] ?? null) && isset($params['var'][0]) && is_array($params['var'][0])
            ? $params['var'][0] : (is_array($params['var'] ?? null) ? $params['var'] : array());
        $isVideo = strpos($mimeType, 'video/') === 0;
        $isAudio = strpos($mimeType, 'audio/') === 0;
        $isImage = strpos($mimeType, 'image/') === 0;
        $isIiif = strpos((string)($first['ulf_OrigFileName'] ?? ''), self::ULF_IIIF) === 0
            || strpos((string)($first['ulf_PreferredSource'] ?? ''), 'iiif') === 0;
        $db = $this->database;

        // an external resource (not plain http) is used as it is; else the file endpoint
        $useExternal = $external !== '' && strpos($external, 'http://') !== 0;
        $filePath = $useExternal ? $external : $this->url($nonce, 'asset');
        $imagePath = $useExternal ? $external : $this->url($nonce, 'web');
        $thumbUrl = $this->url($nonce, 'thumbnail');
        $viewer3d = self::viewer3d((string)($first['ulf_MimeExt'] ?? ''));

        if($viewer3d !== ''){
            $playerUrl = $this->viewerBaseUrl.'hclient/widgets/viewers/'.$viewer3d.'Viewer.php?db='.$db.'&file='.$nonce;
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
            $viewer = $this->viewerBaseUrl.'hclient/widgets/viewers/miradorViewer.php?db='.$db
                .'&id='.($recordId > 0 ? $recordId : $nonce);
            $result = "<iframe $size $style src=\"$viewer\" frameborder=\"0\"></iframe>";
        }elseif($isImage){
            if($size === '' && $style === ''){ $size = 'width="300"'; }
            $fancybox = !empty($params['fancybox'])
                ? ' class="fancybox-thumb" data-id="'.$nonce.'" '
                : ($external === '' ? ' data-id="'.$nonce.'" ' : '');
            $result = '<img '.$size.$style.' src="'.$imagePath.'"'.$fancybox.'/>';
        }elseif($mimeType === 'application/pdf'){
            $fileId = $external !== '' ? '' : $nonce;
            $result = '<embed width="100%" height="100%" name="plugin" src="'.$filePath.'" data-id="'.$fileId
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
}
