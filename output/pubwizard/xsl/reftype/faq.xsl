<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
	<xsl:template name="faq" match="reference[reftype/@id=47]">
		<tr>
			<td>
				<a target="_new" href="reftype_renderer/{id}">
					<xsl:value-of select="detail[@id=160]"/><!-- Title -->
				</a>
				<br/>
			</td>
		</tr>

	</xsl:template>
</xsl:stylesheet>
