<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:include href="myvariables.xsl"/>
	<xsl:include href="util.xsl"/>
	<xsl:include href="factoid.xsl"/>

	<!--FIXME: this should be factored a bit -->
	<xsl:template match="/">

		<xsl:variable name="record" select="hml/records/record[@depth=0]"/>

		<xsl:apply-templates select="$record">
			<xsl:with-param name="type">
				<xsl:call-template name="getRecordTypeClassName">
					<xsl:with-param name="record" select="$record"/>
				</xsl:call-template>
			</xsl:with-param>
		</xsl:apply-templates>

	</xsl:template>


	<xsl:template match="record[type/@conceptID='2-5'] |
	                     record[type/@conceptID='2-11'][detail[@conceptID='2-30'] = 'image']">
		<xsl:param name="type"/>

		<div class="picbox-container">

			<div class="picbox-close">
				<a href="#" onclick="Boxy.get(this).hide(); return false;">[close]</a>
			</div>
			<div class="clearfix"/>

			<xsl:if test="starts-with(detail[@conceptID='2-29'], 'image') or type/@conceptID = '2-11'">
				<div class="picbox-image">
					<img style="max-width:'800px';max-height:'400px';">
						<xsl:attribute name="src">
							<xsl:call-template name="getFileURL">
								<xsl:with-param name="file" select="detail[@conceptID='2-38']"/>
								<xsl:with-param name="size" select="'wide'"/>
							</xsl:call-template>
						</xsl:attribute>
					</img>
				</div>
			</xsl:if>

			<div class="picbox-heading balloon-{$type}">
				<h2><xsl:value-of select="detail[@conceptID='2-1']"/></h2>
			</div>

			<div class="picbox-content">

				<xsl:if test="starts-with(detail[@conceptID='2-29'], 'audio') or starts-with(detail[@conceptID='2-29'], 'video')">

					<xsl:variable name="elem">
						<xsl:choose>
							<xsl:when test="starts-with(detail[@conceptID='2-29'], 'audio')">
								<xsl:text>audio</xsl:text>
							</xsl:when>
							<xsl:when test="starts-with(detail[@conceptID='2-29'], 'video')">
								<xsl:text>video</xsl:text>
							</xsl:when>
						</xsl:choose>
					</xsl:variable>

					<div class="picbox-flash">
						<div id="{$elem}">
							<a>
								<xsl:attribute name="href">
									<xsl:call-template name="getFileURL">
										<xsl:with-param name="file" select="detail[@conceptID='2-38']"/>
									</xsl:call-template>
								</xsl:attribute>
							</a>
						</div>
					</div>
				</xsl:if>

				<xsl:if test="detail[@conceptID='2-4']">
					<p>
						<xsl:value-of select="detail[@conceptID='2-4']"/>
					</p>
				</xsl:if>
				<p class="attribution">
					<xsl:call-template name="makeMediaAttributionStatement">
						<xsl:with-param name="record" select="."/>
					</xsl:call-template>
				</p>
				<p>
					<xsl:if test="type/@conceptID='2-11'">
						<xsl:text>This is a high-resolution image - to view in more detail, go to the </xsl:text>
					</xsl:if>
					<a href="{id}">full record &#187;</a>
				</p>
				<div class="clearfix"></div>
			</div>
		</div>
	</xsl:template>


</xsl:stylesheet>
