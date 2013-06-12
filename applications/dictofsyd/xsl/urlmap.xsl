<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:template name="getPath">
		<xsl:param name="id"/>
		<xsl:text>item/</xsl:text>
		<xsl:value-of select="$id"/>
	</xsl:template>

</xsl:stylesheet>
