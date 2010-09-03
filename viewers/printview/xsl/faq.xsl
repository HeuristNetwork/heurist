<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
<xsl:template name="faq" match="record[type/@id=47]">
<div id="{id}" class="record">
	<a target="_new" href="reftype_renderer/{id}">
		<xsl:value-of select="detail[@id=160]"/><!-- Title -->
	</a>
</div>
</xsl:template>
</xsl:stylesheet>
