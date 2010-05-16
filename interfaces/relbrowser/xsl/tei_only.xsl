<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
  <!-- stylesheeet that strips out everything but TEI.  Written by Maria Shvedova  18/09/2008 -->

  <xsl:template match="/">
    <xsl:copy-of select="//TEI"/>
  </xsl:template>

</xsl:stylesheet>
