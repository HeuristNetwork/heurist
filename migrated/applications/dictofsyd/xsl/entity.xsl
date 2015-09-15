<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:template name="entity" match="record[type/@conceptID='1084-25']">

		<!-- select="/hml/records/record[@depth=0]/relationship" -->
		<xsl:variable name="related" select="relationship"/> 

		<div class="list-left-col"/>
		<div class="list-right-col">

			<!-- dc.description -->
			<xsl:if test="detail[@conceptID='2-4']">
				<div class="list-right-col-content">
					<div class="entity-content">
						<p>
							<xsl:value-of select="detail[@conceptID='2-4']"/>
						</p>
					</div>
				</div>
			</xsl:if>

			<!-- default image: dos.main_image -->
			<xsl:if test="detail[@conceptID='2-61']">
				<xsl:variable name="main_img_id" select="detail[@conceptID='2-61']"/>
				<xsl:variable name="main_img" select="/hml/records/record[id=$main_img_id]"/>
				
				<a class="popup preview-{$main_img_id}" href="{$main_img_id}">
					<img class="entity-picture" height="180">
						<xsl:attribute name="alt"/><!-- FIXME -->
						<xsl:attribute name="src">
							<xsl:call-template name="getFileURL">
								<xsl:with-param name="file" select="$main_img/detail[@conceptID='2-38']"/>
								<xsl:with-param name="size" select="'medium'"/>
							</xsl:call-template>
						</xsl:attribute>
					</img>
				</a>
			</xsl:if>

			<xsl:variable name="factoids" select="/hml/records/record[@depth=1 and type/@conceptID='1084-26' and (detail[@conceptID='1084-86'] = current()/id or detail[@conceptID='1084-87'] = current()/id) ]"/>
			<xsl:variable name="factoids_withgeo" select="$factoids[detail[@conceptID='2-28' or @conceptID='2-10']]"/>
				
			<!-- map -->
			<xsl:if test="$factoids_withgeo">
				
				<xsl:variable name="geo" select="$factoids_withgeo/detail[@conceptID='2-28']"/>
				<xsl:variable name="base" select="current()"/> 
				
				<div id="map">
					<xsl:attribute name="class">
						<xsl:text>entity-map</xsl:text>
						<xsl:if test="not($geo)">
							<xsl:text> hide</xsl:text>
						</xsl:if>
					</xsl:attribute>
				</div>
				<div class="clearfix"/>
				<div id="timeline-zoom">
					<xsl:if test="not($factoids_withgeo/detail[@conceptID='2-10'])">
						<xsl:attribute name="class">hide</xsl:attribute>
					</xsl:if>
				</div>
				<div id="timeline"> <!--  style="height: 52px;" -->
					<xsl:attribute name="class">
						<xsl:text>entity-timeline</xsl:text>
						<xsl:if test="not($factoids_withgeo/detail[@conceptID='2-10'])">
							<xsl:text> hide</xsl:text>
						</xsl:if>
					</xsl:attribute>
				</div>
	
				
				<script type="text/javascript">
					
					var mapdata = {
						//mini: true,
						timemap: [ 
							{
								type: "basic",	
								options: { items: [
								<xsl:for-each select="$factoids_withgeo">
											<xsl:variable name="geoobj" select="current()/detail[@conceptID='2-28']/geo"/>
											getTimeMapItem(<xsl:value-of select="$base/id"/>,
														"<xsl:value-of select="$base/detail[@conceptID='1084-75']"/>",
														'<xsl:call-template name="getRoleName">
																<xsl:with-param name="factoid" select="current()"/>
																<xsl:with-param name="base_rec_id" select="$base/id"/>
														</xsl:call-template>',
														'<xsl:value-of select="current()/detail[@conceptID='2-10']/raw"/>', 
														'<xsl:value-of select="current()/detail[@conceptID='2-11']/raw"/>', 
														'<xsl:value-of select="$geoobj/type"/>', 
														'<xsl:value-of select="$geoobj/wkt"/>')
									<xsl:if test="position()!=last()">
										<xsl:text>,</xsl:text>
									</xsl:if>									
								</xsl:for-each>
									] }
							}
							/*,{
							type: "kml",
							options: {
								//url: "http://dictionaryofsydney.org/kml/full/rename/<xsl:value-of select="id"/>"
								url: "http://dictionaryofsydney.org/kml/full/<xsl:value-of select="id"/>.kml"
								}
							}*/
						],
						layers: [],
						count_mapobjects: <xsl:value-of select="count($geo)"/>
					};
					setTimeout(function(){ initMapping("map", mapdata); }, 500);
					
				</script>
			</xsl:if>
			
			<div class="clearfix"/>

			
			<!-- factoids  --> 
			<xsl:if test="$factoids">
				<xsl:call-template name="factoids">
					<xsl:with-param name="factoid_recs" select="$factoids"/>
				</xsl:call-template>
			</xsl:if>
			
			
			

		</div>
		<div class="clearfix"/>

		<!-- related entries -->
		<xsl:for-each select="$related">
			
			<xsl:variable name="related_recid" select="@relatedRecordID"/>
			<xsl:variable name="related_rec" select="/hml/records/record[id=$related_recid]"/>
			
			<xsl:if test="$related_rec/type[@conceptID='2-13']">
			
			<xsl:variable name="content_class">
				<xsl:choose>
					<xsl:when test="position() = last()">list-right-col-content</xsl:when>
					<xsl:otherwise>list-right-col-content margin</xsl:otherwise>
				</xsl:choose>
			</xsl:variable>
			<div class="list-left-col list-entry" title="Entries"/>
			<div class="list-right-col">
				<div class="entity-entry">
					<div class="list-right-col-heading">
						<h2>
							<xsl:value-of select="$related_rec/detail[@conceptID='2-1']"/>
						</h2>
						<xsl:if test="$related_rec/detail[@conceptID='1084-90']">
							<span class="contributor">
								<xsl:text>by </xsl:text>
								<xsl:call-template name="makeAuthorList">
									<xsl:with-param name="authors" select="/hml/records/record[id=$related_rec/detail[@conceptID='1084-90']]"/>
									<xsl:with-param name="year" select="$related_rec/detail[@conceptID='2-9']/year"/>
								</xsl:call-template>
							</span>
						</xsl:if>
						<span class="copyright">
							<xsl:call-template name="makeLicenseIcon">
								<xsl:with-param name="record" select="$related_rec"/>
							</xsl:call-template>
						</span>
					</div>
					<div class="{$content_class}">
						<p>
							<xsl:value-of select="$related_rec/detail[@conceptID='2-4']"/>
							<br/><a href="{$related_recid}">more &#187;</a>
						</p>
					</div>
				</div>
			</div>
				
			</xsl:if>
		</xsl:for-each>
		
		
		<div class="clearfix"/>

		<xsl:variable name="related_recs" select="/hml/records/record[@depth=1 and
			id = current()/relationship/@relatedRecordID]"/>

		<!-- new structure - to handle relocated demographic links -->

		<xsl:variable name="links_of" select="$related_recs[type/@conceptID='2-2']"/>
		
		<xsl:for-each select="$links_of">
			
			<xsl:variable name="substring-test">demographics</xsl:variable>
			
			<!-- following gymnastics to deal with the fact that XSL 1.0 has no ends-with function -->
			<xsl:if test="substring(detail[@conceptID='2-1'],(string-length(detail[@conceptID='2-1'])
				- string-length($substring-test)) + 1) = $substring-test">
				
				<xsl:variable name="content_class">
					<xsl:choose>
						<xsl:when test="position() = last()">list-right-col-content</xsl:when>
						<xsl:otherwise>list-right-col-content margin</xsl:otherwise>
					</xsl:choose>
				</xsl:variable>
				<div class="list-left-col list-link" title="Demographics"/>
				<div class="list-right-col">
					<div class="entity-entry">
						<div class="list-right-col-heading">
							<h2>
								<xsl:value-of select="detail[@conceptID='2-1']"/>
							</h2>
							<div class="{$content_class}">
								<p><a href="{url}" target="_blank">more &#187;</a></p>
							</div>
						</div>
					</div>
					
				</div>
			</xsl:if>
		</xsl:for-each>
		
		<!-- end new structure -->		

		<xsl:variable name="related_non_entities" select="/hml/records/record[@depth=1 and
			type/@conceptID!='1084-25' and relationship/@relatedRecordID = current()/id]"/>
		
		<!-- images of this entity -->
		<xsl:variable name="images_of" select="$related_non_entities[type/@conceptID='2-5'][starts-with(detail[@conceptID='2-29'], 'image')][not(id=current()/detail[@conceptID='2-61'])]"/>
		<xsl:if test="$images_of">
			<div class="list-left-col list-image" title="Pictures"></div>
			<div class="list-right-col">
				<div class="list-right-col-content entity-thumbnail">
					<xsl:for-each select="$images_of">
						<xsl:if test="position() > 4 and position() mod 4 = 1">
							<div class="clearfix"/>
						</xsl:if>
						<div>
							<xsl:if test="position() > 3">
								<xsl:attribute name="class">
									<xsl:choose>
										<xsl:when test="position() = 4">no-right-margin</xsl:when>
										<xsl:when test="position() > 4 and position() mod 4 = 0">top-margin no-right-margin</xsl:when>
										<xsl:otherwise>top-margin</xsl:otherwise>
									</xsl:choose>
								</xsl:attribute>
							</xsl:if>
							<a href="{id}" class="popup preview-{id}">
								<img class="thumbnail">
									<xsl:attribute name="alt"/><!-- FIXME -->
									<xsl:attribute name="src">
										<xsl:call-template name="getFileURL">
											<xsl:with-param name="file" select="detail[@conceptID='2-38']"/>
											<xsl:with-param name="size" select="'thumbnail'"/>
										</xsl:call-template>
									</xsl:attribute>
								</img>
							</a>
						</div>
					</xsl:for-each>
				</div>
			</div>
			<div class="clearfix"/>
		</xsl:if>

		<!-- audio of this entity -->
		<xsl:variable name="audio_of" select="$related_non_entities[type/@conceptID='2-5'][starts-with(detail[@conceptID='2-29'], 'audio')]"/>
		<xsl:if test="$audio_of">
			<div class="list-left-col list-audio" title="Sound"></div>
			<div class="list-right-col">
				<div class="list-right-col-audio">
					<xsl:for-each select="$audio_of">
						<a href="{id}" class="popup preview-{id}">
							<img src="{$urlbase}images/img-entity-audio.jpg" alt=""/><!-- FIXME -->
						</a>
					</xsl:for-each>
				</div>
			</div>
			<div class="clearfix"/>
		</xsl:if>

		<!-- videos of this entity -->
		<xsl:variable name="video_of" select="$related_non_entities[type/@conceptID='2-5'][starts-with(detail[@conceptID='2-29'], 'video')]"/>
		<xsl:if test="$video_of">
			<div class="list-left-col list-video" title="Video"></div>
			<div class="list-right-col">
				<div class="list-right-col-content entity-thumbnail">
					<xsl:for-each select="$video_of">
						<a href="{id}" class="popup preview-{id}">
							<xsl:value-of select="detail[@id=160]"/>
						</a>
					</xsl:for-each>
				</div>
			</div>
			<div class="clearfix"/>
		</xsl:if>

		<!-- maps of this entity  ARTEM : NOT USED ???? 
		<xsl:variable name="maps_of" select="$related[@type='IsIn'][type/@id=103]"/>
		<xsl:if test="$maps_of">
			<div class="list-left-col list-map" title="Maps"></div>
			<div class="list-right-col">
				<div class="list-right-col-content">
					<xsl:for-each select="$maps_of">
						<a href="{id}" class="preview-{id}c{@id}">
							<xsl:value-of select="detail[@id=160]"/>
						</a>
					</xsl:for-each>
				</div>
			</div>
			<div class="clearfix"/>
		</xsl:if>
		-->

	</xsl:template>


	<xsl:template match="record[type/@conceptID='1084-25']" mode="sidebar">
		<div id="connections">
			<h3>Connections</h3>
			<xsl:call-template name="relatedEntitiesByType"/>
			<xsl:call-template name="connections"/>
		</div>
	</xsl:template>
	
</xsl:stylesheet>
