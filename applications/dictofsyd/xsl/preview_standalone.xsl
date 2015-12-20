<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:include href="myvariables.xsl"/>
	<xsl:include href="util.xsl"/>
	<xsl:include href="factoid.xsl"/>
	<xsl:include href="previews.xsl"/>

	<xsl:param name="context"/>

	<xsl:template match="/">
		<xsl:call-template name="preview222">
			<xsl:with-param name="record" select="hml/records/record[@depth=0]"/>
			<xsl:with-param name="context" select="$context"/>
		</xsl:call-template>
	</xsl:template>

</xsl:stylesheet>
