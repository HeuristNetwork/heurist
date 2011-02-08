<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    <xsl:template name="research_project" match="reference[rectype/@id=63]">
        <tr>
            <td>
				<xsl:if test="detail[@id=223]"><!-- thumbnail -->
					<div style="float: right;">
						<xsl:for-each select="detail[@id=223]">
							<img src="{file_thumb_url}"/>
						</xsl:for-each>
					</div>
				</xsl:if>
                <a target="_new">
                    <xsl:attribute name="href">rectype_renderer/<xsl:value-of select="id"/></xsl:attribute>
                    <xsl:value-of select="detail[@id=160]"/><!-- Title -->
                </a>&#160;
                (<xsl:value-of select="detail[@id=177]/text()"/><!-- Start Date -->
                  - <xsl:value-of select="detail[@id=178]/text()"/><!-- End Date -->)
                <br/>
                <xsl:for-each select="pointer[@id=249]"><!-- Person -->
                    <xsl:value-of select="title"/>
                    <xsl:if test="position() != last()">,
						</xsl:if>
                </xsl:for-each>
                <br/>
                <xsl:value-of select="detail[@id=303]"/><!-- Summary for web -->
                <br/><br/>
            </td>
        </tr>
    </xsl:template>
</xsl:stylesheet>
