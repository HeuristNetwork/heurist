<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
<!--
 this style turns HML into CSV

 To begin with this is just a specific record type - namely events

 Once a preamble is added to HML describing records then we will make it more generic

 author  Steven Hayes
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
	  [name]CSV[/name]
	  <!-- match the name of the stylesheet-->
	  [output]csv[/output]
	</xsl:comment>
	<!-- end including code -->
	<xsl:apply-templates select="/hml/records/record"></xsl:apply-templates>
</xsl:template>

<!-- main template -->
	<xsl:template match="/hml/records/record">
		<div id="{id}" class="record">
			<xsl:value-of select="id"/>,
			<xsl:value-of select="title"/>,
			<xsl:value-of select="url"/>,
			<xsl:value-of select="detail[@id=197]"/>,
			"<xsl:value-of select="detail[@id=173]"/>",
			"<xsl:value-of select="detail[@id=303]"/>",
			<xsl:if test="detail[@id= 177]">
				<xsl:call-template name="start-date"></xsl:call-template>
			</xsl:if>
		</div>
	</xsl:template>


	<xsl:template name="start-date" match="detail[@id=177]">
		<xsl:choose>
			<xsl:when test="detail[@id=177]/temporal[@type='Simple Date']/date/raw">
				<xsl:value-of select="detail[@id=177]/temporal/date/raw"/>
			</xsl:when>
			<xsl:when test="detail[@id=177]/temporal/date[@type='PDB']/raw and detail[@id=177]/temporal/date[@type='PDE']/raw">
				<xsl:value-of select="detail[@id=177]/temporal/date[@type='PDB']/raw"/> – <xsl:value-of select="detail[@id=177]/temporal/date[@type='PDE']/raw"/>
			</xsl:when>
			<xsl:when test="detail[@id=177]/temporal/date[@type='TPQ']/raw and detail[@id=177]/temporal/date[@type='TAQ']/raw">
				<xsl:value-of select="detail[@id=177]/temporal/date[@type='TPQ']/raw"/> – <xsl:value-of select="detail[@id=177]/temporal/date[@type='TAQ']/raw"/>
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="raw"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template name="end-date" match="detail[@id=178]">
		<xsl:choose>
			<xsl:when test="detail[@id=178]/temporal[@type='Simple Date']/date/raw">
				<xsl:value-of select="detail[@id=178]/temporal/date/raw"/>
			</xsl:when>
			<xsl:when test="detail[@id=178]/temporal/date[@type='PDB']/raw and detail[@id=178]/temporal/date[@type='PDE']/raw">
				<xsl:value-of select="detail[@id=178]/temporal/date[@type='PDB']/raw"/> – <xsl:value-of select="detail[@id=178]/temporal/date[@type='PDE']/raw"/>
			</xsl:when>
			<xsl:when test="detail[@id=178]/temporal/date[@type='TPQ']/raw and detail[@id=178]/temporal/date[@type='TAQ']/raw">
				<xsl:value-of select="detail[@id=178]/temporal/date[@type='TPQ']/raw"/> – <xsl:value-of select="detail[@id=178]/temporal/date[@type='TAQ']/raw"/>
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="raw"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>


</xsl:stylesheet>
