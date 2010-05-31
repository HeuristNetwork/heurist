<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">


	<xsl:template name="internet_bookmark" match="reference[reftype/@id=1]">
		<table width="100%">
			<tr>
				<td width="100%">

					<xsl:choose>
						<xsl:when test="string-length(url) > 0">

							<iframe id="relbrowserframe" frameborder="0"
								src="{url}" width="95%" height="900px"
								scrolling="auto"/>
							<p><xsl:value-of select="url"/></p>
						</xsl:when>
						<xsl:otherwise>
							<xsl:if test="detail[@id=223]">
								<!-- thumbnail -->
								<div style="float: left;">
									<xsl:for-each select="detail[@id=223]">
										<img src="{file_fetch_url}" vspace="10" hspace="10"/>
									</xsl:for-each>
								</div>
							</xsl:if>
						</xsl:otherwise>
					</xsl:choose>

					<br/>

					<xsl:value-of select="title"/>

					<xsl:if test="detail[@id=255]">
						<xsl:for-each select="detail[@id=255]">
							<!-- role -->
							<em><xsl:value-of select="text()"/></em>
							<xsl:if test="position() != last()">,&#160; </xsl:if>
						</xsl:for-each>
					</xsl:if>
					<xsl:if test="detail[@id=303]">
						<p><xsl:value-of select="detail[@id=303]"/></p>
					</xsl:if>
					<xsl:if test="detail[@id=198]">
						<p><xsl:for-each select="detail[@id=198]">
						<xsl:value-of select="."/><br></br>
						</xsl:for-each>
						</p>
					</xsl:if>
					<xsl:if test="detail[@id=191]">
						<p><xsl:value-of select="detail[@id=191]"/></p>
					</xsl:if>
					<br/>
					<br/>
				</td>
			</tr>
		</table>
	</xsl:template>

	

</xsl:stylesheet>
