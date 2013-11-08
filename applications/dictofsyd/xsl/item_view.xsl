<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:exsl="http://exslt.org/common"
                exclude-result-prefixes="exsl"
                version="1.0">

	<xsl:include href="myvariables.xsl"/>
	<xsl:include href="util.xsl"/>
	<xsl:include href="media.xsl"/>
	<xsl:include href="factoid.xsl"/>
	<xsl:include href="entity.xsl"/>
	<xsl:include href="previews.xsl"/>
	<xsl:include href="entry.xsl"/>
	<xsl:include href="contributor.xsl"/>
	<xsl:include href="role.xsl"/>
	<xsl:include href="term.xsl"/>
	<xsl:include href="map.xsl"/>

<!--
	<xsl:include href="framework.xsl"/>

	<xsl:include href="hi_res_image.xsl"/>
	<xsl:include href="annotation.xsl"/>
-->

	<xsl:variable name="record" select="hml/records/record[@depth=0]"/>

	<xsl:template match="/">
		<xsl:call-template name="framework">
			<xsl:with-param name="title" select="$record/detail[@conceptID='2-1']"/>
		</xsl:call-template>
	</xsl:template>

	<xsl:template name="content">
		<xsl:call-template name="makeTitleDiv">
			<xsl:with-param name="record" select="$record"/>
		</xsl:call-template>
		<xsl:apply-templates select="$record"/>
	</xsl:template>


	<xsl:template name="sidebar">
		<xsl:apply-templates select="$record" mode="sidebar"/>
	</xsl:template>

	<xsl:template match="record" mode="sidebar"/>

	<!-- connection via annotations and non-entities (terms) -->

	<xsl:template name="connections">
		<xsl:param name="omit"/>

		<xsl:variable name="related" select="/hml/records/record[@depth=1 and
			type/@conceptID!='1084-25' and
			relationship/@relatedRecordID = current()/id]"/>


		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Entries</xsl:with-param>
			<xsl:with-param name="items" select="$related[type/@conceptID='2-13']"/>
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Pictures</xsl:with-param>
			<xsl:with-param name="items" select="$related[type/@conceptID='2-5'][starts-with(detail[@conceptID='2-29'], 'image')]"/>
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Sound</xsl:with-param> <!-- ARTEM TODO additional filter $related[@type='IsRelatedTo'] -->
			<xsl:with-param name="items" select="$related[type/@conceptID='2-5'][starts-with(detail[@conceptID='2-29'], 'audio')]"/>
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Video</xsl:with-param> <!-- ARTEM TODO additional filter $related[@type='IsRelatedTo'] -->
			<xsl:with-param name="items" select="$related[type/@conceptID='2-5'][starts-with(detail[@conceptID='2-29'], 'video')]"/>
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Maps</xsl:with-param> <!-- ARTEM TODO additional filter $related[@type='IsRelatedTo'] -->
			<xsl:with-param name="items" select="$related[type/@conceptID='1084-28']"/>
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Subjects</xsl:with-param>
			<xsl:with-param name="items" select="$related[type/@conceptID='1084-29']"/>  <!-- ARTEM TOCHECK   term -->
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">External links</xsl:with-param> <!-- ARTEM TODO additional filter $related[@type='hasExternalLink'] -->
			<xsl:with-param name="items" select="$related[type/@conceptID='2-2']"/>
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>


		<xsl:variable name="annotations" select="/hml/records/record[@depth=1 and type/@conceptID='2-15' and detail[@conceptID='2-13'] = current()/id]"/>


		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Mentioned in</xsl:with-param>
			<xsl:with-param name="items" select="/hml/records/record[@depth=2 and id = $annotations/detail[@conceptID='2-42']]"/>
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>
	</xsl:template>

	<xsl:template name="relatedEntitiesByType">
		<!-- find all related entities -->
		<xsl:variable name="related_rec" select="/hml/records/record[@depth=1 and
														type/@conceptID='1084-25' and
														relationship/@useInverse='true' and
														relationship/@relatedRecordID = current()/id]"/>

		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Places</xsl:with-param>
			<xsl:with-param name="items" select="$related_rec[detail[@conceptID='1084-75'] = 'Place']"/>
		</xsl:call-template>

		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">People</xsl:with-param>
			<xsl:with-param name="items" select="$related_rec[detail[@conceptID='1084-75'] = 'Person']"/>
		</xsl:call-template>

		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Artefacts</xsl:with-param>
			<xsl:with-param name="items" select="$related_rec[detail[@conceptID='1084-75'] = 'Artefact']"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Buildings</xsl:with-param>
			<xsl:with-param name="items" select="$related_rec[detail[@conceptID='1084-75'] = 'Building']"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Events</xsl:with-param>
			<xsl:with-param name="items" select="$related_rec[detail[@conceptID='1084-75'] = 'Event']"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Natural features</xsl:with-param>
			<xsl:with-param name="items" select="$related_rec[detail[@conceptID='1084-75'] = 'Natural feature']"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Organisations</xsl:with-param>
			<xsl:with-param name="items" select="$related_rec[detail[@conceptID='1084-75'] = 'Organisation']"/>
		</xsl:call-template>

		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Structures</xsl:with-param>
			<xsl:with-param name="items" select="$related_rec[detail[@conceptID='1084-75'] = 'Structure']"/>
		</xsl:call-template>

	</xsl:template>

	<xsl:template name="relatedItems">
		<xsl:param name="label"/>
		<xsl:param name="items"/>
		<xsl:param name="omit"/>

		<xsl:variable name="type">
			<xsl:call-template name="getRecordTypeClassName">
				<xsl:with-param name="record" select="$items[1]"/>
			</xsl:call-template>
		</xsl:variable>


		<xsl:if test="count($items) > 0  and  not(exsl:node-set($omit)[section=$label])">
			<div class="menu">
				<h4 class="menu-{$type}"><xsl:value-of select="$label"/></h4>
			</div>
			<div class="submenu">
				<ul>
					<!--
					<xsl:apply-templates select="$items[1]">
						<xsl:with-param name="matches" select="$items"/>
					</xsl:apply-templates>
					-->
					<xsl:call-template name="menuentries">
						<xsl:with-param name="matches" select="$items"/>
					</xsl:call-template>
				</ul>
			</div>
		</xsl:if>

	</xsl:template>


		<!-- This template is to be called in the context of just one record,
		     with the whole list in the "matches" variable.  This gives the template
		     a chance to sort the list itself, while still allowing the magic of the
		     match parameter: the single apply-templates in the relatedItems template
		     above might call this template, or another, such as the one below,
		     depending on the items in question.

		match="detail/record | reversePointer/record"
		-->
	<xsl:template name="menuentries" >
		<xsl:param name="matches"/>

		<xsl:for-each select="$matches">
			<xsl:sort select="detail[@conceptID='2-1']"/>

			<xsl:variable name="class">
				<xsl:if test="type/@conceptID='2-5'"> <!-- media -->
					<xsl:text>popup </xsl:text>
				</xsl:if>
				<xsl:text>preview-</xsl:text>
				<xsl:value-of select="id"/>
				<xsl:if test="local-name(../../..) = 'relationships'">
					<xsl:text>c</xsl:text>
					<xsl:value-of select="../../id"/>
				</xsl:if>
			</xsl:variable>

			<li>
				<xsl:choose>
					<xsl:when test="type/@conceptID='2-2'">
						<a href="{url}" class="{$class}">
							<xsl:value-of select="detail[@conceptID='2-1']"/>
						</a>
					</xsl:when>
					<xsl:otherwise>
						<a href="{id}" class="{$class}">
							<xsl:choose>
								<xsl:when test="detail[@conceptID='2-1']">
									<xsl:value-of select="detail[@conceptID='2-1']"/>
								</xsl:when>
								<xsl:otherwise>
									<xsl:value-of select="title"/>
								</xsl:otherwise>
							</xsl:choose>
						</a>
					</xsl:otherwise>
				</xsl:choose>
			</li>
		</xsl:for-each>
	</xsl:template>


	<!-- external links: link to external link, new window, no preview ARTEM TODO -->
	<xsl:template match="relationships/record/detail/record[type/@id=1]">

		<xsl:param name="matches"/>
		<xsl:for-each select="$matches">
			<xsl:sort select="detail[@id=160]"/>
			<li>
				<a href="{detail[@id=198]}" target="_blank">
					<xsl:choose>
						<xsl:when test="detail[@id=160]">
							<xsl:value-of select="detail[@id=160]"/>
						</xsl:when>
						<xsl:otherwise>
							<xsl:value-of select="title"/>
						</xsl:otherwise>
					</xsl:choose>
				</a>
			</li>
		</xsl:for-each>
	</xsl:template>


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
				<title><xsl:value-of select="$title"/></title>
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
				<!-- &amp;amp;key={$hapi-key}
				<script src="http://hapi.heuristscholar.org/load?instance={$instance}" type="text/javascript"/>
				<script src="{$urlbase}jquery/jquery.js" type="text/javascript"/>
				-->
				<script src="{$urlbase}js/timemap.js/2.0.1/lib/jquery-1.6.2.min.js" type="text/javascript"/>
				<script src="{$urlbase}js/cookies.js" type="text/javascript"/>
				<script src="{$urlbase}js/fontsize.js" type="text/javascript"/>
				<script src="{$urlbase}js/history.js" type="text/javascript"/>
				<script src="{$urlbase}js/search.js" type="text/javascript"/>
				<script src="{$urlbase}js/menu.js" type="text/javascript"/>
				<xsl:call-template name="extraScripts"/>
				<script src="{$urlbase}js/tooltip.js" type="text/javascript"/>
				<script src="{$urlbase}js/swfobject.js" type="text/javascript"/>
				<script src="{$urlbase}js/media.js" type="text/javascript"/>


				<!-- factoids with geo or time -->
				<xsl:if test="$record[type/@conceptID='1084-25']">
					<xsl:variable name="factoids" select="/hml/records/record[@depth=1 and
						detail[@conceptID='1084-87']=$record/id and
						(detail[@conceptID='1084-85']='TimePlace' or detail[@conceptID='2-28' or @conceptID='2-10'])]"/>;
				</xsl:if>


				<!-- map tileimage or entity with factoid with geo or startdate -->
				<xsl:if test="$record[type/@conceptID='1084-28' or
										type/@conceptID='2-11'] or
							  ($record[type/@conceptID='1084-25'] and
							  /hml/records/record[@depth=1 and
							  detail[@conceptID='1084-87']=$record/id and
							  (detail[@conceptID='1084-85']='TimePlace' or detail[@conceptID='2-28' or @conceptID='2-10'])])">
					<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>

<!-- timemap ver 2.0
						<script type="text/javascript">
							Timeline_urlPrefix = RelBrowser.baseURL+"js/timemap.js/2.0/lib/timeline/";
							Timeline_ajax_url  = RelBrowser.baseURL+"js/timemap.js/2.0/lib/timeline/timeline_ajax/simile-ajax-api.js";
							//SimileAjax_urlPrefix = RelBrowser.baseURL+"js/timemap.js/2.0/lib/timeline/timeline_ajax/";
						</script>

						<script type="text/javascript" src="{$urlbase}js/timemap.js/2.0/lib/timeline/timeline-api.js?bundle=true"></script>
						<script type="text/javascript" src="{$urlbase}js/timemap.js/2.0/lib/jquery-ui-1.8.13.custom.min.js"></script>

						<script type="text/javascript" src="{$urlbase}js/timemap.js/2.0/lib/mxn/mxn.js?(googlev3)"></script>
						<script type="text/javascript" src="{$urlbase}js/timemap.js/2.0/lib/markerclusterer.js"></script>

						<script src="{$urlbase}js/timemap.js/2.0/src/timemap.js" type="text/javascript"></script>
						<script src="{$urlbase}js/timemap.js/2.0/src/param.js" type="text/javascript"></script>
						<script src="{$urlbase}js/timemap.js/2.0/src/manipulation.js" type="text/javascript"></script>
						<script src="{$urlbase}js/timemap.js/2.0/src/loaders/xml.js" type="text/javascript"></script>
						<script src="{$urlbase}js/timemap.js/2.0/src/loaders/kml.js" type="text/javascript"></script>
-->

<!-- -->
						<script type="text/javascript">
							Timeline_urlPrefix = RelBrowser.baseURL+"js/timemap.js/2.0.1/lib/";
							//Timeline_ajax_url  = RelBrowser.baseURL+"js/timemap.js/2.0/lib/timeline/timeline_ajax/simile-ajax-api.js";
						</script>

						<script type="text/javascript" src="{$urlbase}js/timemap.js/2.0.1/lib/mxn/mxn.js?(googlev3)"></script>
						<script type="text/javascript" src="{$urlbase}js/timemap.js/2.0.1/lib/timeline-1.2.js"></script>
						<script type="text/javascript" src="{$urlbase}js/timemap.js/2.0.1/src/timemap.js"></script>
						<script type="text/javascript" src="{$urlbase}js/timemap.js/2.0.1/src/param.js"></script>
						<script type="text/javascript" src="{$urlbase}js/timemap.js/2.0.1/src/loaders/xml.js"></script>
						<script type="text/javascript" src="{$urlbase}js/timemap.js/2.0.1/src/loaders/kml.js"></script>

						<script src="{$urlbase}js/wkt2tm.js" type="text/javascript"></script>
						<script src="{$urlbase}js/mapping.js" type="text/javascript"></script>
				</xsl:if>

				<xsl:if test="/hml/records/record[type/@conceptID='2-11'][detail[@conceptID='2-30'] = 'image']">
					<script src="{$urlbase}js/gmapimage.js" type="text/javascript"/>
				</xsl:if>

				<xsl:if test="$record[type/@conceptID='2-13']">
					<script src="http://yui.yahooapis.com/2.7.0/build/yahoo/yahoo-min.js" type="text/javascript"/>
					<script src="http://yui.yahooapis.com/2.7.0/build/event/event-min.js" type="text/javascript"/>
					<script src="http://yui.yahooapis.com/2.7.0/build/history/history-min.js" type="text/javascript"/>
					<script src="{$urlbase}js/highlight.js" type="text/javascript"/>
<!--
					<xsl:variable name="annotation_rec_ids" select="$record/reversePointer[@conceptID='2-42']"/>
					<xsl:call-template name="addAnnotations">
						<xsl:with-param name="matches" select="/hml/records/record[id=$annotation_rec_ids]"/>
					</xsl:call-template>
-->
				</xsl:if>


			</head>


			<body>

				<xsl:if test="$record[type/@conceptID='2-13']">
					<iframe id="yui-history-iframe" src="{$urlbase}images/minus.png"></iframe>
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

					<div id="browse-link">
						<a title="Dictionary of Sydney Browse">
							<xsl:attribute name="href">
								<xsl:choose>
									<xsl:when test="$urlbase = ''">
										<xsl:value-of select="'./'"/><xsl:text>browse.php</xsl:text>
									</xsl:when>
									<xsl:otherwise>
										<xsl:value-of select="$urlbase"/><xsl:text>browse.php</xsl:text>
									</xsl:otherwise>
								</xsl:choose>
							</xsl:attribute>
							Browse
						</a>
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
							<li><a href="http://trust.dictionaryofsydney.org/about-the-trust/">About</a></li>
							<li><a href="http://trust.dictionaryofsydney.org/participate/copyright/">Copyright</a></li>
							<li><a href="http://trust.dictionaryofsydney.org/participate/contact-form/">Contact</a></li>
							<li>
								<a href="#" class="increasefont" title="Increase font size">Font +</a>
								<xsl:text> </xsl:text>
								<a href="#" class="decreasefont" title="Decrease font size">-</a>
							</li>
						</ul>
						<ul id="footer-right-col">
							<li class="no-bullet"><a href="http://www.everydayhero.com.au/dictionaryofsydney">Donate</a></li>
							<li><a href="http://trust.dictionaryofsydney.org/participate/contact-form/">Contribute</a></li>
							<li><a href="http://www.addthis.com/bookmark.php?v=250&amp;amp;pub=dictionaryofsydney" class="addthis_button">Share</a></li>
						</ul>
					</div>
				</div>

				<div id="previews">
					<!--	<xsl:call-template name="previewStubs"/> -->
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
