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
			<xsl:apply-templates select="@*|node()"/>
		</export>
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
		<reftype>
			<xsl:apply-templates select="@*|node()"/>
		</reftype>
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

	<xsl:template match="detail[geo]">
		<xsl:copy>
			<xsl:apply-templates select="@*"/>
			<xsl:if test="geo/type='point'">p</xsl:if>
			<xsl:if test="geo/type ='bounds'">r</xsl:if>
			<xsl:if test="geo/type ='circle'">c</xsl:if>
			<xsl:if test="geo/type ='polygon'">pl</xsl:if>
			<xsl:if test="geo/type ='path'">l</xsl:if>
		</xsl:copy>
	</xsl:template>

	<xsl:template match="detail/file">
		<file_id><xsl:value-of select="nonce"/></file_id>
		<file_orig_name><xsl:value-of select="origName"/></file_orig_name>
		<file_date><xsl:value-of select="date"/></file_date>
		<file_size><xsl:value-of select="size"/></file_size>
		<file_fetch_url><xsl:value-of select="url"/></file_fetch_url>
		<file_thumb_url><xsl:value-of select="thumbURL"/></file_thumb_url>
	</xsl:template>

</xsl:stylesheet>


