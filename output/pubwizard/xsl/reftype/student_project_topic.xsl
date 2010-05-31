<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    <xsl:template name="student_project_topic" match="reference[reftype/@id=65]">
        <tr>
            <td><a target="_new">
                <xsl:attribute name="href">reftype_renderer/<xsl:value-of select="id"/></xsl:attribute>
                <xsl:value-of select="detail[@id=160]"/><!-- Title -->
            </a></td>
            <td>
                <xsl:for-each select="pointer[@id=249]"><!-- Person -->
                    <xsl:value-of select="title"/>
                    <xsl:if test="position() != last()">,
						</xsl:if>
                </xsl:for-each>
            </td>
            <td><xsl:value-of select="detail[@id=172]"/></td><!-- Place published -->
            <td><xsl:value-of select="detail[@id=177]/text()"/></td><!-- Start Date -->
            <td><xsl:value-of select="detail[@id=233]"/></td><!-- Start time -->
            <td><xsl:value-of select="detail[@id=234]"/></td><!-- End time -->
        </tr>
    </xsl:template>
</xsl:stylesheet>
