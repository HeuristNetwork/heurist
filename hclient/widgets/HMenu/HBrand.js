/**
* HBrand - shows one or several images(brands) with titles
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/
import '../HBase/HBaseWidget.js';

$.widget( 'heurist.HBrand', $.heurist.HBaseWidget, {

    // default options
    options: {
        resourcePath: 'hclient/widgets/HMenu/HBrand',
        resources:[], // url, img, img_small, title  - if it is not defined it uses content
        colCount: 8,
        imgHeight: 40
    },
    
    _needLoadContent: false,
    _needLoadCss: false,
    
    /**
     * Initializes UI controls and event listeners after content is loaded.
     */
    _initControls:function(){

        this._super();
        
        this.element.addClass('row'); //' row-cols-'+this.options.colCount
        
        if(this.options.resources?.length>0)
        {
            this.options.resources.forEach(function(res) {
                // flex-column
                let item = '<div class="col d-flex align-items-center">';
                
                let switcher1 = '';
                let switcher2 = '';
                if(res.img_small && if(res.img)){
                    switcher1 = 'd-block d-md-none';
                    switcher2 = 'd-none d-md-block';
                }
                if(res.img_small){
                    item += `<img src="${res.img_small}" height="${this.options.imgHeight}" class="me-2 ${switcher1}">`;
                }
                if(res.img){
                    item += `<img src="${res.img}" height="${this.options.imgHeight}" class="me-2 ${switcher2}">`;
                }
                if(res.title){
                    item += `<div class="h6 mb-0">${res.title}</div>`;
                }
                item += '</div>';
                
                let ele = $(item).appendTo(this.element);
                
                //this._on( ele, {click : this.menuActionHandler });
            
            });
        }
    },
   
});
