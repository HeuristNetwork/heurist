<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
<!--
 this style renders record images
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
	  [name]Image view[/name]
	  <!-- match the name of the stylesheet-->
	  [output]images[/output]
	</xsl:comment>
	<!-- end including code -->
	<xsl:apply-templates select="/hml/records/record"></xsl:apply-templates>
</xsl:template>

<!-- main template -->
<xsl:template match="/hml/records/record">
	<!-- HEADER  -->
	<div id="{id}" class="record  L{@depth}">
		<div class="headerRow">
			<div id="recID">Record ID: <xsl:value-of select="id"/></div>
			<h2><xsl:value-of select="title"/></h2><br/>
			<h3><xsl:value-of select="type"/></h3>
		</div>

		<xsl:if test="detail[@id = 221 or @id= 222 or @id= 223 or  @id= 224]">
			<xsl:element name="a">
				<xsl:attribute name="href"><xsl:value-of select="detail[@id = 221 or @id= 222 or @id= 223 or  @id= 224]/file/url"/></xsl:attribute>
				<xsl:attribute name="target">_blank</xsl:attribute>
			<div class="image">
					<!-- only display 223 = Thumbnail in top-right corner -->
					<xsl:element name="img">
						<xsl:attribute name="src"><xsl:value-of select="detail[@id = 221 or @id= 222 or @id= 223 or  @id= 224]/file/url"/></xsl:attribute>
						<xsl:attribute name="width">100%</xsl:attribute>
					</xsl:element>
			</div>
				</xsl:element>
		</xsl:if>
		
		<xsl:if test="detail[@id=603]">
			<div class="image">
				<xsl:element name="a">
					<xsl:attribute name="href"><xsl:value-of select="detail[@id = 603]"/></xsl:attribute>
					<xsl:attribute name="target">_blank</xsl:attribute>
				<xsl:element name="img">
					<xsl:attribute name="src"><xsl:value-of select="detail[@id = 603]"/></xsl:attribute>
					<xsl:attribute name="width">100%</xsl:attribute>
				</xsl:element>
				</xsl:element>
			</div>
		</xsl:if>

	<!-- DETAIL LISTING -->
		<!--put what is being grouped in a variable-->
		<xsl:variable name="details" select="detail"/>
		<!--walk through the variable-->
		<xsl:for-each select="detail">
			<!--act on the first in document order-->
			<xsl:if test="generate-id(.)= generate-id($details[@id=current()/@id][1]) and self::node()[@id!= 249]">
				<div class="detailRow">
					<xsl:if test="self::node()[@id!= 223]">
						<div class="detailType">
							<xsl:choose>
								<xsl:when test="@name !=''">
									<xsl:value-of select="@name"/>
								</xsl:when>
								<xsl:otherwise>
									<xsl:value-of select="@type"/>
								</xsl:otherwise>
							</xsl:choose>
						</div>
					</xsl:if>
			<!--revisit all-->
						<div class="detail">
							<xsl:for-each select="$details[@id=current()/@id]">
								<xsl:sort select="."/>
							<xsl:choose>
								<xsl:when test="self::node()/record">
									<!-- <xsl:value-of select="./record/type"/>: -->
									<a target="_new" href="#" onclick="this.href = hBase +'search/search.html?q=ids:{self::node()/record/id}&amp;db=' + database;"> <xsl:value-of select="./record/title"/> </a>
								</xsl:when>
								<xsl:when test="self::node()[@id!= 222 and @id!= 221 and @id!=177 and @id != 223 and @id != 231 and @id != 268 and @id !=256 and @id!=304 and @id != 224]">
									<xsl:value-of select="."/>
								</xsl:when>
								<xsl:otherwise>
									<xsl:if test="self::node()[@id= 177]">
										<xsl:call-template name="start-date"></xsl:call-template>
								</xsl:if>
								<xsl:if test="self::node()[@id= 231 or @id=221]">
									<xsl:call-template name="file">
										<xsl:with-param name="id"><xsl:value-of select="@id"/></xsl:with-param>
									</xsl:call-template>
								</xsl:if>
								<xsl:if test="self::node()[@id= 268 or @id=304]">
									<xsl:call-template name="url">
										<xsl:with-param name="key"><xsl:value-of select="."/></xsl:with-param>
										<xsl:with-param name="value"><xsl:value-of select="."/></xsl:with-param>
									</xsl:call-template>
								</xsl:if>
								<xsl:if test="self::node()[@id= 256]">
									<xsl:call-template name="url">
										<xsl:with-param name="key"><xsl:value-of select="."/></xsl:with-param>
										<xsl:with-param name="value"><xsl:value-of select="."/></xsl:with-param>
									</xsl:call-template>
								</xsl:if>
								</xsl:otherwise>
							</xsl:choose><br/>
							</xsl:for-each>
						</div>
				</div>
			</xsl:if>
		</xsl:for-each>

	<!-- POINTER LISTING -->
		  <xsl:variable name="pointer" select="detail"/>
		  <!--walk through the variable-->
		  <xsl:for-each select="detail">

			<!--act on the first in document order-->
			<xsl:if test="generate-id(.)=generate-id($pointer[@id=current()/@id][1]) and self::node()[@id= 249]">
	<div class="detailRow">
		<div class="detailType">
				  <xsl:choose>
					<xsl:when test="@name !=''">
					<xsl:value-of select="@name"/>
					</xsl:when>
					<xsl:otherwise>
					<div>type:  <xsl:value-of select="@type"/></div>
					</xsl:otherwise>
				  </xsl:choose>
				</div>
				<div class="detail">
			  <!--revisit all-->
			  <xsl:for-each select="$pointer[@id=current()/@id]">
			  <xsl:choose>
				<xsl:when test="self::node()[@id=158]">
				  <xsl:apply-templates select="." mode="creator"/>
				</xsl:when>
				<xsl:otherwise>
				<xsl:value-of select="record/title"/>
				</xsl:otherwise>
			  </xsl:choose>
				<br/>
			  </xsl:for-each>
				</div>
			  </div>
			</xsl:if>
		  </xsl:for-each>

	<!-- RELATED LISTING -->
	<xsl:variable name="relation" select="relationships"/>
	<!--walk through the variable-->
	<xsl:for-each select="relationships">

	  <!--act on the first in document order-->
	  <xsl:if test="generate-id(.)=
		generate-id($relation[@type=current()/@type][1])">
	<div class="detailRow">
		<div class="detailType">
			<xsl:value-of select="@type"/>
		  </div>
		  <div class="detail">
			<!--revisit all-->
			<xsl:for-each select="$relation[@type=current()/@type]">
			  <xsl:value-of select="record/title"/>
			  <br/>
			</xsl:for-each>
		  </div>
		</div>
	  </xsl:if>
	</xsl:for-each>
	<xsl:if test="woot !=''">
	<div class="detailRow">
		<div class="detailType">WYSIWIG Text</div>
			<div class="detail"><xsl:call-template name="woot_content"></xsl:call-template></div>
		</div>
	</xsl:if>

	<div class="detailRow">
	<xsl:if test="url != ''">
	<div class="detailRow">
		<div class="detailType">URL</div>
		<div class="detail">
		<a href="{url}" TARGET="_blank">
			<xsl:choose>
			<xsl:when test="string-length(url) &gt; 50">
					<xsl:value-of select="substring(url, 0, 50)"/> ... </xsl:when>
			<xsl:otherwise>
			<xsl:value-of select="url"/>
					</xsl:otherwise>
			</xsl:choose>
		</a>
		</div>
	</div>
	</xsl:if>
	</div>
	<div class="detailRow">
	<xsl:if test="modified !=''">
		<div class="detailType">Last Updated</div><div class="detail"><xsl:value-of select="modified"/></div>
	</xsl:if>
	</div>

	<!-- related records div -->
	<div class="related-records"><div class="show-related" onClick="displayRelatedRecords({id},this.parentNode)">Show related records</div>
	</div>

	<!-- record separator div -->
	<div class="separator_row"></div>
</div>
	<!--/xsl:element-->


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
