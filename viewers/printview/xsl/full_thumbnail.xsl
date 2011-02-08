<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
<!--
 this style renders standard html
 author  Maria Shvedova
 last updated 24/08/2010 I.Golka
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
	<!-- use the following bit of code to include the stylesheet to display it in Heurist publishing wizard otherwise it will be ommited-->
	<!-- begin including code -->
	<xsl:comment>
	  <!-- name (desc.) that will appear in dropdown list -->
	  [name]Full Thumbnails[/name]
	  <!-- match the name of the stylesheet-->
	  [output]full_thumbnail[/output]
	</xsl:comment>
	<!-- end including code -->

	<xsl:apply-templates select="/hml/records/record"></xsl:apply-templates>
</xsl:template>

<!-- main template -->
<xsl:template match="/hml/records/record">
	<!-- HEADER  -->
		<xsl:choose>
			<xsl:when test="detail[@id= 222 or @id= 223 or  @id= 224]">
				<div id="{id}" class="record full_result_thumb" style="background-image:url({detail[@id= 222 or @id= 223 or  @id= 224]/file/thumbURL})">
					<xsl:call-template name="recordInfo"></xsl:call-template>
				</div>
			</xsl:when>
			<xsl:when test="detail[@id= 606]">
				<div id="{id}" class="record full_result_thumb" style="background-image:url({detail[@id=606]})" title="{detail[@id=606]}">
				<xsl:call-template name="recordInfo"></xsl:call-template>
				</div>
			</xsl:when>
			<xsl:when test="not (detail[@id=224]) and (detail[@id=603])">
				<div id="{id}" class="record full_result_thumb" style="background-image:url({$hBase}common/php/resizeImage.php?file_url={detail[@id=603]}&amp;w=140)">
					<xsl:call-template name="recordInfo"></xsl:call-template>
				</div>
			</xsl:when>
			<xsl:when test="not (detail[@id=224]) and not (detail[@id=603]) and (detail[@id=604])">
				<div id="{id}" class="record full_result_thumb" style="background-image:url({detail[@id=604]})">
					<xsl:call-template name="recordInfo"></xsl:call-template>
				</div>
			</xsl:when>
			<xsl:when test="(type[@id = 74]) and not (detail[@id=224]) and not (detail[@id=603]) and not (detail[@id=604]) and url">
				<div id="{id}" class="record full_result_thumb" style="background-image:url({url})">
					<xsl:call-template name="recordInfo"></xsl:call-template>
				</div>
			</xsl:when>
			
			<xsl:when test="detail[@id=224]">
				<div id="{id}" class="record full_result_thumb" style="background-image:url({detail[@id=224]/file_fetch_url})">
					<xsl:call-template name="recordInfo"></xsl:call-template>
				</div>
			</xsl:when>
			<xsl:when test="detail[@id=221]">
				<div id="{id}" class="record full_result_thumb" style="background-image:url({detail[@id=221]/file/thumbURL}&amp;w=140)">
					<xsl:call-template name="recordInfo"></xsl:call-template>
				</div>
			</xsl:when>
			<xsl:otherwise>
				<div id="{id}" class="record full_result_thumb" style="background-image:url(../../common/images/rectype-icons/thumb/th_{type/@id}.png)">
					<xsl:call-template name="recordInfo"></xsl:call-template>
				</div>
			</xsl:otherwise>
		</xsl:choose>
</xsl:template>

 <!-- helper templates -->
<xsl:template name="recordInfo">
	<div class="rec_title" title="{title}"><xsl:value-of select="title"/><br/>
		<div id="recID"><a target="_new" href="#" onclick="this.href = hBase +'search/search.html?q=ids:{id}&amp;instance=' + instance;"><nobr><span class="greyType">Record ID: </span><xsl:value-of select="id"/></nobr></a></div>
	</div>
	<div class="rec_type" title="{type}"><xsl:value-of select="type"/></div>
</xsl:template>

</xsl:stylesheet>
