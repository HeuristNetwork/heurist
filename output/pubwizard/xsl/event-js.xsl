<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    <xsl:template name="event" match="reference[reftype/@id=64]">
document.writeln("        <tr>");
document.writeln("            <!--td><xsl:value-of select="detail[@id=205]"/></td--><!-- Discipline -->");
document.writeln("            <!--td><xsl:value-of select="detail[@id=232]"/></td--><!-- Event type -->");
document.writeln("            <td style="font-weight: bold;">");
document.writeln("                <xsl:call-template name="quoter"><xsl:with-param name="outputString" select="detail[@id=160]"/></xsl:call-template><!-- Title -->");
document.writeln("            </td>");
document.writeln("		</tr>");
document.writeln("		<tr>");
document.writeln("            <td>");
                <xsl:for-each select="container[reftype/@id=75]"><!-- Author/Editor -->
document.writeln("                    <xsl:value-of select="title"/>,");
                </xsl:for-each>
document.writeln("            </td>");
document.writeln("		</tr>");
document.writeln("		<tr>");
document.writeln("            <td><xsl:call-template name="quoter"><xsl:with-param name="outputString" select="detail[@id=172]"/></xsl:call-template></td><!-- Place published -->");
document.writeln("            <td><xsl:call-template name="quoter"><xsl:with-param name="outputString" select="detail[@id=177]"/></xsl:call-template></td><!-- Start Date -->");
document.writeln("            <td><xsl:call-template name="quoter"><xsl:with-param name="outputString" select="detail[@id=233]"/></xsl:call-template></td><!-- Start time -->");
document.writeln("            <td><xsl:call-template name="quoter"><xsl:with-param name="outputString" select="detail[@id=234]"/></xsl:call-template></td><!-- End time -->");
document.writeln("        </tr>");
    </xsl:template>
</xsl:stylesheet>
