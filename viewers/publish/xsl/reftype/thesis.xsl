<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    <xsl:template name="thesis" match="reference[rectype/@id=13]">
        <tr>
            <td>
                <xsl:value-of select="title"/>
                &#160;
                <xsl:value-of select="detail[@id=243]"/><!-- Thesis type -->
                <br/>
                <xsl:value-of select="detail[@id=303]"/><!-- Summary for web -->
            </td>
        </tr>
    </xsl:template>
</xsl:stylesheet>
