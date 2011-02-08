<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

<!--
<xsl:template name="td_flatten">
<xsl:param name="x"/>
<xsl:choose>
 <xsl:when test="$x/*">
  <xsl:element name="{name()}">
   <xsl:copy-of select="@*"/>
   <xsl:for-each select="*|text()">
    <xsl:call-template name="td_flatten"><xsl:with-param name="x" select="."/></xsl:call-template>
   </xsl:for-each>
  </xsl:element>
 </xsl:when>
 <xsl:otherwise><xsl:value-of select="normalize-space(.)"/></xsl:otherwise>
</xsl:choose>
</xsl:template>

<xsl:template match="/html/*/*"></xsl:template>

<xsl:template match="/html/body/*">
document.write('  <xsl:value-of select="." />  ');
</xsl:template>

<xsl:template match="/html/body/table">
document.write('<table>');
<xsl:for-each select="tr">document.write('<tr><xsl:apply-templates/></tr>');
</xsl:for-each>document.write('</table>');
</xsl:template>

<xsl:template match="/html/body/table/tr/td">
<xsl:call-template name="td_flatten"><xsl:with-param name="x" select="."/></xsl:call-template>
</xsl:template>
-->

<xsl:template match="text()">
<xsl:call-template name="quote_escaper"><xsl:with-param name="str" select="normalize-space(.)"/></xsl:call-template>
</xsl:template>

<xsl:template name="quote_escaper">
 <xsl:param name="str"/>
 <xsl:choose>
  <xsl:when test="contains($str,&quot;'&quot;)">
   <xsl:value-of select="concat(substring-before($str,&quot;'&quot;), &quot;\'&quot;)"/>
   <xsl:call-template name="quote_escaper">
    <xsl:with-param name="str" select="substring-after($str, &quot;'&quot;)"/>
   </xsl:call-template>
  </xsl:when>
  <xsl:otherwise>
   <xsl:value-of select="$str"/>
  </xsl:otherwise>
 </xsl:choose>
</xsl:template>

<xsl:template match="head" />


<xsl:template match="table" >
rows = 0;
document.write('<table style="font-size: 90%; line-height: 1.4em;" class="jstable">');<xsl:apply-templates/>
if (rows == 0) document.write('<tr><td><i>There are currently no items to display.</i></td></tr>');
document.write('</table>');
</xsl:template>

<xsl:template match="tr">
document.write('  <tr><xsl:apply-templates/></tr>'); ++rows;</xsl:template>

<xsl:template match="table/text()">
 <xsl:choose>
  <xsl:when test="normalize-space(.)"><xsl:call-template name="line_per_row"><xsl:with-param name="str" select="."/></xsl:call-template></xsl:when>
 </xsl:choose>
</xsl:template>

<xsl:template name="line_per_row">
 <xsl:param name="str"/>
 <xsl:choose>
  <xsl:when test="contains($str,'&#10;')">
document.write('<tr><td><xsl:call-template name="quote_escaper"><xsl:with-param name="str" select="substring-before($str,'&#10;')"/></xsl:call-template></td></tr>'); ++rows;
<xsl:call-template name="line_per_row"><xsl:with-param name="str" select="substring-after($str,'&#10;')"/></xsl:call-template>
  </xsl:when>
  <xsl:otherwise>
   <xsl:if test="$str">
document.write('<tr><tx><xsl:call-template name="quote_escaper"><xsl:with-param name="str" select="$str"/></xsl:call-template></tx></tr>'); ++rows;
   </xsl:if>
  </xsl:otherwise>
 </xsl:choose>
</xsl:template>

<xsl:template match="td//a[starts-with(@href,'rectype_renderer')]" priority="1">
 <a>
  <xsl:copy-of select="@*"/>
  <xsl:attribute name="href"><xsl:value-of select="concat('http://sylvester.acl.arts.usyd.edu.au/cocoon/heurist/',/html/body/@pub_id,'/', @href)"/></xsl:attribute>
  <xsl:attribute name="onclick">window.open(href,\'\',\'scrollbars=1,resizable=yes, width=600,height=500\'); return false;</xsl:attribute>
  <xsl:apply-templates/>
 </a>
</xsl:template>
 <xsl:template match="td//a[starts-with(@href,'wiki')]" priority="2">
  <a>
   <xsl:copy-of select="@*"/>
   <xsl:attribute name="href"><xsl:value-of select="concat('http://sylvester.acl.arts.usyd.edu.au/cocoon/heurist/',/html/body/@pub_id,'/', @href)"/></xsl:attribute>
   <xsl:attribute name="onclick">window.open(href,\'\',\'scrollbars=1,resizable=yes, width=600,height=600\'); return false;</xsl:attribute>
   <xsl:apply-templates/>
  </a>
 </xsl:template>

<xsl:template match="tr//*">
<xsl:element name="{name()}">
   <xsl:for-each select="@*">
    <xsl:attribute name="{name()}"><xsl:call-template name="quote_escaper"><xsl:with-param name="str" select="."/></xsl:call-template></xsl:attribute>
   </xsl:for-each>
   <!-- <xsl:copy-of select="@*"/> -->
   <xsl:apply-templates/>
</xsl:element>
</xsl:template>
</xsl:stylesheet>
