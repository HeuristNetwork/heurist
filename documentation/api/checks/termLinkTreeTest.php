<?php
/** Run with php documentation/api/checks/termLinkTreeTest.php; no database required. */
require_once __DIR__.'/../../../hserv/entity/TermLinkTree.php';
use hserv\entity\TermLinkTree;

function check($condition, $message){
    if(!$condition){ throw new RuntimeException($message); }
}
function rejects($call, $class){
    try{ $call(); }catch(Throwable $error){ check($error instanceof $class, $error->getMessage()); return; }
    throw new RuntimeException('Expected '.$class);
}
$rows = array();
foreach(array(array(1,'Vocabulary A',null), array(2,'Parent',1), array(3,'Alpha',2),
        array(4,'Beta',2), array(5,'Unused',1), array(10,'Vocabulary B',null), array(11,'Other',10)) as $row){
    $rows[] = array('trm_ID'=>$row[0], 'trm_Label'=>$row[1], 'trm_ParentTermID'=>$row[2]);
}
check(TermLinkTree::ids('3,4,3') === array(3,4), 'Unique comma IDs');
check(TermLinkTree::ids(array('3','4')) === array(3,4), 'Array IDs');
foreach(array('', '0', '-1', '1,x', '1.5', array(array(1))) as $bad){
    rejects(function() use ($bad){ TermLinkTree::ids($bad); }, InvalidArgumentException::class);
}
$trees = TermLinkTree::build($rows, array(3,4,11,999), false);
check(array_column($trees, 'id') === array(1,10), 'Separate vocabulary roots; unknown ID omitted');
check(count($trees[0]['children']) === 1, 'Unused sibling omitted');
check(array_column($trees[0]['children'][0]['children'], 'id') === array(3,4), 'Shared ancestors merged');
$links = array(array('trl_ParentID'=>2,'trl_TermID'=>11), array('trl_ParentID'=>11,'trl_TermID'=>2));
$full = TermLinkTree::build($rows, array(2), true, $links);
check(array_column($full[0]['children'], 'id') === array(3,4,11), 'Full children including references');
check($full[0]['children'][2]['parentId'] === 10, 'Reference retains its real parent');
check($full[0]['children'][2]['children'] === array(), 'Reference cycle is not followed');
check(TermLinkTree::build($rows, array(999), false) === array(), 'Missing-only request');
$deep = array();
for($i=1; $i<=30; $i++){ $deep[] = array('trm_ID'=>$i,'trm_Label'=>(string)$i,'trm_ParentTermID'=>$i-1); }
$tree = TermLinkTree::build($deep, array(30), false)[0];
for($i=1; $i<30; $i++){ $tree = $tree['children'][0]; }
check($tree['id'] === 30, 'No former 12-level truncation');
$cycle = array(array('trm_ID'=>1,'trm_Label'=>'A','trm_ParentTermID'=>2),array('trm_ID'=>2,'trm_Label'=>'B','trm_ParentTermID'=>1));
rejects(function() use ($cycle){ TermLinkTree::build($cycle,array(1),false); }, RuntimeException::class);
echo "TermLinkTree tests passed\n";
