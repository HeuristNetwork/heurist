<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="2.0">
 
<xsl:template match="/">
    <html>
    <head>
        <style type="text/css">
            
            body { 
            font-family:arial,helvetica,clean,sans-serif;
            font-size:11px;
            margin-top: 0px;
            background-color:#EFEFEF;
            text-align: center;
            }
        </style>
    </head>

    <body>
        
                    <xsl:apply-templates select="/export/rowcount"></xsl:apply-templates>
               
    </body>

    </html>
</xsl:template>
    
    <xsl:template match="rowcount">
        
        <xsl:value-of select="."></xsl:value-of> records found
		<input type="hidden" id="loaded" name="loaded" value="1" />
    </xsl:template>
</xsl:stylesheet>
