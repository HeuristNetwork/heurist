<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
<xsl:output method="xml" omit-xml-declaration="yes"/>

<xsl:template name="quoter">
  <xsl:param name="outputString"/>
  <xsl:choose>
    <xsl:when test="contains($outputString,'&quot;')">
   
      <xsl:value-of select=
        "concat(substring-before($outputString,'&quot;'), '&amp;quot;')"/>
      <xsl:call-template name="quoter">
        <xsl:with-param name="outputString" select="substring-after($outputString,'&quot;')"/>
        <xsl:with-param name="target" select="'&quot;'"/>
        <xsl:with-param name="replacement" select="'&amp;quot;'"/>
      </xsl:call-template>
    </xsl:when>
    <xsl:otherwise>
      <xsl:value-of select="$outputString"/>
    </xsl:otherwise>
  </xsl:choose>
</xsl:template>

<xsl:template match="text()">
  <xsl:call-template name="quoter">
  <xsl:with-param name="outputString" select="."/>
  </xsl:call-template>
</xsl:template>

<xsl:template match="@*|*">
  <xsl:copy>
    <xsl:apply-templates select="@*|node()"/>
  </xsl:copy>
</xsl:template>

</xsl:stylesheet>
