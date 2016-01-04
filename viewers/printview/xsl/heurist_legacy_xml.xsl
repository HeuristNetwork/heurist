<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<!-- identity transform -->
	<xsl:template match="@*|node()">
		<xsl:copy>
			<xsl:apply-templates select="@*|node()"/>
		</xsl:copy>
	</xsl:template>

	<xsl:template match="hml">
		<export>
			<xsl:if test="query/@pubID">
				<xsl:attribute name="pub_id">
					<xsl:value-of select="query/@pubID"/>
				</xsl:attribute>
			</xsl:if>
			<xsl:apply-templates select="@*|node()"/>
		</export>
	</xsl:template>

	<xsl:template match="dateStamp">
		<date_generated>
			<year><xsl:value-of select="number(substring(., 1, 4))"/></year>
			<month><xsl:value-of select="number(substring(., 6, 2))"/></month>
			<day><xsl:value-of select="number(substring(., 9, 2))"/></day>
		</date_generated>
	</xsl:template>

	<xsl:template match="records">
		<references>
			<xsl:apply-templates select="@*|node()"/>
		</references>
	</xsl:template>

	<xsl:template match="records/record">
		<reference>
			<xsl:apply-templates select="@*|node()"/>
		</reference>
	</xsl:template>

	<xsl:template match="detail[record]">
		<pointer>
			<xsl:apply-templates select="@*|node()"/>
		</pointer>
	</xsl:template>

	<xsl:template match="reversePointer">
		<reverse-pointer>
			<xsl:apply-templates select="@*|node()"/>
		</reverse-pointer>
	</xsl:template>

	<xsl:template match="detail/record | reversePointer/record">
		<xsl:apply-templates select="@*|node()"/>
	</xsl:template>

	<xsl:template match="record/type">
		<rectype>
			<xsl:apply-templates select="@*|node()"/>
		</rectype>
	</xsl:template>

	<xsl:template match="relationships">
		<xsl:for-each select="record">
			<xsl:variable name="relType">
				<xsl:choose>
					<xsl:when test="detail[@id=202]/record/id = ../../id">
						<xsl:value-of select="detail[@id=200]"/>
					</xsl:when>
					<xsl:otherwise>
						<xsl:value-of select="detail[@id=200]/@inverse"/>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:variable>
			<related
				id="{id}"
				type="{$relType}"
				title="{detail[@id=160]}"
				notes="{detail[@id=201]}"
				start="{detail[@id=177]}"
				end="{detail[@id=178]}">
				<xsl:apply-templates select="detail[@id=202 or @id=199]/record[id != current()/../../id]"/>
			</related>
		</xsl:for-each>
	</xsl:template>

	<xsl:template match="relationships/record/detail/record">
		<xsl:apply-templates select="@*|node()"/>
	</xsl:template>


	<xsl:template match="detail/raw">
		<xsl:apply-templates select="@*|node()"/>
	</xsl:template>

	<xsl:template match="detail/geo">
		<xsl:value-of select="type"/>
	</xsl:template>

	<xsl:template match="detail/file">
		<id><xsl:value-of select="nonce"/></id>
		<name><xsl:value-of select="origName"/></name>
		<date><xsl:value-of select="date"/></date>
		<size><xsl:value-of select="size"/></size>
		<url><xsl:value-of select="url"/></url>
		<thumbUrl><xsl:value-of select="thumbURL"/></thumbUrl>
	</xsl:template>

</xsl:stylesheet>


