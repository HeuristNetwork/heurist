<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    <xsl:output method="html"/>
    
    <xsl:template match="/">
        <html>
            <head></head>
            <body>
                <xsl:apply-templates select="export/references/reference"/>
            </body>
        </html>
    </xsl:template>
    
    <xsl:template match="reference">
        <p>
        <xsl:element name="a"><xsl:attribute name="href"><xsl:value-of select="url"/></xsl:attribute><xsl:value-of select="title"/></xsl:element>
        </p>
        <p>
            <xsl:value-of select="notes"/>
        </p>
    </xsl:template>

</xsl:stylesheet>
