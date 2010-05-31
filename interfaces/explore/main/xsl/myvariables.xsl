<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0"
	xmlns:exsl="http://exslt.org/common" extension-element-prefixes="exsl">
	<xsl:param name="appBase"/>
	<xsl:param name="hBase"/>
	<xsl:param name="hIBase"/>
	<xsl:param name="cocoonBase"/>
	<xsl:param name="serverBaseUrl"/>
	<xsl:param name="instance"/>
    <xsl:variable name="hapi-key">147983c93cdd221dd23f9a93884034f2246b7e01</xsl:variable>
    <xsl:variable name="site-title">Main Data Explorer</xsl:variable>
	<xsl:variable name="home-id">130843</xsl:variable>
	<xsl:variable name="show-timelines-with-maps">yes</xsl:variable>

  <xsl:variable name="enableMapTrack">false</xsl:variable> <!-- include map track functionality for this  browser instance -->
  <xsl:variable name="bcrumbNameTrack">anythingyouwant</xsl:variable> <!-- a more or less unique name for map track recording -->

	<!-- custom timemap colours -->
	<xsl:variable name="focusTheme">purple</xsl:variable>
	<xsl:variable name="relatedTheme">blue</xsl:variable>
	<xsl:variable name="mapCrumbThemes">
		<theme>red</theme>
		<theme>orange</theme>
		<theme>yellow</theme>
		<theme>green</theme>
		<theme>pink</theme>
	</xsl:variable>

	<!-- number of breadcrumbs in "my map track", calculated based on number of themes defined in $mapCrumbThemes
	 - DO NOT CHANGE THIS VARIABLE -->
	<xsl:variable name="maptrackCrumbNumber"><xsl:value-of select="count(exsl:node-set($mapCrumbThemes)/theme)"/></xsl:variable>

	<xsl:variable name="timeMapThemes">
		<theme name="red">
			<colour>red</colour>
			<icon><xsl:value-of select="$appBase"/>images/red-dot.png</icon>
		</theme>
		<theme name="black">
			<colour>black</colour>
			<icon><xsl:value-of select="$appBase"/>images/purple-dot.png</icon>
		</theme>
		<theme name="orange">
			<colour>orange</colour>
			<icon><xsl:value-of select="$appBase"/>images/orange-dot.png</icon>
		</theme>
		<theme name="purple">
			<colour>purple</colour>
			<icon><xsl:value-of select="$appBase"/>images/purple-dot.png</icon>
		</theme>
		<theme name="green">
			<colour>green</colour>
			<icon><xsl:value-of select="$appBase"/>images/green-dot.png</icon>
		</theme>
		<theme name="blue">
			<colour>blue</colour>
			<icon><xsl:value-of select="$appBase"/>images/blue-dot.png</icon>
		</theme>
		<theme name="yellow">
			<colour>yellow</colour>
			<icon><xsl:value-of select="$appBase"/>images/yellow-dot.png</icon>
		</theme>
		<theme name="ltblue">
			<colour>blue</colour>
			<icon><xsl:value-of select="$appBase"/>images/ltblue-dot.png</icon>
		</theme>
		<theme name="pink">
			<colour>yellow</colour>
			<icon><xsl:value-of select="$appBase"/>images/pink-dot.png</icon>
		</theme>
		<theme name="customOne">
			<colour>#ff0000</colour>
			<icon><xsl:value-of select="$appBase"/>images/red-dot.png</icon>
		</theme>
		<theme name="customTwo">
			<colour>#ff3333</colour>
			<icon><xsl:value-of select="$appBase"/>images/red-dot.png</icon>
		</theme>
		<theme name="customThree">
			<colour>#ff6666</colour>
			<icon><xsl:value-of select="$appBase"/>images/red-dot.png</icon>
		</theme>
		<theme name="customFour">
			<colour>#ff9999</colour>
			<icon><xsl:value-of select="$appBase"/>images/red-dot.png</icon>
		</theme>
		<theme name="customFive">
			<colour>#ffcccc</colour>
			<icon><xsl:value-of select="$appBase"/>images/red-dot.png</icon>
		</theme>
	</xsl:variable>

</xsl:stylesheet>
