<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    <xsl:template name="person" match="reference[rectype/@id=55]">
        <tr>
            <td>
				<xsl:if test="detail[@id=223]"><!-- thumbnail -->
					<div style="float: right;">
						<xsl:for-each select="detail[@id=223]">
							<img src="{file_thumb_url}"/>
						</xsl:for-each>
					</div>
				</xsl:if>
                <a target="_new" href="rectype_renderer/{id}">
                    <xsl:value-of select="title"/>
                </a>
				<br/>
				<xsl:if test="detail[@id=255]">
					<xsl:for-each select="detail[@id=255]"><!-- role -->
						<xsl:value-of select="text()"/>
						<xsl:if test="position() != last()">,&#160; </xsl:if>
					</xsl:for-each>
				</xsl:if>
				<xsl:if test="detail[@id=191]">
					<br/>
					<b>Teaching and research interests:&#160;</b>
					<xsl:value-of select="detail[@id=191]"/>
				</xsl:if>
				<br/><br/>
            </td>
        </tr>
    </xsl:template>
</xsl:stylesheet>
