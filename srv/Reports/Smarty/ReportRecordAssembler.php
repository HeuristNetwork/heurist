<?php
/**
* ReportRecordAssembler.php - Records as Smarty arrays (the "$r" of a template)
*
* Loads records in batches (Records\Data\RecordDataService: headers, every
* visible detail, resolved pointers/terms/files/geo) and builds the array shape
* the legacy engine gave to templates (ReportRecord::getRecordForSmarty,
* getDetailForSmarty, getDetailForEnum):
*
*   recID, recRecTypeID, recTypeID, recTypeName, recTitle, recURL, ... (headers)
*   fN / fNs / fN_originalvalue per field:
*     enum        fN = first term {id,internalid,code,label,term,conceptid,desc}, fNs = all terms
*     date        fN = "date, date" (human readable), fNs = dates, raw values
*     file        fN = URL of the first file, fNs = links, raw = file infos
*     geo         fN = WKT, fNs = map link, raw = [type, wkt, recid]
*     resource    fN = first linked id, fNs = linked ids
*     text        values in the report language (prefix "fre:"), sanitized
*   recTags, rec_Tags (personal tags), recIsVisible
*
* Only records the current user may view are loaded (the legacy engine loaded
* any record and only flagged it with rec_IsVisible).
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

use Heurist\Database\DatabaseInterface;
use Heurist\Records\Data\RecordDataService;
use Heurist\Utilities\Temporal;

/** Loads records and converts them to the template record shape. */
final class ReportRecordAssembler
{
    /** Header columns, in the order of the legacy recordSearchByID. */
    public const HEADERS = array(
        'rec_URL', 'rec_ScratchPad', 'rec_OwnerUGrpID', 'rec_NonOwnerVisibility',
        'rec_URLLastVerified', 'rec_URLErrorMessage', 'rec_Added', 'rec_Modified',
        'rec_AddedByUGrpID', 'rec_Hash', 'rec_FlagTemporary'
    );

    /** Suffix of the unprocessed values of a field. */
    public const RAW = '_originalvalue';

    private const GEO_TYPES = array('p' => 'Point', 'pl' => 'Polygon', 'c' => 'Circle', 'r' => 'Rectangle', 'l' => 'Path', 'm' => 'Collection');

    private RecordDataService $data;
    private DatabaseInterface $database;
    private ReportDefinitions $definitions;
    private ReportEnvironment $environment;
    private LanguageCodes $languages;
    /** @var callable fn(array $ids): array - the ids the current user may view */
    private $visibleIds;
    private int $userId;
    /** @var array<string,int> RT_/DT_ constants used here */
    private array $codes;

    /**
     * @param RecordDataService $data Batched record loading.
     * @param DatabaseInterface $database Database (personal tags).
     * @param ReportDefinitions $definitions Definitions of the run.
     * @param ReportEnvironment $environment Installation values.
     * @param LanguageCodes $languages Language codes.
     * @param callable $visibleIds fn(array $ids): array.
     * @param int $userId Current user (personal tags).
     * @param array<string,int> $codes RT_CMS_MENU, DT_EXTENDED_DESCRIPTION.
     */
    public function __construct(
        RecordDataService $data,
        DatabaseInterface $database,
        ReportDefinitions $definitions,
        ReportEnvironment $environment,
        LanguageCodes $languages,
        callable $visibleIds,
        int $userId,
        array $codes = array()
    )
    {
        $this->data = $data;
        $this->database = $database;
        $this->definitions = $definitions;
        $this->environment = $environment;
        $this->languages = $languages;
        $this->visibleIds = $visibleIds;
        $this->userId = $userId;
        $this->codes = $codes;
    }

    /**
     * Load visible records (headers and, with $details, all visible values).
     * Records the user may not view are left out.
     *
     * @param array $ids Record ids.
     * @param bool $details Load field values too.
     * @return array<int,array> id => raw record (legacy recordSearchByID shape)
     */
    public function loadRaw(array $ids, bool $details = true): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){ return $id > 0; })));
        if(empty($ids)){ return array(); }
        $ids = array_map('intval', call_user_func($this->visibleIds, $ids));
        if(empty($ids)){ return array(); }

        $loaded = $this->data->loadRecords($ids, self::HEADERS, array(), $details
            ? array('allDetails' => true, 'resolveDetails' => true)
            : array());
        $tags = $details ? $this->personalTags($ids) : array();
        $records = array();
        foreach($loaded as $row){
            $id = intval($row['rec_ID']);
            $record = array();
            foreach(array_merge(array('rec_ID', 'rec_RecTypeID', 'rec_Title'), self::HEADERS) as $column){
                $value = $row[$column] ?? null;
                $record[$column] = $value === null ? null : (string)$value;
            }
            if($details){
                $record['details'] = $this->rawDetails($row['details'] ?? array(), intval($record['rec_RecTypeID']));
                $record['rec_Tags'] = implode(',', $tags[$id] ?? array());
                $record['rec_IsVisible'] = true;
            }
            $records[$id] = $record;
        }
        return $records;
    }

    /**
     * Template array of a record (see the file comment).
     *
     * @param array $raw Record from loadRaw().
     * @param string $lang Report language (3 letters).
     * @return array
     */
    public function toSmarty(array $raw, string $lang): array
    {
        $record = array();
        $recordId = intval($raw['rec_ID']);
        foreach($raw as $key => $value){
            if(strpos($key, 'rec_') === 0){
                $record['rec'.substr($key, 4)] = $value;
                if($key === 'rec_RecTypeID'){
                    $record['recTypeID'] = $value;
                    $record['recTypeName'] = $this->definitions->rectypeName($value);
                }elseif($key === 'rec_Tags'){
                    $record['rec_Tags'] = $value;
                }
            }elseif($key === 'details'){
                foreach($value as $dtyId => $values){
                    $field = $this->fieldForSmarty(intval($dtyId), $values, $recordId, $lang);
                    if($field !== null){
                        $record = array_merge($record, $field);
                    }
                }
            }
        }
        return $record;
    }

    /**
     * HTML link of an uploaded file: icon, name and size (ReportRecord::composeFileLink).
     *
     * @param array $file File info.
     * @return string
     */
    public function fileLink(array $file): string
    {
        $external = $file['ulf_ExternalFileReference'] ?? null;
        $name = $file['ulf_OrigFileName'] ?? null;
        $size = $file['ulf_FileSizeKB'] ?? null;
        $url = $this->environment->fileUrl((string)($file['ulf_ObfuscatedFileID'] ?? ''));
        $base = $this->environment->baseUrl;

        $link = '<a target="_surf" href="'.htmlspecialchars($external ?: $url).'">'
            .'<span style="padding-left: 16px;background-image: url('.$base
            .'hclient/assets/external_link_16x16.gif);vertical-align: bottom;"></span>';
        $isIiif = strpos((string)($file['ulf_PreferredSource'] ?? ''), 'iiif') === 0
            || strpos((string)$name, '_iiif') === 0;
        if($isIiif){
            $link .= '<img src="'.$base.'hclient/assets/iiif_logo.png" style="width:16px"/>';
            if(strpos((string)$name, '_iiif') === 0){
                $name = null;
            }
        }
        $link .= '<span>'.htmlspecialchars((string)(($name && $name !== '_remote') ? $name : ($external ?: $url)))
            .'</span></a> '.($size > 0 ? '['.htmlspecialchars((string)$size).'kB]' : '');
        return $link;
    }

    /** Values of a record in the legacy shape: dty => [value | {file} | {geo} | {id}]. */
    private function rawDetails(array $details, int $rtyId): array
    {
        $raw = array();
        foreach($details as $dtyId => $values){
            $dtyId = intval($dtyId);
            $type = $this->definitions->fieldType($dtyId);
            foreach($values as $value){
                $converted = $this->rawValue($type, $value);
                if($converted !== null && $converted !== ''){
                    $raw[$dtyId][] = $converted;
                }
            }
        }
        // CMS menu: all descriptions joined (as recordSearchDetails)
        $menu = $this->codes['RT_CMS_MENU'] ?? 0;
        $description = $this->codes['DT_EXTENDED_DESCRIPTION'] ?? 0;
        if($menu > 0 && $rtyId === $menu && $description > 0 && isset($raw[$description])){
            $raw[$description] = array(implode('', $raw[$description]));
        }
        return $raw;
    }

    /** One value in the legacy shape. */
    private function rawValue(?string $type, $value)
    {
        switch($type){
            case 'enum':
            case 'relationtype':
                return is_array($value) ? (string)($value['value'] ?? '') : (string)$value;
            case 'resource':
                $id = is_array($value) ? ($value['rec_ID'] ?? null) : $value;
                return $id === null ? null : array('id' => (string)$id);
            case 'file':
                if(!is_array($value) || empty($value['file'])){ return null; }
                $file = $value['file'] + array(
                    'ulf_ID' => null, 'fullPath' => null, 'ulf_ExternalFileReference' => null, 'fxm_MimeType' => null,
                    'ulf_PreferredSource' => null, 'ulf_OrigFileName' => null, 'ulf_FileSizeKB' => null,
                    'ulf_ObfuscatedFileID' => null, 'ulf_Description' => null, 'ulf_MimeExt' => null,
                    'ulf_Caption' => null, 'ulf_Copyright' => null, 'ulf_Copyowner' => null
                );
                return array('file' => $file, 'fileid' => $file['ulf_ObfuscatedFileID']);
            case 'geo':
                return is_array($value) && isset($value['geo']) ? array('geo' => $value['geo']) : null;
            case 'separator':
            case 'relmarker':
            case null:
                return null;
            default:
                return is_array($value) ? ($value['value'] ?? null) : $value;
        }
    }

    /** Personal tags of the current user: id => [tag, ...]. */
    private function personalTags(array $ids): array
    {
        if($this->userId < 1){ return array(); }
        $tags = array();
        foreach(array_chunk($ids, 500) as $chunk){
            foreach($this->database->fetchRows(
                'SELECT rtl_RecID,tag_Text FROM usrRecTagLinks JOIN usrTags ON tag_ID=rtl_TagID '
                .'WHERE tag_UGrpID=? AND rtl_RecID IN ('.implode(',', array_fill(0, count($chunk), '?')).') '
                .'ORDER BY rtl_RecID,rtl_Order',
                array_merge(array($this->userId), $chunk)
            ) as $row){
                $tags[intval($row[0])][] = (string)$row[1];
            }
        }
        return $tags;
    }

    /** fN, fNs and fN_originalvalue of one field (ReportRecord::getDetailForSmarty). */
    private function fieldForSmarty(int $dtyId, $values, int $recordId, string $lang): ?array
    {
        if($dtyId < 1){
            $name = 'Relationship';
            $type = 'relmarker';
        }else{
            $type = $this->definitions->fieldType($dtyId);
            if($type === null){ return null; }
            $name = 'f'.$dtyId;
        }
        if(!is_array($values)){
            return array($name => $values);
        }

        switch($type){
            case 'enum':
            case 'relationtype':
                return $this->termsForSmarty($name, $values, $lang);

            case 'date':
                $prepared = array();
                $original = array();
                foreach($values as $value){
                    $prepared[] = Temporal::toHumanReadable($value, true, 0, '|', 'native');
                    $original[] = $value;
                }
                $text = implode(', ', $prepared);
                return $text === '' ? null : array($name => $text, $name.'s' => $prepared, $name.self::RAW => $original);

            case 'file':
                $prepared = array();
                $original = array();
                $url = null;
                foreach($values as $value){
                    $file = $value['file'];
                    $file['rec_ID'] = $recordId;
                    $file['ulf_Caption'] = $this->definitions->translation('ulf', $file['ulf_ID'], 'ulf_Caption', $lang);
                    $file['ulf_Description'] = $this->definitions->translation('ulf', $file['ulf_ID'], 'ulf_Description', $lang);
                    $prepared[] = $this->fileLink($file);
                    $original[] = $file;
                    if($url !== null){ continue; }
                    $external = $file['ulf_ExternalFileReference'];
                    if($external && strpos($external, 'http://') !== 0){
                        $url = $external;
                    }elseif($file['ulf_ObfuscatedFileID']){
                        $url = $this->environment->fileUrl((string)$file['ulf_ObfuscatedFileID']);
                    }
                }
                return $url === null ? null : array($name => $url, $name.'s' => $prepared, $name.self::RAW => $original);

            case 'geo':
                return $this->geoForSmarty($name, $values, $recordId);

            case 'separator':
            case 'fieldsetmarker':
            case 'relmarker':
                return null;

            case 'resource':
                $ids = array();
                foreach($values as $value){ $ids[] = $value['id']; }
                return empty($ids) ? null : array($name => $ids[0], $name.'s' => $ids);

            default:
                $original = array();
                $prepared = array();
                if($type === 'freetext' || $type === 'blocktext'){
                    $code = $this->languages->code3($lang);
                    $default = array();
                    foreach($values as $value){
                        list($valueLang, $text) = $this->languages->splitPrefix($value);
                        $text = ReportText::sanitize($text);
                        if($valueLang !== null && $valueLang === $code){
                            $prepared[] = $text;
                        }elseif($valueLang === null){
                            $default[] = $text;
                        }
                        $original[] = $value;
                    }
                    if(empty($prepared) && !empty($default)){
                        $prepared = $default;
                    }
                }else{
                    $original = array_values($values);
                    $prepared = $original;
                }
                return empty($prepared) ? null
                    : array($name => implode(', ', $prepared), $name.'s' => $prepared, $name.self::RAW => $original);
        }
    }

    /** Terms of an enum field (ReportRecord::getDetailForEnum). */
    private function termsForSmarty(string $name, array $values, string $lang): ?array
    {
        $terms = array();
        $original = array();
        foreach($values as $value){
            $term = $this->definitions->term($value);
            if($term === null){ continue; }
            $terms[] = array(
                'id' => $value,
                'internalid' => $value,
                'code' => $term['code'],
                'label' => $this->definitions->translation('trm', $value, 'trm_Label', $lang),
                'term' => $this->definitions->termFullLabel($value),
                'conceptid' => $term['conceptid'],
                'desc' => $this->definitions->translation('trm', $value, 'trm_Description', $lang)
            );
            $original[] = $value;
        }
        return empty($terms) ? null : array($name => $terms[0], $name.'s' => $terms, $name.self::RAW => $original);
    }

    /** First geo value: WKT, a map link and the raw value. */
    private function geoForSmarty(string $name, array $values, int $recordId): ?array
    {
        foreach($values as $value){
            $geo = $value['geo'];
            $geo['recid'] = $recordId;
            $prepared = array();
            if(class_exists('\geoPHP')){
                $geometry = \geoPHP::load($geo['wkt'], 'wkt');
                if($geometry && !$geometry->isEmpty()){
                    $box = $geometry->getBBox();
                    $type = self::GEO_TYPES[$geo['type']] ?? 'Collection';
                    if($type === 'Point'){
                        $link = '<b>Point</b> '.($box['minx'] !== null ? round($box['minx'], 7).', '.round($box['miny'], 7) : '');
                    }else{
                        // the legacy text repeats maxx for the second Y value; kept for the same output
                        $link = '<b>'.$type.'</b> X '.($box['minx'] !== null
                            ? round($box['minx'], 7).', '.round($box['maxx'], 7).' Y '.round($box['miny'], 7).', '.round($box['maxx'], 7)
                            : '');
                    }
                    $base = $this->environment->baseUrl;
                    $url = $base.'viewers/map/map.php?q=ids:'.$recordId.'&db='.$this->environment->databaseName
                        .'&notimeline=1&nocluster=1&basemap=OpenStreetMap&controls=none&published=true&popup=none';
                    $prepared[] = '<img class="geo-image" style="vertical-align:top;" src="'.$base
                        .'hclient/assets/geo.gif" onclick="{if(window.hWin && window.hWin.HEURIST4){window.hWin.HEURIST4.msg.showDialog(\''
                        .$url.'\')}}">&nbsp;'.$link;
                }
            }
            $wkt = (string)$geo['wkt'];
            return $wkt === '' ? null : array($name => $wkt, $name.'s' => $prepared, $name.self::RAW => array($geo));
        }
        return null;
    }
}
