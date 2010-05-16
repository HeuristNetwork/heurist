<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    <xsl:template name="conference_paper" match="reference[reftype/@id=31]">
        <tr>
            <td>
                 <xsl:choose>
                  <xsl:when test="string-length(url)">
                   <a>
                    <xsl:attribute name="href"><xsl:value-of select="url"/></xsl:attribute>
                    <b><xsl:value-of select="detail[@id=160]"/></b>
                   </a>
                  </xsl:when>
                  <xsl:otherwise><b><xsl:value-of select="detail[@id=160]"/></b></xsl:otherwise>
                 </xsl:choose>
                <xsl:if test="detail[@id=159]"><!-- publication year -->
                 &#160;(<xsl:value-of select="detail[@id=159]"/>)
                </xsl:if>
                &#160;
                <br/>
                <xsl:for-each select="pointer[@type=158]"><!-- creator -->
                 <xsl:choose>
                  <xsl:when test="position() = 1"></xsl:when>
                  <xsl:when test="position() != last()">,&#160; </xsl:when>
                  <xsl:otherwise> and&#160; </xsl:otherwise>
                 </xsl:choose>
                 <xsl:value-of select="title"/>
                </xsl:for-each>
                <br/>
                <i>
                 <xsl:if test="pointer[@type=217]"><!-- conference ref -->
                  Paper presented at <xsl:value-of select="pointer[@type=217]/title"/>
                 </xsl:if>
                </i>
                <br/>
                <br/>
            </td>
        </tr>
    </xsl:template>
</xsl:stylesheet>
