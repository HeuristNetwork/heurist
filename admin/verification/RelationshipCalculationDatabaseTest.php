<?php
/** Integration fixture. Creates its own database on a disposable MySQL server at 127.0.0.1. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--disposable-mysql', $argv, true)) {
    fwrite(STDERR, "Run only against a disposable MySQL server, with --disposable-mysql\n");
    exit(2);
}
require_once __DIR__.'/RelationshipCalculationTest.php';
require_once dirname(__DIR__, 2).'/hserv/records/edit/recordModify.php';
$GLOBALS['glb_lang_codes'] = array(array('a3'=>'eng','a2'=>'en'));
$GLOBALS['glb_lang_codes_index'] = array('ENG'=>'EN');

use hserv\records\edit\RelationshipCalculation as Calculation;

// SQL placeholders bind values, not identifiers. Accept only our generated
// fixture names before quoting an identifier for CREATE/DROP DATABASE.
function calculationTestDatabaseIdentifier($name) {
    if (!is_string($name) || !preg_match('/\Acurrent_locations_test_[a-f0-9]{8}\z/D', $name)) {
        throw new InvalidArgumentException('Invalid calculation fixture database name');
    }
    return '`'.$name.'`';
}
foreach (array('current_locations_test_deadbeef`; DROP DATABASE mysql; --',
    'mysql', 'current_locations_test_deadbeef;') as $unsafeName) {
    $rejected = false;
    try { calculationTestDatabaseIdentifier($unsafeName); }
    catch (InvalidArgumentException $expected) { $rejected = true; }
    same(true, $rejected, 'Unsafe database identifier is rejected');
}

$testPassword = getenv('HEURIST_MYSQL_ROOT_PASSWORD') ?: '';
$db = new mysqli('127.0.0.1', 'root', $testPassword);
$testDatabase = 'current_locations_test_'.bin2hex(random_bytes(4));
$testDatabaseIdentifier = calculationTestDatabaseIdentifier($testDatabase);
$db->query('CREATE DATABASE '.$testDatabaseIdentifier);
$db->select_db($testDatabase);
$db->query("SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'");
$db->multi_query(file_get_contents(dirname(__DIR__, 2).'/admin/setup/dbcreate/blankDBStructure.sql'));
do { if ($result = $db->store_result()) { $result->free(); } } while ($db->more_results() && $db->next_result());

class CalculationTestSystem {
    private $db;
    public $settings;
    function __construct($db) { $this->db = $db; $this->settings = new class {
        function get($key) { return $key === 'sys_dbVersion' ? '1.3.14' : 0; }
        function isDbVersionAtLeast($version) { return true; }
        function getDatabaseSetting($name) { return null; }
    }; }
    function getMysqli() { return $this->db; }
    function dbname() { return $this->db->query('SELECT DATABASE()')->fetch_row()[0]; }
    function getSysDir($kind) { $dir = '/tmp/calculation-test/'.$kind.'/'; @mkdir($dir, 0777, true); return $dir; }
    function getCurrentUser() { return array('ugr_ID'=>2, 'ugr_Groups'=>array()); }
    function getUserId() { return 2; }
    function hasAccess() { return true; }
    function isAdmin() { return true; }
    function clearError() {}
    function userGetPreference($name) { return $name === 'layout_language' ? 'eng' : null; }
    function getUserGroupIds() { return array(); }
    function getConstant($name) { return defined($name) ? constant($name) : null; }
    function defineConstant($name) {
        $codes = array('RT_RELATION'=>1,'DT_PRIMARY_RESOURCE'=>7,'DT_TARGET_RESOURCE'=>5,'DT_RELATION_TYPE'=>6,'DT_NAME'=>1,
            'RT_CMS_HOME'=>99,'RT_CMS_PAGE'=>100,'DT_EXTENDED_DESCRIPTION'=>4);
        if (!defined($name) && isset($codes[$name])) { define($name,$codes[$name]); }
        return defined($name);
    }
    function addError($code, $message, $extra = '') { throw new RuntimeException($message.' '.$extra); }
}
$system = new CalculationTestSystem($db);
$db->query("INSERT INTO defRecTypes (rty_ID,rty_Name,rty_TitleMask) VALUES (1,'Relationship','[3]'),(104,'Item','[1]'),(114,'Housing Location','[1]'),(115,'Box','[1]')");
$db->query("INSERT INTO defDetailTypes (dty_ID,dty_Name,dty_Type,dty_PtrTargetRectypeIDs) VALUES (1,'Name','freetext',NULL),(10,'Start date/time','date',NULL),(1177,'Current Location','resource','114')");
$formula = Calculation::REFRESH_MARKER.'{$heurist->latestRelatedRecord($r,114,"9878,9879,13113",10)}';
$stmt = $db->prepare('INSERT INTO defCalcFunctions (cfn_ID,cfn_Name,cfn_FunctionSpecification,cfn_RecTypeIDs) VALUES (1,\'Current location\',?,\'1\')');
$stmt->bind_param('s', $formula); $stmt->execute();
$db->query("INSERT INTO defRecStructure (rst_RecTypeID,rst_DetailTypeID,rst_DisplayName,rst_CalcFunctionID,rst_MaxValues) VALUES (104,1177,'Current Location',1,1),(115,1177,'Current Location',1,1)");
$fixtureInsert = $db->prepare("INSERT INTO Records (rec_ID,rec_RecTypeID,rec_Title,rec_FlagTemporary,rec_OwnerUGrpID,rec_NonOwnerVisibility) VALUES (?,?,?,0,2,'public')");
$fixtureInsert->bind_param('iis', $id, $type, $title);
foreach (array(100=>104,101=>115,200=>114,201=>114,202=>114,300=>1,301=>1,302=>1,303=>1,304=>1) as $id=>$type) {
    $title = 'Fixture '.$id;
    $fixtureInsert->execute();
}
// SQL-looking text must survive as data, including quotes and a comment marker.
$id = 306; $type = 1; $title = "Fixture '); DROP TABLE Records; --";
$fixtureInsert->execute();
same($title, mysql__select_value($db, 'SELECT rec_Title FROM Records WHERE rec_ID=306'),
    'Prepared inserts preserve SQL-looking text literally');
same(11, (int)mysql__select_value($db, 'SELECT COUNT(*) FROM Records'),
    'SQL-looking fixture text leaves the records table intact');
$db->query("INSERT INTO recDetails (dtl_RecID,dtl_DetailTypeID,dtl_Value) VALUES (300,10,'2025-01-01'),(301,10,'2026-01-01'),(303,10,'2026-10-14')");
$db->query("INSERT INTO recLinks (rl_SourceID,rl_TargetID,rl_RelationID,rl_RelationTypeID) VALUES (100,200,300,9878),(100,201,301,9879),(100,202,302,9878),(100,202,303,9999),(101,200,304,9878)");
same('201', Calculation::latestTarget($system,100,114,'9878,9879,13113'), 'Storage relationship types are isolated');
same('200', Calculation::latestTarget($system,101,114,'9878'), 'Box links work too');
same(array('100'), Calculation::sources($system,array(301)), 'Relationship source is captured');
$before = Calculation::sources($system,array(301));
$db->query('UPDATE recLinks SET rl_SourceID=101 WHERE rl_RelationID=301');
same(array('101'), Calculation::sources($system,array(301)), 'Moved relationship exposes its new source');
same(array('100','101'), array_merge($before,Calculation::sources($system,array(301))), 'Both old and new sources can refresh');
$db->query('UPDATE recLinks SET rl_SourceID=100 WHERE rl_RelationID=301');
$result = recordUpdateCalcFields($system,array(100,101),null,null,0,true);
same(array(),$result['errors'],'Smarty formula evaluates without errors');
same('201',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=100 AND dtl_DetailTypeID=1177'),'Calculated target is persisted');
same('200',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=101 AND dtl_DetailTypeID=1177'),'Box calculated target is persisted');

// More than the legacy 100-record limit must not prevent local refreshes.
$type = 104; $title = 'Untouched';
for ($id=1000; $id<1105; $id++) {
    $fixtureInsert->execute();
}
$fixtureInsert->close();
$db->query("UPDATE recDetails SET dtl_Value='2027-01-01' WHERE dtl_RecID=300 AND dtl_DetailTypeID=10");
$refreshed = Calculation::refresh($system,array(100));
same(array(),$refreshed['errors'],'Targeted update ignores the database-wide limit');
same('200',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=100 AND dtl_DetailTypeID=1177'),'Changed relationship date refreshes the source');
same(2,(int)mysql__select_value($db,'SELECT COUNT(*) FROM recDetails WHERE dtl_DetailTypeID=1177'),'Unrelated records are untouched');
$db->query('DELETE FROM recLinks WHERE rl_RelationID=300');
Calculation::refresh($system,array(100));
same('201',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=100 AND dtl_DetailTypeID=1177'),'Deleting the latest relationship selects the next');
$db->query('DELETE FROM recLinks WHERE rl_SourceID=100');
Calculation::refresh($system,array(100));
same(null,mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=100 AND dtl_DetailTypeID=1177'),'Deleting all storage history clears the link');

// Bad formula outputs must preserve the previous link, including target-type mismatches.
foreach (array('999999','100','200,201','<a href="200">Location</a>') as $invalid) {
    $formula = Calculation::REFRESH_MARKER.$invalid;
    $stmt = $db->prepare('UPDATE defCalcFunctions SET cfn_FunctionSpecification=? WHERE cfn_ID=1');
    $stmt->bind_param('s',$formula); $stmt->execute();
    $result = recordUpdateCalcFields($system,array(101),null,null,0,true);
    same(true,!empty($result['errors']),'Invalid pointer is reported: '.$invalid);
    same('200',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=101 AND dtl_DetailTypeID=1177'),'Existing pointer survives invalid output');
}
$formula=Calculation::REFRESH_MARKER.'200';
$stmt=$db->prepare('UPDATE defCalcFunctions SET cfn_FunctionSpecification=? WHERE cfn_ID=1');
$stmt->bind_param('s',$formula); $stmt->execute();
$db->query("INSERT INTO recDetails (dtl_RecID,dtl_DetailTypeID,dtl_Value) VALUES (101,1177,'201')");
$result=recordUpdateCalcFields($system,array(101),null,null,0,true);
same(array(),$result['errors'],'A calculated pointer with duplicate existing values evaluates');
same(1,(int)mysql__select_value($db,'SELECT COUNT(*) FROM recDetails WHERE dtl_RecID=101 AND dtl_DetailTypeID=1177'),'Duplicate pointers are normalized even when the first value is correct');
same('200',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=101 AND dtl_DetailTypeID=1177'),'The selected pointer is retained');
$db->query("INSERT INTO recDetails (dtl_RecID,dtl_DetailTypeID,dtl_Value) VALUES (101,1177,'201')");
$pdo = new PDO('mysql:host=127.0.0.1;dbname='.$testDatabase, 'root', $testPassword);
$adapter = new \Heurist\Database\MysqlDatabase($pdo);
$definitions = new \Heurist\Definitions\DefinitionLookup($adapter);
$store = new \Heurist\Records\Store\RecordStore($adapter);
$output = '100';
$modern = new \Heurist\Records\Edit\CalculatedFields($definitions,$store,
    function($formula,$id) use (&$output) { return array('output'=>$output,'error'=>null); });
same(true,!empty($modern->update(101,115)),'New API also rejects a disallowed target type');
same('200',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=101 AND dtl_DetailTypeID=1177'),'New API preserves the previous pointer on error');
$output='200';
same(array(),$modern->update(101,115),'New API evaluates an already-correct pointer with duplicate values');
same(1,(int)mysql__select_value($db,'SELECT COUNT(*) FROM recDetails WHERE dtl_RecID=101 AND dtl_DetailTypeID=1177'),'New API also normalizes duplicate pointers');
$output='201';
same(array(),$modern->update(101,115),'New API accepts an existing allowed target');
same('201',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=101 AND dtl_DetailTypeID=1177'),'New API writes the calculated pointer');
// Install the actual production triggers to exercise recordSave/deleteOneRecord hooks.
$sql = file_get_contents(dirname(__DIR__, 2).'/admin/setup/dbcreate/addProceduresTriggers.sql');
list($before,$after) = preg_split('/^DELIMITER \$\$\s*$/m',$sql,2);
list($body,$tail) = preg_split('/^DELIMITER ;\s*$/m',$after,2);
$db->multi_query($before);
do { if ($r=$db->store_result()) { $r->free(); } } while ($db->more_results() && $db->next_result());
foreach (explode('$$',$body) as $statement) {
    if(trim($statement)!=='') { $db->query($statement); }
}
$db->query("UPDATE sysUGrps SET ugr_Enabled='y' WHERE ugr_ID=2");
$db->query("INSERT INTO defDetailTypes (dty_ID,dty_Name,dty_Type) VALUES (5,'Target','resource'),(7,'Source','resource'),(6,'Relationship type','relationtype'),(3,'Description','blocktext')");
$formula = Calculation::REFRESH_MARKER.'{$heurist->latestRelatedRecord($r,114,"9878,9879,13113",10)}';
$stmt=$db->prepare('UPDATE defCalcFunctions SET cfn_FunctionSpecification=? WHERE cfn_ID=1');
$stmt->bind_param('s',$formula); $stmt->execute();
$system->defineConstant('RT_RELATION');
$structureInsert = $db->prepare("INSERT INTO defRecStructure (rst_RecTypeID,rst_DetailTypeID,rst_DisplayName,rst_MaxValues) VALUES (1,?,'Relationship field',1)");
$structureInsert->bind_param('i', $field);
foreach (array(5,7,6,10,3) as $field) {
    $structureInsert->execute();
}
$structureInsert->close();
$db->query("INSERT INTO Records (rec_ID,rec_RecTypeID,rec_Title,rec_FlagTemporary,rec_OwnerUGrpID,rec_NonOwnerVisibility) VALUES (305,1,'New relationship',0,2,'public')");
$saved=recordSave($system,array('ID'=>305,'RecTypeID'=>1,'FlagTemporary'=>0,'OwnerUGrpID'=>2,'NonOwnerVisibility'=>'public',
    'no_validation'=>'ignore_all','details'=>array(7=>array('100'),5=>array('201'),6=>array('9878'),10=>array('2028-01-01'),3=>array('Test move'))));
same(HEURIST_OK,$saved['status'],'A relationship edit succeeds');
same('201',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=100 AND dtl_DetailTypeID=1177'),'A real save refreshes Current Location');
same('201',mysql__select_value($db,'SELECT rl_TargetID FROM recLinks WHERE rl_SourceID=100 AND rl_DetailTypeID=1177'),'Production triggers maintain the calculated pointer index');
$saved=recordSave($system,array('ID'=>305,'RecTypeID'=>1,'FlagTemporary'=>0,'OwnerUGrpID'=>2,'NonOwnerVisibility'=>'public',
    'no_validation'=>'ignore_all','details'=>array(7=>array('101'),5=>array('201'),6=>array('9878'),10=>array('2028-01-01'),3=>array('Moved source'))));
same(HEURIST_OK,$saved['status'],'Changing a relationship source succeeds');
same(null,mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=100 AND dtl_DetailTypeID=1177'),'Changing a relationship source refreshes its previous source');
same('201',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=101 AND dtl_DetailTypeID=1177'),'Changing a relationship source refreshes its new source');
$badFormula=Calculation::REFRESH_MARKER.'100';
$stmt=$db->prepare('UPDATE defCalcFunctions SET cfn_FunctionSpecification=? WHERE cfn_ID=1');
$stmt->bind_param('s',$badFormula); $stmt->execute();
$failed=false;
try {
    recordSave($system,array('ID'=>305,'RecTypeID'=>1,'FlagTemporary'=>0,'OwnerUGrpID'=>2,'NonOwnerVisibility'=>'public',
        'no_validation'=>'ignore_all','details'=>array(7=>array('100'),5=>array('200'),6=>array('9878'),10=>array('2028-01-01'),3=>array('Must roll back'))));
} catch (RuntimeException $expected) {
    $failed=strpos($expected->getMessage(),'Cannot refresh relationship calculations')!==false;
}
same(true,$failed,'A failed relationship calculation reports the save failure');
same('101',mysql__select_value($db,'SELECT rl_SourceID FROM recLinks WHERE rl_RelationID=305'),'A failed calculation rolls back the relationship change');
same('201',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=101 AND dtl_DetailTypeID=1177'),'A failed calculation preserves Current Location');
$stmt=$db->prepare('UPDATE defCalcFunctions SET cfn_FunctionSpecification=? WHERE cfn_ID=1');
$stmt->bind_param('s',$formula); $stmt->execute();
$deleted=recordDelete($system,array(305));
same(HEURIST_OK,$deleted['status'],'A relationship delete succeeds');
same(null,mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=100 AND dtl_DetailTypeID=1177'),'A real deletion refreshes Current Location');
same('200',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=101 AND dtl_DetailTypeID=1177'),'Deletion restores the remaining storage location');
$created=recordSave($system,array('ID'=>0,'RecTypeID'=>1,'FlagTemporary'=>0,'OwnerUGrpID'=>2,'NonOwnerVisibility'=>'public',
    'no_validation'=>'ignore_all','details'=>array(7=>array('100'),5=>array('202'),6=>array('9878'),10=>array('2029-01-01'),3=>array('New move'))));
same(HEURIST_OK,$created['status'],'A new relationship can be saved');
same('202',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=100 AND dtl_DetailTypeID=1177'),'Creating a relationship refreshes its source');
$created=recordSave($system,array('ID'=>0,'RecTypeID'=>1,'FlagTemporary'=>0,'OwnerUGrpID'=>2,'NonOwnerVisibility'=>'public','AddedByImport'=>1,
    'no_validation'=>'ignore_all','details'=>array(7=>array('101'),5=>array('202'),6=>array('9878'),10=>array('2030-01-01'),3=>array('Imported move'))));
same(HEURIST_OK,$created['status'],'An imported relationship can be saved');
same('202',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=101 AND dtl_DetailTypeID=1177'),'Imported relationships refresh their source');
$deleted=recordDelete($system,array(202));
same(HEURIST_OK,$deleted['status'],'Deleting a housing location succeeds');
same(null,mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=100 AND dtl_DetailTypeID=1177'),'Deleting housing clears a source with no remaining history');
same('200',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=101 AND dtl_DetailTypeID=1177'),'Deleting housing restores the remaining valid location');

// The supplied collection formula preserves manual pointers when history is absent.
$formula=file_get_contents(dirname(__DIR__, 2).'/documentation/examples/current-location.tpl');
$stmt=$db->prepare('UPDATE defCalcFunctions SET cfn_FunctionSpecification=? WHERE cfn_ID=1');
$stmt->bind_param('s',$formula); $stmt->execute();
$db->query("INSERT INTO recDetails (dtl_RecID,dtl_DetailTypeID,dtl_Value) VALUES (100,1177,'201')");
$result=recordUpdateCalcFields($system,array(100,1100),null,null,0,true);
same(array(),$result['errors'],'The supplied preservation formula evaluates');
same('201',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=100 AND dtl_DetailTypeID=1177'),'No history preserves the existing location');
same(null,mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=1100 AND dtl_DetailTypeID=1177'),'No history and no existing location remains empty');
$created=recordSave($system,array('ID'=>0,'RecTypeID'=>1,'FlagTemporary'=>0,'OwnerUGrpID'=>2,'NonOwnerVisibility'=>'public',
    'no_validation'=>'ignore_all','details'=>array(7=>array('100'),5=>array('200'),6=>array('9878'),10=>array('2031-01-01'),3=>array('Preservation example'))));
same('200',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=100 AND dtl_DetailTypeID=1177'),'History overrides a manual location');
$deleted=recordDelete($system,array($created['data']));
same(HEURIST_OK,$deleted['status'],'The last history relationship can be deleted');
same('200',mysql__select_value($db,'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=100 AND dtl_DetailTypeID=1177'),'Deleting the last history entry keeps the current location');
$db->query('DROP DATABASE '.$testDatabaseIdentifier);
echo "Passed $checks checks including MySQL and Smarty\n";
