<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    <xsl:template name="conference" match="record[type/@id=49]">
        <xsl:param name="style"/>
        <tr>
            <!--td><xsl:value-of select="detail[@id=205]"/></td-->
            <!-- Discipline -->
            <!--td><xsl:value-of select="detail[@id=232]"/></td-->
            <!-- Event type -->
            <td>
                <span style="font-weight: bold;"><xsl:value-of select="detail[@id=160]"/></span>
                <!-- Title -->
            </td>
        </tr>
        <tr>

            <td>
                <xsl:choose>
                    <xsl:when test="$style = 'compressed'"> </xsl:when>
                    <xsl:otherwise>
                        <xsl:value-of select="detail[@id=181]"/>
                        <br/>
                        <!-- Conference location -->
                    </xsl:otherwise>
                </xsl:choose>
                <xsl:value-of select="detail[@id=177]/text()"/><!-- Start Date -->
                <xsl:if test="detail[@id=178]">
                 - 
                 <xsl:value-of select="detail[@id=178]/text()"/><!-- End date -->
                </xsl:if>
<xsl:if test="url">
                <br/>
          <a><xsl:attribute name="href"><xsl:value-of select="url"/></xsl:attribute><xsl:value-of select="url"/></a>
</xsl:if>
                <br/><br/>
            </td>
        </tr>
    </xsl:template>
</xsl:stylesheet>
