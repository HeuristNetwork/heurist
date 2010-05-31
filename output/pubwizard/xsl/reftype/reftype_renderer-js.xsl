<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

<xsl:param name="bib_id"/>

<xsl:include href="quoter.xsl"/>

<xsl:include href="course_unit.xsl"/>
<xsl:include href="event-js.xsl"/>
<xsl:include href="student_project_topic.xsl"/>
<xsl:include href="research_project.xsl"/>
<xsl:include href="faq.xsl"/>
<xsl:include href="person.xsl"/>
<xsl:include href="news_item.xsl"/>
<xsl:include href="organisation.xsl"/>

<xsl:template match="/">
document.writeln('			<link rel="stylesheet" type="text/css" href="http://www.arts.usyd.edu.au/departs/archaeology/styles/base.css"/>');
document.writeln('			<style type="text/css">');
document.writeln("				td { vertical-align: top; }");
document.writeln("			</style>");
document.writeln("			<table>");
				<xsl:choose>
					<xsl:when test="$bib_id">
						<xsl:apply-templates select="export/references/reference[id=$bib_id]"/>
					</xsl:when>
					<xsl:otherwise>
						<xsl:apply-templates select="export/references/reference"/>
					</xsl:otherwise>
				</xsl:choose>
document.writeln("			</table>");
</xsl:template>


<!-- detail output template -->
<xsl:template match="reference[id=$bib_id]">
document.writeln("	<tr>");
document.writeln("		<td><img><xsl:attribute name="src">http://heuristscholar.org/reftype/<xsl:value-of select="reftype/@id"/>.gif</xsl:attribute></img></td>");
document.writeln('		<td style="font-weight: bold;"><xsl:value-of select="title"/></td>');
document.writeln("	</tr>");
document.writeln("	<tr>");
document.writeln("		<td><nobr>Reference type</nobr></td>");
document.writeln("		<td><xsl:value-of select="reftype"/></td>");
document.writeln("	</tr>");
	<xsl:if test="url != ''">
document.writeln("		<tr>");
document.writeln("			<td>URL</td>");
document.writeln("			<td>");
document.writeln("				<a>");
document.writeln("					<xsl:attribute name="href"><xsl:value-of select="url"/></xsl:attribute>");
document.writeln("					<xsl:value-of select="url"/>");
document.writeln("				</a>");
document.writeln("			</td>");
document.writeln("		</tr>");
	</xsl:if>

	<xsl:for-each select="detail">
document.writeln("		<tr>");
document.writeln("			<td><nobr><xsl:value-of select="@name"/></nobr></td>");
document.writeln("			<td>");
				<xsl:choose>
					<!-- 223 = Thumbnail,  231 = Associated File,  268 = Contact details URL,  256 = Web links -->
					<xsl:when test="@id=223  or  @id=231  or  @id=268  or  @id=256  or  starts-with(., 'http')">
document.writeln("						<a>");
document.writeln("							<xsl:attribute name="href"><xsl:value-of select="."/></xsl:attribute>");
document.writeln("							<xsl:value-of select="."/>");
document.writeln("						</a>");
					</xsl:when>
					<xsl:otherwise>
document.writeln("						<xsl:call-template name="quoter"><xsl:with-param name="outputString" select="."/></xsl:call-template>");
					</xsl:otherwise>
				</xsl:choose>
document.writeln("			</td>");
document.writeln("		</tr>");
	</xsl:for-each>

	<xsl:if test="notes != ''">
document.writeln("		<tr>");
document.writeln("			<td>Notes</td>");
document.writeln("			<td><xsl:value-of select="notes"/></td>");
document.writeln("		</tr>");
	</xsl:if>
</xsl:template>

</xsl:stylesheet>
