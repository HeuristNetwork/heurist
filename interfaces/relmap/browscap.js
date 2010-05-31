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
var agt=navigator.userAgent.toLowerCase();var appVer = navigator.appVersion.toLowerCase();var is_minor = parseFloat(appVer);var is_major = parseInt(is_minor);var is_opera = (agt.indexOf("opera") != -1);var is_opera2 = (agt.indexOf("opera 2") != -1 || agt.indexOf("opera/2") != -1);var is_opera3 = (agt.indexOf("opera 3") != -1 || agt.indexOf("opera/3") != -1);var is_opera4 = (agt.indexOf("opera 4") != -1 || agt.indexOf("opera/4") != -1);var is_opera5 = (agt.indexOf("opera 5") != -1 || agt.indexOf("opera/5") != -1);var is_opera6 = (agt.indexOf("opera 6") != -1 || agt.indexOf("opera/6") != -1);var is_opera7 = (agt.indexOf("opera 7") != -1 || agt.indexOf("opera/7") != -1);var is_opera5up = (is_opera && !is_opera2 && !is_opera3 && !is_opera4);var is_opera6up = (is_opera && !is_opera2 && !is_opera3 && !is_opera4 && !is_opera5);var is_opera7up = (is_opera && !is_opera2 && !is_opera3 && !is_opera4 && !is_opera5 && !is_opera6);var iePos  = appVer.indexOf('msie');if (iePos !=-1) {
is_minor = parseFloat(appVer.substring(iePos+5,appVer.indexOf(';',iePos)))
is_major = parseInt(is_minor);}
var is_konq = false;var kqPos   = agt.indexOf('konqueror');if (kqPos !=-1) {
is_konq  = true;is_minor = parseFloat(agt.substring(kqPos+10,agt.indexOf(';',kqPos)));is_major = parseInt(is_minor);}
var is_getElementById   = (document.getElementById) ? "true" : "false";var is_getElementsByTagName = (document.getElementsByTagName) ? "true" : "false";var is_documentElement = (document.documentElement) ? "true" : "false";var is_safari = ((agt.indexOf('safari')!=-1)&&(agt.indexOf('mac')!=-1))?true:false;var is_khtml  = (is_safari || is_konq);var is_gecko = ((!is_khtml)&&(navigator.product)&&(navigator.product.toLowerCase()=="gecko"))?true:false;var is_gver  = 0;if (is_gecko) is_gver=navigator.productSub;var is_moz   = ((agt.indexOf('mozilla/5')!=-1) && (agt.indexOf('spoofer')==-1) &&
(agt.indexOf('compatible')==-1) && (agt.indexOf('opera')==-1)  &&
(agt.indexOf('webtv')==-1) && (agt.indexOf('hotjava')==-1)     &&
(is_gecko) &&
((navigator.vendor=="")||(navigator.vendor=="Mozilla")));var is_fb = ((agt.indexOf('mozilla/5')!=-1) && (agt.indexOf('spoofer')==-1) &&
(agt.indexOf('compatible')==-1) && (agt.indexOf('opera')==-1)  &&
(agt.indexOf('webtv')==-1) && (agt.indexOf('hotjava')==-1)     &&
(is_gecko) && ((navigator.vendor=="Firebird") || (navigator.vendor=="Firefox")));if ((is_moz)||(is_fb)) {
var is_moz_ver = (navigator.vendorSub)?navigator.vendorSub:0;if(!(is_moz_ver)) {
is_moz_ver = agt.indexOf('rv:');is_moz_ver = agt.substring(is_moz_ver+3);is_paren   = is_moz_ver.indexOf(')');is_moz_ver = is_moz_ver.substring(0,is_paren);}
is_minor = is_moz_ver;is_major = parseInt(is_moz_ver);}
var is_fb_ver = is_moz_ver;var is_nav  = ((agt.indexOf('mozilla')!=-1) && (agt.indexOf('spoofer')==-1)
&& (agt.indexOf('compatible') == -1) && (agt.indexOf('opera')==-1)
&& (agt.indexOf('webtv')==-1) && (agt.indexOf('hotjava')==-1)
&& (!is_khtml) && (!(is_moz)) &&(!is_fb));if ((navigator.vendor)&&
((navigator.vendor=="Netscape6")||(navigator.vendor=="Netscape"))&&
(is_nav)) {
is_major = parseInt(navigator.vendorSub);is_minor = parseFloat(navigator.vendorSub);}
var is_nav2 = (is_nav && (is_major == 2));var is_nav3 = (is_nav && (is_major == 3));var is_nav4 = (is_nav && (is_major == 4));var is_nav4up = (is_nav && is_minor >= 4);var is_navonly      = (is_nav && ((agt.indexOf(";nav") != -1) ||
(agt.indexOf("; nav") != -1)) );var is_nav6   = (is_nav && is_major==6);var is_nav6up = (is_nav && is_minor >= 6)
var is_nav5   = (is_nav && is_major == 5 && !is_nav6);var is_nav5up = (is_nav && is_minor >= 5);var is_nav7   = (is_nav && is_major == 7);var is_nav7up = (is_nav && is_minor >= 7);var is_ie   = ((iePos!=-1) && (!is_opera) && (!is_khtml));var is_ie3  = (is_ie && (is_major < 4));var is_ie4   = (is_ie && is_major == 4);var is_ie4up = (is_ie && is_minor >= 4);var is_ie5   = (is_ie && is_major == 5);var is_ie5up = (is_ie && is_minor >= 5);var is_ie5_5  = (is_ie && (agt.indexOf("msie 5.5") !=-1));var is_ie5_5up =(is_ie && is_minor >= 5.5);var is_ie6   = (is_ie && is_major == 6);var is_ie6up = (is_ie && is_minor >= 6);var is_aol   = (agt.indexOf("aol") != -1);var is_aol3  = (is_aol && is_ie3);var is_aol4  = (is_aol && is_ie4);var is_aol5  = (agt.indexOf("aol 5") != -1);var is_aol6  = (agt.indexOf("aol 6") != -1);var is_aol7  = ((agt.indexOf("aol 7")!=-1) || (agt.indexOf("aol7")!=-1));var is_aol8  = ((agt.indexOf("aol 8")!=-1) || (agt.indexOf("aol8")!=-1));var is_webtv = (agt.indexOf("webtv") != -1);var is_TVNavigator = ((agt.indexOf("navio") != -1) || (agt.indexOf("navio_aoltv") != -1));var is_AOLTV = is_TVNavigator;var is_hotjava = (agt.indexOf("hotjava") != -1);var is_hotjava3 = (is_hotjava && (is_major == 3));var is_hotjava3up = (is_hotjava && (is_major >= 3));var is_js;if (is_nav2 || is_ie3) is_js = 1.0;else if (is_nav3) is_js = 1.1;else if ((is_opera5)||(is_opera6)) is_js = 1.3;else if (is_opera7up) is_js = 1.5;else if (is_khtml) is_js = 1.5;else if (is_opera) is_js = 1.1;else if ((is_nav4 && (is_minor <= 4.05)) || is_ie4) is_js = 1.2;else if ((is_nav4 && (is_minor > 4.05)) || is_ie5) is_js = 1.3;else if (is_nav5 && !(is_nav6)) is_js = 1.4;else if (is_hotjava3up) is_js = 1.4;else if (is_nav6up) is_js = 1.5;else if (is_nav && (is_major > 5)) is_js = 1.4;else if (is_ie && (is_major > 5)) is_js = 1.3;else if (is_moz) is_js = 1.5;else if (is_fb) is_js = 1.5;else is_js = 0.0;if ((agt.indexOf("mac")!=-1) && is_ie5up) is_js = 1.4;if (is_nav6up) {
is_minor = navigator.vendorSub;}
var is_win   = ( (agt.indexOf("win")!=-1) || (agt.indexOf("16bit")!=-1) );var is_win95 = ((agt.indexOf("win95")!=-1) || (agt.indexOf("windows 95")!=-1));var is_win16 = ((agt.indexOf("win16")!=-1) ||
(agt.indexOf("16bit")!=-1) || (agt.indexOf("windows 3.1")!=-1) ||
(agt.indexOf("windows 16-bit")!=-1) );var is_win31 = ((agt.indexOf("windows 3.1")!=-1) || (agt.indexOf("win16")!=-1) ||
(agt.indexOf("windows 16-bit")!=-1));var is_winme = ((agt.indexOf("win 9x 4.90")!=-1));var is_win2k = ((agt.indexOf("windows nt 5.0")!=-1) || (agt.indexOf("windows 2000")!=-1));var is_winxp = ((agt.indexOf("windows nt 5.1")!=-1) || (agt.indexOf("windows xp")!=-1));var is_win98 = ((agt.indexOf("win98")!=-1) || (agt.indexOf("windows 98")!=-1));var is_winnt = ((agt.indexOf("winnt")!=-1) || (agt.indexOf("windows nt")!=-1));var is_win32 = (is_win95 || is_winnt || is_win98 ||
((is_major >= 4) && (navigator.platform == "Win32")) ||
(agt.indexOf("win32")!=-1) || (agt.indexOf("32bit")!=-1));var is_os2   = ((agt.indexOf("os/2")!=-1) ||
(navigator.appVersion.indexOf("OS/2")!=-1) ||
(agt.indexOf("ibm-webexplorer")!=-1));var is_mac    = (agt.indexOf("mac")!=-1);if (is_mac) { is_win = !is_mac; }
var is_mac68k = (is_mac && ((agt.indexOf("68k")!=-1) ||
(agt.indexOf("68000")!=-1)));var is_macppc = (is_mac && ((agt.indexOf("ppc")!=-1) ||
(agt.indexOf("powerpc")!=-1)));var is_sun   = (agt.indexOf("sunos")!=-1);var is_sun4  = (agt.indexOf("sunos 4")!=-1);var is_sun5  = (agt.indexOf("sunos 5")!=-1);var is_suni86= (is_sun && (agt.indexOf("i86")!=-1));var is_irix  = (agt.indexOf("irix") !=-1);var is_irix5 = (agt.indexOf("irix 5") !=-1);var is_irix6 = ((agt.indexOf("irix 6") !=-1) || (agt.indexOf("irix6") !=-1));var is_hpux  = (agt.indexOf("hp-ux")!=-1);var is_hpux9 = (is_hpux && (agt.indexOf("09.")!=-1));var is_hpux10= (is_hpux && (agt.indexOf("10.")!=-1));var is_aix   = (agt.indexOf("aix") !=-1);var is_aix1  = (agt.indexOf("aix 1") !=-1);var is_aix2  = (agt.indexOf("aix 2") !=-1);var is_aix3  = (agt.indexOf("aix 3") !=-1);var is_aix4  = (agt.indexOf("aix 4") !=-1);var is_linux = (agt.indexOf("inux")!=-1);var is_sco   = (agt.indexOf("sco")!=-1) || (agt.indexOf("unix_sv")!=-1);var is_unixware = (agt.indexOf("unix_system_v")!=-1);var is_mpras    = (agt.indexOf("ncr")!=-1);var is_reliant  = (agt.indexOf("reliantunix")!=-1);var is_dec   = ((agt.indexOf("dec")!=-1) || (agt.indexOf("osf1")!=-1) ||
(agt.indexOf("dec_alpha")!=-1) || (agt.indexOf("alphaserver")!=-1) ||
(agt.indexOf("ultrix")!=-1) || (agt.indexOf("alphastation")!=-1));var is_sinix = (agt.indexOf("sinix")!=-1);var is_freebsd = (agt.indexOf("freebsd")!=-1);var is_bsd = (agt.indexOf("bsd")!=-1);var is_unix  = ((agt.indexOf("x11")!=-1) || is_sun || is_irix || is_hpux ||
is_sco ||is_unixware || is_mpras || is_reliant ||
is_dec || is_sinix || is_aix || is_linux || is_bsd || is_freebsd);var is_vms   = ((agt.indexOf("vax")!=-1) || (agt.indexOf("openvms")!=-1));var is_anchors = (document.anchors) ? "true":"false";var is_regexp = (window.RegExp) ? "true":"false";var is_option = (window.Option) ? "true":"false";var is_all = (document.all) ? "true":"false";document.cookie = "cookies=true";var is_cookie = (document.cookie) ? "true" : "false";var is_images = (document.images) ? "true":"false";var is_layers = (document.layers) ? "true":"false";var is_forms = (document.forms) ? "true" : "false";var is_links = (document.links) ? "true" : "false";var is_frames = (window.frames) ? "true" : "false";var is_screen = (window.screen) ? "true" : "false";var is_java = (navigator.javaEnabled());var is_Flash        = false;var is_FlashVersion = 0;if ((is_nav||is_opera||is_moz)||
(is_mac&&is_ie5up)) {
var plugin = (navigator.mimeTypes &&
navigator.mimeTypes["application/x-shockwave-flash"] &&
navigator.mimeTypes["application/x-shockwave-flash"].enabledPlugin) ?
navigator.mimeTypes["application/x-shockwave-flash"].enabledPlugin : 0;if (plugin) {
is_Flash = true;is_FlashVersion = parseInt(plugin.description.substring(plugin.description.indexOf(".")-1));}
}
if (is_win&&is_ie4up)
{
document.write(
'<scr' + 'ipt language=VBScript>' + '\n' +
'Dim hasPlayer, playerversion' + '\n' +
'hasPlayer = false' + '\n' +
'playerversion = 12' + '\n' +
'Do While playerversion > 0' + '\n' +
'On Error Resume Next' + '\n' +
'hasPlayer = (IsObject(CreateObject("ShockwaveFlash.ShockwaveFlash." & playerversion)))' + '\n' +
'If hasPlayer = true Then Exit Do' + '\n' +
'playerversion = playerversion - 1' + '\n' +
'Loop' + '\n' +
'is_FlashVersion = playerversion' + '\n' +
'is_Flash = hasPlayer' + '\n' +
'<\/sc' + 'ript>'
);}
