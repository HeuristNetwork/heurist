<?php
/**
* OutputSanitizer.php - Final processing of report output
*
* Port of ReportExecute::stripJavascriptAndSantize, handleJsAllowed,
* sanitizeHtml and saveOutputAsJavascript for the uses of the new API (preview,
* generated file, single-record render; no CMS css record, no widget test):
* - html/js output: database fonts and TinyMCE formats; JavaScript is kept only
*   when the database is authorised (js_in_database_authorised.txt), otherwise
*   HTMLPurifier removes it; a full page gets <html>/<body> and the media viewer
*   scripts when fancybox thumbnails are used;
* - links href="123" and href="123/name.tpl" become record links;
* - txt/csv/xml/json output: only the content of <body> is kept (the legacy
*   regular expression had an invalid modifier and did nothing);
* - js output: document.write('...').
*
* @project     Heurist academic knowledge management system
* @package     Reports\Smarty
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Reports\Smarty;

/** Sanitizes and completes report output. */
final class OutputSanitizer
{
    private const HEAD_END = '</head>';

    private ReportEnvironment $environment;

    /** @param ReportEnvironment $environment Installation values. */
    public function __construct(ReportEnvironment $environment)
    {
        $this->environment = $environment;
    }

    /**
     * @param string $output Template output.
     * @param string $mode html, js, txt, csv, xml, json.
     * @param bool $snippet Part of a page (no <html>/<head>), e.g. a record card.
     * @param bool $popupLinks Record links open in a Heurist dialog (preview and files).
     * @return string
     */
    public function process(string $output, string $mode, bool $snippet, bool $popupLinks = true): string
    {
        if($mode !== 'html' && $mode !== 'js'){
            return self::bodyContent($output);
        }
        $styles = '';
        if($this->environment->tinyMceStyles !== ''){
            $styles .= '<style> '.$this->environment->tinyMceStyles.' </style>';
        }
        if($this->environment->fontStyles !== ''){
            $styles .= '<style> '.$this->environment->fontStyles.' </style>';
        }

        $output = $this->environment->javaScriptAllowed
            ? $this->withScripts($output, $styles, $snippet)
            : $this->purify($output, $styles);

        // images of blocktext fields with relative URLs
        $db = $this->environment->databaseName;
        $output = str_replace(' src="./?db='.$db.'&', ' src="'.$this->environment->baseUrl.'?db='.$db.'&', $output);

        $onclick = $popupLinks ? 'onclick="'
            .'{try'
                .'{'
                    .'let event_target = event.target.getAttribute("target");'
                    .'let def_targets = ["_self","_blank","_parent","_top"];'
                    .'if(event_target && def_targets.indexOf(event_target) !== -1){ return true; }'
                    .'var h=window.hWin?window.hWin.HEURIST4:window.parent.hWin.HEURIST4;'
                    .'h.msg.showDialog(event.target.href,{title:\'.\',width: 600,height:500,modal:false});'
                    .'return false'
                .'}catch(e){'
                    .'return true'
                .'}'
            .'}" ' : '';
        $replaced = preg_replace_callback('/href=["|\']?(\d+\/.+\.tpl|\d+)["|\']?/', function($matches) use ($onclick){
            return $onclick.'href="'.$this->environment->recordLink($matches[1]).'"';
        }, $output);
        $output = $replaced === null ? $output : $replaced;

        if($mode === 'js'){
            $output = "document.write('".str_replace(array("\n", "\r", "'"), array('', '', '&#039;'), $output)."');";
        }
        return $output;
    }

    /**
     * Content of <body> (without the tag); the text unchanged when it has no body.
     *
     * @param string $output
     * @return string
     */
    public static function bodyContent(string $output): string
    {
        if(preg_match('/<body[^>]*>(.*)<\/body>/is', $output, $matches)){
            return $matches[1];
        }
        return $output;
    }

    /** Output of a database that may run JavaScript (ReportExecute::handleJsAllowed). */
    private function withScripts(string $output, string $styles, bool $snippet): string
    {
        $base = $this->environment->baseUrl;
        $db = $this->environment->databaseName;
        $script = '<script type="text/javascript" src="'.$base;
        $viewerInit = '$("body").mediaViewer({rec_Files:rec_Files, showLink:false, selector:".fancybox-thumb", '
            .'database:"'.$db.'", baseURL:"'.$base.'"});';
        $toolbarStyle = '<style>.fancybox-toolbar{visibility: visible !important; opacity: 1 !important;}</style>';
        $hasThumbs = strpos($output, 'fancybox-thumb') > 0;

        if(!$snippet){
            $head = $styles;
            $open = '';
            $close = '';
            if(strpos($output, '<html>') === false){
                $open = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
                $close = '</body></html>';
            }
            if($hasThumbs){
                $head .= $script.'external/jquery/jquery-3.7.1.js"></script>'
                    .$script.'external/jquery/jquery-ui.js"></script>'
                    .$script.'external/jquery.fancybox/jquery.fancybox.js"></script>'
                    .$script.'hclient/core/detectHeurist.js"></script>'
                    .$script.'hclient/widgets/viewers/mediaViewer.js"></script>'
                    .'<link rel="stylesheet" href="'.$base.'external/jquery.fancybox/jquery.fancybox.css" />'
                    .'<script>var rec_Files=[];$(document).ready(function() {'.$viewerInit.'});</script>'
                    .$toolbarStyle;
            }
            $output = str_replace('<body>', '<body class="smarty-report">', $open.$output.$close);
            if($head !== ''){
                $output = str_replace(self::HEAD_END, $head.self::HEAD_END, $output);
            }
            return $output;
        }

        $head = $styles;
        if($hasThumbs){
            $head = $script.'external/jquery/jquery-3.7.1.js"></script>'
                .$script.'external/jquery/jquery-ui.js"></script>'
                .$script.'external/jquery.fancybox/jquery.fancybox.js"></script>'
                .$script.'hclient/core/detectHeurist.js"></script>'
                .$script.'hclient/widgets/viewers/mediaViewer.js"></script>'
                .'<script>var rec_Files=[];</script>'
                .'<script>$(document).ready(function() {'
                .'document.getElementsByTagName("head")[0].insertAdjacentHTML("beforeend","<link rel=\"stylesheet\" href=\"'
                .$base.'external/jquery.fancybox/jquery.fancybox.css\" />");'
                .$viewerInit.'});</script>'
                .$toolbarStyle;
        }
        if($head !== ''){
            $output = strpos($output, '<head>') > 0
                ? str_replace(self::HEAD_END, $head.self::HEAD_END, $output)
                : $head.self::bodyContent($output);
        }
        return $output;
    }

    /** Output without JavaScript (HTMLPurifier, ReportExecute::sanitizeHtml). */
    private function purify(string $output, string $styles): string
    {
        if(class_exists('\HTMLPurifier_Config')){
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
            $config->set('HTML.DefinitionID', 'html5-definitions');
            $config->set('HTML.DefinitionRev', 1);
            if($this->environment->scratchDir !== '' && is_dir($this->environment->scratchDir)){
                $config->set('Cache.SerializerPath', rtrim($this->environment->scratchDir, '/'));
            }else{
                $config->set('Cache.DefinitionImpl', null);
            }
            $config->set('CSS.Trusted', true);
            $config->set('Attr.AllowedFrameTargets', '_blank');
            $config->set('HTML.SafeEmbed', true);
            $config->set('HTML.SafeIframe', true);
            $config->set('URI.SafeIframeRegexp',
                '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/|w\.soundcloud\.com/player/)%');
            // null when the definition is already cached (then it has these additions)
            $definition = $config->maybeGetRawHTMLDefinition();
            if($definition !== null){
                $definition->addElement('audio', 'Block', 'Flow', 'Common',
                    array('controls' => 'Bool', 'autoplay' => 'Bool', 'data-id' => 'Number'));
                $definition->addElement('source', 'Block', 'Flow', 'Common', array('src' => 'URI', 'type' => 'Text'));
                $definition->addAttribute('div', 'data-heurist-rec', 'Number');
            }
            $output = (new \HTMLPurifier($config))->purify($output);
        }
        if($styles !== ''){
            $output = strpos($output, '<head>') > 0
                ? str_replace(self::HEAD_END, $styles.self::HEAD_END, $output)
                : $styles.$output;
        }
        return $output;
    }
}
