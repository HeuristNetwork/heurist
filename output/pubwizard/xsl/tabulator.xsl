<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    <xsl:output method="html"/>

    <xsl:template match="/export">
        <html>
            <head></head>
            <body>
                <p>Heurist query: <xsl:value-of select="query/@q"/></p>
                
                <table border="1" cellpadding="10">
                    
                    
                        <xsl:apply-templates select="references/reference"/>

                </table>
            </body>
        </html>
    </xsl:template>

    <xsl:template match="references/reference">
        <tr>
        <td bgcolor="#cococo"><xsl:value-of select="id"/></td>
            <td bgcolor="#cfcfcf"><xsl:value-of select="detail[@id=177]/day"/></td>
            <td bgcolor="#cfcfcf"><xsl:value-of select="detail[@id=177]/time"/></td>
        <td><xsl:value-of select="detail[@id=160]"/></td>
            </tr>
    </xsl:template>

</xsl:stylesheet>
