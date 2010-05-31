<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
	<xsl:template name="artwork" match="reference[reftype/@id=129]">
		<!-- root template for artworks -->

		<div id="artwork" class="artwork">

		<xsl:choose>
			<xsl:when test="not (detail[@id=224]) and (detail[@id=603])">
				<div  id = "img-external">
					<img src ="{detail[@id=603]}"></img>
				</div>
			</xsl:when>

			<xsl:when test="not (detail[@id=224]) and not (detail[@id=603]) and (detail[@id=604])">
				<div  id = "img-external">
					<a href="{url}"><img src ="{detail[@id=604]}" border="0"  vspace="10" hspace="10" align="center"/></a>
				</div>
			</xsl:when>

			<xsl:when test="not (detail[@id=224]) and not (detail[@id=603]) and not (detail[@id=604]) and url">
				<div  id = "img-external">
					<a href="{url}"><img src ="{url}" border="0"  vspace="10" hspace="10" align="center"/></a>
				</div>
			</xsl:when>
			<xsl:when test="detail[@id=224]">
				<a href="{url}"><img src="{detail[@id=224]/file_fetch_url}" border="0"  vspace="10" hspace="10" align="center"/></a>
				</xsl:when>


			<xsl:otherwise>[no images found]</xsl:otherwise>
		</xsl:choose>

		<p><xsl:call-template name="artworkTitle"/></p>

		<p><xsl:call-template name="photocredit"/></p>

		



		<p><xsl:call-template name="medium"/></p>



			<p><xsl:call-template name="description"/></p>
			
			<p><xsl:call-template name="abstract"/></p>


			<p><xsl:call-template name="dimensions"/></p>

			<p>
			<b>quality:</b> <xsl:call-template name="quality"/><br/>
			<b>condition:</b> <xsl:call-template name="condition"/>
			</p>




		</div>
		
		<xsl:call-template name="tableOfArtworkThumbnails">
			<xsl:with-param name="artworks" select="pointer[@id=508]"/>
		</xsl:call-template>
		
	</xsl:template>
	
	
	<xsl:template name="artworkTitle">
		<h2><xsl:value-of select="title"/></h2>
	</xsl:template>

	<xsl:template name="photocredit" match="detail[@id=609]">
		<!-- photcredit -->
		<em><small>artwork photographed by: <xsl:value-of select="detail[@id=609]"/></small></em>


	</xsl:template>

	

	

	<xsl:template name="description" match="detail[@id=303]">
			<div width="80%"><xsl:call-template name="paragraphise">
				<xsl:with-param name="text">
					<xsl:value-of select="detail[@id=303]"/>
				</xsl:with-param>
			</xsl:call-template></div>
	</xsl:template>

	<xsl:template name="abstract" match="detail[@id=560]">
		<div width="80%"><xsl:call-template name="paragraphise">
			<xsl:with-param name="text">
				<xsl:value-of select="detail[@id=560]"/>
			</xsl:with-param>
		</xsl:call-template></div>
	</xsl:template>

	<xsl:template name="medium" match="detail[@id=437]">
		<xsl:value-of select="detail[@id=437]"/>
	</xsl:template>

	<xsl:template name="quality" match="detail[@id=424]">
		<xsl:value-of select="detail[@id=424]"/>
	</xsl:template>

	<xsl:template name="condition" match="detail[@id=578]">
		<xsl:value-of select="detail[@id=578]"/>
	</xsl:template>

	<xsl:template name="dimensions">
		<xsl:if test="detail[@id=594]">
		<xsl:value-of select="detail[@id=594]"/> cm <i>by </i> <xsl:value-of select="detail[@id=595]"/> cm
		</xsl:if>
		<br/>

		<xsl:if test="detail[@id=597]">
		<xsl:value-of select="detail[@id=597]"/> inches <i>by</i> <xsl:value-of select="detail[@id=595]"/> inches
		</xsl:if>

	</xsl:template>
	
	
	
</xsl:stylesheet>
