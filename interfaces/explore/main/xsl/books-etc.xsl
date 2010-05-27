<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">


	<xsl:template name="note" match="reference[reftype/@id=2]">
		<table width="100%">
			<tr>
				<td width="100%">
				<xsl:value-of select="notes"/>
				</td>
			</tr>
		</table>


	</xsl:template>

	<xsl:template name="book" match="reference[reftype/@id=5]">
		<table width="100%">
			<tr>
				<td width="100%">






							<xsl:if test="detail[@id=223]">
								<!-- thumbnail -->
								<div style="float: left;">
									<xsl:for-each select="detail[@id=223]">
										<img src="{file_fetch_url}" vspace="10" hspace="10" align="left"/>
									</xsl:for-each>

									<xsl:for-each select="detail[@id=191]">
										<xsl:call-template name="paragraphise">
											<xsl:with-param name="text">
												<xsl:value-of select="."/>
											</xsl:with-param>
										</xsl:call-template>
									</xsl:for-each>
								</div>
							</xsl:if>


					<br/>
					<xsl:if test="detail[@id=255]">
						<xsl:for-each select="detail[@id=255]">
							<!-- role -->
							<em><xsl:value-of select="text()"/></em>
							<xsl:if test="position() != last()">,&#160; </xsl:if>
						</xsl:for-each>
					</xsl:if>
					<xsl:if test="detail[@id=266]">
						<p><xsl:value-of select="detail[@id=266]"/></p>
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
