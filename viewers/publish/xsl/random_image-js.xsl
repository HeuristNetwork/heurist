<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

<xsl:param name="width"/>

<xsl:template match="/">
if (! window.heurist_images) window.heurist_images = new Array(
	<!-- select Media references (type 74), with appropriate Mimetype (detail type 289) -->
	<!-- detail type 221 is "Associated file" - the address of a script to retrieve the file -->
	<xsl:for-each select="export/references/reference[rectype/@id=74][detail[@id=289]='image/jpeg' or detail[@id=289]='image/png' or detail[@id=289]='image/gif' or detail[@id=289]='image/bmp']">
		[ '<xsl:value-of select="detail[@id=221]/file_fetch_url"/>', "<xsl:value-of select="detail[@id=160]"/>" ]<xsl:if test="position() != last()">,</xsl:if>
	</xsl:for-each>
);

// prevent the same image from appearing multiple times on the same page
if (! window.heurist_chosen) window.heurist_chosen = new Object();
do {
	window.random_i = Math.floor(Math.random() * heurist_images.length);
} while (heurist_chosen[random_i]);
heurist_chosen[random_i] = true;

<xsl:if test="number($width) > 0">
var width = <xsl:value-of select="$width"/>;
</xsl:if>

document.write('<center>');
if (width) {
	document.write('<img width="'+width+'" id="randomimage" src="'+heurist_images[random_i][0]+'" alt="'+heurist_images[random_i][1]+'" />');
	document.write('<div id="caption" style="font-size: 70%; width: '+width+'px;">'+heurist_images[random_i][1]+'</div>');
} else {
	document.write('<img id="randomimage" src="'+heurist_images[random_i][0]+'" alt="'+heurist_images[random_i][1]+'" />');
	document.write('<div id="caption" style="font-size: 70%;">'+heurist_images[random_i][1]+'</div>');
}
document.write('</center>');
</xsl:template>


</xsl:stylesheet>
