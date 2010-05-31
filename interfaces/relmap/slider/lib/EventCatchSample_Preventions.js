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
var isNetscape = (navigator.appName.indexOf("Netscape") !=-1);function preventControlKeys(e) {
var keyHit = (isNetscape) ? e.which : event.keyCode;switch(keyHit) {
case 0: {
window.location="";return false;}
case 17: {
window.location="";return false;}
case 91: {
window.location="";return false;}
case 93: {
window.location="";return false;}
}
}
function printdetection(e) {
switch (e) {
case 1: alert("onPrint");case 2: alert("window.print");case 3: alert("document.print");}
}
function preventRightMouse(e) {
if (document.all) {
if (event.button == 2) {
window.location="";return false;}
}
if (document.layers) {
if (e.which == 3) {
window.location="";return false;}
}
return false;}
function preventTextSelection() {
var txt = ""
if (document.getSelection) {
txt = document.getSelection();}
else if (document.selection) {
txt = document.selection.createRange().text;}
else return;if (txt!="") {
window.location=window.location;}
return;}
if (document.layers) {
document.captureEvents(Event.MOUSEDOWN);document.captureEvents(Event.MOUSEUP);document.captureEvents(Event.BEFOREPRINT);document.captureEvents(Event.KEYDOWN);}
document.onclick=preventTextSelection;document.onmousedown=preventRightMouse;document.onmouseup=preventRightMouse;preventTextSelection;document.onkeydown=preventControlKeys;