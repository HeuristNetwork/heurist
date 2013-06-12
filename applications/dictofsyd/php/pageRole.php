<?php
/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* Render role page
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/

function makeRolePage($record)
{
	print '<div id="subject-list">';
	$val = $record->getDet(DT_DESCRIPTION);
	if($val){
		print '<div class="entity-content"><p>'.$val.'</p></div>';
	}

	$factoids = $record->getRelationRecords();

	//sort by target
	uasort($factoids, 'sort_factoids_bytarget');

	foreach ($factoids as $id=>$factoid){
		if(!isset($target_id) || $target_id!=$factoid->getDet(DT_FACTOID_TARGET)){

			if(isset($target_id)){
				makeRoleFactoidGroup($record, $fgroup, $target_id);
			}

			$target_id = $factoid->getDet(DT_FACTOID_TARGET);
			$fgroup = array();
		}
		$fgroup[$id] = $factoid;
	}
	if(isset($target_id)){
		makeRoleFactoidGroup($record, $fgroup, $target_id);
	}

	print "</div>";
}


function sort_factoids_bytarget(Record $a,  Record $b){
            if($a && $b){
                return (getTargetName($a)<getTargetName($b))?-1:1;
            }else{
                return 0;
            }
}
function sort_factoids_bysource(Record $a,  Record $b){
            if($a && $b){
                return ($a->getDet(DT_FACTOID_SOURCE, 'ref') < $b->getDet(DT_FACTOID_SOURCE, 'ref'))?-1:1;/* &&
                        $a->getDet(DT_DATE_START) < $b->getDet(DT_DATE_START) &&
                        $a->getDet(DT_DATE_END) < $b->getDet(DT_DATE_END))?-1:1;*/

            }else{
                return 0;
            }
}

function getTargetName($factoid){
    $id = $factoid->getDet(DT_FACTOID_TARGET);
    if($id){
        $res = $factoid->getDet(DT_FACTOID_TARGET, 'ref');
        if(!$res) $res = $factoid->getDet(DT_FACTOID_DESCR);
        return $res;
    }else{
        return "";
    }
}

function makeRoleFactoidGroup(Record $record, $factoids, $target_id){
    if($factoids && count($factoids)>0){

        $factoid0 = reset($factoids);

        if($target_id){
            $title = $factoid0->getFeatureTypeName().' - '.$record->getDet(DT_NAME).' of '.
            			getLinkTag($factoid0, DT_FACTOID_TARGET);
        }else{
            $title = $record->getFeatureTypeName().' - '.$record->getDet(DT_NAME);
        }
?>
            <div class="entity-information">
                <div class="entity-information-heading">
                    <?=$title ?>
                </div>

<?php
        uasort($factoids, 'sort_factoids_bysource');
        foreach ($factoids as $factoid) {
                makeRoleFactoid($factoid);
        }
?>
                    <div class="clearfix"></div>
            </div>
<?php
    }
}

function makeRoleFactoid(Record $factoid){

        print '<div class="entity-information-col01-02">'.
                    			getLinkTag($factoid, DT_FACTOID_SOURCE).'</div>';

        print '<div class="entity-information-col03">'.formatDate($factoid->getDet(DT_DATE_START)).'</div>';

        if($factoid->getDet(DT_DATE_START)!=$factoid->getDet(DT_DATE_END)){
            print '<div class="entity-information-col04"> - </div>';
            print '<div class="entity-information-col05">'.formatDate($factoid->getDet(DT_DATE_END)).'</div>';
        }

}
?>

