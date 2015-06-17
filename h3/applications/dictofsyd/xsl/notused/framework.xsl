<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:template name="extraCSS">
		<link rel="stylesheet" href="{$urlbase}boxy.css" type="text/css" media="screen"/>
		<xsl:comment>[if IE]&gt;
			&lt;link rel="stylesheet" href="<xsl:value-of select="$urlbase"/>boxy-ie.css" type="text/css" media="screen"/&gt;
			&lt;![endif]</xsl:comment>
	</xsl:template>
	
	<xsl:template name="extraScripts">
		<script src="{$urlbase}js/jquery.boxy.js" type="text/javascript"/>
		<script src="{$urlbase}js/popups.js" type="text/javascript"/>
	</xsl:template>
	
	<xsl:template name="framework">
		<xsl:param name="title"/>

		<html>
			<head>
				<title>rrr<xsl:value-of select="$title"/></title>
				<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=2.0; user-scalable=1;" />
				<xsl:call-template name="makeMetaTags"/>
				<link rel="icon" href="{$urlbase}images/favicon.ico" type="image/x-icon"/>
				<link rel="shortcut icon" href="{$urlbase}images/favicon.ico" type="image/x-icon"/>
				<link rel="stylesheet" href="{$urlbase}style.css" type="text/css"/>
				<xsl:call-template name="extraCSS"/>
				<xsl:comment><![CDATA[[if lt IE 8]>
					<style type="text/css">
						.entity-information { zoom: 1; }
					</style>
				<![endif]]]></xsl:comment>
				<script type="text/javascript">
					RelBrowser = {
						baseURL: "<xsl:value-of select="$urlbase"/>",
						pipelineBaseURL: "../"
					};
				</script>
				<!-- &amp;amp;key={$hapi-key} -->
				<script src="http://hapi.heuristscholar.org/load?instance={$instance}" type="text/javascript"/>
				<script src="{$urlbase}jquery/jquery.js" type="text/javascript"/>
				<script src="{$urlbase}js/cookies.js" type="text/javascript"/>
				<script src="{$urlbase}js/fontsize.js" type="text/javascript"/>
				<script src="{$urlbase}js/history.js" type="text/javascript"/>
				<script src="{$urlbase}js/search.js" type="text/javascript"/>
				<script src="{$urlbase}js/menu.js" type="text/javascript"/>
				<xsl:call-template name="extraScripts"/>
				<script src="{$urlbase}js/tooltip.js" type="text/javascript"/>
				<script src="{$urlbase}js/swfobject.js" type="text/javascript"/>
				<script src="{$urlbase}js/media.js" type="text/javascript"/>

				<!-- map tileimage or entity -->
				<xsl:if test="/hml/records/record[
				                  type/@conceptID='1084-28'  or
				                  type/@conceptID='2-11'  or
				                  type/@conceptID='1084-25'  and  reversePointer/record[type/@conceptID='1084-26']/detail[@conceptID='2-28' or @conceptID='2-10']]">
					<script src="http://maps.google.com/maps?file=api&amp;amp;v=2&amp;amp;key=ABQIAAAAGZugEZOePOFa_Kc5QZ0UQRQUeYPJPN0iHdI_mpOIQDTyJGt-ARSOyMjfz0UjulQTRjpuNpjk72vQ3w" type="text/javascript"/>
				</xsl:if>

				<xsl:if test="/hml/records/record[type/@conceptID='1084-28' or type/@conceptID='1084-25']">
					<!-- pointer to kml -->
					<xsl:if test="/hml/records/record[
					                  (
					                      type/@conceptID='1084-28'  and  (
					                          detail[@conceptID='1084-91']/record  or
					                          relationships/record/detail/record
					                              [type/@conceptID='1084-25']
					                              [reversePointer[@conceptID='1084-87']/record
					                                  [detail[@conceptID='1084-85']='TimePlace']
					                              ]
					                      )
					                  )  or  (
					                      type/@conceptID='1084-25'  and
					                      reversePointer/record[type/@conceptID='1084-26']/detail[@conceptID='2-28' or @conceptID='2-10']
					                  )
					              ]">
						<script type="text/javascript">
							var Timeline_urlPrefix = RelBrowser.baseURL + "timeline/timeline_js/";
							var Timeline_ajax_url = RelBrowser.baseURL + "timeline/timeline_ajax/simile-ajax-api.js";
							var Timeline_parameters = "bundle=true";
						</script>
						<script src="{$urlbase}timeline/timeline_js/timeline-api.js" type="text/javascript"/>
						<script src="{$urlbase}timemap.js/timemap.js" type="text/javascript"/>
						<script src="{$urlbase}timemap.js/loaders/kml.js" type="text/javascript"/>
						<script src="{$urlbase}timemap.js/manipulation.js" type="text/javascript"/>
					</xsl:if>
					<script src="{$urlbase}js/mapping.js" type="text/javascript"/>
				</xsl:if>

				<xsl:if test="/hml/records/record[type/@conceptID='2-11'][detail[@conceptID='2-30'] = 'image']">
					<script src="{$urlbase}js/gmapimage.js" type="text/javascript"/>
				</xsl:if>

				<xsl:if test="/hml/records/record/type/@conceptID='2-13'">
					<script src="http://yui.yahooapis.com/2.7.0/build/yahoo/yahoo-min.js" type="text/javascript"/>
					<script src="http://yui.yahooapis.com/2.7.0/build/event/event-min.js" type="text/javascript"/>
					<script src="http://yui.yahooapis.com/2.7.0/build/history/history-min.js" type="text/javascript"/>
					<script src="{$urlbase}js/highlight.js" type="text/javascript"/>
				</xsl:if>
			</head>


			<body>
			<xsl:if test="/hml/records/record/type/@conceptID='2-13'">
				<iframe id="yui-history-iframe" src="{$urlbase}images/minus.png"/>
				<input id="yui-history-field" type="hidden"/>
			</xsl:if>
			<div id="header"></div>
			<div id="subheader">
				<div id="navigation">
					<div id="breadcrumbs"/>
				</div>
			</div>

			<div id="middle">
				<div id="container">

					<div id="left-col">
						<div id="content">
							<xsl:call-template name="content"/>

						</div>
					</div>

					<div id="right-col">
						<a title="Dictionary of Sydney Home">
							<xsl:attribute name="href">
								<xsl:choose>
									<xsl:when test="$urlbase = ''">
										<xsl:value-of select="'./'"/>
									</xsl:when>
									<xsl:otherwise>
										<xsl:value-of select="$urlbase"/>
									</xsl:otherwise>
								</xsl:choose>
							</xsl:attribute>
							<img src="{$urlbase}images/img-logo.jpg" alt="Dictionary of Sydney" width="198" height="125" class="logo"/>
						</a>
						<div id="search-bar">
							<form method="post" action=".">
								<input type="text" name="search" id="search" size="20"/>
								<div id="search-submit"/>
							</form>
						</div>

						<xsl:call-template name="sidebar"/>

					</div>

					<div class="clearfix"/>
					<div id="container-bottom"/>

				</div>
			</div>

			<div id="footer">
				<div id="footer-content">
					<ul id="footer-left-col">
						<li class="no-bullet">
							<a>
								<xsl:attribute name="href">
									<xsl:choose>
										<xsl:when test="$urlbase = ''">
											<xsl:value-of select="'./'"/>
										</xsl:when>
										<xsl:otherwise>
											<xsl:value-of select="$urlbase"/>
										</xsl:otherwise>
									</xsl:choose>
								</xsl:attribute>
								<xsl:text>Home</xsl:text>
							</a>
						</li>
						<li><a href="{$urlbase}about.html">About</a></li>
						<li><a href="{$urlbase}copyright.html">Copyright</a></li>
						<li><a href="{$urlbase}faq.html">FAQ</a></li>
						<li><a href="{$urlbase}contact.html">Contact</a></li>
						<li>
							<a href="#" class="increasefont" title="Increase font size">Font +</a>
							<xsl:text> </xsl:text>
							<a href="#" class="decreasefont" title="Decrease font size">-</a>
						</li>
					</ul>
					<ul id="footer-right-col">
						<li class="no-bullet"><a href="{$urlbase}about.html#donations">Donate</a></li>
						<li><a href="{$urlbase}contribute.html">Contribute</a></li>
						<li><a href="http://www.addthis.com/bookmark.php?v=250&amp;amp;pub=dictionaryofsydney" class="addthis_button">Share</a></li>
					</ul>
				</div>
			</div>

			<div id="previews">
<!-- ARTEM TODO				
				<xsl:call-template name="previewStubs"/>
-->				
			</div>

			<script type="text/javascript" src="http://www.google-analytics.com/ga.js"></script>
			<script type="text/javascript">
				try {
					var pageTracker = _gat._getTracker("UA-11403264-1");
					pageTracker._trackPageview();
				} catch(err) {}
			</script>

			<script type="text/javascript" src="http://s7.addthis.com/js/250/addthis_widget.js#pub=dictionaryofsydney"></script>
			<script type="text/javascript">
				var addthis_config = {
					services_exclude: 'myaol'
				}
			</script>


			</body>
		</html>
	</xsl:template>


</xsl:stylesheet>
