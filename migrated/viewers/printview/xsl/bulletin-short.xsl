<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

<!--
this style renders standard html
author  Maria Shvedova
last updated 10/09/2007 ms
-->
	<xsl:template name="creator" match="detail/record" mode="creator">
		<xsl:choose>
			<xsl:when test="contains(title,',') ">
				<!-- display initials instead of a full first name, if applicable-->
				<xsl:variable name="lname">
					<xsl:value-of select="substring-before(title, ',')"/>
				</xsl:variable>
				<xsl:variable name="fname">
					<xsl:value-of select="substring-after(title, ', ')"/>
				</xsl:variable>
				<xsl:value-of select="$lname"/>&#xa0; <xsl:choose>
					<xsl:when test="contains($fname,' ') or contains($fname, '.')">
						<xsl:choose>
							<xsl:when test="string-length($fname) &gt; 4">
								<xsl:value-of select="substring($fname, 1, 1)"/>. </xsl:when>
							<xsl:otherwise>
								<xsl:value-of select="$fname"/>
							</xsl:otherwise>
						</xsl:choose>
					</xsl:when>
					<xsl:otherwise>
						<xsl:value-of select="substring($fname, 1, 1)"/>. </xsl:otherwise>
				</xsl:choose>
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="title"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

<xsl:template match="/">
	<!-- use the following bit of code to include the stylesheet to display it in Heurist publishing wizard
	  otherwise it will be ommited-->
	<!-- begin including code -->
	<xsl:comment>
	<!-- name (desc.) that will appear in dropdown list -->
	[name]Bulletin - short[/name]
	<!-- match the name of the stylesheet-->
	[output]bulletin-short[/output]
	</xsl:comment>
	<!-- end including code -->

	<xsl:apply-templates select="/hml/records/record"></xsl:apply-templates>

</xsl:template>

<!-- main template -->
<xsl:template match="/hml/records/record">
	<!-- HEADER  -->
	<div id="{id}" class="record L{@depth}">
	<table style="margin-bottom: 10px; ">
		<tr>
			<td>
			</td>
			<td >
			<b>
				<xsl:value-of select="title"/>
			</b>
			</td>
		</tr>
	<xsl:if test="url != ''">
		<tr>
			<td>
			</td>
			<td>
				<a href="{url}">
					<xsl:choose>
						<xsl:when test="string-length(url) &gt; 50">
						<xsl:value-of select="substring(url, 0, 50)"/> ... </xsl:when>
						<xsl:otherwise>
						<xsl:value-of select="url"/>
						</xsl:otherwise>
					</xsl:choose>
				</a>
			</td>
		</tr>
	</xsl:if>

	<!-- DETAIL LISTING -->

	<!--put what is being grouped in a variable-->
	<xsl:variable name="details" select="detail"/>
		<!--walk through the variable-->

		<xsl:if test="detail[@id=303]">
			<tr>
				<td>
				</td>
				<!--revisit all-->
				<td>
					<xsl:value-of select="detail[@id=303]"/>
				</td>
			</tr>
		</xsl:if>
		</table>
	</div>
</xsl:template>

	<!-- helper templates -->
	<xsl:template name="logo">
		<xsl:param name="id"></xsl:param>
		<xsl:if test="self::node()[@id =$id]">
			<xsl:element name="a">
			<xsl:attribute name="href"><xsl:value-of select="self::node()[@id =$id]/url"/></xsl:attribute>
				<xsl:element name="img">
					<xsl:attribute name="src"><xsl:value-of select="self::node()[@id =$id]/thumbURL"/></xsl:attribute>
					<xsl:attribute name="border">0</xsl:attribute>
				</xsl:element>
			</xsl:element>
		</xsl:if>
	</xsl:template>

	<xsl:template name="file">
		<xsl:param name="id"></xsl:param>
		<xsl:if test="self::node()[@id =$id]">
			<xsl:element name="a">
				<xsl:attribute name="href"><xsl:value-of select="self::node()[@id =$id]/url"/></xsl:attribute>
				<xsl:value-of select="origName"/>
			</xsl:element>  [<xsl:value-of select="size"/>]
		</xsl:if>
	</xsl:template>

	<xsl:template name="start-date" match="detail[@id=177]">
		<xsl:if test="self::node()[@id =177]">
			<xsl:value-of select="self::node()[@id =177]/year"/>
		</xsl:if>
	</xsl:template>

	<xsl:template name="url">
		<xsl:param name="key"></xsl:param>
		<xsl:param name="value"></xsl:param>
		<xsl:element name="a">
			<xsl:attribute name="href"><xsl:value-of select="$key"/></xsl:attribute>
			<xsl:value-of select="$value"/>
		</xsl:element>
	</xsl:template>

	<xsl:template name="woot_content">
		<xsl:if test="woot">
			<xsl:copy-of select="woot"/>
		</xsl:if>
	</xsl:template>

</xsl:stylesheet>
