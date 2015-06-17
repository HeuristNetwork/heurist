<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:template name="media" match="record[type/@conceptID='2-5']">
		
		<div id="resource">
			
			<!-- ARTEM
				<xsl:if test="detail[@conceptID='2-3']">
				<p>
				<xsl:value-of select="detail[@conceptID='2-3']"/>
				</p>
				</xsl:if>
			-->			
			<!-- dc.description -->
			<xsl:if test="detail[@conceptID='2-4']">
				<p>
					<xsl:value-of select="detail[@conceptID='2-4']"/>
				</p>
			</xsl:if>
			
			<xsl:if test="starts-with(detail[@conceptID='2-29'], 'image')">
				<img class="resource-image">
					<xsl:attribute name="src">
						<xsl:call-template name="getFileURL">
							<xsl:with-param name="file" select="detail[@conceptID='2-38']"/>
							<xsl:with-param name="size" select="'large'"/>
						</xsl:call-template>
					</xsl:attribute>
				</img>
			</xsl:if>
			
			<xsl:if test="starts-with(detail[@conceptID='2-29'], 'audio')">
				<div id="media"></div>
				<script type="text/javascript">
					DOS.Media.playAudio(
						"media",
						"<xsl:call-template name="getFileURL">
							<xsl:with-param name="file" select="detail[@conceptID='2-38']"/>
						</xsl:call-template>"
					);
				</script>
			</xsl:if>
			
			<xsl:if test="starts-with(detail[@conceptID='2-29'], 'video')">
				<div id="media"></div>
				<script type="text/javascript">
					DOS.Media.playVideo(
						"media",
						"<xsl:call-template name="getFileURL">
							<xsl:with-param name="file" select="detail[@conceptID='2-38']"/>
						</xsl:call-template>",
						"<xsl:call-template name="getFileURL">
							<xsl:with-param name="file" select="detail[@conceptID='2-39']"/>
						</xsl:call-template>"
					);
				</script>
			</xsl:if>
			
			<!-- dc.coverage.start - dc.coverage.finish>
				<xsl:if test="detail[@conceptID='2-10']">
				<p>
				<xsl:value-of select="detail[@conceptID='2-10']"/>
				<xsl:if test="detail[@conceptID='2-11']"> - <xsl:value-of select="detail[@conceptID='2-11']"/></xsl:if>
				</p>
				</xsl:if>
			-->
			
			<p class="attribution">
				<xsl:call-template name="makeMediaAttributionStatement">
					<xsl:with-param name="record" select="."/>
				</xsl:call-template>
			</p>
			
			<xsl:if test="detail[@conceptID='1084-94']">
				<p class="license">
					<xsl:call-template name="makeLicenseIcon">
						<xsl:with-param name="record" select="."/>
					</xsl:call-template>
				</p>
			</xsl:if>
			
		</div>
		
	</xsl:template>
	
	<xsl:template match="record[type/@conceptID='2-5']" mode="sidebar">
		<div id="connections">
			<h3>Connections</h3>
				<xsl:call-template name="relatedEntitiesByType"/> 
				<xsl:call-template name="connections"/>
		</div>
	</xsl:template>
	
</xsl:stylesheet>
