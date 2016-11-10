<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    <xsl:template name="conference_paper" match="record[type/@id=31]">
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
                <xsl:if test="detail[@id=159]"><!--  publication year -->
                 &#160;(<xsl:value-of select="detail[@id=159]"/>)
                </xsl:if>
                &#160;
                <br/>
                <xsl:for-each select="detail[@type=158]"><!-- creator -->
                 <xsl:choose>
                  <xsl:when test="position() = 1"></xsl:when>
                  <xsl:when test="position() != last()">,&#160; </xsl:when>
                  <xsl:otherwise> and&#160; </xsl:otherwise>
                 </xsl:choose>
                 <xsl:value-of select="record/title"/>
                </xsl:for-each>
                <br/>
                <i>
                 <xsl:if test="detail[@type=217]/record"><!-- conference ref -->
                  Paper presented at <xsl:value-of select="detail/record[@type=217]/title"/>
                 </xsl:if>
                </i>
                <br/>
                <br/>
            </td>
        </tr>
    </xsl:template>
</xsl:stylesheet>
