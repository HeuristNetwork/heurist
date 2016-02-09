
/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

function hEditing(container, _recdata, _recstructure) {
     var _className = "Editing",
         _version   = "0.4";

     var $container = null,
         recdata = null,     //hRecordSet with data to be edited
         editing_inputs = [],
         recstructure;

    /**
    * Initialization
    * 
    * container - element Id or jqyuery element
    */
    function _init(container, _recdata, _recstructure) {
        if (typeof container==="string") {
            $container = $("#"+container);
        }else{
            $container = $(container);
        }
        if($container==null || $container.length==0){
            $container = null;
            alert('Container element for editing not found');
        }
        
        _initEditForm(_recstructure, _recdata);
    }

    //
    // relaod existing form
    //
    function _reload(_recdata) {
        
        if(!$container) return;

        recdata = _recdata;

        if(!recdata) return; //nothing to edit

        //create form, fieldset and input elements according to record type/entity structure

        var record = recdata.getFirstRecord();
        /*if(record){
            var recID = recdata.fld(record, 'rec_ID');
            var rectypeID = recdata.fld(record, 'rec_RecTypeID');
            //load recordtype/entity structure
            var rectypes = recdata.getStructures();
        }*/
        if(record!=null){
            //fill form with values
            var idx, ele;
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                var val = recdata.fld(record, ele.editing_input('option', 'dtID'));
                if(!top.HEURIST4.util.isArray(val)) val = [val];
                ele.editing_input('setValue', val );
            }
            
        }
    }
    
    function _initEditForm(_recstructure, _recdata){
        
        if(_recstructure) recstructure = _recstructure;
        
        editing_inputs = [];
        $container.empty(); //clear previous edit elements
        
        
        if(!top.HEURIST4.util.isArrayNotEmpty(recstructure)) return;   
        
        recdata = _recdata;
        record = null;
                
        if(recdata!=null){
             record = recdata.getFirstRecord();
        }
        
        //rec structure is arrya in following format
        /*
            only type 'header' can have children
               [
                    {
                    groupHeader: '',
                    groupType: '',  accordeon, tab, group 
                    groupStye: {}
                    children:[
                        dtID, dtID, dtID, 
                        {groupHeader: , children:},
                    ]
                    },.....
                ],
                
                [
                    {
                    dtID: 'dty_Name',
                    dtFields:{
                        dty_Type:'freetext',
                        rst_DisplayName:'Default field type name:',
                        rst_DisplayHelpText: '', 
                        rst_DisplayExtendedDescription:'',
                        rst_DisplayWidth:60,
                        rst_DefaultValue:'',
                        rst_RequirementType:'required',
                        rst_MaxValues:1
                    }
                    },....]
        */
        
        function __createGroup(fields, groupContainer, fieldContainer){
            var idx;
                
            var currGroupType = null; //current accodion or tab control
            var groupTabHeader, groupEle;
            
            //var groupEle,      //current accodion or tab control
            //    fieldContainer, groupTabHeader;
                
            for (idx=0; idx<fields.length; idx++){
                
                if( $.isPlainObject(fields[idx]) && 
                    top.HEURIST4.util.isArrayNotEmpty(fields[idx].children)){ //this is group
                    
                    if(fields[idx].groupType != currGroupType){ //create new group container and init previous
                        //init previous one 
                        if(groupEle!=null){
                            if(currGroupType == 'accordion'){
                                groupEle.accordion({heightStyle: "content"});
                            }else if(currGroupType == 'tabs'){
                                groupEle.tabs();
                            }
                        }
                        
                        currGroupType = fields[idx].groupType;
                        //create new accordion or tabcontrol
                        if(currGroupType == 'accordion'){
                            groupEle = $('<div>').appendTo(groupContainer);
                        }else if(currGroupType == 'tabs'){
                            groupEle = $('<div>').appendTo(groupContainer);
                            groupTabHeader = $('<ul>').appendTo(groupEle);
                        }else{
                            groupEle = null;
                        }
                    }
                    
                    var headerText = fields[idx]['groupHeader'];
                    fieldContainer = $('<fieldset>').uniqueId();
                    if(!$.isEmptyObject(fields[idx]['groupStyle'])){
                        fieldContainer.css(fields[idx]['groupStyle']);    
                    }

                    //add header and field container
                    if(currGroupType == 'accordion'){
                         $('<h3>').text(headerText).appendTo(groupEle);
                         fieldContainer.addClass('ui-heurist-bg-light').appendTo($('<div>').appendTo(groupEle));
                         
                    }else if(currGroupType == 'tabs'){
                         $('<li>').html('<a href="#'+fieldContainer.attr('id')+'"><span>'+headerText+'</span></a>')
                                .appendTo(groupTabHeader);
                         $(fieldContainer).addClass('ui-heurist-bg-light').appendTo(groupEle);
                         //.css({'font-size':'1em'})
                    }else{
                         fieldContainer.appendTo(container);
                         $('<h3>').text(headerText).appendTo(groupContainer);
                    }
                        
                    __createGroup(fields[idx].children, groupContainer, fieldContainer);
                }//has children
                else{ //this is entry field 
                
                    if(fieldContainer==null){ 
                        //we do not create it before loop to avoid create empty fieldset 
                        // in case first element is group
                        fieldContainer = $('<fieldset>').uniqueId().appendTo(groupContainer);
                    }
                    
                    if(fields[idx]['dty_Type']=="separator"){
                        $('<h3>').text(fields[idx]['rst_DisplayName']).addClass('separator').appendTo(fieldContainer);
                    }else  //do not include hidden 
                    if(fields[idx]['dtFields']['rst_Display']!="hidden") {
                        
                        if(record!=null){
                            var val = recdata.fld(record, fields[idx]['dtID']);
                            if(!top.HEURIST4.util.isnull(val)){
                                if(!top.HEURIST4.util.isArray(val)) val = [val];
                                fields[idx].values = val;
                            }  
                        }
                        
                        var inpt = $('<div>').appendTo(fieldContainer).editing_input(fields[idx]);     
                        editing_inputs.push(inpt);  
                    }
                }
                
            }//for
            
            //init last one
            if(groupEle!=null){
                if(currGroupType == 'accordion'){
                    groupEle.accordion({heightStyle: "content"});
                }else if(currGroupType == 'tabs'){
                    groupEle.tabs();
                }
            }
            
        }//end of function

        $container.addClass('ui-heurist-bg-light');        
        __createGroup(recstructure, $container, null);
    }

    function _save(){
        alert('save');
        return true;
    }

    //
    // returns values as object {'xyz_Field':'value','xyz_Field2':['val1','val2','val3]}
    //    
    function _getValues(needArrays){
        
        var idx, ele, details = {};
        for (idx in editing_inputs) {
            ele = $(editing_inputs[idx]);
            var vals = ele.editing_input('getValues');
            //var vals = ele.list("getValues"); 
            //var vals = ele.data("editing_input").getValues();
            if(vals && vals.length>0){
                details[ ele.editing_input('option', 'dtID') ] = (needArrays || vals.length>1) ?vals :vals[0];
            }
        }
        return details;
    }
    
    function _validate(){
        
        var idx, res = true;
        for (idx in editing_inputs) {
            res = $(editing_inputs[idx]).editing_input('validate') && res;
        }
        
        return res;
    }
    
    //
    // returns array of input elements for given field
    // 
    function _getFieldByName(fieldName){
        var idx, ele;
        for (idx in editing_inputs) {
            ele = $(editing_inputs[idx]);
            if(ele.editing_input('option', 'dtID')  == fieldName){
                return ele;
            }
        }
    }

    //
    // returns array of input elements for field value
    // 
    function _getFieldByName(fieldName, value){
        var idx, ele, ress = [];
        for (idx in editing_inputs) {
            ele = $(editing_inputs[idx]);
            if(ele.editing_input('f', fieldName)  == value){
                ress.push(ele);
            }
        }
        return ress;
    }
        
    //
    // returns array of input elements for given field
    // 
    function _getInputs(fieldName){
        var ele = _getFieldByName(fieldName);
        if(ele && ele.length>0){
            return ele.editing_inputs('getInputs');
        }
    }


    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        //assign values only based on old structure
        reload: function(_recdata){
            _reload(_recdata);
        },

        validate: function(){
            return _validate();
        },

        save: function(){
            _save();
        },
        
        initEditForm: function(_recstructure, _recdata){
            _initEditForm(_recstructure, _recdata);
        },
        
        getValues:function(needArrays){
            return _getValues(needArrays);
        },
        
        //
        // returns editing_element by name
        //
        getFieldByName:function(fieldName){
            return _getFieldByName(fieldName);
        },
        
        getFieldByValue:function(fieldName, value){
            return _getFieldByName(fieldName, value);
        },
        
        getInputs:function(fieldName){
            return _getInputs(fieldName);
        }
        
    }

    _init(container, _recdata, _recstructure);
    return that;  //returns object
}
/*
           var rfrs = rectypes.typedefs[rectypeID].dtFields;
            var fi = rectypes.typedefs.dtFieldNamesToIndex;

            var order = rectypes.dtDisplayOrder[rectypeID];
            if(order){
                var i, l = order.length;

                //???? move outside????
                var $header = $('<div>')
                        .css('padding','0.4em')
                        .addClass('ui-widget-header ui-corner-all')
                        .appendTo($container);
                $('<div style="display:inline-block"><span>'
                        + rectypes.names[rectypeID]
                        +'</span><h3 id="recTitle">'
                        +recdata.fld(record, 'rec_Title')
                        +'</h3></div>')
                        .appendTo($header);

                var $toolbar = $('<div>')
                    .css('float','right')
                    .appendTo( $header );

                _create_toolbar($toolbar);

                $('<button>', {text:'Save'}).button().on("click", function(event){ _save(); } ).appendTo($toolbar);
                $('<button>', {text:'Cancel'}).button().appendTo($toolbar);
                //???? END move outside????

                var $formedit = $("<div>").appendTo($container);
                var $fieldset = $("<fieldset>").appendTo($formedit);

                for (i = 0; i < l; ++i) {
                    var dtID = order[i];
                    if (rfrs[dtID][fi['rst_RequirementType']] == 'forbidden') continue;

                    var values = recdata.fld(record, dtID);

                    $("<div>").editing_input(
                              {
                                recID: recID,
                                rectypeID: rectypeID,
                                dtID: dtID,
                                rectypes: rectypes,
                                values: values
                              })
                              .appendTo($fieldset);

                }
            }
*/