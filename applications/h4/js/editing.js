/**
* Class for edit Heurist record. It has methods to add new or edit existing record (it can search and load beforehand) 
* 
* @see editing_input.js
*/
function hEditing() {
     var _className = "Editing",
         _version   = "0.4";

     var $container = null,
         recdata = null,     //hRecordSet
         recID = null,
         rectypeID = null,
         _isdialog = false,  //open edit as popup dialogue
         _isaddnewrecord = false,
         _inputs = null; //array of editing_input widgets

    /**
    * Initialization
    */
    function _init() {
    }

    /**
    * define the container and clear it
    * 
    * @param _container - html element id or element
    */
    function _setcontainer(_container){

         if (typeof container==="string") {
            $container = $("#"+container);
        }else{
            $container = $(_container);
        }
        if($container.length==0){
            _isdialog = true;
            $container = $("#heurist-dialog");
        }else{
            _isdialog = false;
        }

        $container.children().remove();
        $container.empty(); //.hide();
    }

    /**
    * Set container and create new record for given record type
    * on load of new record ir call _load() to init input controls
    * 
    * @param _recordtype
    * @param _container
    */
    function _add(_recordtype, _container){

            _setcontainer(_container);

            _isaddnewrecord = true;
            recdata = null;

            top.HAPI.RecordMgr.add( {rt:_recordtype}, //ro - owner,  rv - visibility
                function(response){
                    if(response.status == top.HAPI.ResponseStatus.OK){

                        recdata = new hRecordSet(response.data);
                        _load();

                    }else{
                        top.HEURIST.util.showMsgErr(response);
                    }
                }

            );
    }

    /**
    * Init container and load record data for edit
    * 
    * @param _recdata - either recordSet or  record id. 
    *           if it is record id, it performs the search         
    * @param _container
    */
    function _edit(_recdata, _container){

            _setcontainer(_container);

            _isaddnewrecord = false;
            recdata = null;

            if ( _recdata && (typeof _recdata.isA == "function") && _recdata.isA("hRecordSet") ){
                if(_recdata.length()>0){
                    recdata = _recdata;
                    _load();
                }
            } else if( !isNaN(parseInt(_recdata)) ) {

                top.HAPI.RecordMgr.search({q: 'ids:'+_recdata, w: "all", f:"structure", l:1},
                    function(response){
                        if(response.status == top.HAPI.ResponseStatus.OK){

                            recdata = new hRecordSet(response.data);
                            _load();

                        }else{
                            top.HEURIST.util.showMsgErr(response);
                        }
                    }
                );

            }else{
                top.HEURIST.util.showMsgErr('Wrong parameters for record edit');
            }
    }

    /**
    * Load record data (@see hRecordSet)
    *   adds html elements - header and input control widgets, save/cancel buttons
    * 
    */
    function _load() {
        if (!($container && recdata)) return;

        //create form, fieldset and input elements according to record structure
        var record = recdata.getFirstRecord();
        if(!record) return;

        var rectypes = recdata.getStructures();

        rectypeID = recdata.fld(record, 'rec_RecTypeID');
        if(!rectypes || rectypes.length==0){
            rectypes = top.HEURIST.rectypes;
        }

        recID = recdata.fld(record, 'rec_ID');

        var rfrs = rectypes.typedefs[rectypeID].dtFields;
        var fi = rectypes.typedefs.dtFieldNamesToIndex;

        //header: rectype and title
        var $header = $('<div>')
                .css({'padding':'0.4em', 'border-bottom':'solid 1px #6A7C99'})
                //.addClass('ui-widget-header ui-corner-all')
                .appendTo($container);

        $('<h2>' + recdata.fld(record, 'rec_Title') + '</h2>')
                .appendTo($header);

       // control buttons - save and cancel         
        var btn_div = $('<div>')
            .css({position:'absolute', right:'0.4em', top:'0.4em'})
            .appendTo($header);
                
        $('<button>', {text:top.HR('Save')})
                .button().on("click", function(event){ _save(); } )
                .appendTo(btn_div);
        $('<button>', {text:top.HR('Cancel')})
                .button().on("click", function(event){ if(_isdialog) $container.dialog( "close" ); } )
                .appendTo(btn_div);

        $('<div>')
            .css('display','inline-block')
            .append( $('<img>',{
                    src:  top.HAPI.basePath+'assets/16x16.gif',
                    title: '@todo rectypeTitle'.htmlEscape()
                })
                .css({'background-image':'url('+ top.HAPI.iconBaseURL + rectypeID + '.png)','margin-right':'0.4em'}))
            .append('<span>'+(rectypes ?rectypes.names[rectypeID]: 'rectypes not defined')+'</span>')
            .appendTo($header);


        // create input controls - see editing_input.js
        _inputs = [];

        var order = rectypes.dtDisplayOrder[rectypeID];
        if(order){

            // main fields
            var i, l = order.length;

            var $fieldset = $("<fieldset>").css('font-size','0.9em').appendTo($container);

            for (i = 0; i < l; ++i) {
                var dtID = order[i];
                if (values=='' ||
                    rfrs[dtID][fi['rst_RequirementType']] == 'forbidden' ||
                   (top.HAPI.has_access(  recdata.fld(record, 'rec_OwnerUGrpID') )<0 &&
                    rfrs[dtID][fi['rst_NonOwnerVisibility']] == 'hidden' )) //@todo: server not return hidden details for non-owner
                {
                        continue;
                }

                var values = recdata.fld(record, dtID);

                /* readonly stuff
                if( (rfrs[dtID][fi['dty_Type']])=="separator" || !values) continue;
                var isempty = true;
                $.each(values, function(idx,value){
                        if(!top.HEURIST.util.isempty(value)){ isempty=false; return false; }
                } );
                if(isempty) continue;
                */
                if ( (rfrs[dtID][fi['dty_Type']])=="separator" ){

                     $( "<h3>")
                        .addClass('separator')
                        .html(rfrs[dtID][fi['rst_DisplayName']])
                        .appendTo( $fieldset );
                    continue;
                }


                var inpt = $("<div>").editing_input(
                          {
                            recID: recID,
                            rectypeID: rectypeID,
                            dtID: dtID,
                            rectypes: rectypes,
                            values: values,
                            readonly: false
                          });
                          
                          inpt.appendTo($fieldset);

                _inputs.push(inpt);

            }
        }//order


        if (_isdialog) {

                    $container.dialog({
                        autoOpen: true,
                        height: 640,
                        width: 740,
                        modal: true,
                        resizable: true,
                        draggable: true,
                        title: top.HR(_isaddnewrecord?"Add new record":"Edit record"),
                        resizeStop: function( event, ui ) {
                            $container.css('width','100%');
                        }                        
                    });

        }

    }

    /**
    * @todo - verify the required fields
    * 
    * @returns {Boolean}
    */
    function _verify(){
        return true;
    }

    /**
    * call RecordMgr.save method
    * 
    * @returns {Boolean}
    */
    function _save(){

//            array_push($details["t:".$key], $value);

        var ele, idx, details = {};
        for (idx in _inputs) {
            ele = $(_inputs[idx]);
            var vals = ele.editing_input('getValues');
            //var vals = ele.list("getValues"); 
            //var vals = ele.data("editing_input").getValues();
            if(vals && vals.length>0){
                details[ "t:"+ele.editing_input('option', 'dtID') ] = vals;
            }
        }

            var request = {ID: recID, RecTypeID: rectypeID, 'details': details};


            top.HAPI.RecordMgr.save( request,
                function(response){
                    if(response.status == top.HAPI.ResponseStatus.OK){

                        alert('Record saved');

                    }else{

                        top.HEURIST.util.showMsgErr(response);
                    }
                }

            );

        return true;
    }


    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        /**
        * _container - element, if null will be opened in popup dialog
        */
        add: function(_recordtype, _container){
            _add(_recordtype, _container);
        },

        /**
        * _recdata - record ID  or record data
        * _container - element, if null will be opened in popup dialog
        */
        edit:function(_recdata, _container){
            _edit(_recdata, _container);
        },

        verify: function(){
            _verify();
        },

        save: function(){
            _save();
        }

    }

    _init();
    return that;  //returns object
}
