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
	<!-- use the following bit of code to include the stylesheet to display it in Heurist publishing wizard
	  otherwise it will be ommited-->
	<!-- begin including code -->
	<xsl:comment>
	  <!-- name (desc.) that will appear in dropdown list -->
	  [name]Thumbnails[/name]
	  <!-- match the name of the stylesheet-->
	  [output]thumbnail[/output]
	</xsl:comment>
	<!-- end including code -->

	<xsl:apply-templates select="/hml/records/record"></xsl:apply-templates>
</xsl:template>

<!-- main template -->
<xsl:template match="/hml/records/record">
	<!-- HEADER  -->
	<div id="{id}" class="record result_thumb">
		<div id="recID"><nobr><span class="greyType">Record ID: </span><xsl:value-of select="id"/></nobr></div>

			<div class="rec_title"><xsl:value-of select="title"/></div>
			<div class="rec_type" title="{type}"><xsl:value-of select="type"/></div>
		<xsl:choose>
			<xsl:when test="detail[@id= 222 or @id= 223 or  @id= 224]">
				<div class="thumbnail" style="background-image:url({detail[@id= 222 or @id= 223 or  @id= 224]/file/thumbURL})">
<!--						<xsl:element name="img">
						<xsl:attribute name="src"><xsl:value-of select="detail[@id= 222 or @id= 223 or  @id= 224]/file/thumbURL"/></xsl:attribute>
						<xsl:attribute name="title"><xsl:value-of select="type"/></xsl:attribute>
						</xsl:element>-->
				</div>
			</xsl:when>
			<xsl:when test="detail[@id= 606]">
				<div class="thumbnail" style="background-image:url({detail[@id=606]})" title="{detail[@id=606]}">
				</div>
			</xsl:when>
			
			<xsl:when test="not (detail[@id=224]) and (detail[@id=603])">
				<div class="thumbnail">
					<img  src="{$hBase}common/php/resize_image.php?file_url={detail[@id=603]}&amp;w=140" title="603"/>
				</div>
			</xsl:when>

			<xsl:when test="not (detail[@id=224]) and not (detail[@id=603]) and (detail[@id=604])">
				<div class="thumbnail">
					<img src ="{detail[@id=604]}" title="604"/>
				</div>
			</xsl:when>

			<xsl:when test="(type[@id = 74]) and not (detail[@id=224]) and not (detail[@id=603]) and not (detail[@id=604]) and url">
				<div class="thumbnail url">
					<img  src="{url}" title="{url}"/>
				</div>
			</xsl:when>
			
			<xsl:when test="detail[@id=224]">
				<div class="thumbnail">
					<img src="{detail[@id=224]/file_fetch_url}" title="224"/>
				</div>
			</xsl:when>
			<xsl:when test="detail[@id=221]">
				<div class="thumbnail">
					<img  src="{detail[@id=221]/file/thumbURL}&amp;w=140" title="221:{title}"/>
				</div>
			</xsl:when>
			
		</xsl:choose>

</div>



  </xsl:template>

 <!-- helper templates -->
  <xsl:template name="logo">
	<xsl:param name="id"></xsl:param>
	<xsl:if test="self::node()[@id =$id]">
	  <xsl:element name="a">
	  <xsl:attribute name="TARGET">_blank</xsl:attribute>
		<xsl:attribute name="href" ><xsl:value-of select="self::node()[@id =$id]/file/url"/></xsl:attribute>
		<xsl:element name="img">
		  <xsl:attribute name="src"><xsl:value-of select="self::node()[@id =$id]/file/thumbURL"/></xsl:attribute>
		  <xsl:attribute name="border">0</xsl:attribute>
		</xsl:element>
	  </xsl:element>
	</xsl:if>
  </xsl:template>

  <xsl:template name="file">
	<xsl:param name="id"></xsl:param>
	<xsl:if test="self::node()[@id =$id]">
	  <xsl:element name="a">
	  <xsl:attribute name="TARGET">_blank</xsl:attribute>
		<xsl:attribute name="href"><xsl:value-of select="self::node()[@id =$id]/file/url"/></xsl:attribute>
		<xsl:value-of select="file/origName"/>
	  </xsl:element>  [<xsl:value-of select="file/size"/>]
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
	  <xsl:attribute name="TARGET">_blank</xsl:attribute>
	  <xsl:value-of select="$value"/>
	</xsl:element>
  </xsl:template>

  <xsl:template name="woot_content">
	<xsl:if test="woot">
	  <xsl:copy-of select="woot"/>
	</xsl:if>
  </xsl:template>

</xsl:stylesheet>
