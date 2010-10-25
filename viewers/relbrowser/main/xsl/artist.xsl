<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:template name="artist" match="reference[reftype/@id=128]">

		<div class="recordTypeHeading">
					<img src="{$hBase}common/images/reftype-icons/{reftype/@id}.png"/>
					<xsl:text> </xsl:text>
					<xsl:value-of select="reftype"/>
        </div>
 
		<xsl:call-template name="personDetailsForArtist"> 
			<xsl:with-param name="personDetails" select="pointer[@id=249]"/>
		</xsl:call-template>    
				
		<xsl:if test="reverse-pointer[@id=580] != ''">		
		<div class="detailRow">
			<div class="detailType">Paintings by this artist
                   <xsl:call-template name="tableOfArtworkThumbnails">
				   <xsl:with-param name="artworks" select="reverse-pointer[@id=580]"/>
				</xsl:call-template>
			</div>
		</div>
		</xsl:if>		
	</xsl:template>

	<xsl:template name="personDetailsForArtist">
		<xsl:param name="personDetails"/>
			<div class="detailRow">
				<div class="detailType">
					<img src="{$personDetails/detail[@id=223]/file_fetch_url}" align="left" class="personPhoto"/>  
		         	<h1><xsl:value-of select="$personDetails/title"/></h1>
		         	 Born:<xsl:value-of select="$personDetails/detail[@id=293]/year"/> in <xsl:value-of select="$personDetails/detail[@id=216]"/>             
	      		</div>	
		     </div>				
	</xsl:template>

</xsl:stylesheet>
