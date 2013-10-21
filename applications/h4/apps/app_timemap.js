$.widget( "heurist.app_timemap", {

  // default options
  options: {
    recordset: null
  },

  recordset_changed: true,

  // the constructor
  _create: function() {

    var that = this;

    this.element.hide();

    this.framecontent = $(document.createElement('div'))
             .addClass('frame_container')
             .appendTo( this.element );

    this.mapframe = $( "<iframe>" )
        .attr('id', 'map-frame')
        //.attr('src', 'php/mapping.php?db='+top.HAPI.database)
        .appendTo( this.framecontent );


    $(this.document).on(top.HAPI.Event.LOGIN+' '+top.HAPI.Event.LOGOUT+' '+top.HAPI.Event.ON_REC_SEARCHRESULT, function(e, data) {

        if(e.type == top.HAPI.Event.LOGOUT)
        {
            if(that.options.recordset != null){
                that.recordset_changed = true;
                that.option("recordset", null);
            }

        }else if(e.type == top.HAPI.Event.ON_REC_SEARCHRESULT)
        {
            that.recordset_changed = true;
            that.option("recordset", data); //hRecordSet
            that._refresh();
        }
    });

   // (this.mapframe).load(that._initmap);
   this._on( this.mapframe, {
            load: this._initmap
        }
   );


    this.element.on("myOnShowEvent", function(event){
        if( event.target.id == that.element.attr('id')){
            that._refresh();
        }
    });

    //this._refresh();

  }, //end _create

  /* private function */
  _refresh: function(){

      if ( this.element.is(':visible') && this.recordset_changed) {

          if( this.mapframe.attr('src') ){
               this._initmap()
          }else {
               (this.mapframe).attr('src', 'php/mapping.php?db='+top.HAPI.database);
          }
      }

  },

  _initmap: function(){
          var mapping = document.getElementById('map-frame').contentWindow.mapping;
          //var mapping = $(this.mapframe).contents().mapping;

          if(mapping){

              if(this.options.recordset == null){
                    mapping.load();
              }else{
                    mapping.load(this.options.recordset.toTimemap());
              }
              this.recordset_changed = false;

          }
  },



  // events bound via _on are removed automatically
  // revert other modifications here
  _destroy: function() {

    this.element.off("myOnShowEvent");
    $(this.document).off(top.HAPI.Event.LOGIN+' '+top.HAPI.Event.LOGOUT+' '+top.HAPI.Event.ON_REC_SEARCHRESULT);

    // remove generated elements
    this.mapframe.remove();
    this.framecontent.remove();

  }

});
