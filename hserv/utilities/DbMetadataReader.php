<?php
/**
* DbMetadataReader.php - Class DbMetadataReader
*
* Reads and parses registration metadata from either:
*  (a) a locally cached DBMetadata.xml file (downloaded nightly by downloadDBMetadata.php), or
*  (b) a live fetch directly from the Heurist Reference Index (used when no local file exists yet,
*      i.e. between registration and the first nightly cron run — DO NOT store the live result).
*
* Returns a consistent response shape for both cases, used by usr_info?a=get_db_metadata and
* consumed by manageSysIdentification.js to drive the 3-state Design > Properties display.
*
* @project     Heurist academic knowledge management system
* @package Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       7
*/
namespace hserv\utilities;

class DbMetadataReader {

    private function __construct() {}

    /**
     * Concept codes we expose, keyed by the conceptID attribute value found in the flathml XML
     * <detail conceptID="2-1">...</detail>. These map to the fields Artem/Ian configured in the
     * Heurist_Reference_Index edit form for database registration records.
     */
    const KNOWN_FIELDS = [
        '2-1'   => 'Display name',
        '2-12'  => 'Description',
        '2-311' => 'Rights statement',
    ];

    /**
     * All field labels in display order — sent to the client so it can render missing fields in red
     * (per Ian's spec: "show missing fields in red so it makes people want to fix them").
     */
    const ALL_LABELS = ['Display name', 'Description', 'Rights statement'];

    // ─── file helpers ──────────────────────────────────────────────────────────

    public static function getMetadataFilePath($filestore_dir){
        return rtrim($filestore_dir, '/').'/settings/DBMetadata.xml';
    }

    // ─── public API ────────────────────────────────────────────────────────────

    /**
     * Reads and parses the locally cached DBMetadata.xml file.
     *
     * @param string $filestore_dir Database filestore root (e.g. /var/www/filestore/mydb/).
     * @return array{has_local_xml:bool, exists:bool, modified:?string, fields:array, all_labels:array}
     */
    public static function read($filestore_dir){

        $fname = self::getMetadataFilePath($filestore_dir);

        $base = ['has_local_xml' => false, 'exists' => false, 'modified' => null,
                 'fields' => [], 'all_labels' => self::ALL_LABELS];

        if(!file_exists($fname) || !is_readable($fname)){
            return $base;
        }

        $base['has_local_xml'] = true;
        $base['exists']        = true;
        $base['modified']      = date(DATE_8601, filemtime($fname));

        $content = file_get_contents($fname);
        if($content === false){
            return $base;
        }

        $fields = self::parseXmlString($content);
        $base['fields'] = $fields;

        return $base;
    }

    /**
     * Fetches metadata LIVE from the Heurist Reference Index for a given registration ID.
     * Used when the database is registered but no local DBMetadata.xml exists yet.
     * The result must NOT be stored — callers should use it only for immediate display.
     *
     * @param int $regID The sysIdentification.sys_dbRegisteredID value.
     * @return array Same shape as read(), with has_local_xml always false.
     */
    public static function fetchLive($regID){

        $base = ['has_local_xml' => false, 'exists' => false, 'modified' => null,
                 'fields' => [], 'all_labels' => self::ALL_LABELS];

        if(!isPositiveInt($regID)){
            return $base;
        }

        $url = rtrim(HEURIST_INDEX_BASE_URL, '/')
            . '/export/xml/flathml.php?w=a&db=' . HEURIST_INDEX_DATABASE . '&q=ids:' . intval($regID);

        $xml_data = loadRemoteURLContentWithRange($url, null, true, 15);

        if(empty($xml_data)){
            return $base; // Reference Index unreachable — caller shows the disabled local fields only
        }

        $fields = self::parseXmlString($xml_data);

        $base['fields'] = $fields;
        // 'exists' stays false (no local file); client uses has_local_xml to distinguish states

        return $base;
    }

    // ─── parsing ───────────────────────────────────────────────────────────────

    /**
     * Parses a flathml XML string and extracts the known concept-code detail fields.
     * Returns an ordered array of {label, value} pairs, in the order of KNOWN_FIELDS.
     *
     * @param string $xml_string Raw XML from flathml.php export.
     * @return array<int, array{label:string, value:string}>
     */
    private static function parseXmlString($xml_string){

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_string);
        libxml_clear_errors();

        if($xml === false){
            return [];
        }

        // flathml wraps records under <hml><records><record> but the exact depth varies with
        // export parameters. Search broadly with XPath to avoid fragile positional assumptions.
        $details = $xml->xpath('//detail');

        $found = [];
        if(is_array($details)){
            foreach($details as $detail){
                $attrs = $detail->attributes();
                $conceptID = trim((string)($attrs['conceptID'] ?? ''));

                if($conceptID === '' || !array_key_exists($conceptID, self::KNOWN_FIELDS)){
                    continue;
                }

                $value = trim((string)$detail);
                if($value === ''){
                    continue;
                }

                // keep only the first occurrence per field
                if(!isset($found[$conceptID])){
                    $found[$conceptID] = $value;
                }
            }
        }

        // Return in the stable display order defined by KNOWN_FIELDS
        $ordered = [];
        foreach(self::KNOWN_FIELDS as $conceptID => $label){
            if(isset($found[$conceptID])){
                $ordered[] = ['label' => $label, 'value' => $found[$conceptID]];
            }
        }

        return $ordered;
    }
}
