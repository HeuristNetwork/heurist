/**
*  Utility functions for database structure
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/*
Selectors:

TERMS

getInverseTermById

getTermValue - returns label and code for term by id

getTermByCode - returns term by code in given vocab

getTermVocab - returns vocabulary for given term - real vocabulary (not by reference)

trm_InVocab - returns true if term belongs to vocabulary (including by reference)

isTermByReference - return false if given term belongs to vocabulary, otherwise returns level of reference

getColorFromTermValue - Returns hex color by label or code for term by id

    trm_TreeData  - returns hierarchy for given vocabulary as a flat array, recordset or tree data
    trm_HasChildren - is given term has children
    trm_getVocabs - get all vocabularies OR for given domain
    trm_getAllVocabs - get all vocab where given term presents directly or by reference
    trm_RemoveLinks - remove all entries of term from trm_Links


RECTYPES
   

createRectypeStructureTree
getLinkedRecordTypes  -  FIX in search_faceted.js

hasFields - returns true if rectype has a field in its structure
rstField - Returns rectype header or details field values


    getLocalID
    getConceptID

getTrashGroupId
*/

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}

//init only once
if (!window.hWin.HEURIST4.dbs) 
{

window.hWin.HEURIST4.dbs = {
    
    baseFieldType: {},
    
    needUpdateRtyCount: -1,
    
    rtg_trash_id: 0,
    dtg_trash_id: 0,
    vcg_trash_id: 0,
    
    vocabs_already_synched: false, //set to true after first direct import by mapping to avoid sync on every request

    //
    // return vocabulary for given term - real vocabulary (not by reference)
    //    
    getTermVocab: function(trm_ID){
        var trm_ParentTermID;
        do{
            trm_ParentTermID = $Db.trm(trm_ID, 'trm_ParentTermID');
            if(trm_ParentTermID>0){
                trm_ID = trm_ParentTermID;
            }else{
                break;
            }
        }while (trm_ParentTermID>0);
        
        return trm_ID;        
    },

    //
    // return false if given term belongs to vocabulary, otherwise returns level of reference
    // 0 - first level - parent is either vocab or "real" terms
    // 1 or more - parent is term by reference also.
    //
    isTermByReference: function(vocab_id, trm_ID){
        
        var real_vocab_id = $Db.getTermVocab(trm_ID);
        
        if(real_vocab_id==vocab_id){
            return false; //this is not reference
        }
        
        var t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 

        function __checkParents(recID, lvl){
            
            var children = t_idx[recID]; //array of children ids trm_Links (including references)    
            if(children){
                var k = window.hWin.HEURIST4.util.findArrayIndex(trm_ID, children);
                
                if(k>=0){
                    return lvl;
                }
                
                for(k=0;k<children.length;k++){
                    
                    var real_parent_id = $Db.trm(children[k], 'trm_ParentTermID');
                    var lvl2 = lvl + (lvl>0 || (real_parent_id>0 && real_parent_id!=recID))?1:0;
                    
                    var res = __checkParents(children[k], lvl2);
                    if(res!==false) return res;
                }
            }
            return false;
        }    
        
        return __checkParents(vocab_id, 0);
    },
    
    //
    // Returns label and code for term by id
    //
    getTermValue: function(termID, withcode){
        
        var term = $Db.trm(termID);
        var termName, termCode='';

        if(term){
            termName = term['trm_Label'];
            termCode = term['trm_Code'];
            if(window.hWin.HEURIST4.util.isempty(termCode)){
                termCode = '';
            }else{
                termCode = " ("+termCode+")";
            }
        } else {
            termName = 'not found term#'+termID;
        }

        return termName+(withcode ?termCode :'');
    },
    
    //
    // get inverse term id
    //
    getInverseTermById: function(termID){
        var term = $Db.trm(termID);
        if(term){
            var invTermID = term['trm_InverseTermId'];
            if(invTermID>0) return invTermID;
            return termID;
        }
        return '';
    },
    
    //
    // Returns hex color by label or code for term by id
    //
    getColorFromTermValue: function(termID){

        var termName, termCode='';

        if(termID>0){
            var term = $Db.trm(termID);
            if(term){

                termName = term['trm_Label'];
                termCode = term['trm_Code'];
                if(window.hWin.HEURIST4.util.isempty(termCode)){
                    var cnames = window.hWin.HEURIST4.ui.getColorArr('names');
                    var idx = window.hWin.HEURIST4.util.findArrayIndex(termName.toLowerCase(),cnames);
                    if(idx>=0){
                        cnames = window.hWin.HEURIST4.ui.getColorArr('hexs');
                        termCode = '#'+cnames[idx]; 
                    }
                }
            }
        }

        return termCode;
    },

    //========================================================================
    /*
     
      returns rectype structure as treeview data
      there is similar method on server side - however on client side it is faster
      used for treeview in import structure, faceted search wizard
      todo - use it in smarty editor and title mask editor
     
      rectypeids - set of rty ids     
      fieldtypes - 
            array of fieldtypes, 
                    all
                    header - only title and modified fields
                    header_ext - all header fields
                    parent_link - include field DT_PARENT_ENTITY - link to parent record
            header - all+header fields
      $mode 
         3 - for record title mask editor - without reverse, enum (id,label,code,internal id)
         4 - find reverse links and relations   
         5 - for lazy treeview with reverse links (faceted search wiz, filter builder)
         6 - for lazy tree without reverse (import structure, export csv)
         
      parentcode - prefix for code 
         
      returns:
         
       children:[{key: field#, type: fieldtype, title:'', code , name, conceptCode, dtyID_local, children:[]},... ]
     
    */
    createRectypeStructureTree: function( db_structure, $mode, rectypeids, fieldtypes, parentcode ) {
        
        var DT_PARENT_ENTITY  = window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY'];
        
        var rst_links = $Db.rst_links();
        
        var _separator = ($mode==3)?'..':':';
        
    //-------------------- internal functions    
        
    function __getRecordTypeTree($recTypeId, $recursion_depth, $mode, $fieldtypes, $pointer_fields){
            
            var $res = {};
            var $children = [];
            var headerFields = [];
            
            //add default fields - RECORD TYPE HEADER
            if($mode==3){
                
                    $children.push({key:'rec_ID',title:'Record ID', code:'Record ID'});
                    if($recursion_depth>0){
                        $children.push({key:'rec_Title', type:'freetext',
                            title:'Record Title', 
                            code:'Record Title'});
                    }
                    $children.push({key:'rec_TypeID', title:'Record TypeID', code:'Record TypeID'});
                    $children.push({key:'rec_TypeName', title:'Record TypeName', code:'Record TypeName'});
                    $children.push({key:'rec_Modified', title:"Record Modified", code:'Record Modified'});
            }else
            if($recursion_depth==0 && $fieldtypes.length>0){    
                 //include record header fields
                var all_header_fields = $fieldtypes.indexOf('header_ext')>=0;
                if($fieldtypes.indexOf('header')>=0){
                    $fieldtypes.push('title');
                    $fieldtypes.push('modified');
                }  
                
                if(all_header_fields || $fieldtypes.indexOf('ID')>=0 || $fieldtypes.indexOf('rec_ID')>=0){
                    $children.push({key:'rec_ID', type:'integer',
                        title:"ID  <span style='font-size:0.7em'>(Integer)</span>", 
                        code:($recTypeId+_separator+'ids'), name:'Record ID'});
                }

                if(all_header_fields || $fieldtypes.indexOf('title')>=0 || $fieldtypes.indexOf('rec_Title')>=0){
                    $children.push({key:'rec_Title', type:'freetext',
                        title:"Title <span style='font-size:0.7em'>(Constructed Text)</span>", 
                        code:($recTypeId+_separator+'title'), name:'Record title'});
                }
                if(all_header_fields || $fieldtypes.indexOf('added')>=0 || $fieldtypes.indexOf('rec_Added')>=0){
                    $children.push({key:'rec_Modified', type:'date',
                        title:"Added  <span style='font-size:0.7em'>(Date)</span>", 
                        code:($recTypeId+_separator+'added'), name:'Date added'});
                }
                if(all_header_fields || $fieldtypes.indexOf('modified')>=0 || $fieldtypes.indexOf('rec_Modified')>=0){
                    $children.push({key:'rec_Modified', type:'date',
                        title:"Modified  <span style='font-size:0.7em'>(Date)</span>", 
                        code:($recTypeId+_separator+'modified'), name:'Date modified'});
                }
                if(all_header_fields || $fieldtypes.indexOf('addedby')>=0 || $fieldtypes.indexOf('rec_AddedBy')>=0){
                    $children.push({key:'rec_AddedBy', type:'freetext',
                        title:"Creator  <span style='font-size:0.7em'>(User)</span>", 
                        code:($recTypeId+_separator+'addedby'), name:'Creator (user)'});
                }
                if(all_header_fields || $fieldtypes.indexOf('url')>=0 || $fieldtypes.indexOf('rec_URL')>=0){
                    $children.push({key:'rec_URL', type:'freetext',
                        title:"URL  <span style='font-size:0.7em'>(Text)</span>", 
                        code:($recTypeId+_separator+'url'), name:'Record URL'});
                }
                if(all_header_fields || $fieldtypes.indexOf('notes')>=0 || $fieldtypes.indexOf('rec_ScratchPad')>=0){
                    $children.push({key:'rec_ScratchPad', type:'freetext',
                        title:"Notes  <span style='font-size:0.7em'>(Text)</span>", 
                        code:($recTypeId+_separator+'notes'), name:'Record Notes'});
                }
                if(all_header_fields || $fieldtypes.indexOf('owner')>=0 || $fieldtypes.indexOf('rec_OwnerUGrpID')>=0){
                    $children.push({key:'rec_OwnerUGrpID', type:'freetext',
                        title:"Owner  <span style='font-size:0.7em'>(User or Group)</span>", 
                        code:($recTypeId+_separator+'owner'), name:'Record Owner'});
                }
                if(all_header_fields || $fieldtypes.indexOf('visibility')>=0 || $fieldtypes.indexOf('rec_NonOwnerVisibility')>=0){
                    $children.push({key:'rec_NonOwnerVisibility', type:'enum',
                        title:"Visibility  <span style='font-size:0.7em'>(Terms)</span>", 
                        code:($recTypeId+_separator+'access'), name:'Record Visibility'});
                }

                if(all_header_fields || $fieldtypes.indexOf('tags')>=0 || $fieldtypes.indexOf('rec_Tags')>=0){
                    $children.push({key:'rec_Tags', type:'terms',
                        title:"Tags  <span style='font-size:0.7em'>(Terms)</span>", 
                        code:($recTypeId+_separator+'tag'), name:'Record Tags'});
                }
                
                if(all_header_fields){
                    var s = '<span style="font-style:italic">Generic Fields</span>';
                    $children = [
                        {title:s, folder:true, is_generic_fields:true, children:$children}];
                    if($mode==5){ //for filter builder 
                        $children.push({key:'anyfield', type:'freetext', //-16px -80px      -48px -80px open
                        title:"<span style='font-size:0.9em;font-style:italic;padding-left:22px'>ANY FIELD</span>", 
                        code:($recTypeId+_separator+'anyfield'), name:'Any field'});    
                    }
                }
            }

            if($recTypeId>0 && $Db.rty($recTypeId,'rty_Name')){//---------------

                $res['key'] = $recTypeId;
                $res['title'] = $Db.rty($recTypeId,'rty_Name');
                $res['type'] = 'rectype';
                
                $res['conceptCode'] = $Db.getConceptID('rty', $recTypeId);
                $res['rtyID_local'] = $recTypeId; //$Db.getLocalID('rty', $rt_conceptcode); //for import structure
                                
                                                                                                                  
                if(($mode<5 || $recursion_depth==0)){


                    var $details = $Db.rst($recTypeId);
                    
                    //
                    if($fieldtypes.indexOf('parent_link')>=0 && !$Db.rst($recTypeId,DT_PARENT_ENTITY)){
                        
                        //find all parent record types that refers to this record type
                        var $parent_Rts = rst_links.parents[$recTypeId];
                        
                        if($parent_Rts && $parent_Rts.length>0){
                        
                            //create fake rectype structure field
                            $ffr = {};
                            $ffr['rst_DisplayName'] = 'Parent entity';
                            $ffr['rst_PtrFilteredIDs'] = $parent_Rts.join(',');
                            //$ffr['dty_Type'] = 'resource';
                            $ffr['rst_DisplayHelpText'] = 'Reverse pointer to parent record';
                            $ffr['rst_RequirementType'] = 'optional';
                                  
                            $details.addRecord(DT_PARENT_ENTITY, $ffr)
                        }
                    }
                    
                    var $children_links = [];
                    var $new_pointer_fields = [];

                    // add details --------------------------------
                    $details.each2(function($dtID, $dtValue){
                        
                        //@TODO forbidden for import????
                        if($dtValue['rst_RequirementType']!='forbidden'){

                            var $dt_type = $Db.dty($dtID,'dty_Type');
                            
                            if($dt_type=='resource' || $dt_type=='relmarker'){ //title mask w/o relations
                                    $new_pointer_fields.push( $dtID );
                            }
                            
                            $res_dt = __getDetailSection($recTypeId, $dtID, $recursion_depth, $mode, 
                                                                    $fieldtypes, null, $new_pointer_fields);
                            if($res_dt){
                                
                                if($res_dt['type']=='resource' || $res_dt['type']=='relmarker'){
                                    
                                    
                                    if($mode==3 && $res_dt['constraint'] && $res_dt['constraint']>1){ 
                                        //for rectitle mask do not create additional level for  multiconstrained link
                                        
                                        for (var i=0; i<$res_dt['constraint']; i++){
                                            $res_dt['children'][i]['code'] = $res_dt['code']
                                                                + _separator + '{'+$res_dt['children'][i]['title'] +'}';
                                            $res_dt['children'][i]['title'] = $res_dt['title'] + ' ('+ $res_dt['children'][i]['title']+')';
                                            $children_links.push($res_dt['children'][i]);    
                                        }
                                    }else{
                                        $children_links.push($res_dt);    
                                    }                                           
                                    
                                }else{
                                    $children.push($res_dt);
                                }
                                /*
                                if(is_array($res_dt) && count($res_dt)==1){
                                $res["f".$dtID] = $res_dt[0];    
                                }else{
                                //multi-constrained pointers or simple variable
                                $res["f".$dtID] = $res_dt;
                                }
                                */
                            }
                        }
                    });//for details
                    
                    //sort bt rst_DisplayOrder
                    $children.sort(function(a,b){
                        return (a['display_order']<b['display_order'])?-1:1;
                    });
                    
                    //add resource and relation at the end of result array
                    $children = $children.concat($children_links);
                    
                    //--------------------------------------------
                    //find all reverse links and relations
                    if( ($mode==4 && $recursion_depth<2) || ($mode==5 && $recursion_depth==0) )
                    {
                        var rev_fields = {};
                        var reverse_fields = rst_links.reverse[$recTypeId]; //all:, dty_ID:[rty_ID,...]
                        var twice_only = 0;
                        while(twice_only<2){

                            for (var $dtID in reverse_fields) {
                                if($dtID>0 && 
                                    ( $pointer_fields==null ||    // to avoid recursion
                                        ($.isArray($pointer_fields) &&   
                                        window.hWin.HEURIST4.util.findArrayIndex($dtID, $pointer_fields)<0) ) )
                                {
                                    rev_fields[$dtID] = reverse_fields[$dtID];
                                }
                            }
                            reverse_fields = rst_links.rel_reverse[$recTypeId]; //all:, dty_ID:[rty_ID,...]
                            twice_only++;
                        }
                        
                        for (var $dtID in rev_fields) {
                                var $rtyIDs = rev_fields[$dtID];
                                for(var i=0; i<$rtyIDs.length; i++)  {
                                    $res_dt = __getDetailSection($rtyIDs[i], $dtID, $recursion_depth, $mode, $fieldtypes, $recTypeId, null);
                     
                                    if($res_dt){
                                        $children.push( $res_dt );
                                    }
                                }
                        }//for
                    }
                    
                    $details.removeRecord(DT_PARENT_ENTITY); //remove fake parent link
                }
                
                if($mode==3 && $recursion_depth==0){
                    //$children.push(__getRecordTypeTree('Relationship', $recursion_depth+1, $mode, $fieldtypes, null));
                }   

            }
            else if($recTypeId=="Relationship") { //----------------------------

                $res['title'] = "Relationship";
                $res['type'] = "relationship";

                //add specific Relationship fields
                $children.push({key:'recRelationType', title:'RelationType'});
                $children.push({key:'recRelationNotes', title:'RelationNotes'});
                $children.push({key:'recRelationStartDate', title:'RelationStartDate'});
                $children.push({key:'recRelationEndDate', title:'RelationEndDate'});
            }else if($mode==5 || $mode==6) //-----------------------------------
            {
                //record type is array - add common fields only
                
                $res['title'] = 'Any record type';
                $res['type'] = 'rectype';
                
                if(false && $mode==5 && $recursion_depth==0 && $recTypeId && $recTypeId.indexOf(',')>0){ //for faceted search
                    $res['key'] = $recTypeId;
                    $res['type'] = 'rectype';
                    
                    var recTypes = $recTypeId.split(',');
                    
                    $res['title'] = $Db.rty( recTypes[0],'rty_Name');
                    
                    var  $details = $Db.rst(recTypes[0]); 

                    //if there are several rectypes - find common fields only
                    //IJ wants show all fields of fist record type only
                    /*  2020-04-25
                    var names = [];
                    $.each(recTypes, function(i, rtid){ 
                        names.push(rectypes.names[rtid]) 
                        if(i>0){
                            var fields = rectypes['typedefs'][rtid]['dtFields'];
                            var dtIds = Object.keys($details);
                            for (var k=0; k<dtIds.length; k++){
                                if(!fields[dtIds[k]]){
                                    //it does not exist 
                                    $details[dtIds[k]] = null;
                                    delete $details[dtIds[k]];
                                }
                            }
                        }
                    });
                    $res['title'] = names.join(', ');
                    */
                    
                    var $children_links = [];
                    var $new_pointer_fields = [];

                    $details.each2(function($dtID, $dtValue){
                        
                        if($dtValue['rst_RequirementType']!='forbidden'){

                            var $dt_type = $Db.dty($dtID,'dty_Type');
                            if($dt_type=='resource' || $dt_type=='relmarker'){
                                    $new_pointer_fields.push( $dtID );
                            }
                            
                            $res_dt = __getDetailSection(recTypes[0], $dtID, $recursion_depth, $mode, 
                                                                    $fieldtypes, null, $new_pointer_fields);
                            if($res_dt){
                                
                                var codes = $res_dt['code'].split(_separator);
                                codes[0] = $recTypeId;
                                $res_dt['code'] = codes.join(_separator);
                                
                                if($res_dt['type']=='resource' || $res_dt['type']=='relmarker'){
                                    $children_links.push($res_dt);
                                }else{
                                    $children.push($res_dt);
                                }
                            }
                        }
                    });//for details
                    
                    //sort bt rst_DisplayOrder
                    $children.sort(function(a,b){
                        return (a['display_order']<b['display_order'])?-1:1;
                    });
                    
                    //add resource and relation at the end of result array
                    $children = $children.concat($children_links);                    
                    
                }
                
            }

            
            if($mode<5 || $recursion_depth==0){
                $res['children'] = $children;
            }

            return $res;
            
    } //__getRecordTypeTree

    /*
    $dtID   - detail type ID
    $dtValue - record type structure definition
    returns display name  or if enum array
    $mode - 3 all, 4, 5 for treeview (5 lazy) , 6 - for import csv(dependencies)
    */
    function __getDetailSection($recTypeId, $dtID, $recursion_depth, $mode, $fieldtypes, $reverseRecTypeId, $pointer_fields){

        $res = null;

        var $dtValue = $Db.rst($recTypeId, $dtID);

        var $detailType = $Db.dty($dtID,'dty_Type');
        
        if($mode==3 && $detailType=='relmarker'){
            return null;   
        }
        
        var $dt_label   = $dtValue['rst_DisplayName'];
        var $dt_title   = $dtValue['rst_DisplayName'];
        var $dt_tooltip = $dtValue['rst_DisplayHelpText']; //help text
        var $dt_conceptcode   = $Db.getConceptID('dty', $dtID);
        var $dt_display_order = $dtValue['rst_DisplayOrder'];
        
        var $pointerRecTypeId = ($dtID==DT_PARENT_ENTITY)?$dtValue['rst_PtrFilteredIDs']:$Db.dty($dtID,'dty_PtrTargetRectypeIDs');
        if(window.hWin.HEURIST4.util.isnull($pointerRecTypeId)) $pointerRecTypeId = '';
        
        var $pref = "";
        
        if (($mode==3) || $fieldtypes.indexOf('all')>=0 
            || window.hWin.HEURIST4.util.findArrayIndex($detailType, $fieldtypes)>=0) //$fieldtypes - allowed types
        {

        var $res = null;
            
        switch ($detailType) {
            case 'separator':
                return null;
            case 'enum':
            case 'relationtype':

                $res = {};
                if($mode==3){

                    $res['children'] = [
                        {key:'term',title: 'Term',code: 'Term'},       
                        {key:'code',title: 'Code',code: 'Code'},       
                        {key:'conceptid',title: 'Concept ID',code: 'Concept ID'},       
                        {key:'internalid',title: 'Internal ID',code: 'Internal ID'}
                                ];
                }
                break;

            case 'resource': // link to another record type
            case 'relmarker':
            
                var $max_depth = 2;
                if ($mode==4) //$mode==6 || 
                   $max_depth = 3;
                else if ($mode==5 || $mode==6) //make it 1 for lazy load
                   $max_depth = 1; 
                                                                
                if($recursion_depth<$max_depth){
                    
                    if($reverseRecTypeId!=null){
                            var $res = __getRecordTypeTree($recTypeId, $recursion_depth+1, $mode, $fieldtypes, $pointer_fields);
                            if($res){
                                $res['rt_ids'] = $recTypeId; //list of rectype - constraint
                                //$res['reverse'] = "yes";
                                $pref = ($detailType=="resource")?"lf":"rf";

                                $dt_title = "<span>&lt;&lt; <span style='font-weight:bold'>" 
                                        + $Db.rty($recTypeId, 'rty_Name') + "</span> . " + $dt_title + '</span>';
                                
                                if($mode==5 || $mode==6){
                                    $res['lazy'] = true;
                                }
                                $res['isreverse'] = 1;
                            }
                    }else{

                            var $pref = ($detailType=="resource")?"lt":"rt";

                            var $is_required = ($dtValue['rst_RequirementType']=='required');
                            var $rectype_ids = $pointerRecTypeId.split(",");
                             
                            if($mode==4 || $mode==5 || $mode==6){
                                
                                var $type_name = $Db.baseFieldType[$detailType];
                                
                                $dt_title = " <span style='font-style:italic'>" + $dt_title + "</span> <span style='font-size:0.7em'>(" + $type_name + ")</span>";
                            }

                            $res = {};                            
                            
                            if($pointerRecTypeId=="" || $rectype_ids.length==0){ //unconstrainded
                                                    //
                                //$res['constraint'] = 0;
                                if($mode==5){
                                    $res['rt_ids'] = '';
                                    $res['lazy'] = true;
                                }else{
                                    $res = __getRecordTypeTree( null, $recursion_depth+1, $mode, $fieldtypes, $pointer_fields);
                                }

                            }else{ //constrained pointer

                                if($rectype_ids.length>1){
                                    $res['rt_ids'] = $pointerRecTypeId; //list of rectype - constraint
                                    $res['constraint'] = $rectype_ids.length;
                                    if($mode<5) $res['children'] = [];
                                }
                                if($mode==5 || $mode==6){
                                    $res['rt_ids'] = $pointerRecTypeId;
                                    $res['lazy'] = true;
                                    
                                }else{
                                
                                    for (var k in $rectype_ids){
                                        var $rtID = $rectype_ids[k];
                                        $rt_res = __getRecordTypeTree($rtID, $recursion_depth+1, $mode, $fieldtypes, $pointer_fields);
                                        if($rectype_ids.length==1){//exact one rectype constraint
                                            //avoid redundant level in tree
                                            $res = $rt_res;
                                            $res['constraint'] = 1;
                                            $res['rt_ids'] = $pointerRecTypeId; //list of rectype - constraint
                                        }else if($rt_res!=null){
                                            
                                            $res['children'].push($rt_res);
                                            $res['constraint'] = $rectype_ids.length;
                                            
                                        }else{
                                            $res['constraint'] = null;
                                            $res['children'].push({
                                                title:'Unconstrained pointer; constrain to record type to see field', 
                                                code:null});
                                        }
                                    }
                                
                                }
                            
                            }
                            
                            $res['required'] = $is_required;
                    }
                }

                break;

            default:
                    $res = {};
        }//end switch
        }

        if($res!=null){

            if(window.hWin.HEURIST4.util.isnull($res['code'])){
              
              if($mode==3){
                  $res['code'] = $dt_label;
              }else{
                  $res['code'] = (($reverseRecTypeId!=null)?$reverseRecTypeId:$recTypeId)+_separator+$pref+$dtID;  //(($reverseRecTypeId!=null)?$reverseRecTypeId:$recTypeId)  
              }  
                
            } 
            $res['key'] = "f:"+$dtID;
            if($mode==4 || $mode==5 || $mode==6){
                    
                var $stype = ($detailType=='resource' || $detailType=='relmarker')?'':$Db.baseFieldType[$detailType];
                if($reverseRecTypeId!=null){
                    //before 2017-06-20  $stype = $stype."linked from";
                    $res['isreverse'] = 1;
                }
                if($stype!=''){
                    $stype = " <span style='font-size:0.7em'>(" + $stype + ")</span>";   
                }
                
                $res['title'] = $dt_title + $stype;
                //$res['code'] = 
            }else{
                $res['title'] = $dt_title;    
            }
            $res['type'] = $detailType;
            $res['name'] = $dt_label;
            
            $res['display_order'] = $dt_display_order;
            
            $res['conceptCode'] = $dt_conceptcode;
            $res['dtyID_local'] = $dtID; //$Db.getLocalID('dty', $dt_conceptcode); for import
        }            
        return $res;
        
        
    }
    
    //
    // add parent code to children
    //
    function __assignCodes($def){
        
        for(var $idx in $def['children']){
            $det = $def['children'][$idx];
            if(!window.hWin.HEURIST4.util.isnull($def['code'])){

                if(!window.hWin.HEURIST4.util.isnull($det['code'])){
                    $def['children'][$idx]['code'] = $def['code'] + _separator + $det['code']; 
                }else{
                    $def['children'][$idx]['code'] = $def['code'];    
                }
            }
            //debug $def['children'][$idx]['title'] = $def['children'][$idx]['code'].$det['title']; 
                 
            if($.isArray($det['children'])){
                   $def['children'][$idx] = __assignCodes($def['children'][$idx]);
            }
        }
        return $def;
    }
    //========================= end internal 
        
        if(fieldtypes==null){
            fieldtypes = ['integer','date','freetext','year','float','enum','resource','relmarker','relationtype'];
        }else if(!$.isArray(fieldtypes) && fieldtypes!='all'){
            fieldtypes = fieldtypes.split(',');
        }
        
        var res = [];

/*        
        if($mode==5){ //with reverse links
            var def = __getRecordTypeTree(rectypeids, 0, $mode, fieldtypes, null);

            if(def!==null) {
                if(parentcode!=null){
                    if(def['code']){
                        def['code'] = parentcode+_separator+def['code'];
                    }else{
                        def['code'] = parentcode;
                    }
                }
                if($.isArray(def['children'])){
                    def = __assignCodes(def);
                    res.push( def );
                }                    
            }
        
        } else {
*/        
            rectypeids = (!$.isArray(rectypeids)?rectypeids.split(','):rectypeids);    
            
            
            //create hierarchy tree 
            for (var k=0; k<rectypeids.length; k++) {
                var rectypeID = rectypeids[k];
                var def = __getRecordTypeTree(rectypeID, 0, $mode, fieldtypes, null);
                
                    if(def!==null) {
                        if(parentcode!=null){
                            if(def['code']){
                                def['code'] = parentcode+_separator+def['code'];
                            }else{
                                def['code'] = parentcode;
                            }
                        }
                        //debug $def['title'] = @$def['code'].$def['title'];   
                        //asign codes
                        if($.isArray(def['children'])){
                            def = __assignCodes(def);
                            res.push( def );
                        }                    
                    }
            }
            
            if(rectypeids.length==1 && $mode==3){
                res = res[0]['children'];            
            }
            
//        }

        return res;    
        
    },    
    
    // use in search_faceted.js  
    //
    // returns array of record types that are resources for given record type
    //    {'linkedto':[],'relatedto':[]}
    // need_separate - returns separate array for linked and related 
    //
    getLinkedRecordTypes: function ($rt_ID, db_structure, need_separate){
        
        if(!db_structure){
            db_structure = window.hWin.HEURIST4;
        }
        
        var $dbs_rtStructs = db_structure.rectypes;
        //find all DIREreverse links (pointers and relation that point to selected rt_ID)
        var $alldetails = $dbs_rtStructs['typedefs'];
        var $fi_type = $alldetails['dtFieldNamesToIndex']['dty_Type'];
        var $fi_rectypes = $alldetails['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];
        
        var $arr_rectypes = [];
        var res = {'linkedto':[],'relatedto':[]};
        
        var $details = $dbs_rtStructs['typedefs'][$rt_ID]['dtFields'];
        if($details) {
            for (var $dtID in $details) {
                
                var $dtValue = $details[$dtID];
        
                if(($dtValue[$fi_type]=='resource' || $dtValue[$fi_type]=='relmarker')){

                        //find constraints
                        var $constraints = $dtValue[$fi_rectypes];
                        if(!window.hWin.HEURIST4.util.isempty($constraints)){
                            $constraints = $constraints.split(",");
                            //verify record type exists
                            if($constraints.length>0){
                                for (var i=0; i<$constraints.length; i++) {
                                    var $recTypeId = $constraints[i];
                                    if( !$arr_rectypes[$recTypeId] && 
                                        $dbs_rtStructs['typedefs'][$recTypeId]){
                                            
                                            $arr_rectypes.push( $recTypeId );
                                            
                                            if(need_separate){
                                                var t1 = ($dtValue[$fi_type]=='resource')?'linkedto':'relatedto';
                                                res[t1].push( $recTypeId );
                                            }
                                    }
                                }                            
                            } 
                        }
                }
            }
        }
        
        return  need_separate ?res :$arr_rectypes;
        
    },

    //
    // returns true if rectype has a field in its structure
    // fieldtype - base field type
    //
    hasFields: function( rty_ID, fieldtype, db_structure ){
        
        var is_exist = false;
        
        $Db.rst(rty_ID).each(function(dty_ID, record){
            if($Db.dty(dty_ID,'dty_Type')==fieldtype){
                is_exist = true;
                return false;
            }
        });
        
        return is_exist;
    },

    //--------------------------------------------------------------------------
    
    /*
    shortcuts for working wit db definitions
    
    $Db = window.hWin.HEURIST4.dbs
    
    rty,dty,rst,rtg,dtg,trm = dbdef(entityName,....)  access hEntityMgr.entity_data[entityName]
    
    set(entityName, id, field, newvalue)    
        id - localcode or concept code. For rst this are 2 params rtyID, dtyID
        field - field name. If empty returns entire record
        newvalue - assign value of field
    
    */
    
    rtg: function(rec_ID, fieldName, newValue){
        return $Db.getset('defRecTypeGroups', rec_ID, fieldName, newValue);        
    },

    dtg: function(rec_ID, fieldName, newValue){
        return $Db.getset('defDetailTypeGroups', rec_ID, fieldName, newValue);        
    },

    vcg: function(rec_ID, fieldName, newValue){
        return $Db.getset('defVocabularyGroups', rec_ID, fieldName, newValue);        
    },
    
    rty: function(rec_ID, fieldName, newValue){
        return $Db.getset('defRecTypes', rec_ID, fieldName, newValue);        
    },

    dty: function(rec_ID, fieldName, newValue){
        return $Db.getset('defDetailTypes', rec_ID, fieldName, newValue);        
    },

    trm: function(rec_ID, fieldName, newValue){
        return $Db.getset('defTerms', rec_ID, fieldName, newValue);        
    },
    
    //
    //  get structures for all record types
    //
    rst_idx2: function(){
        return window.hWin.HAPI4.EntityMgr.getEntityData2('rst_Index');
    },
    
    // BASED on rectype structure
    //
    // Returns
    // direct:   rty_ID:[{all:[],dty_ID:[rty_ID,rty_ID,....],  }]
    // reverse:
    // parents:  {child_rty_ID:[parents rtyIDs,...],....}
    // rel_direct:
    // rel_reverse:
    //
    // forbidden fields are ignored
    //
    rst_links: function(){

        var rst_reverse_parent = {};  //linked FROM rectypes as a child (list of parent rectypes)
        var rst_reverse = {};    //linked FROM rectypes
        var rst_direct = {};     //linked TO rectypes

        var rst_rel_reverse = {};    //linked FROM rectypes
        var rst_rel_direct = {};     //linked TO rectypes

      
        var is_parent = false;
        var all_structs = $Db.rst_idx2();
        for (var rty_ID in all_structs){
            var recset = all_structs[rty_ID];
            recset.each2(function(dty_ID, record){

                //links
                var dty_Type = $Db.dty(dty_ID, 'dty_Type');
                if((dty_Type=='resource' || dty_Type=='relmarker') 
                    && record['rst_RequirementType']!='forbidden')
                {
                    is_parent = false;
                    
                    var ptr = $Db.dty(dty_ID, 'dty_PtrTargetRectypeIDs');
                    if(ptr) ptr = ptr.split(',');
                    if(ptr && ptr.length>0){
                        
                            var direct;
                            var reverse;
                    
                            if(dty_Type=='resource'){
                                //LINK
                                is_parent = (record['rst_CreateChildIfRecPtr']==1);
                                
                                direct = rst_direct;
                                reverse = rst_reverse;
                            }else{
                                //RELATION
                                direct = rst_rel_direct;
                                reverse = rst_rel_reverse;
                            }      
                            
                            
                            if(!direct[rty_ID]) direct[rty_ID] = {all:[]};  
                            direct[rty_ID][dty_ID] = ptr;

                            for(var i=0; i<ptr.length; i++){
                                
                                var target_rty = ptr[i];
                                
                                //all rectypes that is referenced FROM rty_ID
                                if(direct[rty_ID].all.indexOf(target_rty)<0){
                                    direct[rty_ID].all.push(target_rty);   
                                }    
                                
                                // reverse links
                                if(!reverse[target_rty]) reverse[target_rty] = {all:[]};  

                                //all rectypes that refer TO rty_ID
                                if(reverse[target_rty].all.indexOf(rty_ID)<0){
                                    reverse[target_rty].all.push(rty_ID);        
                                }    
                                if(is_parent){
                                    if(!rst_reverse_parent[target_rty]) rst_reverse_parent[target_rty] = [];
                                    if(rst_reverse_parent[target_rty].indexOf(rty_ID)<0){
                                            rst_reverse_parent[target_rty].push(rty_ID);
                                    }
                                }
                                
                                if(!reverse[target_rty][dty_ID]) reverse[target_rty][dty_ID] = [];
                                reverse[target_rty][dty_ID].push(rty_ID)

                            }//for constraints
                    }
                }                

            });
        }
        
        return {
            parents: rst_reverse_parent,
            reverse: rst_reverse,
            direct: rst_direct,

            rel_reverse: rst_rel_reverse,
            rel_direct: rst_rel_direct
        };
        
    },

    //
    // returns links by basefield - disregard usage of field
    //
    rst_links_base: function(){
        
        var links = {};
        
        $Db.dty().each2(function(dty_ID, record){
            
                var dty_Type = record['dty_Type'];
                if(dty_Type=='resource' || dty_Type=='relmarker') 
                {
                    is_parent = false;
                    
                    var ptr = record['dty_PtrTargetRectypeIDs'];
                    if(ptr) ptr = ptr.split(',');
                    if(ptr && ptr.length>0){
                        for(var i=0; i<ptr.length; i++){
                            if(!links[ptr[i]]) links[ptr[i]] = [];
                            
                            links[ptr[i]].push(dty_ID);       
                        }
                    }
                }
        });

        return links;
    },    
    
    //
    // returns usage (list of rty_ID) for given field
    //     
    rst_usage: function(dty_ID){
       
        var usage = [];
        var all_structs = $Db.rst_idx2();
        for (var rty_ID in all_structs){
            if(all_structs[rty_ID].getById(dty_ID)){
                usage.push(rty_ID);
            }
        }
        return usage;
    },
    
    
    rst: function(rec_ID, dty_ID, fieldName, newValue){
        
        //fieldnames for backward capability
        var dfname = null;
        if(fieldName) dfname = $Db.rst_to_dtyField( fieldName );
        if(dfname){
            return $Db.dty(dty_ID, dfname);
        }else{
            //direct access (without check and reload)
            var rectype_structure = window.hWin.HAPI4.EntityMgr.getEntityData2('rst_Index');
            
            if(rectype_structure && rectype_structure[rec_ID]){
                if(dty_ID>0){
                    return $Db.getset(rectype_structure[rec_ID], dty_ID, fieldName, newValue);                
                }else{
                    return rectype_structure[rec_ID];            
                }
            }
        }
        return null
        
    },
    
    
    getset: function(entityName, rec_ID, fieldName, newValue){
        if(typeof newValue == 'undefined'){
            return $Db.get(entityName, rec_ID, fieldName);        
        }else{
            $Db.set(entityName, rec_ID, fieldName, newValue);        
            return null;
        }
    },
    
    //
    // returns 
    // recordset if rec_ID not defined
    // record - as object if fieldName not defined
    //    
    get: function (entityName, rec_ID, fieldName){
        //it is assumed that db definitions ara always exists on client side
        var recset =  window.hWin.HEURIST4.util.isRecordSet(entityName)?entityName
                        :window.hWin.HAPI4.EntityMgr.getEntityData(entityName); 
        
        if(recset && rec_ID>0){
            
            if(fieldName){
                return recset.fld(rec_ID, fieldName);
            }else{
                return recset.getRecord(rec_ID); //returns JSON {fieldname:value,....}
            }
            
        }else{
            return recset;
        }
        
    },

    //
    // assign value of field OR entire record
    //
    set: function (entityName, rec_ID, fieldName, newValue){

        if(rec_ID>0){
        
            var recset =  window.hWin.HEURIST4.util.isRecordSet(entityName)
                            ?entityName
                            :window.hWin.HAPI4.EntityMgr.getEntityData(entityName); 
            
            if(fieldName){
                recset.setFldById(rec_ID, fieldName, newValue);
            }else{
                recset.addRecord(rec_ID, newValue);
            }
            
        }
    },
    
    //
    // Some fields in rectype structure are taken from basefield (dty) directly
    //
    rst_to_dtyField: function(fieldName)
    {
        var dfname = null;
        if(fieldName=='rst_FilteredJsonTermIDTree') dfname='dty_JsonTermIDTree'
        else if(fieldName=='rst_PtrFilteredIDs') dfname='dty_PtrTargetRectypeIDs'
        //else if(fieldName=='rst_TermIDTreeNonSelectableIDs') dfname='dty_TermIDTreeNonSelectableIDs' //not used anymore
        else if( //fieldName=='dty_TermIDTreeNonSelectableIDs' || fieldName=='dty_FieldSetRectypeID' || 
                fieldName=='dty_Type')
        {
            dfname = fieldName; 
        } 
        
        return null;
    },
    
/*    
    //
    //
    //
    rst_set: function(rty_ID, dty_ID, fieldName, value){
        
        var dfname = $Db.rst_to_dtyField( fieldName );
        
        if(dfname){
            $Db.dty( dty_ID, dfname, value );
        }else{
        
            var recset = window.hWin.HAPI4.EntityMgr.getEntityData('defRecStructure');
            var details = window.hWin.HAPI4.EntityMgr.getEntityData2('rst_Index'); 
            if(details[rty_ID]){
                var rst_ID = details[rty_ID][dty_ID];    
                if(rst_ID>0){
                    recset.setFldById(rst_ID, fieldName, newValue);
                }else{
                    //add new basefield
                    rst_ID = recset.addRecord3({fieldName: newValue});
                    details[rty_ID][dty_ID] = rst_ID;
                }
            }
        }
    },
    //  
    // special behavior for defRecStructure
    // it returns value for given field or entire recstrucure field
    //    
    rst_idx: function(rty_ID, dty_ID, fieldName){
        
        var recset = window.hWin.HAPI4.EntityMgr.getEntityData('defRecStructure'); 
        
        if(rty_ID>0){
            
            //rty_ID:{dty_ID:rstID, ..... }
            var details = window.hWin.HAPI4.EntityMgr.getEntityData2('rst_Index');
            
            if(!details || !details[rty_ID]){
                return null;
            }else if(dty_ID>0){
                var rst_ID = details[rty_ID][dty_ID];
                
                if(!(rst_ID>0)){
                    return null;
                }else if(fieldName){
                    
                    //for backward capability
                    var dfname = $Db.rst_to_dtyField( fieldName );
                    if(dfname){
                        return $Db.dty(dty_ID, dfname);
                    }else{
                        return recset.fld(rst_ID, fieldName);        
                    }
                    
                }else{
                    return recset.getRecord(rst_ID); //json for paticular detail
                }
            }else{
                return details[rty_ID]; //array of dty_ID:rst_ID
            }
            
        }else{
            return recset;
        }
        //create group
        
        //return $Db.getset('defRecStructure', rec_ID, fieldName, newValue);        
    },
*/    
    //
    // find by concept code in local definitions
    //
    // entities - prefix for rectypes, detailtypes, terms - rty, dty, trm
    //
    // return local id or zero if not found
    //
    getLocalID: function(entity, concept_code){

        var findID = 0;
        var codes = null;
        
        if(typeof concept_code == 'string' && concept_code.indexOf('-')>0)
        {
            codes = concept_code.split('-');
            if(codes.length==2 && 
                (parseInt(codes[0])==0 || codes[0]==window.hWin.HAPI4.sysinfo['db_registeredid']) )
            {
                findID = codes[1];
            }
        }else if(parseInt(concept_code)>0){
            findID = concept_code;    
        }
        
        if(findID>0 && $Db[entity](findID)){
            return findID; 
        }
        
        if(codes && codes.length==2){
        
            var f_dbid = entity+'_OriginatingDBID';
            var f_id = entity+'_IDInOriginatingDB';
            
            var recset = $Db[entity]();
            recset.each2( function(id, record){
                if(record[f_dbid]==codes[0] && record[f_id]==codes[1]){
                    findID = id;
                    return false;
                }
            });
            
        }
        
        return findID;
    },
    
    //
    // get concept code by local id
    //
    getConceptID: function(entity, local_id, is_ui){
        
        var rec = $Db[entity](local_id);
        if(rec!=null){
            var dbid = rec[entity+'_OriginatingDBID'];
            var id = rec[entity+'_IDInOriginatingDB'];
            if(parseInt(dbid)>0 && parseInt(id)>0){
                return dbid+'-'+id;
            }else if( window.hWin.HAPI4.sysinfo['db_registeredid']>0 ){
                return window.hWin.HAPI4.sysinfo['db_registeredid']+'-'+local_id;
            }else{
                if(is_ui===true){
                  return '<span '
                    +'title="Concept IDs are attributed when a database is registered with the '
                    +'Heurist Master Index using Design > Setup > Register. In the meantime only local codes are defined.">'
                    +'0000-'+local_id+'</span>';
                }else{
                    return '0000-'+local_id;    
                }
                
                
            }
        }else{
            return '';
        }
    
    },

    //
    //  Returns term ID in vocabulary by code
    //
    getTermByCode: function(vocab_id, code){

        var _terms = $Db.trm_TreeData(vocab_id, 'set');
        
        for(var i=0; i<_terms.length; i++){
            if($Db.trm(_terms[i],'trm_Code')==code){
                return _terms[i];
            }
        }
        return null;
    },

    //
    //  Returns term ID in vocabulary by label
    //
    getTermByLabel: function(vocab_id, label){

        var _terms = $Db.trm_TreeData(vocab_id, 'set');
        
        label = label.toLowerCase();
        
        for(var i=0; i<_terms.length; i++){
            if($Db.trm(_terms[i],'trm_Label').toLowerCase()==label){
                return _terms[i];
            }
        }
        return null;
    },

    
    //
    // returns true if term belongs to vocabulary (including by reference)
    //
    trm_InVocab: function(vocab_id, term_id){
        
        var all_terms = $Db.trm_TreeData(vocab_id, 'set');
        
        return (window.hWin.HEURIST4.util.findArrayIndex(term_id, all_terms)>=0);
    },
    
    //
    // Returns hierarchy for given vocabulary as a flat array, recordset or tree data
    // (it uses trm_Links)
    // vocab_id - id or "relation"
    // mode - 0, flat - returns recordset with defined trm_Parents 
    //        1, tree - returns treedata for fancytree
    //        2, select - return array of options for selector {key: title: depth: is_vocab}
    //        3, set  - array of ids 
    //        4, labels - flat array of labels in lower case 
    //
    trm_TreeData: function(vocab_id, mode, without_refs){
        
        var recset = window.hWin.HAPI4.EntityMgr.getEntityData('defTerms');
        //parent:[children]
        var t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        var trm_ids = [];
        var res = {};
        
        if(window.hWin.HEURIST4.util.isNumber(mode)){
            if(mode==1) mode='tree'
            else if(mode==2) mode='select'
            else if(mode==3) mode='set'
            else if(mode==4) mode='labels'
            else mode='flat';
        }
        

        function __addChilds(recID, lvl_parents, include_vocab){
        
            //recID = parseInt(recID);
            var node = {title: recset.fld(recID, 'trm_Label'), key: recID};
            
            if(include_vocab && lvl_parents==0){
                node.is_vocab = true;
                trm_ids.push({title: recset.fld(recID, 'trm_Label'), 
                                is_vocab: true,
                                key: recID, depth:lvl_parents});
            }

            var children = t_idx[recID]; //array of children ids trm_Links (including references)
            
            if(children && children.length>0){
                
                if(without_refs===true){
                    //remove terms by reference
                    var real_children = [];
                    $.each(children, function(i,id){
                        if(recset.fld(id,'trm_ParentTermID')==recID) real_children.push(id);
                    });
                    children = real_children;
                }

                //sort children by name
                children.sort(function(a,b){
                    return recset.fld(a,'trm_Label').toLowerCase()<recset.fld(b,'trm_Label').toLowerCase()?-1:1;        
                });
                
                if(mode=='tree'){

                    var child_nodes = [];  
                    for(var i=0; i<children.length;i++){  
                        child_nodes.push( __addChilds(children[i]) );          
                    }
                    node['children'] = child_nodes;
                    node['folder'] = true;

                }else if(mode=='select'){

                    for(var i=0; i<children.length;i++){ 
                        recID = children[i];
                        trm_ids.push({title: recset.fld(recID, 'trm_Label'), 
                                      code: recset.fld(recID, 'trm_Code'),
                                      key: recID, 
                                      depth: lvl_parents});
                        __addChilds(recID, lvl_parents+1);
                    }

                }else if(mode=='set' || mode=='labels'){
                    
                    for(var i=0; i<children.length;i++){  
                        recID = children[i];
                        trm_ids.push(mode=='labels'?recset.fld(recID, 'trm_Label').toLowerCase() 
                                                   :recID);
                        __addChilds(recID);
                    }
                    
                }else{ //gather ids onlys - for recordset

                    lvl_parents = lvl_parents?lvl_parents.split(','):[];
                    lvl_parents.push(recID);

                    for(var i=0; i<children.length;i++){  
                        recID = children[i];
                        trm_ids.push(recID);

                        recset.setFldById(recID, 'trm_Parents', lvl_parents.join(','));
                        __addChilds(recID, lvl_parents.join(','));
                    }

                }
            }
            
            return node;
        }
        
        if(vocab_id=='relation'){
            //find all vocabulary with domain "relation"
            var vocab_ids = $Db.trm_getVocabs('relation');
            for (var i=0; i<vocab_ids.length; i++){
                var trm_ID = vocab_ids[i];
                res = __addChilds(trm_ID, 0, true);
            }
            
        }else{
            res = __addChilds(vocab_id, 0, false);
        }
        
        if(mode=='tree'){
            return res['children'];
        }else if(mode=='select'){
            return trm_ids;
        }else if(mode=='set' || mode=='labels'){
            return trm_ids;
        }else{
            return recset.getSubSetByIds(trm_ids);
        }
        
    },
    
    //
    // is given term has children (including references)
    //
    trm_HasChildren: function(trm_id){
        var t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        var children = t_idx[trm_id];
        return (children && children.length>0);
    },

    //
    // change parent in links
    //
    trm_ChangeChildren: function(old_parent_id, new_parent_id){
        var t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        var children = t_idx[old_parent_id];
        
        if((children && children.length>0)){
            
            $.each(children,function(i,trm_id){
                 if($Db.trm(trm_id,'trm_ParentTermID')==old_parent_id){
                     $Db.trm(trm_id,'trm_ParentTermID',new_parent_id);
                 }
            });
            
            var target_children = t_idx[new_parent_id];
            if(target_children && target_children.length>0){
                t_idx[new_parent_id] = target_children.concat(children)
            }else{
                t_idx[new_parent_id] = children;
            }
           //window.hWin.HAPI4.EntityMgr.setEntityData('trm_Links',t_idx);
        }
    },
    
    
    //
    // get all vocabularies OR for given domain
    //
    trm_getVocabs: function(domain){

        var t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        var res = [];
        var parents = Object.keys(t_idx);
        for (var i=0; i<parents.length; i++){ //first level
            var trm_ID = parents[i];
            var trm_ParentTermID = $Db.trm(trm_ID, 'trm_ParentTermID');
            if(!(trm_ParentTermID>0)){
                if(!domain || $Db.trm(trm_ID, 'trm_Domain')==domain)
                    res.push(trm_ID);    
            }
        }
        
        return res;
    },
    
    //
    // get array of vocabularies by reference
    // (where the given term directly or by referecne belongs to)
    //
    trm_getAllVocabs: function(trm_id){
        var t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        
        var res = [];
        var parents = Object.keys(t_idx);
        for (var i=0; i<parents.length; i++){
            var parent_ID = parents[i];
            var k = window.hWin.HEURIST4.util.findArrayIndex(trm_id, t_idx[parent_ID]);
            if(k>=0){
                var trm_ParentTermID = $Db.trm(parent_ID, 'trm_ParentTermID');
                if(trm_ParentTermID>0){
                    res = res.concat($Db.trm_getAllVocabs(parent_ID));
                }else{
                    //vocabulary!
                    res.push( parent_ID );     
                }
            }
        }
        return res;
    },
    
    //
    // remove any mention of term from hierarchy (trm_Links)
    //
    trm_RemoveLinks: function(trm_id){
        var t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        var parents = Object.keys(t_idx);
        var i = 0;
        while(i<parents.length){
            if(parents[i]==trm_id){
                delete parents[i];   
            }else{
                var k = window.hWin.HEURIST4.util.findArrayIndex(trm_id, t_idx[parents[i]]);
                if(k>=0){
                    t_idx[parents[i]].splice(k,1);
                }
                i = i +1;
            }
        }
    },
    
    //
    // add/remove terms reference links 
    // it calls server side and then update client side by changeParentInIndex
    //
    setTermReferences: function(term_IDs, new_vocab_id, new_parent_id, old_vocab_id, old_parent_id, callback){

        var default_palette_class = 'ui-heurist-design';
        
        if(new_vocab_id>0){
            
            if(!(new_parent_id>0)) new_parent_id = new_vocab_id;

            var trm_ids = $Db.trm_TreeData(new_vocab_id, 'set'); //all terms in target vocab

            var all_children = [];
            var is_exists = 0;
            for(var i=0; i<term_IDs.length; i++){
                if(window.hWin.HEURIST4.util.findArrayIndex(term_IDs[i], trm_ids)>=0){
                    is_exists = term_IDs[i];
                    break;
                }
                var children = $Db.trm_TreeData(term_IDs[i], 'set');
                for(var j=0; j<children.length; j++){
                    if(window.hWin.HEURIST4.util.findArrayIndex(children[j], trm_ids)>=0){
                        is_exists = children[j];
                        break;
                    }
                    if(all_children.indexOf(children[j])<0) all_children.push(children[j]);
                }
            }
            
            {default_palette_class:'ui-heurist-design'}

            //some of selected terms are already in this vocabulary
            if(is_exists>0){
                window.hWin.HEURIST4.msg.showMsgDlg('Term <b>'+$Db.trm(is_exists,'trm_Label')
                    +'</b> is already in vocabulary <b>'+$Db.trm(new_vocab_id,'trm_Label')+'</b>', 
                    null, {title:'Terms'},
                    {default_palette_class:default_palette_class});                        
                return;
            }

            //exclude all child terms - they will be added via their parent
            var i=0;
            while(i<term_IDs.length){
                if(all_children.indexOf(term_IDs[i])<0){
                    i++;
                }else{
                    term_IDs.splice(i,1);
                } 
            }
        }
        if(old_vocab_id>0){
            if(!(old_parent_id>0)) old_parent_id = old_vocab_id;
        }

        var request = {
            'a'          : 'action',
            'reference'  : 1,
            'entity'     : 'defTerms',
            'request_id' : window.hWin.HEURIST4.util.random(),
            'old_VocabID': old_vocab_id,  
            'old_ParentTermID': old_parent_id,  
            'new_VocabID': new_vocab_id,  
            'new_ParentTermID': new_parent_id,  
            'trm_ID': term_IDs                   
        };

        window.hWin.HEURIST4.msg.bringCoverallToFront();                                             

        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(response.status == window.hWin.ResponseStatus.OK){

                    $Db.changeParentInIndex(new_parent_id, term_IDs, old_parent_id);

                    if($.isFunction(callback)){
                            callback.call();
                    }

                }else{
                    if(response.status == window.hWin.ResponseStatus.ACTION_BLOCKED){
                        
                        var sMsg;
                        if(response.sysmsg && response.sysmsg.reccount){
                            
                            var s = '';
                            $.each(response.sysmsg['fields'],function(i,dty_ID){
                               s = s + $Db.dty(dty_ID,'dty_Name'); 
                            });
                              
                            sMsg = '<p>Sorry, we cannot '+(new_parent_id>0?'move':'delete')
                            + ' this term because it (or its children) is already in use in fields '
                            + ' ( '+s+' ) which reference this vocabulary</p> '
                            + ' <p><a href="'+window.hWin.HAPI4.baseURL+'?db='
                            + window.hWin.HAPI4.database+'&q=ids:' + response.sysmsg['records'].join(',')
                            + '" target="_blank">Show '+response.sysmsg['reccount']+' records</a> which use this term (or its descendants).</p>';
                        }else{
                            sMsg = response.message;
                        }
                        
                        window.hWin.HEURIST4.msg.showMsgDlg(sMsg, 
                            null, {title:'Term by Reference'},
                            {default_palette_class:default_palette_class});                        
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);                            
                    }
                }
        });   

    },

    //
    // change links in trm_Links (after server action)
    //
    changeParentInIndex: function(new_parent_id, term_ID, old_parent_id){

        if(new_parent_id==old_parent_id) return;

        var t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        if(new_parent_id>0){
            if(!t_idx[new_parent_id]) t_idx[new_parent_id] = []; 
            if($.isArray(term_ID)){
                //t_idx[new_parent_id] = t_idx[new_parent_id].concat( term_ID );

                for(var i=0; i<term_ID.length; i++)
                    if(window.hWin.HEURIST4.util.findArrayIndex(term_ID[i], t_idx[new_parent_id])<0){
                        t_idx[new_parent_id].push( term_ID[i] );    
                }

            }else if(window.hWin.HEURIST4.util.findArrayIndex(term_ID, t_idx[new_parent_id])<0)
            {
                t_idx[new_parent_id].push(term_ID);
            }

        }
        if(old_parent_id>0){
            var k = window.hWin.HEURIST4.util.findArrayIndex(term_ID, t_idx[old_parent_id]);    
            if(k>=0){
                t_idx[old_parent_id].splice(k,1);
            }
        }

    },    
    
    
        
    //--------------------------------------------------------------------------
    //
    //
    //
    applyOrder: function(recordset, prefix, callback){

        var entityName = recordset.entityName;
        var fieldId    = prefix+'_ID'; 
        var fieldOrder = prefix+'_Order';
        
        //assign new value for vcg_Order and save on server side
        var rec_order = recordset.getOrder();
        var idx = 0, len = rec_order.length;
        var fields = [];
        for(; (idx<len); idx++) {
            var record = recordset.getById(rec_order[idx]);
            var oldval = recordset.fld(record, fieldOrder);
            var newval = String(idx+1).lpad(0,3);
            if(oldval!=newval){
                recordset.setFld(record, fieldOrder, newval);        
                var fld = {};
                fld[fieldId] = rec_order[idx];
                fld[fieldOrder] = newval;
                fields.push(fld);
            }
        }
        if(fields.length>0){

            var request = {
                'a'          : 'save',
                'entity'     : entityName,
                'request_id' : window.hWin.HEURIST4.util.random(),
                'fields'     : fields                     
            };

            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        if($.isFunction(callback)) callback.call();
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
            });

        }else{
            if($.isFunction(callback)) callback.call();
        }
    },
    
    //
    // returns record count by types
    //
    get_record_counts: function( callback )
    {
    
        $Db.needUpdateRtyCount = 0; 
        
        var request = {
                'a'       : 'counts',
                'entity'  : 'defRecTypes',
                'mode'    : 'record_count',
                //'rty_ID'  :
                'ugr_ID'  : window.hWin.HAPI4.user_id()
                };
                             
        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){

                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    $Db.rty().each(function(rty_ID,rec){
                        var cnt = response.data[rty_ID]
                        if(!(cnt>0)) cnt = 0;
                        $Db.rty(rty_ID, 'rty_RecCount', cnt);
                    });
                    
                    if($.isFunction(callback)){
                        callback.call();
                    }
        
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });
        
    },
    
    //
    //
    //
    getTrashGroupId: function(entity){
        
        if(!(this[entity+'_trash_id']>0)){
            var name = entity+'_Name';
            var that = this;
            $Db[entity]().each2(function(id, record){
                if(record[name]=='Trash'){
                    that[entity+'_trash_id'] = id;
                    return false;
                }
            });
        }
        return this[entity+'_trash_id'];
    },
    
    //
    // prase rt:dt:rt:dt....  hierarchy - returns composed label and fields
    // used in facet and query builders
    //
    parseHierarchyCode: function(codes, top_rty_ID){

        codes = codes.split(':');

        var removeFacet = false;
        var harchy = [];
        var harchy_fields = []; //for facet.title - only field names (w/o rectype)
        var j = 0;
        while(j<codes.length){
            var rtid = codes[j];
            var dtid = codes[j+1];

            if(rtid.indexOf(',')>0){ //take first from list of rty_IDs
                rtid = rtid.split(',')[0];
            }
            
            if(rtid=='any'){
                harchy.push('');    
                if(top_rty_ID>0) rtid = top_rty_ID;
                
            }else if($Db.rty(rtid)==null){
                //record type was removed - remove facet
                removeFacet = true;
                break;
            }else{
                harchy.push('<b>'+$Db.rty(rtid,'rty_Name')+'</b>');    
            }

            var rec_header = null;
            
            if(dtid=='title'){
                rec_header = 'Constructed record title';
            }else if(dtid=='ids'){
                rec_header = "IDs"; 
            }else if(dtid=='added'){
                rec_header = "Added"; 
            }else if(dtid=='modified'){
                rec_header = "Modified"; 
            }else if(dtid=='addedby'){
                rec_header = "Record author"; 
            }else if(dtid=='url'){
                rec_header = "URL"; 
            }else if(dtid=='notes'){
                rec_header = "Notes"; 
            }else if(dtid=='owner'){
                rec_header = "Owner"; 
            }else if(dtid=='access'){
                rec_header = "Visibility"; 
            }else if(dtid=='tag'){
                rec_header = "Tags"; 
            }else if(dtid=='anyfield'){
                rec_header = "Any field"; 
            }
            
            if( rec_header ){
            
                    harchy.push(' . '+rec_header);
                    harchy_fields.push(rec_header);
                
            }else{

                var linktype = dtid.substr(0,2);                                
                if(isNaN(Number(linktype))){
                    dtid = dtid.substr(2);

                    if(dtid>0){


                        if(linktype=='lt' || linktype=='rt'){

                            var sFieldName = (rtid=='any')
                                            ?$Db.dty(dtid, 'dty_Name')
                                            :$Db.rst(rtid, dtid, 'rst_DisplayName');

                            if(window.hWin.HEURIST4.util.isempty(sFieldName)){
                                //field was removed - remove facet
                                removeFacet = true;
                                break;
                            }

                            harchy.push(' . '+sFieldName+' &gt ');
                            harchy_fields.push(sFieldName);
                        }else{
                            var from_rtid = codes[j+2];

                            var sFieldName = $Db.rst(from_rtid, dtid, 'rst_DisplayName');

                            if(window.hWin.HEURIST4.util.isempty(sFieldName)){
                                //field was removed - remove facet
                                removeFacet = true;
                                break;
                            }

                            harchy.push(' &lt '+sFieldName+' . ');
                        }

                    }//dtid>0

                }else{

                    var sFieldName = (rtid=='any')
                                ?$Db.dty(dtid, 'dty_Name')
                                :$Db.rst(rtid, dtid, 'rst_DisplayName');

                    if(window.hWin.HEURIST4.util.isempty(sFieldName)){
                        //field was removed - remove facet
                        removeFacet = true;
                        break;
                    }

                    harchy.push(' . '+sFieldName);
                    harchy_fields.push(sFieldName);
                }
            }
            j = j+2;
        }//while codes        



        return removeFacet? false :{harchy:harchy, harchy_fields:harchy_fields};
    }

}//end dbs

}
//alias
var $Db = window.hWin.HEURIST4.dbs;    

