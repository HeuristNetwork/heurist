<?php
/**
* DbMetadataReader.php - Class DbMetadataReader
*
* Reads and parses the locally cached DBMetadata.xml file (downloaded nightly from the Heurist
* Reference Index by admin/utilities/downloadDBMetadata.php) into a simple human-readable list
* of label/value pairs, for display on Design > Properties once a database is registered.
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
     * The fields we know how to label nicely, keyed by the Heurist_Reference_Index concept code
     * used in the <detail conceptID="..."> tags of the flathml export. See DbRegis::registrationAdd()
     * for where these are written when a database is first registered.
     */
    const KNOWN_FIELDS = [
        '2-1'   => 'Display name',
        '2-12'  => 'Description',
        '2-311' => 'Rights statement',
    ];

    /**
     * Returns the path to the locally cached metadata file for a database, given its filestore dir.
     *
     * @param string $filestore_dir
     * @return string
     */
    public static function getMetadataFilePath($filestore_dir){
        return rtrim($filestore_dir, '/').'/settings/DBMetadata.xml';
    }

    /**
     * Reads and parses the cached DBMetadata.xml for a database into a simple ordered list of
     * label/value pairs suitable for direct display.
     *
     * @param string $filestore_dir The database's filestore root directory.
     * @return array{exists: bool, modified: ?string, fields: array<int, array{label:string, value:string}>}
     */
    public static function read($filestore_dir){

        $fname = self::getMetadataFilePath($filestore_dir);

        $result = ['exists' => false, 'modified' => null, 'fields' => []];

        if(!file_exists($fname) || !is_readable($fname)){
            return $result;
        }

        $result['exists'] = true;
        $result['modified'] = date(DATE_8601, filemtime($fname));

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($fname);
        libxml_clear_errors();

        if($xml === false){
            return $result; // exists, but unreadable/corrupt - caller should fall back gracefully
        }

        // The record describing the database may be the top-level <record> or nested under
        // <records><record> depending on export mode - search broadly for <detail> tags by conceptID.
        $details = $xml->xpath('//detail');

        $fields = [];
        if(is_array($details)){
            foreach($details as $detail){
                $attrs = $detail->attributes();
                $conceptID = (string)($attrs['conceptID'] ?? '');

                if($conceptID === '' || !array_key_exists($conceptID, self::KNOWN_FIELDS)){
                    continue;
                }

                $value = trim((string)$detail);
                if($value === ''){
                    continue;
                }

                // keep the first occurrence only (registration record holds a single value per field)
                if(!isset($fields[$conceptID])){
                    $fields[$conceptID] = [
                        'label' => self::KNOWN_FIELDS[$conceptID],
                        'value' => $value
                    ];
                }
            }
        }

        // present in a stable, sensible order regardless of XML order
        $ordered = [];
        foreach(self::KNOWN_FIELDS as $conceptID => $label){
            if(isset($fields[$conceptID])){
                $ordered[] = $fields[$conceptID];
            }
        }

        $result['fields'] = $ordered;

        return $result;
    }
}
