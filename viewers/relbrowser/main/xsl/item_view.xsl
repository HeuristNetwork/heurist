<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:str="http://exslt.org/strings" version="1.0" xmlns:exsl="http://exslt.org/common" extension-element-prefixes="exsl">
	<xsl:param name="id"/>
	<xsl:param name="related_reftype_filter"/>
	<xsl:include href="myvariables.xsl"/>
	<xsl:include href="author_editor.xsl"/>
	<xsl:include href="books-etc.xsl"/>
	<xsl:include href="factoid.xsl"/>
	<xsl:include href="internet_bookmark.xsl"/>
	<xsl:include href="media.xsl"/>
	<xsl:include href="teidoc.xsl"/>
	<xsl:include href="teidoc_reference.xsl"/>
	<xsl:include href="kml-timeline.xsl"/>
	<xsl:include href="collection.xsl"/>
	<xsl:include href="artist.xsl"/>
	<xsl:include href="artwork.xsl"/>
	<xsl:include href="relation_translator.xsl"/>

	<xsl:variable name="currentid">
		<xsl:value-of select="export/references/reference/id"/>
	</xsl:variable>


	<xsl:template match="/">
		<html>
			<head>
				<link rel="stylesheet" href="{$serverBaseUrl}{$appBase}css/browser.css"/>
				<title id="{$currentid}">
					<xsl:value-of select="export/references/reference/title"/>
				</title>
				<script src="{$hBase}external/jquery/jquery.js"/>
				<script>
					var itemPath = "<xsl:value-of select="$cocoonBase"/>item/";
					var imgpath = "<xsl:value-of select="$hBase"/>common/images/reftype-icons/";

					function showFootnote(recordID) {
						document.getElementById("page").style.bottom = "105px";
						document.getElementById("footnotes").style.display = "block";

						var elts = document.getElementsByName("footnote");
						if (elts.length === 0) elts = document.getElementById("footnotes-inner").getElementsByTagName("div");  // fallback compatibility with IE
						for (var i = 0; i &lt; elts.length; ++i) {
							var e = elts[i];
							e.style.display = e.getAttribute("recordID") == recordID ? "" : "none";
						}
						load(recordID);
					}

					function load(id) {
						var loader = new HLoader(
							function(s,r) {
								annotation_loaded(r[0]);
							},
							function(s,e) {
								alert("load failed: " + e);
							});
						HeuristScholarDB.loadRecords(new HSearch("id:" + id), loader);
					}

					function annotation_loaded(record) {
						var elts = document.getElementById("footnotes-inner");
						var notes = record.getDetail(HDetailManager.getDetailTypeById(303));

						elts.innerHTML = "&lt;p>" + record.getTitle() + "&lt;/p>";
						if (notes) {
							elts.innerHTML += "&lt;p>" + notes + "&lt;/p>";
						}

						var val = record.getDetail(HDetailManager.getDetailTypeById(199));
						if (val){
							HeuristScholarDB.loadRecords(new HSearch("id:"+val.getID()),
								new HLoader(function(s,r){MM_loaded(r[0],record)})
							);
						}
					}

					function MM_loaded(val,record) {
						var elts = document.getElementById("footnotes-inner");

						if (val.getRecordType().getID() == 74) {
							var img=val.getDetail(HDetailManager.getDetailTypeById(221)). getThumbnailURL();
							elts.innerHTML += "&lt;br>&lt;a href=\""+itemPath+val.getID()+"/?instance=<xsl:value-of select="$instanceName"/>" + "\">&lt;img src=\"" + img+ "\"/>&lt;/a>";
						}else{
							elts.innerHTML += "&lt;br>&lt;br>&lt;span style=\"padding-right:5px; vertical-align:top\">&lt;a href=\""+itemPath+val.getID()+"\">"+val.getTitle()+"&lt;/a>&lt;/span>"+"&lt;img src=\"" + imgpath+val.getRecordType().getID() +".png\"/>";
						}
					}

				</script>
				<script src="{$hBase}common/php/loadHAPI.php?instance={$instanceName}"/>
				<script>
					if (!HCurrentUser.isLoggedIn()) {
						window.location = '<xsl:value-of select="$hBase"/>common/connect/loginForRelBrowser.php?instance=<xsl:value-of select="$instanceName"/>&amp;logo=<xsl:value-of select="$appBase"/>images/logo.png&amp;home=<xsl:value-of select="$appBase"/>';
					}</script>
				<script src="{$appBase}js/search.js"/>
				<script>
					top.HEURIST = {};
					top.HEURIST.fireEvent = function(e, e){};</script>
				<script src="{$hBase}common/php/loadUserInfo.php?instance={$instanceName}"/>
				<!-- Time Map rendering -->
				<xsl:if test="export/references/reference/reftype[@id=103 or @id=51 or @id=165 or @id=122 or @id=57]">
					<script>
						var appBase = '<xsl:value-of select="$appBase"/>';
						var enableMapTrack  = <xsl:value-of select="$enableMapTrack"/>;
						Timeline_urlPrefix = "<xsl:value-of select="$hBase"/>external/timeline/timeline_js/";
						Timeline_ajax_url = "<xsl:value-of select="$hBase"/>external/timeline/timeline_ajax/simile-ajax-api.js";
						Timeline_parameters = "bundle=true";</script>
					<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key=ABQIAAAAGZugEZOePOFa_Kc5QZ0UQRQUeYPJPN0iHdI_mpOIQDTyJGt-ARSOyMjfz0UjulQTRjpuNpjk72vQ3w"></script>
					<script src="{$hBase}external/timeline/timeline_js/timeline-api.js" type="text/javascript"></script>
					<script src="{$hBase}external/timemap.js/timemap.js" type="text/javascript"></script>
					<script src="{$hBase}external/timemap.js/kmlparser.js" type="text/javascript"></script>
					<script src="{$appBase}js/mapping.js"></script>
					<xsl:if test="$enableMapTrack = 'true'">
						<script>
							var maptrackCrumbNumber = <xsl:value-of select="$maptrackCrumbNumber"/>;
							var crumbThemes = [];
							var _nameTrack ='bcrumb' + '<xsl:value-of select="$bcrumbNameTrack"/>'; //global name for the PJ object<xsl:for-each select="exsl:node-set($mapCrumbThemes)/theme">
								crumbThemes.push({colour:'<xsl:value-of select="exsl:node-set($timeMapThemes)/theme[@name=current()]/colour"/>' , icon : '<xsl:value-of select="exsl:node-set($timeMapThemes)/theme[@name=current()]/icon"/>'});</xsl:for-each></script>
						<script src="{$appBase}js/track.js"></script>
					</xsl:if>
				</xsl:if>
			</head>
			<body pub_id="{/export/@pub_id}">
				<div id="header">
					<a href="{$hBase}search/search.html?instance={$instanceName}">
						<div id="logo"></div>
					</a>
					<div id="instance-name"><xsl:value-of select="$instanceName"/></div>
					<div id="home-link">
						<a href="{$hBase}search/search.html?q=id:{export/references/reference/id}&amp;instance={$instanceName}">Record id:
							<xsl:value-of select="export/references/reference/id"/>
						</a>
					</div>
				</div>
		<div id="sidebar">
				<div class="banner">
						<div id="login">
								<script type="text/javascript">

						var a = document.createElement("a");
						a.href ='<xsl:value-of select="$hBase"/>common/connect/loginForRelBrowser.php?logo={$appBase}images/logo.png&amp;home={$serBaseUrl}{$appBase}';


						if (HCurrentUser.isLoggedIn()) {
							document.getElementById("login").appendChild(document.createTextNode(HCurrentUser.getRealName() + " : "));
							a.appendChild( document.createTextNode("Log out"));
						} else {

							a.appendChild(document.createTextNode("Log in"));
						}

						document.getElementById("login").appendChild(a);</script>
						</div>

				</div>
				<div id="search">
					<div class="outline roundedBoth">
							<form method="post" onsubmit="search(document.getElementById('query-input').value); return false;">
								<input type="text" id="query-input" value=""/>
								<input type="button" class="button" value="search" onclick="search(document.getElementById('query-input').value);"/>
							</form>
					</div>

				</div>

				<div id="sidebar-inner">
						<!-- h1>

							<span style="padding-right:5px; padding-left:5px; vertical-align:top;">
								<a href="#" onclick="window.open('{$appBase}edit.html?id={export/references/reference/id}','','status=0,scrollbars=1,resizable=1,width=800,height=600'); return false; " title="Edit main record">
									<img src="{$hBase}/img/edit-pencil.png" class="editPencil" style="vertical-align: top;"/>
								</a>
							</span>
							<xsl:value-of select="export/references/reference[1]/title"/>
						</h1 -->
						<xsl:call-template name="related_items_section">
							<xsl:with-param name="items" select="export/references/reference/related | export/references/reference/pointer | export/references/reference/reverse-pointer"/>
						</xsl:call-template>


					</div>
			</div>

		 <div id="popup" class="popup" style="z-index:100001; visibility: visible; display:none;">
		 	<div class="close-button"></div>

         <!--iframe frameborder="0" style="width: 100%; height: 100%;" src='{$appBase}addrelationship.html?typeId=52&amp;source={export/references/reference/id}'></iframe-->

     	 </div>
	 <iframe frameborder="0" id="coverall" style="visibility: visible; z-index: 100000;" class=""></iframe>




			<div id="page">
				<div class="banner">
						<a href="{$appBase}edit.html?id={$currentid}&amp;instance={$instanceName}" onclick="window.open(this.href,'','status=0,scrollbars=1,resizable=1,width=800,height=600'); return false;" title="edit" style="background-image:url({$hBase}common/images/edit-pencil.png)">
						Edit Record</a><nobr></nobr>
					<xsl:if test="export/references/reference/reftype/@id = 98">
					<xsl:if test=" $id != $home-id">
						<a href="#" onclick="window.open('{$appBase}edit-annotation.html?refid={export/references/reference/id}','','status=0,scrollbars=1,resizable=1,width=700,height=500'); return false;" title="add Annotation" style="background-image:url({$appBase}images/annotation_tool.png)">Add Annotation</a>
						</xsl:if>
					</xsl:if>
					<!-- a href="#" id="addRelationship" title="add Relationship" style="background-image:url({$appBase}images/rel_icon.png)">Add Relationship Popup Test</a -->
					<a href="#" onclick="window.open('{$appBase}addrelationship.html?typeId=52&amp;source={export/references/reference/id}&amp;instance={$instanceName}','','status=0,scrollbars=1,resizable=1,width=700,height=500'); return false;" title="add Relationship" style="background-image:url({$appBase}images/rel_icon.png)">Add Relationship</a>
				</div>

<!--					<xsl:choose>
						<xsl:when test="export/references/reference/reftype[@id = 51 or @id = 55]" -->
							<div id="page-inner">
								<!-- full version of record -->
								<xsl:apply-templates select="export/references/reference"/>
							</div>
						<!-- /xsl:when>
						<xsl:otherwise>

							<div id="page-inner">



								<xsl:apply-templates select="export/references/reference"/>
							</div>
						</xsl:otherwise>
					</xsl:choose -->
				</div>
				<div id="footnotes">
					<div id="footnotes-inner">
						<xsl:apply-templates select="export/references/reference/reverse-pointer[reftype/@id=99]" mode="footnote"/>
					</div>
				</div>
			</body>
		</html>

<script type="text/javascript">
	 $(document).ready(function(){

	 		$('.relTypeHeading').live("click",function(){
				$(this).siblings('div.relItemList').slideToggle('fast');
				$(this).parent('.relType').toggleClass('hide');
		});

	 });
</script>

<!-- end of HTML output -->
	</xsl:template>
	<xsl:template match="breadcrumbs">
		<xsl:for-each select="breadcrumb">
			<xsl:sort select="id"/>
			<a href="{url}">
				<xsl:value-of select="title"/>
			</a>
		</xsl:for-each>
	</xsl:template>
	<xsl:template name="related_items_section">
		<xsl:param name="items"/>

<!-- top of sidebar before you start listing the type of relationships -->
		<div id="relations-table">
			<div id="map-types" class="map-timeline-key"/>
 			<!-- this step of the code aggregates related items into groupings based on the type of related item -->
			<xsl:for-each select="$items[not(@type = preceding-sibling::*/@type)] ">
				<xsl:choose>
					<xsl:when test="@type != 'Map image layer reference' and @type != 'Source entity reference' and @type != 'Entity reference' and @type != 'Target entity reference'">
						<!-- small>[<xsl:value-of select="local-name()"/>]</small -->
						<xsl:call-template name="related_items_by_reltype">
							<xsl:with-param name="reftype_id" select="reftype/@id"/>
							<xsl:with-param name="reltype" select="@type"/>
							<xsl:with-param name="reldirection" select="local-name()"/>
							<xsl:with-param name="items" select="$items[@type = current()/@type and reftype/@id != 52]"/>
						</xsl:call-template>
					</xsl:when>
				</xsl:choose>
			</xsl:for-each>
       		</div>


	</xsl:template>
	<xsl:template name="related_items_by_reltype">
		<xsl:param name="reftype_id"/>
		<xsl:param name="reltype"/>
		<xsl:param name="reldirection"/>
		<xsl:param name="items"/>
		<xsl:if test="count($items) &gt; 0">
			<xsl:if test="$reftype_id != 150  or  ../reftype/@id = 103">

				<div class="relType">
						<div class="relTypeHeading">
							<xsl:choose>
								<xsl:when test="$reftype_id = 99">Annotations</xsl:when>
								<xsl:otherwise>

									<xsl:call-template name="translate_relations">
										<xsl:with-param name="reltype" select="$reltype"/>
										<xsl:with-param name="reldirection" select="$reldirection"/>
										<xsl:with-param name="reftype_id" select="$reftype_id"/>
									</xsl:call-template>
									<!-- xsl:value-of select="$reltype"/ -->
									<xsl:value-of select="../@id"/>

								</xsl:otherwise>
							</xsl:choose>
						</div>
						<!-- (<xsl:value-of select="$items[1]"/>) -->
						<div class="relItemList">
							<!-- p>id: <xsl:value-of select="$currentid"/> - [<xsl:value-of select="id"/>] - </p -->
							<xsl:apply-templates select="$items[1]">
								<xsl:with-param name="matches" select="$items"/>
							</xsl:apply-templates>
						</div>
				</div>

			</xsl:if>
		</xsl:if>
	</xsl:template>

	<xsl:template name="related_items">
		<xsl:param name="reftype_id"/>
		<xsl:param name="reftype_label"/>
		<xsl:param name="items"/>
		<xsl:if test="count($items) &gt; 0">
			<xsl:if test="$reftype_id != 150  or  ../reftype/@id = 103">
				<tr>
					<td>
						<b>
							<a href="#" onclick="openRelated({$reftype_id}); return false;">
								<xsl:value-of select="$reftype_label"/>
								<!-- (<xsl:value-of select="count($items)"/>) -->
							</a>
						</b>
					</td>
				</tr>
				<tr name="related" reftype="{$reftype_id}">
					<xsl:if test="$reftype_id!=$related_reftype_filter">
						<xsl:attribute name="style">display: none;</xsl:attribute>
					</xsl:if>
					<td>
						<table width="100%">
							<xsl:apply-templates select="$items[1]">
								<xsl:with-param name="matches" select="$items"/>
							</xsl:apply-templates>
						</table>
					</td>
				</tr>
			</xsl:if>
		</xsl:if>
	</xsl:template>
	<xsl:template match="related | pointer | reverse-pointer">
		<!-- this is where the display work is done summarising the related items of various types - pictures, events etc -->
		<!-- reftype-specific templates take precedence over this one -->
		<xsl:param name="matches"/>
		<!-- trickiness!
		     First off, this template will catch a single related (/ pointer / reverse-pointer) record,
		     with the full list as a parameter ("matches").  This gives the template a chance to sort the records
		     and call itself with those sorted records
		-->
		<xsl:choose>
			<xsl:when test="$matches">
				<xsl:apply-templates select="$matches">
					<xsl:sort select="title"/>
					<xsl:sort select="detail[@id=177]/year"/>
					<xsl:sort select="detail[@id=177]/month"/>
					<xsl:sort select="detail[@id=177]/day"/>
					<xsl:sort select="detail[@id=160]"/>
				</xsl:apply-templates>
			</xsl:when>
			<xsl:otherwise>
				<div class="relatedItem">
                    <div class="editIcon">
						<a href="{$appBase}edit.html?id={id}&amp;instance={$instanceName}" onclick="window.open(this.href,'','status=0,scrollbars=1,resizable=1,width=800,height=600'); return false;" title="edit">
							<img src="{$hBase}common/images/edit-pencil.png" class="editPencil"/>
						</a>
					</div>
					<div class="thumbnailCell">
						<!-- PRESENTATION  for KML Map 103, Historical Event 51, KML file 165 , Place record 122 and Site record 57-->
						<xsl:if test="(../reftype/@id = 103 or ../reftype/@id = 165 or ../reftype/@id = 122 or ../reftype/@id = 57)">
							<xsl:call-template name="renderAppropriateLegend">
								<xsl:with-param name="record" select="."/>
								<xsl:with-param name="themeToUse" select="$relatedTheme"/>
							</xsl:call-template>
						</xsl:if>
						<xsl:if test=" ../reftype/@id=51">
							<xsl:choose>
								<xsl:when test="@id = 276">
									<!-- pointer to a site record -->
									<xsl:call-template name="renderAppropriateLegend">
										<xsl:with-param name="record" select="."/>
										<xsl:with-param name="themeToUse" select="$focusTheme"/>
									</xsl:call-template>
								</xsl:when>
								<xsl:otherwise>
									<xsl:call-template name="renderAppropriateLegend">
										<xsl:with-param name="record" select="."/>
										<xsl:with-param name="themeToUse" select="$relatedTheme"/>
									</xsl:call-template>
								</xsl:otherwise>
							</xsl:choose>
						</xsl:if>

						<!--  renders thumbnails  -->
						 <xsl:if test="detail[@id=606]">
								<a href="{$cocoonBase}item/{id}/?instance={$instanceName}">
									<img src="{detail[@id=606]}" title="{detail[@id=606]}" class="thumbnail"/>
								</a>
						 </xsl:if>

						 <xsl:if test="detail[@id = 222 or @id=223 or @id=224]">
							<xsl:if test="detail/file_thumb_url">
								<a href="{$cocoonBase}item/{id}/?instance={$instanceName}">
									<img src="{detail/file_thumb_url}" class="thumbnail"/>
								</a>
							</xsl:if>
						</xsl:if>
					</div>

					<div class="link">

						<a href="{$cocoonBase}item/{id}/?instance={$instanceName}">
							<xsl:choose>
								<!-- related / notes -->
								<xsl:when test="@notes">
									<xsl:attribute name="title">
										<xsl:value-of select="@notes"/>
									</xsl:attribute>
								</xsl:when>
							</xsl:choose>
							<xsl:choose>
								<xsl:when test="detail[@id=160]">
									<xsl:value-of select="detail[@id=160]"/>
								</xsl:when>
								<xsl:otherwise>
									<xsl:value-of select="title"/>
								</xsl:otherwise>
							</xsl:choose>
						</a>
					</div>
					<div class="reftypeIcon">
						<!-- change this to pick up the actuall system name of the reftye or to use the mapping method as in JHSB that calls human-readable-names.xml -->
						<img style="vertical-align: middle;horizontal-align: right" src="{$hBase}common/images/reftype-icons/{reftype/@id}.png" title="{reftype}"/>
					</div>
                </div>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>


	<!-- fall-back template for any reference types that aren't already handled -->
	<xsl:template match="reference">


            <div class="recordTypeHeading">
					<img src="{$hBase}common/images/reftype-icons/{reftype/@id}.png"/>
					<xsl:text> </xsl:text>
					<xsl:value-of select="reftype"/>
            </div>

			<div class="detailRow">
				<xsl:if test="url != ''">
					<div class="detailType">URL</div>
					<div class="detail">
						<a href="{url}">
							<xsl:choose>
								<xsl:when test="string-length(url) &gt; 50">
									<xsl:value-of select="substring(url, 0, 50)"/>
								</xsl:when>
								<xsl:otherwise>
									<xsl:value-of select="url"/>
								</xsl:otherwise>
							</xsl:choose>
						</a>
					</div>
				</xsl:if>
			</div>

			<xsl:for-each select="detail[@id!=222 and @id!=223 and @id!=224]">
				<div class="detailRow">
					<div class="detailType">
							<xsl:choose>
								<xsl:when test="string-length(@name)">
									<xsl:value-of select="@name"/>
								</xsl:when>
								<xsl:otherwise>
									<xsl:value-of select="@type"/>
								</xsl:otherwise>
							</xsl:choose>
					</div>
					<div class="detail">
						<xsl:choose>
							<xsl:when test="@id=606">
								<a href="{text()}"><img src="{text()}" title="{text()}"></img>
								</a>
							</xsl:when>
							<!-- 268 = Contact details URL,  256 = Web links -->
							<xsl:when test="@id=268  or  @id=256  or  starts-with(text(), 'http')">
								<a href="{text()}">
									<xsl:choose>
										<xsl:when test="string-length() &gt; 50">
											<xsl:value-of select="substring(text(), 0, 50)"/>
										</xsl:when>
										<xsl:otherwise>
											<xsl:value-of select="text()"/>
										</xsl:otherwise>
									</xsl:choose>
								</a>
							</xsl:when>
							<!-- 221 = AssociatedFile,  231 = Associated File -->
							<xsl:when test="@id=221  or  @id=231">
								<a href="{file_fetch_url}">
									<xsl:value-of select="file_orig_name"/>
								</a>
							</xsl:when>
							<xsl:otherwise>
								<xsl:value-of select="text()"/>
							</xsl:otherwise>
						</xsl:choose>
					</div>
				</div>
			</xsl:for-each>

			<!-- 264 = Research Project -->
			<xsl:if test="pointer[@id=264]/@name != ''">
				<div class="detailRow">
					<div class="detailType">
						<xsl:value-of select="pointer[@id=264]/@name"/>
					</div>
					<div class="detail">
						<table>
							<xsl:apply-templates select="pointer[@id=264]"/>
						</table>
					</div>
				</div>
			</xsl:if>

			<xsl:if test="pointer[@id=267]/@name != ''">
				<div class="detailRow">
					<div class="detailType">
						<xsl:value-of select="pointer[@id=267]/@name"/>
					</div>
					<div class="detail">
						<xsl:apply-templates select="pointer[@id=267]"/>
					</div>
				</div>
			</xsl:if>

			<xsl:if test="notes != ''">
				<div class="detailRow">
					<div class="detailType">Notes</div>
					<div class="detail">
						<xsl:value-of select="notes"/>
					</div>
				</div>
			</xsl:if>

			<xsl:if test="detail[@id=222 or @id=223 or @id=224]">
				<div class="detailRow">
					<div class="detailType">Images</div>
					<div class="detail">
						<!-- 222 = Logo image,  223 = Thumbnail,  224 = Images -->
						<xsl:for-each select="detail[@id=222 or @id=223 or @id=224]"><a href="{file_fetch_url}">
								<img src="{file_thumb_url}" border="0" class="thumbnail"/>
							</a> &#160;&#160;
						</xsl:for-each>
					</div>
				</div>
			</xsl:if>
	</xsl:template>


	<xsl:template name="paragraphise">
		<xsl:param name="text"/>
		<xsl:for-each select="str:split($text, '&#xa;&#xa;')">
			<p>
				<xsl:value-of select="."/>
			</p>
		</xsl:for-each>
	</xsl:template>
	<xsl:template name="minimise_text">
		<xsl:param name="sstring"/>
		<xsl:choose>
			<xsl:when test="string-length($sstring) &gt; 130">
				<span title="{$sstring}"><xsl:value-of select="substring($sstring, 0, 130)"/> ... </span>
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="$sstring"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>


</xsl:stylesheet>

