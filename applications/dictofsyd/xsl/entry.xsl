<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:xi="http://www.w3.org/2001/XInclude"
                xmlns:str="http://exslt.org/strings"
                exclude-result-prefixes="xi str"
                version="1.0">

	<xsl:include href="urlmap.xsl"/>

<!--
	<xsl:include href="wordml2TEI.xsl"/>
	<xsl:include href="tei_to_html_basic2.xsl"/>
	<xsl:include href="entry_index.xsl"/>
-->

	<xsl:template name="xmldoc" match="record[type/@conceptID='2-13']">

		<!-- content of document -->
		<xsl:variable name="file" select="detail[@conceptID='1084-81' or @conceptID='2-38']"/>

		<div id="content-left-col">

				<xsl:choose>
				<xsl:when test="$file/file/nonce">

					<xsl:variable name="fname" select="concat($urlbase,'php/wml2html.php?id=',$file/file/nonce)"/>
					<xsl:copy-of select="document($fname)/*"/>

					<!-- xsl:value-of select="$fname"/>
					 xsl:apply-templates select="$file/file/content"/ -->
				</xsl:when>
<!--
				<xsl:when test="$file">
					<div id="tei">
					<xi:include>
						<xsl:attribute name="href">
							<xsl:call-template name="getFileURL">
								<xsl:with-param name="file" select="$file"/>
							</xsl:call-template>
						</xsl:attribute>
					</xi:include>
					</div>
				</xsl:when>
-->
				</xsl:choose>


			<div id="pagination">
				<a id="previous" href="#">&#171; previous</a>
				<a id="next" href="#">next &#187;</a>
			</div>
		</div>

		<div id="content-right-col">

			<xsl:variable name="annotation_rec_ids" select="reversePointer[@conceptID='2-42']"/>
			<xsl:call-template name="addAnnotations">
				<xsl:with-param name="matches" select="/hml/records/record[@depth=1 and id=$annotation_rec_ids]"/>
			</xsl:call-template>

		</div>

		<div class="clearfix"/>

	</xsl:template>


	<xsl:template name="addAnnotations">
		<xsl:param name="matches"/>

		<xsl:call-template name="setupRefs"/>

		<xsl:for-each select="$matches">


<!--
	<xsl:variable name="temp1" select="detail[@conceptID='2-1']"/>
	<div>
		<xsl:value-of select="$temp1"/>
	</div>
-->
			<xsl:sort select="str:split(detail[@conceptID='2-46'], ',')[1]" data-type="number"/>
			<xsl:sort select="str:split(detail[@conceptID='2-46'], ',')[2]" data-type="number"/>
			<xsl:sort select="str:split(detail[@conceptID='2-46'], ',')[3]" data-type="number"/>
			<xsl:sort select="str:split(detail[@conceptID='2-46'], ',')[4]" data-type="number"/>
			<xsl:sort select="detail[@conceptID='2-44']" data-type="number"/>

			<xsl:variable name="annotation_type" select="detail[@conceptID='1084-96']"/>

			<xsl:choose>
				<xsl:when test="contains($annotation_type,'Multimedia')">

					<xsl:variable name="mm_rec_id" select="detail[@conceptID='2-13']"/>
					<xsl:variable name="annotated_recource" select="/hml/records/record[@depth=2 and id=$mm_rec_id]"/>

					<xsl:if test="$annotated_recource[type/@conceptID='2-5' or type/@conceptID='2-11']">
						<xsl:choose>
							<xsl:when test="starts-with($annotated_recource/detail[@conceptID='2-29'], 'image') or $annotated_recource/detail[@conceptID='2-30'] = 'image'">
								<div class="annotation-img annotation-id-{id}">
									<a href="{$annotated_recource/id}" class="popup preview-{$annotated_recource/id}c{id}">
										<img>
											<xsl:attribute name="src">
												<xsl:call-template name="getFileURL">
													<xsl:with-param name="file" select="$annotated_recource/detail[@conceptID='2-38']"/>
													<xsl:with-param name="size" select="'thumbnail'"/> <!-- ARTEM was small -->
												</xsl:call-template>
											</xsl:attribute>
										</img>
									</a>
								</div>
							</xsl:when>
							<xsl:when test="starts-with($annotated_recource/detail[@conceptID='2-29'], 'audio')">
								<div class="annotation-img annotation-id-{id}">
									<a href="{$annotated_recource/id}" class="popup preview-{$annotated_recource/id}c{id}">
										<img src="{$urlbase}images/img-entity-audio.jpg"/>
									</a>
								</div>
							</xsl:when>
							<xsl:when test="starts-with($annotated_recource/detail[@conceptID='2-29'], 'video')">
								<div class="annotation-img annotation-id-{id}">
									<a href="{$annotated_recource/id}" class="popup preview-{$annotated_recource/id}c{id}">
										<img>
											<xsl:attribute name="src">
												<xsl:call-template name="getFileURL">
													<xsl:with-param name="file" select="$annotated_recource/detail[@conceptID='2-39']"/>
													<xsl:with-param name="size" select="'small'"/>
												</xsl:call-template>
											</xsl:attribute>
										</img>
									</a>
								</div>
							</xsl:when>
						</xsl:choose>
						<xsl:call-template name="addRef">
							<xsl:with-param name="ref" select="."/>
							<xsl:with-param name="hide">true</xsl:with-param>
						</xsl:call-template>
					</xsl:if>
				</xsl:when>
				<xsl:otherwise>
					<xsl:call-template name="addRef">
						<xsl:with-param name="ref" select="."/>
					</xsl:call-template>
				</xsl:otherwise>
			</xsl:choose>
		</xsl:for-each>
	</xsl:template>


	<xsl:template name="setupRefs">
		<script type="text/javascript">
			if (! window["refs"]) {
				window["refs"] = [];
			}
		</script>
	</xsl:template>

	<xsl:template name="addRef">
		<xsl:param name="ref"/>
		<xsl:param name="hide"/>

		<!--
		<xsl:variable name="temp1" select="detail[@conceptID='2-1']"/>
		<div>
			<xsl:value-of select="$temp1"/>
		</div>
		-->

		<script type="text/javascript">
			if (window["refs"]) {
				window.refs.push( {
					startElems : [ <xsl:value-of select="detail[@conceptID='2-46']"/> ],
					endElems : [ <xsl:value-of select="detail[@conceptID='2-47']"/> ],
					startWord :
						<xsl:choose>
							<xsl:when test="detail[@conceptID='2-44']"><xsl:value-of select="detail[@conceptID='2-44']"/></xsl:when>
							<xsl:otherwise>null</xsl:otherwise>
						</xsl:choose>,
					endWord :
						<xsl:choose>
							<xsl:when test="detail[@conceptID='2-45']"><xsl:value-of select="detail[@conceptID='2-45']"/></xsl:when>
							<xsl:otherwise>null</xsl:otherwise>
						</xsl:choose>,
					<xsl:if test="$hide='true'">
					hide : true,
					</xsl:if>
					<xsl:if test="detail[@conceptID='2-13']">

					<xsl:variable name="target_rec_id" select="detail[@conceptID='2-13']"/>

					targetID : <xsl:value-of select="$target_rec_id"/>,
					href : "../<xsl:call-template name="getPath"><xsl:with-param name="id" select="$target_rec_id"/></xsl:call-template>",
					</xsl:if>
					recordID : "<xsl:value-of select="id"/>"
				} );
			}
		</script>
	</xsl:template>


	<xsl:template match="record[type/@conceptID='2-13']" mode="sidebar">

		<div id="chapters">
			<div id="chapters-top"/>
			<div id="chapters-middle">
				<h3>Chapters</h3>
				<!-- document index generated here -->
			</div>
			<div id="chapters-bottom"/>
		</div>

		<div id="connections">
			<h3>Connections</h3>
			<xsl:call-template name="relatedEntitiesByType"/>
			<xsl:call-template name="connections"/>
		</div>

	</xsl:template>

</xsl:stylesheet>
