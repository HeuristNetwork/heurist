/**
* Activate rich text or code editor for given input
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
      
class EditorCodeMirror {
  
  //html textarea that will be substituted with editor
  input;  
  
  //parent div
  inputdiv;

  //element for codeEditor  
  editorContainer = null;
  
  codeEditor = null;
  
  //
  //
  //
  constructor( _input_element ) { 
      
      this.input = _input_element;
      
      //add hidden textarea element
      this.inputdiv = this.input.parent('.input-div');
  }
  
  //
  // private method to load codeMirror code 
  //
  #getCodeMirror()
  {  
        var path = window.hWin.HAPI4.baseURL + 'external/codemirror-5.61.0/';
        var scripts = [ //'lib/codemirror.css', included in index.php
                        'lib/codemirror.js',
                        'lib/util/formatting.js',
                        'mode/xml/xml.js',
                        'mode/htmlmixed/htmlmixed.js'
                        ];
        var  that = this;
        $.getMultiScripts2(scripts, path)
        .then(function() {  //OK! widget script js has been loaded
            that.showEditor();
        }).catch(function(error) {
            window.hWin.HEURIST4.msg.showMsg_ScriptFail();
        });
                
  }

  //                                                                             
  //
  //
  hideEditor(){
     if(this.editorContainer) {
        this.editorContainer.hide();
     }
  }
  
  //
  //
  //
  showEditor(){

      if(typeof CodeMirror !== 'function'){

            this.#getCodeMirror();
            return;
      }

      var that = this;
      
      if(this.editorContainer==null){
          let iwidth = $(this.input).width();
          if(iwidth<300) iwidth = 300;
          
          var editor_id = $(this.input).attr('id')+'_codemirror';
          this.editorContainer = $( "<div>")
          .attr("id", editor_id)
          .css({'overflow':'auto',resize:'both',width:iwidth})
          .insertAfter(this.input) ;
          this.editorContainer.hide();
      }


      if(this.codeEditor==null){

          this.codeEditor = CodeMirror(this.editorContainer[0], {
              mode           : "htmlmixed",
              tabSize        : 2,
              indentUnit     : 2,
              indentWithTabs : false,
              lineNumbers    : false,
              matchBrackets  : true,
              smartIndent    : true
              /*extraKeys: {
              "Enter": function(e){
              insertAtCursor(null, "");
              }
              },*/
          });

          this.codeEditor.on('change', function(instance){
                that.input.val(instance.getValue());
                that.input.trigger('change');
              });
      }

      //autoformat
      setTimeout(function(){
          if(typeof that.codeEditor.autoFormatRange === 'function'){
                var totalLines = that.codeEditor.lineCount();  
                that.codeEditor.autoFormatRange({line:0, ch:0}, {line:totalLines});                    
          }
          that.codeEditor.scrollTo(0,0);
          that.codeEditor.setCursor(0,0); //clear selection

          that.codeEditor.focus();
          },500);


      this.input.hide();

      var btn_switcher = this.inputdiv.find('.editor_switcher')
      if(btn_switcher.length>0){
          btn_switcher.find('span').css('text-decoration', '');
          btn_switcher.find('span:contains("codeeditor")').css('text-decoration', 'underline');
      }

      this.editorContainer.css({display:'inline-block'});
      if($(this.input).val()!=this.codeEditor.getValue()){
        this.codeEditor.setValue($(this.input).val());    
      }
  }  

  
}