<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:str="http://exslt.org/strings" xmlns:xi="http://www.w3.org/2001/XInclude" version="1.0">

<xsl:template name="cleanQuote">
	<xsl:param name="string" />
	<xsl:if test="contains($string, '&#x22;')"><xsl:value-of
		select="substring-before($string, '&#x22;')" />\"<xsl:call-template name="cleanQuote">
               <xsl:with-param name="string">
				<xsl:value-of select="substring-after($string, '&#x22;')" />
			</xsl:with-param>
		</xsl:call-template>
	</xsl:if>
	<xsl:if test="not(contains($string, '&#x22;'))"><xsl:value-of select="$string" /></xsl:if>
</xsl:template>

</xsl:stylesheet>
