<?php
/**
* ReportEnvironment.php - What the srv Smarty engine needs from the installation
*
* Plain values (URLs, folders, database settings, current user) and one
* callback (record links). Built by Runtime\ServiceFactory from the legacy
* System at the HTTP entry point, so the engine itself never touches hserv/.
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

/** Installation values of one report run. */
final class ReportEnvironment
{
    /** Database name without the hdb_ prefix. */
    public string $databaseName;
    /** HEURIST_BASE_URL (ends with "/"). */
    public string $baseUrl;
    /** HEURIST_BASE_URL_PRO (media URLs); defaults to baseUrl. */
    public string $baseUrlPro;
    /** smarty-templates folder (ends with "/"). */
    public string $templateDir;
    /** Compile folder of this engine (separate from the legacy engine). */
    public string $compileDir;
    /** scratch folder (HTMLPurifier cache). */
    public string $scratchDir;
    /** Database may run JavaScript in reports (js_in_database_authorised.txt). */
    public bool $javaScriptAllowed;
    /** CSS of the database web fonts (without <style>). */
    public string $fontStyles;
    /** CSS of the TinyMCE formats setting (without <style>). */
    public string $tinyMceStyles;
    /** Registered database id ('' when not registered). */
    public string $registeredDbId;
    /** Default language (3 letters) of the current user. */
    public string $language;
    /** Current user: ugr_ID, ugr_Name, ugr_FullName, ... (no preferences). */
    public array $currentUser;
    /** File with the active language codes (hclient/assets/language-codes-active-list.json). */
    public string $languageCodesFile;
    /** @var callable|null fn(string $reference): string - URL of a record (`123` or `123/x.tpl`). */
    private $recordLink;

    /**
     * @param array $values Keys as the public properties, plus `recordLink` (callable).
     */
    public function __construct(array $values)
    {
        $this->databaseName = (string)($values['databaseName'] ?? '');
        $this->baseUrl = self::slash((string)($values['baseUrl'] ?? ''));
        $this->baseUrlPro = self::slash((string)($values['baseUrlPro'] ?? '')) ?: $this->baseUrl;
        $this->templateDir = self::slash((string)($values['templateDir'] ?? ''));
        $this->compileDir = self::slash((string)($values['compileDir'] ?? ''))
            ?: ($this->templateDir === '' ? '' : $this->templateDir.'compiled-srv/');
        $this->scratchDir = self::slash((string)($values['scratchDir'] ?? ''));
        $this->javaScriptAllowed = !empty($values['javaScriptAllowed']);
        $this->fontStyles = trim((string)($values['fontStyles'] ?? ''));
        $this->tinyMceStyles = trim((string)($values['tinyMceStyles'] ?? ''));
        $this->registeredDbId = (string)($values['registeredDbId'] ?? '');
        $this->language = strtoupper((string)($values['language'] ?? '')) ?: 'ENG';
        $this->currentUser = is_array($values['currentUser'] ?? null) ? $values['currentUser'] : array();
        $this->languageCodesFile = (string)($values['languageCodesFile'] ?? '');
        $this->recordLink = is_callable($values['recordLink'] ?? null) ? $values['recordLink'] : null;
    }

    /**
     * URL of a record (`123`) or of a record shown by a template (`123/name.tpl`).
     *
     * @param string $reference Record reference from a report link.
     * @return string
     */
    public function recordLink(string $reference): string
    {
        if($this->recordLink !== null){
            return (string)call_user_func($this->recordLink, $reference);
        }
        if(preg_match('/^(\d+)\/(.+\.tpl)$/', $reference, $matches)){
            return $this->baseUrl.'?db='.rawurlencode($this->databaseName)
                .'&template='.rawurlencode($matches[2]).'&q=ids:'.intval($matches[1]);
        }
        return $this->baseUrl.'?recID='.intval($reference).'&fmt=html&db='.rawurlencode($this->databaseName);
    }

    /** Download URL of an uploaded file. */
    public function fileUrl(string $obfuscatedId, bool $pro = false): string
    {
        return ($pro ? $this->baseUrlPro : $this->baseUrl).'?db='.$this->databaseName.'&file='.$obfuscatedId;
    }

    /** Thumbnail URL of an uploaded file. */
    public function thumbnailUrl(string $obfuscatedId, bool $pro = false): string
    {
        return ($pro ? $this->baseUrlPro : $this->baseUrl).'?db='.$this->databaseName.'&thumb='.$obfuscatedId;
    }

    private static function slash(string $path): string
    {
        return $path === '' ? '' : rtrim($path, '/\\').'/';
    }
}
