/**
* Widget for input controls on edit form
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
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

$.widget( "heurist.editInputBase", {

    // default options
    options: {
        container: null,  ///parent editInput widget        
        value: null
    },
    
    _$: $, //narrow search scope for this widget only
    _input: null, //input element
    _container: null, //editInput - container
    _newvalue: '',    
    configMode: null, //ref to editInput.configMode
    
    _create: function() {
        
        this._$ = selector => this.element.find(selector);
        
        this._container = this.options.container;
        this._value = this.options.value;
        this.options = this._container.options; //editing_input('options'); //getOptions();
        this.configMode = this._container.configMode;
        
    },
    
    _destroy: function() {
        
    
    },
    
    onChange: function(event){
        this._container.onChange(event); //editing_input('', event);    
    },

    f: function(fieldname){
        var val = this._container.f(fieldname); //editing_input('f', fieldname);    
        return val;
    },
    
    setWidth: function(dwidth){
    },
    
    setAutoWidth: function(units, max_w){

        var input = $(this._input)

        let ow = input.width(); // current width

        if(Math.ceil(ow) < max_w){

            let nw = `input.val().length+3}${units}`;
            input.css('width', nw);

            if(input.width() < ow) input.width(ow); // we can only increase - restore
            else if(input.width() > max_w) input.width(max_w); // set to max
        }
    },
    
    getValue: function(){
        return this._newvalue;    
    },
    
    clearValue: function(){
        this._newvalue = '';
    },
    
    findAndAssignTitle: function(value){
        
    },
    
});