<?php
/**
* SystemConstants.php - The RT_* / DT_* / TRM_* constants of Heurist and their concept codes
*
* The only table of these names in srv/. A name is resolved to the local id of the current
* database by DefinitionLookup::constant(); the client gets all of them in the definitions
* snapshot (meta.dbconst). Ported from hserv/consts.php, which srv/ does not include;
* tests/SystemConstantsTest.php keeps both tables equal while legacy code lives (2026-10-27).
* A new constant is added here only.
*
* @project     Heurist academic knowledge management system
* @package     Definitions
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Definitions;

/** Names of the system definitions => [kind (rty, dty, trm), concept code]. */
final class SystemConstants
{
    /** @var array<string,array{0:string,1:string}> */
    public const CODES = array(
        // Record types (ported from hserv/consts.php $rtDefines)
        'RT_RELATION' => array('rty', '2-1'),
        'RT_INTERNET_BOOKMARK' => array('rty', '2-2'),
        'RT_NOTE' => array('rty', '2-3'),
        'RT_ORGANISATION' => array('rty', '2-4'),
        'RT_MEDIA_RECORD' => array('rty', '2-5'),
        'RT_AGGREGATION' => array('rty', '2-6'),
        'RT_COLLECTION' => array('rty', '2-6'), // duplicate naming
        'RT_BLOG_ENTRY' => array('rty', '2-7'),
        'RT_INTERPRETATION' => array('rty', '2-8'),
        'RT_PERSON' => array('rty', '2-10'),
        'RT_FILTER' => array('rty', '2-12'),
        'RT_XML_DOCUMENT' => array('rty', '2-13'),
        'RT_TRANSFORM' => array('rty', '2-14'),
        'RT_ANNOTATION' => array('rty', '2-15'),
        'RT_LAYOUT' => array('rty', '2-16'),
        'RT_PIPELINE' => array('rty', '2-17'),
        'RT_TOOL' => array('rty', '2-19'),
        'RT_BOOK' => array('rty', '3-102'),
        'RT_CONFERENCE' => array('rty', '3-103'),
        'RT_PUB_SERIES' => array('rty', '3-104'),
        'RT_BOOK_CHAPTER' => array('rty', '3-108'),
        'RT_JOURNAL' => array('rty', '3-111'),
        'RT_JOURNAL_ARTICLE' => array('rty', '3-112'),
        'RT_JOURNAL_VOLUME' => array('rty', '3-113'),
        'RT_MAP' => array('rty', '3-115'),
        'RT_OTHER_DOC' => array('rty', '3-117'),
        'RT_REPORT' => array('rty', '3-119'),
        'RT_THESIS' => array('rty', '3-120'),
        'RT_PERSONAL_COMMUNICATION' => array('rty', '3-121'),
        'RT_ARTWORK' => array('rty', '3-122'),
        'RT_MAGAZINE_ARTICLE' => array('rty', '3-123'),
        'RT_MAGAZINE' => array('rty', '3-124'),
        'RT_MAGAZINE_VOLUME' => array('rty', '3-125'),
        'RT_NEWSPAPER' => array('rty', '3-126'),
        'RT_NEWSPAPER_VOLUME' => array('rty', '3-127'),
        'RT_NEWSPAPER_ARTICLE' => array('rty', '3-128'),
        'RT_PHOTOGRAPH' => array('rty', '3-129'),
        'RT_ARCHIVAL_RECORD' => array('rty', '3-1000'),
        'RT_ARCHIVAL_SERIES' => array('rty', '3-1001'),
        'RT_PLACE' => array('rty', '3-1009'),
        'RT_MAP_ANNOTATION' => array('rty', '2-101'),
        'RT_MAP_DOCUMENT' => array('rty', '3-1019'), // HeuristReferenceSet DB 3: Map document, layers and queries for new map function Oct 2014
        'RT_MAP_LAYER' => array('rty', '3-1020'),
        'RT_KML_SOURCE' => array('rty', '3-1014'),
        'RT_FILE_SOURCE' => array('rty', '2-53'), //csv tsv or dbf source
        'RT_SHP_SOURCE' => array('rty', '3-1017'),
        'RT_QUERY_SOURCE' => array('rty', '3-1021'),
        'RT_TLCMAP_DATASET' => array('rty', '1271-54'),
        'RT_IMAGE_SOURCE' => array('rty', '3-1018'),
        'RT_TILED_IMAGE_SOURCE' => array('rty', '2-11'), // added Ian 23/10/14 for consistency
        'RT_GEOTIFF_SOURCE' => array('rty', '3-1018'),
        'RT_WEB_CONTENT' => array('rty', '1147-25'),
        'RT_CMS_HOME' => array('rty', '99-51'),
        'RT_CMS_PAGE' => array('rty', '99-52'),
        'RT_CMS_MENU' => array('rty', '99-52'), // deprecated alias of RT_CMS_PAGE (kept for custom website scripts)
        'RT_BUG_REPORT' => array('rty', '8-23'),
        'RT_IIIF_ANNOTATION' => array('rty', '2-109'),
        'RT_IIIF_MANIFEST' => array('rty', '2-110'),
        'RT_IIIF_CANVAS' => array('rty', '2-111'),
        'RT_CUSTOM_REPORT' => array('rty', '2-1104'),
        'RT_REPORT_SCHEDULE' => array('rty', '2-1105'),
        'RT_REGISTERED_FILE' => array('rty', '2-1106'),
        'RT_USER_BOOKMARKS' => array('rty', '2-1110'),
        'RT_USER_REMINDERS' => array('rty', '2-1111'),
        // Fields (ported from hserv/consts.php $dtDefines)
        'DT_NAME' => array('dty', '2-1'),
        'DT_SHORT_NAME' => array('dty', '2-2'),
        'DT_SHORT_SUMMARY' => array('dty', '2-3'),
        'DT_EXTENDED_DESCRIPTION' => array('dty', '2-4'),
        'DT_TARGET_RESOURCE' => array('dty', '2-5'),
        'DT_RELATION_TYPE' => array('dty', '2-6'),
        'DT_PRIMARY_RESOURCE' => array('dty', '2-7'),
        'DT_INTERPRETATION_REFERENCE' => array('dty', '2-8'),
        'DT_DATE' => array('dty', '2-9'),
        'DT_START_DATE' => array('dty', '2-10'),
        'DT_END_DATE' => array('dty', '2-11'),
        'DT_QUERY_STRING' => array('dty', '2-12'),
        'DT_RESOURCE' => array('dty', '2-13'),
        'DT_CREATOR' => array('dty', '2-15'),
        'DT_CONTACT_INFO' => array('dty', '2-17'),
        'DT_GIVEN_NAMES' => array('dty', '2-18'),
        'DT_GENDER' => array('dty', '2-20'),
        'DT_EMAIL' => array('dty', '2-23'),
        'DT_GEO_OBJECT' => array('dty', '2-28'), //extent
        'DT_IS_LOADED_BY_EXTENT' => array('dty', '2-1160'), //is layer loads features for current extent only
        'DT_GEO_FIELDS' => array('dty', '2-1161'),
        'DT_GEO_OUTPUTMODE' => array('dty', '2-1162'), //combined map features per record or individual features
        'DT_EXPANSION_RULES' => array('dty', '2-1163'),
        'DT_TABLE_FIELDS' => array('dty', '2-1164'),
        'DT_FILTER_FORM' => array('dty', '2-1165'), // QuerySource Filter Form layout JSON
        'DT_TIMELINE_FIELDS' => array('dty', '2-1105'),
        'DT_FILE_EXT' => array('dty', '2-29'), // File type: a term of vocabulary 2-533 (extensions); 2-41 and 2-64 merged into it (2026-10-27)
        'DT_IMAGE_TYPE' => array('dty', '2-30'),
        'DT_MAP_IMAGE_LAYER_SCHEMA' => array('dty', '2-31'),
        'DT_MINIMUM_ZOOM_LEVEL' => array('dty', '2-32'), //in basemap zoom levels (0-20)
        'DT_MAXIMUM_ZOOM_LEVEL' => array('dty', '2-33'),
        'DT_MAXIMUM_ZOOM' => array('dty', '3-1085'), //in UI this field acts as minimum zoom in km
        'DT_MINIMUM_ZOOM' => array('dty', '3-1086'), //in UI this field acts as maximum zoom out km
        'DT_LEGEND_OUT_ZOOM' => array('dty', '3-1087'), //hide or disable layer in legend if layer is out of zoom range
        'DT_IS_VISIBLE' => array('dty', '2-1100'), //is layer initially visible on mapdocument initialization
        'DT_SERVICE_URL' => array('dty', '2-34'),
        'DT_URL' => array('dty', '3-1058'),
        'DT_COPYRIGHT' => array('dty', '2-35'),
        'DT_COPYOWNER' => array('dty', '2-1188'),
        'DT_ORIGINAL_RECORD_ID' => array('dty', '2-36'),
        'DT_FILE_RESOURCE' => array('dty', '2-38'),
        'DT_THUMBNAIL' => array('dty', '2-39'),
        'DT_ANNOTATION_INFO' => array('dty', '2-1098'), //for iiif and map annotations
        'DT_ANNOTATION_MOTIVATION' => array('dty', '2-1150'), //commenting, tagging, supplementing etc
        'DT_ANNOTATION_MANIFEST' => array('dty', '2-1151'), //ref to parent manifest
        'DT_IIIF_CANVAS' => array('dty', '2-1152'), //ref to canvas  former DT_ANNOTATION_TARGET
        'DT_ANNOTATION_STATE' => array('dty', '2-1153'),
        'DT_ANNOTATION_SELECTOR_TYPE' => array('dty', '2-1154'),
        'DT_ANNOTATION_SELECTOR_VALUE' => array('dty', '2-1155'),
        'DT_IIIF_ID' => array('dty', '2-1156'),
        'DT_ORIGINAL_IIIF_ID' => array('dty', '2-1158'),
        'DT_IIIF_IMAGE_SERVICE' => array('dty', '2-1159'), //iiif image service from manifest - specific for particular canvas
        'DT_HEIGHT' => array('dty', '3-1041'),
        'DT_WIDTH' => array('dty', '3-1040'),
        'DT_DURATION' => array('dty', '2-66'),
        'DT_FILTER_STRING' => array('dty', '2-40'),
        'DT_ANNOTATION_RESOURCE' => array('dty', '2-42'),
        'DT_ANNOTATION_RANGE' => array('dty', '2-43'),
        'DT_START_WORD' => array('dty', '2-44'),
        'DT_END_WORD' => array('dty', '2-45'),
        'DT_START_ELEMENT' => array('dty', '2-46'),
        'DT_END_ELEMENT' => array('dty', '2-47'),
        'DT_LAYOUT_STRING' => array('dty', '2-48'),
        'DT_TRANSFORM_RESOURCE' => array('dty', '2-50'),
        'DT_PROPERTY_VALUE' => array('dty', '2-51'),
        'DT_RECORD_TYPE' => array('dty', '2-53'),
        'DT_DETAIL_TYPE' => array('dty', '2-54'),
        'DT_COMMAND' => array('dty', '2-55'),
        'DT_COLOUR' => array('dty', '2-56'),
        'DT_DRAWING' => array('dty', '2-59'),
        'DT_COUNTER' => array('dty', '2-60'),
        'DT_MEDIA_RESOURCE' => array('dty', '2-61'), //refetence to media record
        'DT_FILE_NAME' => array('dty', '2-62'),
        'DT_FILE_FOLDER' => array('dty', '2-63'),
        'DT_FILE_DEVICE' => array('dty', '2-65'),
        'DT_FILE_DURATION' => array('dty', '2-66'),
        'DT_FILE_SIZE' => array('dty', '2-67'),
        'DT_FILE_MD5' => array('dty', '2-68'),
        'DT_PARENT_ENTITY' => array('dty', '2-247'),
        'DT_EDITOR' => array('dty', '3-1013'),
        'DT_DOI' => array('dty', '3-1003'),
        'DT_WEBSITE_ICON' => array('dty', '3-347'), //remove from code
        'DT_ISBN' => array('dty', '3-1011'),
        'DT_ISSN' => array('dty', '3-1032'),
        'DT_JOURNAL_REFERENCE' => array('dty', '3-1034'),
        'DT_MEDIA_REFERENCE' => array('dty', '3-508'), //*******************ERROR  THIS IS MISSING
        'DT_EXTERNAL_ID' => array('dty', '2-581'), //external non heurist record id
        'DT_KML_FILE' => array('dty', '3-1044'),
        'DT_KML' => array('dty', '3-1036'), //snippet
        'DT_MAP_IMAGE_LAYER_REFERENCE' => array('dty', '3-1043'),
        'DT_MAP_IMAGE_WORLDFILE' => array('dty', '3-1095'),
        'DT_ALTERNATE_NAME' => array('dty', '3-1009'),
        'DT_MAP_LAYER' => array('dty', '3-1081'),
        'DT_MAP_BOOKMARK' => array('dty', '3-1082'),
        'DT_SYMBOLOGY_POINTMARKER' => array('dty', '3-1091'), //outdated
        'DT_SYMBOLOGY' => array('dty', '3-1092'), //MAIN field that stores ALL styles for map symbology (including thematic maps)
        'DT_ZOOM_KM_POINT' => array('dty', '2-925'), //area to zoom in on point selection (per map space document)
        'DT_SMARTY_TEMPLATE' => array('dty', '2-922'), // smarty template to produce popup info per layer
        'DT_SYMBOLOGY_COLOR' => array('dty', '3-1037'), // outdated
        'DT_BG_COLOR' => array('dty', '2-551'), // outdated
        'DT_OPACITY' => array('dty', '3-1090'), // outdated
        'DT_ORDERING_HIERARCHY' => array('dty', '2-1082'), // field used to define drag-drop ordering of records
        'DT_DATA_SOURCE' => array('dty', '3-1083'),
        'DT_IS_CARD_VIEW' => array('dty', '2-1182'), // Flag Yes|No: report for a single record (popups, cards, record view)
        'DT_REPORT' => array('dty', '2-1183'), // pointer to Custom Report
        'DT_INTERVAL_MINUTES' => array('dty', '2-1184'), // regeneration interval of a report schedule
        'DT_ZIP_FILE' => array('dty', '3-1072'),
        'DT_SHAPE_FILE' => array('dty', '3-1069'),
        'DT_DBF_FILE' => array('dty', '3-1070'),
        'DT_SHX_FILE' => array('dty', '3-1071'),
        'DT_CRS' => array('dty', '2-1092'), // Coordinate Reference System
        'DT_WORLD_BASEMAP' => array('dty', '2-1093'), // Need to use trm_Label for terms to get basemap name
        'DT_EXTRACTED_TEXT' => array('dty', '2-652'), //for pdf parser
        'DT_CMS_TOP_MENU' => array('dty', '99-742'), //pointer  to top level menues in home page
        'DT_CMS_MENU' => array('dty', '99-761'), //pointer to sub menu
        'DT_CMS_KEYWORDS' => array('dty', '99-948'),
        'DT_CMS_TEMPLATE' => array('dty', '2-1099'),
        'DT_CMS_TARGET' => array('dty', '99-949'),
        'DT_CMS_HEADER' => array('dty', '2-929'),
        'DT_CMS_CSS' => array('dty', '99-946'),
        'DT_CMS_PAGETITLE' => array('dty', '99-952'), //show page title above content
        'DT_CMS_TOPMENUSELECTABLE' => array('dty', '2-938'), // allow top menu to be selectable, if a submenu is present
        'DT_CMS_SCRIPT' => array('dty', '2-927'),
        'DT_CMS_PAGETYPE' => array('dty', '2-928'), //menu (2-6253) or standalone (2-6254)
        'DT_CMS_EXTFILES' => array('dty', '2-939'), //external links and scripts
        'DT_CMS_FOOTER' => array('dty', '2-940'),
        'DT_CMS_FOOTER_FIXED' => array('dty', '2-941'), //fixed 2-532
        'DT_LANGUAGE' => array('dty', '2-967'),
        'DT_CMS_MENU_FORMAT' => array('dty', '2-1104'), //show name + icon, name only, or icon only
        'DT_CMS_MENU_HOME' => array('dty', '2-1149'), //show home entry in main menu
        'DT_CMS_ACTION' => array('dty', '2-1148'),
        'DT_WORKFLOW_STAGE' => array('dty', '2-1080'),
        'DT_VERSION' => array('dty', '2-49'),
        'DT_ORIGINAL_NAME' => array('dty', '2-1185'),
        'DT_REGISTERED_FILE_TYPE' => array('dty', '2-1186'),
        'DT_CONFIGURATION' => array('dty', '2-1187'),
        'DT_CMS_PAGE_TRANSLATIONS' => array('dty', '2-1202'), // translated text blocks of a v4 website page (plan 27a)
        // Terms (ported from hserv/consts.php $trmDefines)
        'TRM_PAGETYPE_WEBPAGE' => array('trm', '2-6254'),
        'TRM_PAGETYPE_MENUITEM' => array('trm', '2-6253'),
        'TRM_NO' => array('trm', '2-531'), // General 'No' term
        'TRM_NO_OLD' => array('trm', '99-5447'), // Older 'No' term, potentially from a specific DB context
        'TRM_SWF' => array('trm', '2-9453'), // Workflow stages vocabulary root/identifier
        'TRM_SWF_ADDED' => array('trm', '2-9464'), // Workflow Stage: 01 - Editing (includes manually created)
        'TRM_SWF_IMPORT' => array('trm', '2-9454'), // Workflow Stage: 00 - Imported
        'TRM_NAME_ONLY' => array('trm', '2-9634'), // Display name only
        'TRM_ICON_ONLY' => array('trm', '2-9635'), // Display icon only
        'TRM_NAME_AND_ICON' => array('trm', '2-9636'), // Display both name and icon
        'TRM_LEGEND_OUT_ZOOM_HIDDEN' => array('trm', '3-5081'), // Legend item becomes hidden
        'TRM_LEGEND_OUT_ZOOM_DISABLED' => array('trm', '3-5082'), // Legend item becomes disabled (greyed out)
        // only in srv (not in hserv/consts.php)
        'TRM_YES' => array('trm', '2-532'), // General 'Yes' term
        // file types (2026-10-27): extensions in groups; subsets by reference for tiled images and reports
        'TRM_FILE_EXT' => array('trm', '2-533'), // vocabulary of DT_FILE_EXT
        'TRM_TILE_FORMATS' => array('trm', '2-10639'), // png jpg jpeg gif webp (Artem, 2026-10-28)
        'TRM_REPORT_FORMATS' => array('trm', '2-10638'), // html csv txt xml json js css (Artem, 2026-10-28)
        // users and groups (plan 20, Phase 4d; user codes given by Artem 2026-10-24)
        'RT_SYS_USER' => array('rty', '2-1107'),
        'RT_SYS_GROUP' => array('rty', '2-1108'),
        'DT_DEPARTMENT' => array('dty', '3-1016'), // user: department / branch / unit
        'DT_LOGIN_NAME' => array('dty', '2-1194'), // user: login name (unique)
        'DT_PASSWORD' => array('dty', '2-1195'), // user: password, write only
        'DT_RESEARCH_INTERESTS' => array('dty', '2-1196'), // user: research interests
        'DT_ADDRESS' => array('dty', '2-1197'), // user: city / state / postcode, one line each
        'DT_ORGANISATION_NAME' => array('dty', '2-1198'), // user: organisation (text)
        'DT_ACCOUNT_STATUS' => array('dty', '2-1199'), // user: enum TRM_ACCOUNT_STATUS (ugr_Enabled)
        'DT_ORCID' => array('dty', '2-37'), // user: ORCID iD (core "Unique public identifier")
        'DT_LAST_LOGIN' => array('dty', '2-9'), // user: last login (read only; core "Date")
        'DT_LOGIN_COUNT' => array('dty', '2-1189'), // user: number of logins (read only; core "Usage count")
        'DT_GROUP_MEMBERSHIP' => array('dty', '2-1200'), // group: relmarker to the users, the role as relation type (plan 24)
        'TRM_ACCOUNT_STATUS' => array('trm', '2-10566'), // y, n, y_no_add, y_no_delete, y_no_add_delete
        'TRM_GROUP_ROLE' => array('trm', '2-10572') // term codes admin, member (ugl_Role)
    );

    /**
     * Kind and concept code of a constant.
     *
     * @return array{0:string,1:string}|null [kind, concept code], null for an unknown name.
     */
    public static function code(string $name): ?array
    {
        return self::CODES[$name] ?? null;
    }

    /** @return array<int,string> All names. */
    public static function names(): array
    {
        return array_keys(self::CODES);
    }

    /** Version of the table: a cached resolution of another version is made again. */
    public static function version(): string
    {
        return substr(md5(json_encode(self::CODES)), 0, 12);
    }
}
