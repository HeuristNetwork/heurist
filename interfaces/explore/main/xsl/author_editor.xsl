<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">


	<xsl:template name="author_editor" match="reference[reftype/@id=75]">
		<table>
			<tr>
				<td>
					<xsl:if test="detail[@id=223]">
						<!-- thumbnail -->
						<div style="float: left;">
							<xsl:for-each select="detail[@id=223]">
								<img src="{file_fetch_url}" vspace="10" hspace="10"/>
							</xsl:for-each>
						</div>
					</xsl:if>

					<h1><xsl:value-of select="title"/></h1>
					<p><xsl:value-of select="notes"/></p>

					<br/>

					<xsl:if test="detail[@id=191]">
						<xsl:call-template name="paragraphise">
							<xsl:with-param name="text">
								<xsl:value-of select="detail[@id=191]"/>
							</xsl:with-param>
						</xsl:call-template>
					</xsl:if>
					<br/>
					<br/>
				</td>
			</tr>
		</table>
	</xsl:template>

	<!-- xsl:template name="person-summary" match="reference[reftype/@id=55]">
		<table>
			<tr>
				<td>
					<xsl:if test="detail[@id=223]">

						<div style="float: left;">
							<xsl:for-each select="detail[@id=223]">
								<img src="{file_thumb_url}" vspace="10" hspace="10"/>
							</xsl:for-each>
						</div>
					</xsl:if>

					<h1><xsl:value-of select="title"/></h1>
				</td>
			</tr>
		</table>
	</xsl:template -->

</xsl:stylesheet>
