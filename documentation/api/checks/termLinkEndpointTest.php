<?php
/** Isolated entity contract tests with an in-memory database fixture. */
namespace hserv\entity;
define('HEURIST_INVALID_REQUEST', 'invalid_request');
define('HEURIST_DB_ERROR', 'db_error');
define('SQL_WHERE', ' WHERE ');
define('SQL_AND', ' AND ');
function isPositiveInt($value){ return filter_var($value, FILTER_VALIDATE_INT) && $value>0; }
function mysql__found_rows($mysqli){ return 1; }
class DbEntityBase {
    protected $data, $system, $config, $searchMgr;
    public function __construct($data, $system){
        $this->data=$data; $this->system=$system; $this->config=array('entityName'=>'defTermsLinks');
        $this->searchMgr=new class { function getLimit(){ return ' LIMIT 1000'; } function getOffset(){ return ''; } };
    }
    public function search(){ return true; }
}
require_once __DIR__.'/../../../hserv/entity/dbDefTermsLinks.php';
function check($condition, $message){ if(!$condition){ throw new \RuntimeException($message); } }
class FixtureResult {
    private $rows;
    function __construct($rows){ $this->rows=$rows; }
    function fetch_all($mode){ return $this->rows; }
    function fetch_row(){ return array_shift($this->rows); }
    function close(){}
}
class FixtureDatabase {
    public $queries=array();
    function query($sql){
        $this->queries[]=$sql;
        if(strpos($sql,'FROM defTermsLinks')!==false){
            return new FixtureResult(strpos($sql,'SQL_CALC')!==false ? array(array(1,2)) : array(array('trl_ParentID'=>1,'trl_TermID'=>2)));
        }
        return new FixtureResult(array(
            array('trm_ID'=>1,'trm_Label'=>'Vocabulary','trm_ParentTermID'=>null),
            array('trm_ID'=>2,'trm_Label'=>'Relation','trm_ParentTermID'=>1),
            array('trm_ID'=>3,'trm_Label'=>'Sibling','trm_ParentTermID'=>1)));
    }
}
class FixtureSystem {
    public $db, $errors=array();
    function __construct(){ $this->db=new FixtureDatabase(); }
    function getMysqli(){ return $this->db; }
    function addError($code, $message, $details=null){ $this->errors[]=array($code,$message); }
}
$system=new FixtureSystem();
$result=(new DbDefTermsLinks(array('tree'=>'1','trl_TermID'=>'2,3'),$system))->search();
check(count($system->db->queries)===1, 'Batch ancestors use one query');
check($result['fields']===array('id','label','parentId','children'), 'Tree fields');
check(count($result['records'][0][3])===2, 'Shared vocabulary includes both terms');
check($result['count']===1, 'Pagination counts roots');
$system=new FixtureSystem();
$result=(new DbDefTermsLinks(array('tree'=>'true','trl_ParentID'=>1),$system))->search();
check(count($system->db->queries)===2, 'Full subtree uses two queries');
check(count($result['records'][0][3])===2, 'Subtree deduplicates implicit and explicit links');
$system=new FixtureSystem();
$result=(new DbDefTermsLinks(array('tree'=>'false','trl_TermID'=>2),$system))->search();
check($result['fields']===array('trl_ParentID','trl_TermID'), 'Legacy pair response unchanged');
foreach(array(array('tree'=>1), array('tree'=>'invalid'), array('tree'=>1,'trl_TermID'=>'2,no'), array('tree'=>1,'trl_TermID'=>2,'trl_ParentID'=>1)) as $data){
    $system=new FixtureSystem();
    check((new DbDefTermsLinks($data,$system))->search()===false, 'Invalid request rejected');
    check(count($system->db->queries)===0, 'Invalid request performs no database query');
}
echo "Term-link entity contract tests passed\n";
