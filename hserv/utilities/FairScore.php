<?php
/**
* FairScore.php - Class FairScore
*
* Calculates and persists an approximate FAIR (Findable, Accessible, Interoperable, Reusable)
* score for a Heurist database, and reads back a previously calculated score.
*
* @project     Heurist academic knowledge management system
* @package Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       7
*/
namespace hserv\utilities;

/**
* Class FairScore
*
* Static helper class - no instances required.
*
* The score is split into the four FAIR components (Findable, Accessible, Interoperable,
* Reusable), each worth up to 2.5 points, for a total out of 10. Roughly half of the score
* is automatic, deriving from Heurist's own affordances (structured archive, interoperability
* tools); the other half depends on the openness of the data (public visibility, a configured
* website) and the quality of the metadata the database owner has supplied.
*
* This is deliberately a rough, indicative measure rather than a rigorous FAIR assessment -
* the weights below are tunable estimates (see $weights) and may be revised as Heurist gains
* more interoperability features (RDF, OAI-PMH harvesting, broader repository registration etc).
*
* Public Static Methods:
* - computeForDatabase(mysqli, string $database_name_full, string $short_name, string $filestore_root): array
* - readScore(string $filestore_dir): array
* - writeScore(string $filestore_dir, array $score): bool
* - getScoreFilePath(string $filestore_dir): string
*/
class FairScore {

    private function __construct() {}

    /** Filename for the persisted score, stored in the 'settings' subfolder of the db filestore */
    const SCORE_FILENAME = 'FAIRscore.txt';

    /**
     * Default/fallback score used when no FAIRscore.txt has been calculated yet for a database.
     * Reflects only the baseline credit Heurist itself provides (per Ian Johnson's initial estimate):
     * F=1 (Heurist is itself findable/listed), A=0 (unknown until assessed), I=1 (Heurist's
     * interoperability tools), R=1 (Heurist's inherently reusable archive structure) => total 3/10.
     */
    const DEFAULT_SCORE = [
        'F' => 1.0,
        'A' => 0.0,
        'I' => 1.0,
        'R' => 1.0,
        'TOTAL' => 3.0,
        'calculated' => null,
        'is_default' => true,
        'suggestions' => []
    ];

    /**
     * Tunable weights/parameters for the FAIR score estimate.
     * Kept as a single array (rather than scattered magic numbers) so they can be revised
     * as Heurist's affordances change, without touching the calculation logic itself.
     */
    private static $weights = [

        // Findable - max 2.5
        'F_heurist_base'   => 1.0,  // credit for Heurist itself being a known, indexed platform
        'F_metadata_max'   => 1.0,  // up to this much for the quality of the user's own metadata
        'F_doi_bonus'      => 0.5,  // bonus if the database has its own DOI
        'F_max'            => 2.5,

        // Accessible - max 2.5
        'A_visibility_max' => 2.0,  // up to this much if most records (and a website) are public
        'A_no_website_cap' => 1.0,  // cap on the accessibility score if there is no website at all
        'A_website_penalty'=> 1.0,  // deducted if a website exists but shows little sign of configuration
        'A_max'            => 2.5,

        // Interoperable - max 2.5
        // Fixed for now - Heurist provides interoperability tools (HML/XML export, API).
        // Could rise to 2 once RDF export and OAI-PMH harvesting are implemented.
        'I_base'           => 1.0,
        'I_max'            => 2.5,

        // Reusable - max 2.5
        'R_structure_base' => 1.0,  // credit for Heurist's inherently reusable archive structure
        'R_metadata_max'   => 1.0,  // up to this much for the quality of the user's own metadata
        'R_max'            => 2.5,

        // metadata quality field weights (relative) - rights statement and description count for
        // much more than the simple name/owner fields, as they carry the bulk of useful information.
        // For the two free-text fields, credit is scaled down proportionally if the text does not
        // reach a reasonable minimum length.
        'metadata_weight_name'        => 1,
        'metadata_weight_owner'       => 1,
        'metadata_weight_rights'      => 10,
        'metadata_weight_description' => 5,
        'metadata_min_chars_rights'      => 40,
        'metadata_min_chars_description' => 200,

        // a website is considered to show signs of real configuration if it has more than this
        // many CMS page/menu records (i.e. more than just the default empty home page)
        'website_min_pages_configured' => 1,
    ];

    /**
     * Returns the full path to the FAIRscore.txt file within a database's filestore directory.
     *
     * @param string $filestore_dir The database's filestore root directory (trailing slash optional).
     * @return string
     */
    public static function getScoreFilePath($filestore_dir){
        return rtrim($filestore_dir, '/').'/settings/'.self::SCORE_FILENAME;
    }

    /**
     * Reads a previously calculated FAIR score for a database.
     * Falls back to DEFAULT_SCORE (the baseline value Heurist provides) if no score has been
     * calculated yet, or if the stored file cannot be read/parsed.
     *
     * @param string $filestore_dir The database's filestore root directory.
     * @return array Associative array with keys F, A, I, R, TOTAL, calculated, is_default, suggestions.
     */
    public static function readScore($filestore_dir){

        $fname = self::getScoreFilePath($filestore_dir);

        if(!file_exists($fname) || !is_readable($fname)){
            return self::DEFAULT_SCORE;
        }

        $content = file_get_contents($fname);
        if($content === false || trim($content) === ''){
            return self::DEFAULT_SCORE;
        }

        $decoded = json_decode($content, true);
        if(!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE
            || !isset($decoded['F'], $decoded['A'], $decoded['I'], $decoded['R'])){
            return self::DEFAULT_SCORE;
        }

        $decoded['is_default'] = false;
        $decoded += self::DEFAULT_SCORE; // fill in any missing keys

        return $decoded;
    }

    /**
     * Writes a calculated FAIR score to FAIRscore.txt for a database.
     * Stored as JSON (despite the .txt extension, as specified) so the F/A/I/R breakdown
     * and improvement suggestions survive the round trip for use in the UI.
     *
     * @param string $filestore_dir The database's filestore root directory.
     * @param array $score The score array, as returned by computeForDatabase().
     * @return bool True on success.
     */
    public static function writeScore($filestore_dir, array $score){

        $settings_dir = rtrim($filestore_dir, '/').'/settings/';

        if(!folderCreate($settings_dir, true)){
            return false;
        }

        unset($score['is_default']);
        $score['calculated'] = date(DATE_8601);

        $json = json_encode($score, JSON_PRETTY_PRINT);
        if($json === false){
            return false;
        }

        return fileSave($json, self::getScoreFilePath($filestore_dir)) > 0;
    }

    /**
     * Calculates the metadata quality fraction (0..1) from the user-supplied descriptive fields.
     *
     * @param string|null $dbName
     * @param string|null $dbOwner
     * @param string|null $dbRights
     * @param string|null $dbDescription
     * @return float 0..1
     */
    private static function metadataQuality($dbName, $dbOwner, $dbRights, $dbDescription){

        $w = self::$weights;

        $total_weight = $w['metadata_weight_name'] + $w['metadata_weight_owner']
            + $w['metadata_weight_rights'] + $w['metadata_weight_description'];

        $score = 0;

        if(!self::isEmptyText($dbName)){
            $score += $w['metadata_weight_name'];
        }
        if(!self::isEmptyText($dbOwner)){
            $score += $w['metadata_weight_owner'];
        }
        if(!self::isEmptyText($dbRights)){
            $frac = min(1, strlen(trim(strip_tags($dbRights))) / max(1, $w['metadata_min_chars_rights']));
            $score += $w['metadata_weight_rights'] * $frac;
        }
        if(!self::isEmptyText($dbDescription)){
            $frac = min(1, strlen(trim(strip_tags($dbDescription))) / max(1, $w['metadata_min_chars_description']));
            $score += $w['metadata_weight_description'] * $frac;
        }

        return $total_weight > 0 ? min(1, $score / $total_weight) : 0;
    }

    /**
     * Treats default placeholder values written by the installer (e.g. 'Please enter a DB name ...')
     * as effectively empty, so a database that was never actually configured doesn't get credit.
     */
    private static function isEmptyText($text){
        if($text === null){
            return true;
        }
        $text = trim($text);
        if($text === ''){
            return true;
        }
        return (stripos($text, 'please enter') === 0 || stripos($text, 'please define') === 0);
    }

    /**
     * Computes a fresh FAIR score for a single database, using direct cross-database queries
     * (this is intended to be called from an admin/cron context iterating over many databases,
     * so it deliberately avoids a full System::init() per database for performance).
     *
     * @param \mysqli $mysqli An active mysqli connection (any database, queries are fully qualified).
     * @param string $database_name_full The full database name, including HEURIST_DB_PREFIX.
     * @param string $short_name The database's short name (used only for messages).
     * @param string $filestore_root HEURIST_FILESTORE_ROOT - used to check for DBMetadata.xml.
     * @return array The computed score, see DEFAULT_SCORE for shape.
     */
    public static function computeForDatabase($mysqli, $database_name_full, $short_name, $filestore_root){

        $w = self::$weights;
        $suggestions = [];

        $db = '`'.$database_name_full.'`';

        // ---- gather descriptive metadata fields -------------------------------------------------
        $row = mysql__select_row_assoc($mysqli,
            "SELECT sys_dbRegisteredID, sys_dbName, sys_dbOwner, sys_dbRights, sys_dbDescription, sys_dbDOI ".
            "FROM $db.sysIdentification LIMIT 1");

        $regID         = isPositiveInt(@$row['sys_dbRegisteredID']) ? intval($row['sys_dbRegisteredID']) : 0;
        $dbName        = @$row['sys_dbName'];
        $dbOwner       = @$row['sys_dbOwner'];
        $dbRights      = @$row['sys_dbRights'];
        $dbDescription = @$row['sys_dbDescription'];
        // sys_dbDOI may not exist yet on databases that have not had the 1.4.0 upgrade applied
        $dbDOI         = array_key_exists('sys_dbDOI', $row ?: []) ? $row['sys_dbDOI'] : null;

        $metadata_quality = self::metadataQuality($dbName, $dbOwner, $dbRights, $dbDescription);
        $has_doi = !self::isEmptyText($dbDOI);

        // ---- Findable ---------------------------------------------------------------------------
        $F = $w['F_heurist_base'] + ($metadata_quality * $w['F_metadata_max']) + ($has_doi ? $w['F_doi_bonus'] : 0);
        $F = min($F, $w['F_max']);

        if($metadata_quality < 0.8){
            $suggestions[] = 'metadata';
        }
        if(!$has_doi){
            $suggestions[] = 'doi';
        }

        // ---- Accessible ---------------------------------------------------------------------------
        $total_records = intval(mysql__select_value($mysqli,
            "SELECT count(*) FROM $db.Records WHERE rec_FlagTemporary=0"));

        $public_records = intval(mysql__select_value($mysqli,
            "SELECT count(*) FROM $db.Records WHERE rec_FlagTemporary=0 AND rec_NonOwnerVisibility='public'"));

        $pct_public = $total_records > 0 ? ($public_records / $total_records) : 0;

        // CMS_HOME = concept 99-51, CMS_MENU (pages) = concept 99-52
        $rty_cms_home = mysql__select_value($mysqli,
            "SELECT rty_ID FROM $db.defRecTypes WHERE rty_OriginatingDBID=99 AND rty_IDInOriginatingDB=51");
        $rty_cms_page = mysql__select_value($mysqli,
            "SELECT rty_ID FROM $db.defRecTypes WHERE rty_OriginatingDBID=99 AND rty_IDInOriginatingDB=52");

        $has_website = isPositiveInt($rty_cms_home) && intval(mysql__select_value($mysqli,
            "SELECT count(*) FROM $db.Records WHERE rec_RecTypeID=".intval($rty_cms_home))) > 0;

        $page_count = isPositiveInt($rty_cms_page) ? intval(mysql__select_value($mysqli,
            "SELECT count(*) FROM $db.Records WHERE rec_RecTypeID=".intval($rty_cms_page))) : 0;

        $website_configured = $page_count > $w['website_min_pages_configured'];

        $A = $pct_public * $w['A_visibility_max'];
        if(!$has_website){
            $A = min($A, $w['A_no_website_cap']);
            $suggestions[] = 'website';
        }elseif(!$website_configured){
            $A = max(0, $A - $w['A_website_penalty']);
            $suggestions[] = 'website_quality';
        }
        $A = min($A, $w['A_max']);

        if($pct_public < 0.8){
            $suggestions[] = 'visibility';
        }

        // ---- Interoperable ------------------------------------------------------------------------
        // Fixed contribution for now - every Heurist database supports HML/XML export and the API.
        $I = min($w['I_base'], $w['I_max']);

        // ---- Reusable -----------------------------------------------------------------------------
        $R = $w['R_structure_base'] + ($metadata_quality * $w['R_metadata_max']);
        $R = min($R, $w['R_max']);

        $TOTAL = round($F + $A + $I + $R, 1);

        return [
            'F' => round($F, 2),
            'A' => round($A, 2),
            'I' => round($I, 2),
            'R' => round($R, 2),
            'TOTAL' => $TOTAL,
            'calculated' => date(DATE_8601),
            'is_default' => false,
            'is_registered' => $regID > 0,
            'metadata_quality' => round($metadata_quality, 2),
            'pct_public' => round($pct_public, 2),
            'has_website' => $has_website,
            'website_configured' => $website_configured,
            'has_doi' => $has_doi,
            'suggestions' => array_values(array_unique($suggestions))
        ];
    }
}
