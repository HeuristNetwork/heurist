<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:template name="artist" match="reference[reftype/@id=128]">

		<table width="100%">
			<tr>
				<td>
		<xsl:call-template name="personDetailsForArtist">
			<xsl:with-param name="personDetails" select="pointer[@id=249]"/>
		</xsl:call-template>
		</td>
			</tr>
			<tr>
				<td>
			<h2>Paintings by this artist</h2>

			<xsl:call-template name="tableOfArtworkThumbnails">
				<xsl:with-param name="artworks" select="reverse-pointer[@id=580]"/>
			</xsl:call-template></td>
		</tr>

		</table>

	</xsl:template>

	<xsl:template name="personDetailsForArtist">
		<xsl:param name="personDetails"/>
		<h1><xsl:value-of select="$personDetails/title"/></h1>

		<xsl:choose><xsl:when test="$personDetails/detail[@id=223]">
			...
			<img src="{$personDetails/detail[@id=223]/file_fetch_url}" width="300" border="0" vspace="10"
						hspace="10" align="left"/>
		</xsl:when></xsl:choose>

		<xsl:choose>
			<xsl:when test="$personDetails/detail[@id=293]">
				<p>Born: <xsl:value-of select="$personDetails/detail[@id=293]/year"/> in <xsl:value-of select="$personDetails/detail[@id=216]"/></p>
			</xsl:when>

		</xsl:choose>

	</xsl:template>

</xsl:stylesheet>
