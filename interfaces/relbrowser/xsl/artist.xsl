<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
	<xsl:template name="artwork" match="reference[reftype/@id=129]">
		<!-- root template for artworks -->

		<div id="artwork" class="artwork">

		<xsl:choose>
			<xsl:when test="not (detail[@id=224]) and url">
				<div  id = "img-external">
					<img src ="{url}"></img>
				</div>
			</xsl:when>
			<xsl:when test="detail[@id=224]">
				<img src="{detail[@id=224]/file_fetch_url}" vspace="10" hspace="10" align="center"/>
				</xsl:when>


			<xsl:otherwise>[no images found]</xsl:otherwise>
		</xsl:choose>

		<p><xsl:call-template name="photocredit"/></p>

		<p><xsl:call-template name="collection"/></p>

		<p><xsl:call-template name="artist"/></p>


		<p><xsl:call-template name="medium"/></p>



			<p><xsl:call-template name="description"/></p>


			<p><xsl:call-template name="dimensions"/></p>

			<p>
			<b>quality:</b> <xsl:call-template name="quality"/><br/>
			<b>condition:</b> <xsl:call-template name="condition"/>
			</p>




		</div>
	</xsl:template>

	<xsl:template name="photocredit" match="detail[@id=609]">
		<!-- photcredit -->
		<em><small>artwork photographed by: <xsl:value-of select="detail[@id=609]"/></small></em>


	</xsl:template>

	<xsl:template name="collection" match="pointer[@id=397]">
		<!-- pointer to collection record -->
		<xsl:value-of select="pointer[@id=397]/title"/>
		<br/>
		<xsl:value-of select="pointer[@id=397]/detail[@id=201]"/>
		<!-- citation protocol -->
	</xsl:template>

	<xsl:template name="artist" match="pointer[@id=580]">
		<!-- pointer to artist record -->
		<xsl:value-of select="pointer[@id=580]/title"/>
	</xsl:template>

	<xsl:template name="description" match="detail[@id=303]">
			<xsl:call-template name="paragraphise">
				<xsl:with-param name="text">
					<xsl:value-of select="detail[@id=303]"/>
				</xsl:with-param>
			</xsl:call-template>
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
