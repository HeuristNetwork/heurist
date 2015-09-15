<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:include href="myvariables.xsl"/>
	<xsl:include href="framework.xsl"/>
	<xsl:include href="util.xsl"/>
	<xsl:include href="factoid.xsl"/>

	<xsl:param name="type"/>

	<xsl:template name="getPluralTypeName">
		<xsl:choose>
			<xsl:when test="$type = 'entries'">Entries</xsl:when>
			<xsl:when test="$type = 'maps'">Maps</xsl:when>
			<xsl:when test="$type = 'subjects'">Subjects</xsl:when>
			<xsl:when test="$type = 'roles'">Roles</xsl:when>
			<xsl:when test="$type = 'contributors'">Contributors</xsl:when>
			<xsl:when test="$type = 'multimedia'">Multimedia</xsl:when>
			<xsl:otherwise>
				<xsl:call-template name="getEntityPluralName">
					<xsl:with-param name="codeName" select="$type"/>
				</xsl:call-template>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template name="getTypeCodeName">
		<xsl:choose>
			<xsl:when test="$type = 'entries'">entry</xsl:when>
			<xsl:when test="$type = 'maps'">map</xsl:when>
			<xsl:when test="$type = 'subjects'">term</xsl:when>
			<xsl:when test="$type = 'roles'">role</xsl:when>
			<xsl:when test="$type = 'contributors'">contributor</xsl:when>
			<xsl:when test="$type = 'multimedia'">image</xsl:when>
			<xsl:otherwise>
				<xsl:call-template name="getEntityCodeName">
					<xsl:with-param name="typeName" select="$type"/>
				</xsl:call-template>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>


	<xsl:template match="/">
		<xsl:call-template name="framework">
			<xsl:with-param name="title">Browse - <xsl:call-template name="getPluralTypeName"/></xsl:with-param>
		</xsl:call-template>
	</xsl:template>

	<xsl:template name="extraCSS"/>

	<xsl:template name="extraScripts">
		<script src="{$urlbase}js/browse.js" type="text/javascript"/>
		<script src="{$urlbase}browse/{$type}.js" type="text/javascript"/>
		<script src="{$urlbase}js/tooltip.js" type="text/javascript"/>
	</xsl:template>

	<xsl:template name="previewStubs"/>


	<xsl:template name="content">

		<xsl:variable name="pluralTypeName">
			<xsl:call-template name="getPluralTypeName"/>
		</xsl:variable>
		<xsl:variable name="typeCodeName">
			<xsl:call-template name="getTypeCodeName"/>
		</xsl:variable>

		<div id="heading" class="title-{$typeCodeName}">
			<h1>Browse <xsl:value-of select="$pluralTypeName"/></h1>
			<span id="sub-title"/>
		</div>

		<div id="loading">
			<p>Loading data</p>
			<img src="{$urlbase}images/loadingAnimation.gif"/>
		</div>

		<ul id="browse-alpha-index"/>
		<ul id="browse-type-index"/>
		<ul id="browse-licence-index"/>

		<div class="list-right-col-browse" id="entities-alpha"/>
		<div class="list-right-col-browse" id="entities-type"/>
		<div class="list-right-col-browse" id="entities-licence"/>
		<div class="list-right-col-browse" id="entities-content">
			<h2><xsl:value-of select="$pluralTypeName"/> with Entries</h2>
			<ul id="entities-with-entries"/>
			<h2>Other <xsl:value-of select="$pluralTypeName"/> mentioned in the Dictionary</h2>
			<ul id="entities-without-entries"/>
		</div>
	</xsl:template>

	<xsl:template name="sidebar">
		<xsl:call-template name="makeBrowseMenu"/>
	</xsl:template>


</xsl:stylesheet>
