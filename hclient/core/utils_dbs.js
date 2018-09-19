/**
*  Utility functions  for database structure
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
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

getChildrenTerms - returns entire terms tree or only part of it for selected termID
getChildrenLabels - returns all tems labels of children terms for given term
getTermValue - Returns label and code for term by id
getTermDesc
getPlainTermsList
getFullTermLabel

*/

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}
//init only once
if (!window.hWin.HEURIST4.dbs) 
{

window.hWin.HEURIST4.dbs = {
    
    //
    // return list of all children for given trm_ParentTermID in lower case
    //
    getChildrenLabels: function(trm_ParentDomain, trm_ParentTermID){
        
            var trm_ParentChildren = [];
        
            //get list of children labels
            function __getSiblings(children){
                for(trmID in children){
                    if(children.hasOwnProperty(trmID)){
                        if(trmID==trm_ParentTermID){
                            for(var id in children[trmID]){
                                if(children[trmID].hasOwnProperty(id)){
                                    var term = allterms.termsByDomainLookup[trm_ParentDomain][id];
                                    if(term && term[0])
                                        trm_ParentChildren.push(term[0].toLowerCase());
                                }
                            }
                            break;
                        }else{
                            __getSiblings(children[trmID]);
                        }
                    }
                }
            }
            
            var allterms = window.hWin.HEURIST4.terms;
 
            
            var trmID, tree = allterms.treesByDomain[trm_ParentDomain];
            __getSiblings(tree);  
              
            return trm_ParentChildren;
    },
    
    getTermById: function(termID){
        
        var terms = window.hWin.HEURIST4.terms;
        if(!terms || window.hWin.HEURIST4.util.isempty(termID)) return '';
        
        var term, termLookup = terms.termsByDomainLookup['enum'];
        if(termLookup[termID]){
            term = termLookup[termID];
        }else{
            termLookup = terms.termsByDomainLookup['relation'];
            term = termLookup[termID];
        }
        
        return term;
    },

    // get inverse term id
    //
    getInverseTermById: function(termID){
        var term = window.hWin.HEURIST4.ui.getTermById(termID);
        if(term){
            var terms = window.hWin.HEURIST4.terms;
            var invTermID = term[terms.fieldNamesToIndex['trm_InverseTermID']];
            if(invTermID>0) return invTermID;
            return termID;
        }
        return '';
    },
    
    //
    // Returns label and code for term by id
    //
    getTermValue: function(termID, withcode){

        var term = window.hWin.HEURIST4.ui.getTermById(termID);    
        
        var termName, termCode='';

        if(term){
            var terms = window.hWin.HEURIST4.terms;
            termName = term[terms.fieldNamesToIndex['trm_Label']];
            termCode = term[terms.fieldNamesToIndex['trm_Code']];
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
    // get description of label for term
    //
    getTermDesc: function(termID){

        var term = window.hWin.HEURIST4.ui.getTermById(termID);    
        if(term){

            var terms = window.hWin.HEURIST4.terms;
            var termDesc = term[terms.fieldNamesToIndex['trm_Description']];
            if(window.hWin.HEURIST4.util.isempty(termDesc)){
                return term[terms.fieldNamesToIndex['trm_Label']];
            }else{
                return termDesc;
            }
            
        }else{
            return 'not found term#'+termID;
        }

    },

    // 
    //
    // see crosstabs
    //
    getPlainTermsList: function(datatype, termIDTree, headerTermIDsList, selectedTermID) {
        
        //var selObj = window.hWin.HEURIST4.ui.createTermSelectExt(null, datatype, termIDTree, headerTermIDsList);
        
        var selObj = window.hWin.HEURIST4.ui.createTermSelectExt2(null,
            {datatype:datatype, termIDTree:termIDTree, headerTermIDsList:headerTermIDsList,
             defaultTermID:null, topOptions:null, needArray:false, useHtmlSelect:true});
        

        var reslist = [];

        if(selObj){
            selObj = selObj[0];
            for (var i=0; i<selObj.length; i++){
                if(!selObj.options[i].disabled){
                    reslist.push({id: parseInt(selObj.options[i].value), text:selObj.options[i].text});
                }
            }
        }
        return reslist;
    },

    //
    //
    //
    isTermInList: function(datatype, termIDTree, headerTermIDsList, selectedTermID) {
        
        //var selObj = window.hWin.HEURIST4.ui.createTermSelectExt(null, datatype, termIDTree, headerTermIDsList);

        var selObj = window.hWin.HEURIST4.ui.createTermSelectExt2(null,
            {datatype:datatype, termIDTree:termIDTree, headerTermIDsList:headerTermIDsList,
             defaultTermID:null, topOptions:null, needArray:false, useHtmlSelect:true});
        
        if(selObj){
            selObj = selObj[0];
            for (var i=0; i<selObj.length; i++){
                if(!selObj.options[i].disabled){
                    if(selObj.options[i].value==selectedTermID){
                        return true;
                    }
                }
            }
        }
        return false;
    },
    
    //
    // return term by selectedTermID and its children as well as comma-separated list of non-disabled ancestors
    // it uses createTermSelectExt to get the entire tree
    // used in search_faceted
    //
    getChildrenTerms: function(datatype, termIDTree, headerTermIDsList, selectedTermID) {

        var termtree = window.hWin.HEURIST4.ui.createTermSelectExt(null, datatype, termIDTree, headerTermIDsList, 
                                                                        null, null, true);
        /*
        function __setParents(parent, terms){

        for (var i=0; i<terms.length; i++)
        {
        if(!terms[i].parents){
        terms[i].parents = [];
        }else{
        terms[i].parents = terms[i].parents.concat(parent.parents);
        }
        terms[i].parents.unshift(parent);

        __setParents(terms[i], terms[i].children);
        }
        }
        */
        function __findTerm(termId, parent, terms)
        {
            for (var i=0; i<terms.length; i++){

                if(terms[i].id==termId){
                    return terms[i];
                }else{
                    var res = __findTerm(termId, terms[i], terms[i].children);
                    if(res!=null){
                        return res;
                    }
                }
            }
            return null; //not found in this level
        }

        var root = {id:null, text:window.hWin.HR('all'), children:termtree};

        //__setParents(root, termtree);

        return window.hWin.HEURIST4.util.isnull(selectedTermID)?root:__findTerm(selectedTermID, root, termtree);
    },
    
    /*
     
      returns rectype structure as treeview data
      there is similar method on client side - however on clinet side it is faster
      used for treeview in import structure, facet wiz
      todo - use it in smarty editor
     
      fieldtypes - 
            array of fieldtypes, and 'all', 'header', 'header_ext'
            header - all+header fields
      $mode 
         4 - find reverse links and relations   
         5 for lazy treeview  
       returns:
         
       children:[{key: field#, type: fieldtype, title:'', code , name, conceptCode, dtyID_local, children:[]},... ]
     
    */
    createRectypeStructureTree: function( db_structure, $mode, rectypeids, fieldtypes, parentcode ) {
        
        
    function __getRecordTypeTree($recTypeId, $recursion_depth, $mode, $fieldtypes, $pointer_fields){
            
            var rectypes = db_structure.rectypes;
            var $res = {};
            var $children = [];
            
            //add default fields
            if($recursion_depth==0 && 
                ($fieldtypes.indexOf('header')>=0 || $fieldtypes.indexOf('header_ext')>=0) ) {

                if($fieldtypes.indexOf('header_ext')>=0){
                    $children.push({key:'rec_ID', type:'integer',
                        title:"ID  <span style='font-size:0.7em'>(integer)</span>", 
                        code:($recTypeId+':id'), name:'Record ID'});
                }

                
                $children.push({key:'rec_Title', type:'freetext',
                    title:"RecTitle <span style='font-size:0.7em'>(Constructed text)</span>", 
                    code:($recTypeId+':title'), name:'Record title'});

                $children.push({key:'rec_Modified', type:'date',
                    title:"Modified  <span style='font-size:0.7em'>(Date)</span>", 
                    code:($recTypeId+':modified'), name:'Record modified'});
                    
                //array_push($children, array('key'=>'recURL',      'type'=>'freetext',  'title'=>'URL', 'code'=>$recTypeId.":url"));
                //array_push($children, array('key'=>'recWootText', 'type'=>'blocktext', 'title'=>'WootText', 'code'=>$recTypeId.":woot"));
                
                if($fieldtypes.indexOf('header_ext')>=0){
                    $children.push({key:'rec_URL', type:'freetext',
                        title:"URL  <span style='font-size:0.7em'>(freetext)</span>", 
                        code:($recTypeId+':url'), name:'Record URL'});
                        
                    $children.push({key:'rec_Tags', type:'freetext',
                        title:"Tags  <span style='font-size:0.7em'>(freetext)</span>", 
                        code:($recTypeId+':tags'), name:'Record Tags'});
                }
                
            }

            if($recTypeId>0 && rectypes['typedefs'][$recTypeId]){

                $res['key'] = $recTypeId;
                $res['title'] = rectypes['names'][$recTypeId];
                $res['type'] = 'rectype';
                
                var idx_ccode = window.hWin.HEURIST4.rectypes.typedefs.commonNamesToIndex.rty_ConceptID;
                var $rt_conceptcode = rectypes['typedefs'][$recTypeId]['commonFields'][idx_ccode];

                $res['conceptCode'] = $rt_conceptcode;
                $res['rtyID_local'] = window.hWin.HEURIST4.dbs.findByConceptCode($rt_conceptcode, 
                                window.hWin.HEURIST4.rectypes.typedefs, idx_ccode);
                                
                //$res['title'] = $res['title']+" <span style='font-size:0.6em'>(" + $rt_conceptcode
                //            +','+$res['rtyID_local']+ ")</span>";   
                                
                                                                                                                  
                if(($mode!=5 || $recursion_depth==0)){
                    $details = rectypes['typedefs'][$recTypeId]['dtFields'];
                    
                    var $children_links = [];
                    var $new_pointer_fields = [];

                    for (var $dtID in $details) {
                        
                        var $dtValue = $details[$dtID];
                        
                        //@TODO forbidden for import????
                        if($dtValue[rectypes['typedefs']['dtFieldNamesToIndex']['rst_RequirementType']]=='forbidden') continue;

                        $dt_type = $dtValue[rectypes['typedefs']['dtFieldNamesToIndex']['dty_Type']];
                        if($dt_type=='resource' || $dt_type=='relmarker'){
                                $new_pointer_fields.push( $dtID );
                        }
                        
                        $res_dt = __getDetailSection($recTypeId, $dtID, $recursion_depth, $mode, 
                                                                $fieldtypes, null, $new_pointer_fields);
                        if($res_dt){
                            
                            if($res_dt['type']=='resource' || $res_dt['type']=='relmarker'){
                                $children_links.push($res_dt);
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
                    }//for
                    
                    //add resource and relation at the end of result array
                    $children = $children.concat($children_links);
                    
                    //find all reverse links and relations
                    if( ($mode==4 && $recursion_depth<2) || ($mode==5 && $recursion_depth==0) ){
                        $reverse_rectypes = __getReverseLinkedRecordTypes($recTypeId);
                        
                        for (var $rtID in $reverse_rectypes) {
                            
                            var $dtID = $reverse_rectypes[$rtID];
                            
                            //$dtValue =  $dbs_rtStructs['typedefs'][$rtID]['dtFields'][$dtID];
                            if( $pointer_fields==null || 
                                ($.isArray($pointer_fields) && 
                                window.hWin.HEURIST4.util.findArrayIndex($dtID, $pointer_fields)<0) )
                            {  // to avoid recursion
                                $res_dt = __getDetailSection($rtID, $dtID, $recursion_depth, $mode, $fieldtypes, $recTypeId, null);
                 
                                if($res_dt){
                                    $children.push( $res_dt );
                                }
                            }
                        }
                    }
                }
                if($mode==3 && $recursion_depth==0){
                    $children.push(__getRecordTypeTree('Relationship', $recursion_depth+1, $mode, $fieldtypes, null));
                }   

            }else if($recTypeId=="Relationship") {

                $res['title'] = "Relationship";
                $res['type'] = "relationship";

                //add specific Relationship fields
                $children.push({key:'recRelationType', title:'RelationType'});
                $children.push({key:'recRelationNotes', title:'RelationNotes'});
                $children.push({key:'recRelationStartDate', title:'RelationStartDate'});
                $children.push({key:'recRelationEndDate', title:'RelationEndDate'});
            }else if($mode==5){
                $res['title'] = 'Any record type';
                $res['type'] = 'rectype';
            }

            
            if($mode!=5 || $recursion_depth==0){
                $res['children'] = $children;
            }

            return $res;
            
        } //__getRecordTypeTree
        
    //
    // returns array of record types that are linked to given record type
    //
    function __getReverseLinkedRecordTypes($rt_ID){
        
        var $dbs_rtStructs = db_structure.rectypes;
        //find all reverse links (pointers and relation that point to selected rt_ID)
        var $alldetails = $dbs_rtStructs['typedefs'];
        var $fi_type = $alldetails['dtFieldNamesToIndex']['dty_Type'];
        var $fi_rectypes = $alldetails['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];
        
        var $arr_rectypes = {};
        
        for (var $recTypeId in $alldetails) {
        
            if($recTypeId>0 && $recTypeId!=$rt_ID){ //not itself
            
                var $details = $alldetails[$recTypeId];
                
                $details = $dbs_rtStructs['typedefs'][$recTypeId]['dtFields'];
                if(!$.isArray($details)) continue;
                
                for (var $dtID in $details) {
                    
                    var $dtValue = $details[$dtID];
            
                    if(($dtValue[$fi_type]=='resource' || $dtValue[$fi_type]=='relmarker')){

                            //find constraints
                            var $constraints = $dtValue[$fi_rectypes];
                            $constraints = $constraints.split(",");
                            //verify that selected record type is in this constaint
                            if($constraints.length>0 && 
                                window.hWin.HEURIST4.util.findArrayIndex($rt_ID, $constraints)>=0 &&
                                !$arr_rectypes[$recTypeId] )
                            {
                                $arr_rectypes[$recTypeId] = $dtID;
                            }
                    }
                }
            }
        }
        
        return  $arr_rectypes;
        
    }

    /*
    $dtID   - detail type ID
    $dtValue - record type structure definition
    returns display name  or if enum array
    $mode - 3 all, 4, 5 for treeview (5 lazy) , 6 - for import csv(dependencies)
    */
    function __getDetailSection($recTypeId, $dtID, $recursion_depth, $mode, $fieldtypes, $reverseRecTypeId, $pointer_fields){

        var $dbs_rtStructs = db_structure.rectypes;
        var $dbs_lookups   = window.hWin.HEURIST4.detailtypes.lookups;

        $res = null;

        var $rtNames = $dbs_rtStructs['names']; //???need
        var $rst_fi = $dbs_rtStructs['typedefs']['dtFieldNamesToIndex'];

        var $dtValue = $dbs_rtStructs['typedefs'][$recTypeId]['dtFields'][$dtID];

        var $detailType = $dtValue[$rst_fi['dty_Type']];
        var $dt_label   = $dtValue[$rst_fi['rst_DisplayName']];
        var $dt_title   = $dtValue[$rst_fi['rst_DisplayName']];
        var $dt_tooltip = $dtValue[$rst_fi['rst_DisplayHelpText']]; //help text
        var $dt_conceptcode = $dtValue[$rst_fi['dty_ConceptID']];

        var $pref = "";
        //$dt_maxvalues = $dtValue[$rst_fi['rst_MaxValues']]; //repeatable
        //$issingle = (is_numeric($dt_maxvalues) && intval($dt_maxvalues)==1)?"true":"false";
        
        if (($mode==3) || $fieldtypes.indexOf('all')>=0 
            || window.hWin.HEURIST4.util.findArrayIndex($detailType, $fieldtypes)>=0) //$fieldtypes - allowed types
        {

        var $res = null;
            
        switch ($detailType) {
            case 'separator':
                return null;
            case 'enum':

                $res = {};
                if($mode==3){
                    /* todo ????
                    $res['children'] = []
                        array("text"=>"internalid"),
                        array("text"=>"code"),
                        array("text"=>"term"),
                        array("text"=>"conceptid"));
                    */    
                }
                break;

            case 'resource': // link to another record type
            case 'relmarker':
            
                var $max_depth = 2;
                if ($mode==6 || $mode==4)
                   $max_depth = 3;
                else if ($mode==5) //make it 1 for lazy load
                   $max_depth = 1; 
                                                                
                if($recursion_depth<$max_depth){
                    
                    if($reverseRecTypeId!=null){
                            var $res = __getRecordTypeTree($recTypeId, $recursion_depth+1, $mode, $fieldtypes, $pointer_fields);
                            if($res){
                                $res['rt_ids'] = $recTypeId; //list of rectype - constraint
                                //$res['reverse'] = "yes";
                                $pref = ($detailType=="resource")?"lf":"rf";
                                //before 2017-06-20 $dt_title = " <span style='font-style:italic'>" . $rtNames[$recTypeId] ."  ". $dt_title . "</span>";

                                $dt_title = "<span>&lt;&lt; <span style='font-weight:bold'>" 
                                        + $rtNames[$recTypeId] + "</span> . " + $dt_title + '</span>';
                                
                                if($mode==5){
                                    $res['lazy'] = true;
                                }
                            }
                    }else{

                            var $pref = ($detailType=="resource")?"lt":"rt";

                            var $pointerRecTypeId = $dtValue[$rst_fi['rst_PtrFilteredIDs']];
                            if(window.hWin.HEURIST4.util.isnull($pointerRecTypeId)) $pointerRecTypeId = '';
                            var $is_required      = ($dtValue[$rst_fi['rst_RequirementType']]=='required');
                            var $rectype_ids = $pointerRecTypeId.split(",");
                             
                            if($mode==4 || $mode==5){
                                /*
                                if($pointerRecTypeId=="" || count($rectype_ids)==0){ //TEMP
                                     $dt_title .= ' unconst';
                                }
                                */
                                
                                $dt_title = " <span style='font-style:italic'>" + $dt_title + "</span>";
                            }
                            
                            if($pointerRecTypeId=="" || $rectype_ids.length==0){ //unconstrainded

                                $res = __getRecordTypeTree( null, $recursion_depth+1, $mode, $fieldtypes, $pointer_fields);
                                //$res['constraint'] = 0;

                            }else{ //constrained pointer

                                $res = {};

                                if($rectype_ids.length>1){
                                    $res['rt_ids'] = $pointerRecTypeId; //list of rectype - constraint
                                    $res['constraint'] = $rectype_ids.length;
                                    if($mode!=5) $res['children'] = array();
                                }
                                if($mode==5){
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
                                            $res['constraint'] = $rt_res.length;
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
              $res['code'] = (($reverseRecTypeId!=null)?$reverseRecTypeId:$recTypeId)+":"+$pref+$dtID;  //(($reverseRecTypeId!=null)?$reverseRecTypeId:$recTypeId)  
            } 
            $res['key'] = "f:"+$dtID;
            if($mode==4 || $mode==5){
                    
                var $stype = ($detailType=='resource' || $detailType=='relmarker')?"":$dbs_lookups[$detailType];
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
            
            
            var idx_ccode = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex.dty_ConceptID;
            
            $res['conceptCode'] = $dt_conceptcode;
            $res['dtyID_local'] = window.hWin.HEURIST4.dbs.findByConceptCode($dt_conceptcode, 
                        window.hWin.HEURIST4.detailtypes.typedefs, idx_ccode);
            
            //$res['title'] = $res['title']+" <span style='font-size:0.6em'>(" + $dt_conceptcode
            //                +','+$res['dtyID_local']+ ")</span>";   
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
                    $def['children'][$idx]['code'] = $def['code'] + ":" + $det['code']; 
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
            fieldtypes = ['integer','date','freetext','year','float','enum','resource','relmarker'];
        }else if(!$.isArray(fieldtypes) && fieldtypes!='all'){
            fieldtypes = fieldtypes.split(',');
        }
        
        if(db_structure==null){
            db_structure = window.hWin.HEURIST4;
        }

        var rtypes = db_structure.rectypes['names'];
        var res = [];
        
        rectypeids = (!$.isArray(rectypeids)?rectypeids.split(','):rectypeids);    
        
        //create hierarchy tree 
        for (var k=0; k<rectypeids.length; k++) {
            var rectypeID = rectypeids[k];
            var def = __getRecordTypeTree(rectypeID, 0, 5, fieldtypes, null);
            
                if(def!==null) {
                    if(parentcode!=null){
                        if(def['code']){
                            def['code'] = parentcode+':'+def['code'];
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

        return res;    
        
    },    


    //
    //  find by concept code in local definitions
    //
    // entities - rectypes, detailtypes, terms
    //
    findByConceptCode: function(concept_code, entities, idx_ccode){

        var res = [];
        var sall = false;

        for (var id in entities) 
        if(id>0){
            var def = entities[id];
            if(def['commonFields'][idx_ccode] == concept_code){
                if(sall){
                    res.push(id);
                }else{
                    return id;
                }
            }
        }
        return (sall)?res:0;
    }

    
}//end dbs

}
