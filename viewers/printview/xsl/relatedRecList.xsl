<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xd="http://www.oxygenxml.com/ns/doc/xsl" version="1.0">


	<xsl:template match="/">

		<!-- Relationship Header goes here -->
			<div id="relationshipHeader{hml/records/record/id}" class="relatedHeader" ><a target="_new" href="#" onclick="this.href = hBase +'search/search.html?q=relatedto:{hml/records/record/id}&amp;db=' + database;">Related Records</a></div>

		<xsl:call-template name="relationShip_record_section">
			<xsl:with-param name="items" select="hml/records/record/relationships/record"/>
			<xsl:with-param name="recId" select="hml/records/record/id"/>
		</xsl:call-template>

		<!-- reversePointer Header -->
			<div id="reversePointerHeader{hml/records/record/id}" class="relatedHeader" ><a target="_new" href="#" onclick="this.href = hBase +'search/search.html?q=linkedto:{hml/records/record/id}&amp;db=' + database;">Records Pointing to this Record</a></div>

		<xsl:call-template name="reversePointer_section">
			<xsl:with-param name="items" select="hml/records/record/reversePointer/record"/>
			<xsl:with-param name="recId" select="hml/records/record/id"/>
		</xsl:call-template>

	</xsl:template>

	<xsl:template name="relationShip_record_section">
		<xsl:param name="items"/>
		<xsl:param name="recId"/>
		<xsl:for-each select="$items/detail/record[not(id = $recId)]">
			<xsl:sort select="../../detail[@id='200']"/>
			<xsl:sort select="type"/>
			<xsl:sort select="title"/>
			<div class="detailRow" id="{id}">
				<div class="detailType" title="{type}" style="background-image:url(../../common/images/rectype-icons/{type/@id}.png)"><img src="../../common/images/16x16.gif" /></div>
				<div class="detail"><a target="_new" href="#" onclick="this.href = hBase +'search/search.html?q=ids:{self::node()/id}&amp;db=' + database;"> <xsl:value-of select="title"/> </a></div>
			</div>
		</xsl:for-each>
	</xsl:template>

	<xsl:template name="reversePointer_section">
		<xsl:param name="items"/>
		<xsl:param name="recId"/>
		<xsl:for-each select="$items">
			<xsl:sort select="type"/>
			<xsl:sort select="title"/>
			<div class="detailRow" id="{id}">
				<div class="detailType" title="{type}" style="background-image:url(../../common/images/rectype-icons/{type/@id}.png)"><img src="../../common/images/16x16.gif" /></div>
				<div class="detail"><a target="_new" href="#" onclick="this.href = hBase +'search/search.html?q=ids:{self::node()/id}&amp;db=' + database;"> <xsl:value-of select="title"/> </a></div>
			</div>
		</xsl:for-each>
	</xsl:template>


	</xsl:stylesheet>
