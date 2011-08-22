<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
<xsl:template name="fieldwork_opportunity" match="record[type/@id=77]">
<div id="{id}" class="record  L{@depth}">
                <a target="_new">
                    <xsl:attribute name="href">rectype_renderer/<xsl:value-of select="id"/></xsl:attribute>
                    <xsl:value-of select="detail[@id=160]"/><!-- Title -->
                </a>,
				<xsl:value-of select="detail[@id=277]"/><!-- Country -->
                (<xsl:for-each select="detail[@id=159]"><!-- Year -->
                    <xsl:value-of select="."/>
                    <xsl:if test="position() != last()">,
						</xsl:if>
                </xsl:for-each>)
                <br/>
                <xsl:for-each select="detail[@id=249]/record"><!-- Person -->
                    <xsl:value-of select="title"/>
                    <xsl:if test="position() != last()">,
						</xsl:if>
                </xsl:for-each>
                <br/>
                <xsl:value-of select="detail[@id=303]"/><!-- Summary for web -->
                <br/>
				<xsl:if test="detail[@id=223]"><!-- thumbnail -->
					<img>
						<xsl:attribute name="src"><xsl:value-of select="detail[@id=223]/file/thumbURL"/></xsl:attribute>
					</img>
				</xsl:if>
</div>
</xsl:template>
</xsl:stylesheet>
