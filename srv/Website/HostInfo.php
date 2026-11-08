<?php
/**
* HostInfo.php - The organisation that hosts this Heurist installation (plan 27f §9.6)
*
* Two files in the parent folder of the Heurist installation (the same rule as the legacy
* USystem::getHostLogoAndUrl): `organisation_logo.jpg` or `organisation_logo.png` and
* `organisation_url.txt`. The logo is served by the legacy address `?logo=host` of the
* Heurist base URL. Used by the badges widget with `source: host` (block Hosted at of the
* footer composer). No logo: nothing is shown.
*
* @project     Heurist academic knowledge management system
* @package     Website
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Website;

/** Logo and URL of the hosting organisation. */
final class HostInfo
{
    /**
     * The logo URL and the organisation URL, or null when there is no logo file.
     *
     * @param string $baseUrl Heurist base URL (the logo address).
     * @param string|null $folder Folder of the files; default: the parent folder of the installation.
     * @return array{logo:string,url:?string}|null
     */
    public static function read(string $baseUrl, ?string $folder = null): ?array
    {
        $folder = $folder ?? dirname(__DIR__, 3);
        $logo = null;
        foreach(array('jpg', 'png') as $extension){
            if(is_file($folder.'/organisation_logo.'.$extension)){
                $logo = $extension;
                break;
            }
        }
        if($logo === null){ return null; }
        $url = null;
        $file = $folder.'/organisation_url.txt';
        if(is_file($file)){
            $text = trim((string)file_get_contents($file));
            if(preg_match('#^https?://\S+$#i', $text) === 1){ $url = $text; }
        }
        return array('logo' => $baseUrl.'?logo=host', 'url' => $url);
    }

    /**
     * The badge of the host: the logo with a link to the organisation, or '' without a logo.
     *
     * @param int $height Height of the logo (px).
     */
    public static function badgeHtml(string $baseUrl, int $height = 48, ?string $folder = null): string
    {
        $host = self::read($baseUrl, $folder);
        if($host === null){ return ''; }
        $image = '<img src="'.self::attr($host['logo']).'" alt="Host organisation" style="height:'.max(8, min(400, $height)).'px">';
        $badge = $host['url'] !== null
            ? '<a class="h-site-badge" href="'.self::attr($host['url']).'" target="_blank" rel="noopener" title="'.self::attr($host['url']).'">'.$image.'</a>'
            : '<span class="h-site-badge">'.$image.'</span>';
        return '<div class="h-site-badges-row" style="justify-content:center;gap:16px">'.$badge.'</div>';
    }

    private static function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
