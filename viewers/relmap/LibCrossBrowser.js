/********************************************************************************************
* BlueShoes Framework; This file is part of the php application framework.
* NOTE: This code is stripped (obfuscated). To get the clean documented code goto 
*       www.blueshoes.org and register for the free open source *DEVELOPER* version or 
*       buy the commercial version.
*       
*       In case you've already got the developer version, then this is one of the few 
*       packages/classes that is only available to *PAYING* customers.
*       To get it go to www.blueshoes.org and buy a commercial version.
* 
* @copyright www.blueshoes.org
* @author    sam blum <sam-at-blueshoes>
* @author    Andrej Arn <andrej-at-blueshoes>
*/
LCB_version = 2.8;_mac=navigator.userAgent.indexOf('Mac')!=-1;_ie512=navigator.userAgent.indexOf('MSIE 5.12')!=-1;_dom = document.all?(document.getElementById?2:1)
:(document.getElementById?4:(document.layers?3:0));_createLayerNo = 0;function initCrossBrowserLib(){
_mac=navigator.userAgent.indexOf('Mac')!=-1;_ie512=navigator.userAgent.indexOf('MSIE 5.12')!=-1;_dom=document.all?(document.getElementById?2:1)
:(document.getElementById?4:(document.layers?3:0));_createLayerNo=0;}
initCrossBrowserLib();function getWindowWidth (){
if(_dom==4 || _dom==3) return window.innerWidth;if(_dom==2 || _dom==1) return document.body.clientWidth;return 0;}
function getWindowHeight(){
if(_dom==4 || _dom==3) return window.innerHeight;if(_dom==2 || _dom==1) return document.body.clientHeight;return 0;}
function getWinXOffset(){
if(_dom==4)            return window.scrollX;if(_dom==2 || _dom==1) return document.body.scrollLeft;if(_dom==3)            return window.pageXOffset;return 0;}
function getWinYOffset(){
if(_dom==4)            return window.scrollY;if(_dom==2 || _dom==1) return document.body.scrollTop;if(_dom==3)            return window.pageYOffset;return 0;}
function getDivFromName(nm){
if(_dom==4 || _dom==2) return document.getElementById(nm);if(_dom==1)            return document.all(nm);if(_dom==3){
var s='';for(var i=1; i<arguments.length; i++) s+='document.layers.'+arguments[i]+'.';return eval(s+'document.layers.'+nm);}
return null;}
function getDivName(div){
if(_dom==4 || _dom==2 || _dom==1) return div.id;if(_dom==3)                       return div.name;return '';}
function createLayer(left,top,width,height,parentDiv){
var s='';if(arguments.length>5){
for(var i=5; i<arguments.length; i++) s+=arguments[i];}
if(_dom==4){
var divName= '_js_layer_'+_createLayerNo; _createLayerNo++;var pDiv   =parentDiv?parentDiv:document.body;var div    =document.createElement('DIV');div.id=divName;div.setAttribute('style',
'position:absolute;left:'+left+';top:'+top
+(width >0?(';width:' +width ):'')
+(height>0?(';height:'+height):'')
+';visibility:hidden');var range=document.createRange();range.selectNodeContents(div);range.collapse(true);var cf=range.createContextualFragment(s);div.appendChild(cf);pDiv.appendChild(div);return div;}
if(_dom==2 || _dom==1){
var adj    =(_mac&&!_ie512)?' ':'';var divName= '_js_layer_'+_createLayerNo; _createLayerNo++;var ha     =(height>0)?(';height:'+height):'';var pDiv   =parentDiv?parentDiv:document.body;pDiv.insertAdjacentHTML('BeforeEnd',
'<div id="'+divName
+'" style="position:absolute;left:'+left+';top:'+top
+(width >0?(';width:' +width ):';width:1')
+(height>0?(';height:'+height):'')
+';visibility:hidden;">'+s+'<\/div>'+adj);return document.all(divName);}
if(_dom==3){
var div=parentDiv?(new Layer(width,parentDiv)):(new Layer(width));if(height>0) div.resizeTo(width,height);div.moveTo(left,top);if(s!=''){
div.document.open('text/html','replace');div.document.write(s);div.document.close();}
return div;}
return null;}
function createExLayer(url,left,top,width,height,parentDiv){
if(_dom==4){
var divName= '_js_layer_'+_createLayerNo; _createLayerNo++;var pDiv   =parentDiv?parentDiv:document.body;var div    =document.createElement('IFRAME');div.id=divName;div.name=divName;div.setAttribute('style',
'position:absolute;left:'+left+';top:'+top
+';width:'+width+(height>0?(';height:'+height):'')
+';visibility:hidden');div.setAttribute('src',url);div.setAttribute('frameborder',0);div.setAttribute('scrolling','no');pDiv.appendChild(div);return div;}
if(_dom==2 || _dom==1){
var adj=(_mac&&_ie512)?' ':'';var bd, divName='_js_layer_'+_createLayerNo;_createLayerNo++;var ha=(height>0)?(';height:'+height):'';if(arguments.length>5 && parentDiv)
bd=parentDiv;else bd=document.body;bd.insertAdjacentHTML('BeforeEnd',
'<div id="'+divName
+'" style="position:absolute;left:'+left+';top:'+top
+';width:'+width+ha+';visibility:hidden;">'
+'<iframe src="'+url+'" name="'+divName+'_if" '
+'width='+width+' height='+height
+'marginwidth=0 marginheight=0 '
+'scrolling="no" frameborder="no">'
+'<\/iframe>'
+'<\/div>'+adj);return document.all(divName);}
if(_dom==3){
var div=parentDiv?(new Layer(width,parentDiv)):(new Layer(width));if(height>0) div.resizeTo(width,height);div.moveTo(left,top);div.load(url,width);return div;}
return null;}
function getDivImage(div,imgName){
if(_dom==4)            return document.images[imgName];if(_dom==2 || _dom==1) return document.images(imgName);if(_dom==3)            return div.document.images[imgName];return null;}
function getDivForm(div,frmName){
if(_dom==4)            return document.forms[frmName];if(_dom==2 || _dom==1) return document.forms(frmName);if(_dom==3)            return div.document.forms[frmName];return null;}
function initDivPos(div){
if(_dom==4){
div.style.left=div.offsetLeft+'px';div.style.top =div.offsetTop +'px';}
else if(_dom==2 || _dom==1){
div.style.pixelLeft=div.offsetLeft;div.style.pixelTop =div.offsetTop;}
return div;}
function getDivLeft(div){
if(_dom==4 || _dom==2) return div.offsetLeft;if(_dom==1)            return div.style.pixelLeft;if(_dom==3)            return div.left;return 0;}
function getDivTop(div){
if(_dom==4 || _dom==2) return div.offsetTop;if(_dom==1)            return div.style.pixelTop;if(_dom==3)            return div.top;return 0;}
function moveDivTo(div,left,top){
if(_dom==4){
div.style.left=left+'px';div.style.top =top +'px';return;}
if(_dom==2 || _dom==1){
div.style.pixelLeft=left;div.style.pixelTop =top;return;}
if(_dom==3){
div.moveTo(left,top);return;}
}
function moveDivBy(div,left,top){
if(_dom==4){
div.style.left=div.offsetLeft+left;div.style.top =div.offsetTop +top;return;}
if(_dom==2){
div.style.pixelLeft=div.offsetLeft+left;div.style.pixelTop =div.offsetTop +top;return;}
if(_dom==1){
div.style.pixelLeft+=left;div.style.pixelTop +=top;return;}
if(_dom==3){
div.moveBy(left,top);return;}
}
function scrollExlHItTo(exdiv,x){
if(_dom==4){
frames[exdiv.id].scrollTo(x,frames[exdiv.id].scrollY);return;}
if(_dom==2 || _dom==1){
frames(exdiv.id+'_if').scrollTo(x,frames(exdiv.id+'_if').document.body.scrollTop);return;}
if(_dom==3){
var dx=x-exdiv.clip.left, ch=exdiv.clip.width;exdiv.left-=dx;exdiv.clip.left=x; exdiv.clip.width=ch;return;}
return;}
function scrollExlVItTo(exdiv,y){
if(_dom==4){
frames[exdiv.id].scrollTo(frames[exdiv.id].scrollX,y);return;}
if(_dom==2 || _dom==1){
frames(exdiv.id+'_if').scrollTo(frames(exdiv.id+'_if').document.body.scrollLeft,y);return;}
if(_dom==3){
var dy=y-exdiv.clip.top, ch=exdiv.clip.height;exdiv.top-=dy;exdiv.clip.top=y; exdiv.clip.height=ch;return;}
return;}
function initDivSize(div){
if(_dom==4){
div.style.width =div.offsetWidth +'px';div.style.height=div.offsetHeight+'px';}
else if(_dom==2 || _dom==1){
div.style.pixelWidth =div.offsetWidth;div.style.pixelHeight=div.offsetHeight;}
return div;}
function getDivWidth (div){
if(_dom==4 || _dom==2) return div.offsetWidth;if(_dom==1)            return div.style.pixelWidth;if(_dom==3)            return div.clip.width;return 0;}
function getDivHeight(div){
if(_dom==4 || _dom==2) return div.offsetHeight;if(_dom==1)            return div.style.pixelHeight;if(_dom==3)            return div.clip.height;return 0;}
function resizeDivTo(div,width,height){
if(_dom==4){
div.style.width =width +'px';div.style.height=height+'px';return;}
if(_dom==2 || _dom==1){
div.style.pixelWidth =width;div.style.pixelHeight=height;return;}
if(_dom==3){
div.resizeTo(width,height);return;}
}
function resizeDivBy(div,width,height){
if(_dom==4){
div.style.width =(div.offsetWidth +width )+'px';div.style.height=(div.offsetHeight+height)+'px';return;}
if(_dom==2){
div.style.pixelWidth =div.offsetWidth +width;div.style.pixelHeight=div.offsetHeight+height;return;}
if(_dom==1){
div.style.pixelWidth +=width;div.style.pixelHeight+=height;return;}
if(_dom==3){
div.resizeBy(width,height);return;}
}
function getExlWidth (exdiv){
if(_dom==4)
return exdiv.contentDocument.body.offsetWidth;if(_dom==2 || _dom==1)
return _mac?frames(exdiv.id+'_if').document.body.offsetWidth
:frames(exdiv.id+'_if').document.body.scrollWidth;if(_dom==3)
return exdiv.document.width;return 0;}
function getExlHeight(exdiv){
if(_dom==4)
return exdiv.contentDocument.body.offsetHeight;if(_dom==2 || _dom==1)
return _mac?frames(exdiv.id+'_if').document.body.offsetHeight
:frames(exdiv.id+'_if').document.body.scrollHeight;if(_dom==3)
return exdiv.document.height;return 0;}
function setDivVisibility(div,visible){
if(_dom==4 || _dom==2 || _dom==1){
div.style.visibility=(visible)?'inherit':'hidden';return;}
if(_dom==3){
div.visibility      =(visible)?'inherit':'hide';return;}
}
function setDivVisibilities(divs,visible){
if(_dom==4 || _dom==2 || _dom==1){
for(var i=0; i<divs.length; i++)
divs[i].style.visibility=(visible)?'inherit':'hidden';}
if(_dom==3){
for(var i=0; i<divs.length; i++)
divs[i].visibility      =(visible)?'inherit':'hide';}
return divs;}
function setDivClip(div,top,right,bottom,left){
if(_dom==4 || _dom==2 || _dom==1){
div.style.clip='rect('+top+'px '+right+'px '+bottom+'px '+left+'px)';return;}
if(_dom==3){
div.clip.top   =top;   div.clip.right=right;div.clip.bottom=bottom;div.clip.left =left;return;}
}
function writeDivHTML(div,op,cl){
var s='';for(var i=3; i<arguments.length; i++) s+=arguments[i];if(_dom==4){
if(op){ while(div.hasChildNodes()) div.removeChild(div.lastChild); }
var range=document.createRange();range.selectNodeContents(div);range.collapse(true);var cf=range.createContextualFragment(s);div.appendChild(cf);return;}
if(_dom==2 || _dom==1){
if(op)   div.innerHTML='';if(_mac&&!_ie512) div.innerHTML+=s;else     div.insertAdjacentHTML('BeforeEnd',s);return;}
if(_dom==3){
if(op) div.document.open('text/html','replace');div.document.write(s);if(cl) div.document.close();return;}
}
function setDivBackgroundColor(div,color){
if(color==null) color='transparent';if(_dom==3) div.bgColor=color;else        div.style.backgroundColor=color;}
function setDivBackgroundImage(div,url){
if(_dom==3) div.background.src=url?url:null;else        div.style.backgroundImage=url?('url('+url+')'):'none';}
function setDivZIndex(div,order){
if(_dom==4 || _dom==2 || _dom==1){
div.style.zIndex=order;return;}
if(_dom==3){
div.zIndex      =order;return;}
}
function setDivStyleAttribute(div,nm,value){
if(_dom!=0 && _dom!=3) eval('div.style.'+nm+'=value');return div;}
function changeExlURL(exdiv,url){
if(_dom==4){
exdiv.setAttribute('src',url);return;}
if(_dom==2 || _dom==1){
    frames(exdiv.id+'_if').location.replace(url);
return;}
if(_dom==3){
exdiv.load(url,exdiv.clip.width);return;}
return;}
function getLeftFromEvent(e){
if(_dom==4)          return e.clientX+window.scrollX;if(_dom==2||_dom==1) return document.body.scrollLeft+window.event.clientX;if(_dom==3)          return e.pageX;return 0;}
function getTopFromEvent(e){
if(_dom==4)          return e.clientY+window.scrollY;if(_dom==2||_dom==1) return document.body.scrollTop+window.event.clientY;if(_dom==3)          return e.pageY;return 0;}
