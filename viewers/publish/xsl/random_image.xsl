<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

<xsl:template match="/">
	<html>
		<head>
			<style type="text/css">
				* { margin: 0px; padding: 0px; font-family: sans-serif; font-size: small; }
				#caption { padding: 3px 0; }
			</style>
			<script type="text/javascript">
				function selectImage() {
					var images = new Array(
						<!-- select Media references (type 74), with appropriate Mimetype (detail type 289) -->
						<!-- detail type 221 is "Associated file" - the address of a script to retrieve the file -->
						<xsl:for-each select="export/references/reference[rectype/@id=74][detail[@id=289]='image/jpeg' or detail[@id=289]='image/gif' or detail[@id=289]='image/bmp']">
							[ '<xsl:value-of select="detail[@id=221]"/>', "<xsl:value-of select="detail[@id=160]"/>" ]<xsl:if test="position() != last()">,</xsl:if>
						</xsl:for-each>
					);
					var i = Math.floor(Math.random() * images.length);
					var e = document.getElementById('randomimage');
					e.src = images[i][0];
					e.alt = images[i][1];
					document.getElementById('caption').appendChild(document.createTextNode(images[i][1]));
				}
			</script>
		</head>
		<body onload="selectImage();">
			<img id="randomimage"/>
                        <div id="caption"/>
		</body>
	</html>
</xsl:template>


</xsl:stylesheet>
