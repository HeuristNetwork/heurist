<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
<xsl:output method="html"/>
  <xsl:template match="/">
    <html>
      <body>
        <h1>output</h1>
        
        <xsl:apply-templates select="export/references/reference"/>
      </body>
    </html>
  </xsl:template>
  
  <xsl:template match="reference">
    
    <p><em><xsl:value-of select="detail[@type = 'Title']"/></em></p>
    
  </xsl:template>
       
</xsl:stylesheet>
