<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE stylesheet [
<!ENTITY raquo  "&#187;" ><!-- double right arrow -->
]>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:str="http://exslt.org/strings" version="1.0">
	
	<!-- stylesheet modified by Steven Hayes from persons template 
		in order to deal with collections of multiple images
		should layout associated thumbnails in nice format in 
		main window -->
	
	
	
	
	<xsl:template name="collectionInFocus" match="reference[reftype/@id=115]">
		
		<h1><xsl:value-of select="title"/></h1>
		
		
		<xsl:call-template name="tableOfArtworkThumbnails">
			<xsl:with-param name="artworks" select="reverse-pointer[@id=397]"/>
		</xsl:call-template>
		
	</xsl:template>
	
	<xsl:template name="tableOfArtworkThumbnails">
		<xsl:param name="artworks"/>
		
		<table align="center" name="artworkthumbnails" width="80%">
			<xsl:for-each select="$artworks">
				<!-- a hack to dynamically display images in 4 coulumns -->
				<xsl:if test="position() mod 4 = 1">
					<script type="text/javascript">document.write('&lt;tr&gt; ');</script>
				</xsl:if>	
				<xsl:element name="td">
					<xsl:attribute name="class">group-td</xsl:attribute>
					<xsl:attribute name="width">25%</xsl:attribute>
					<xsl:attribute name="valign">top</xsl:attribute>
					<xsl:attribute name="align">center</xsl:attribute>
					
					<div>
					<!-- div style="background-color: white; text-align:center; border:1px solid white; margin:20px; padding-top:10px;" -->
						
						<!-- xsl:choose>
						<xsl:when test="detail[@id=224]">
							<img  src="{detail[@id=224]/file_thumb_url}"/>
						</xsl:when>
						<xsl:otherwise>
							<img  src="{$hbase}/php/resize_image.php?file_url={detail[@id=603]}&amp;w=150"/>
						</xsl:otherwise>
						</xsl:choose -->
						
						<xsl:choose>
							<xsl:when test="detail[@id=224]">
							<img  src="{detail[@id=224]/file_thumb_url}"/>
							</xsl:when>
							<xsl:when test="detail[@id=606]">
								<img  src="{detail[@id=606]}"/>
							</xsl:when>
							<xsl:otherwise>
							<img  src="{$hbase}/php/resize_image.php?file_url={detail[@id=603]}&amp;w=150"/>
							</xsl:otherwise>
						</xsl:choose>
						
						
						
					</div>
					<a href="{$cocoonbase}/item/{id}" class="bodynav"><xsl:value-of select="title"/></a>
					
					
				</xsl:element>
				<xsl:if test="position() mod 4= 0">
					<script type="text/javascript">document.write('&lt;/tr&gt; ');</script>
				</xsl:if>
				
			</xsl:for-each>			
			
			
			
			
			
		</table>
		
	</xsl:template>
	
	
	
	<xsl:template match="related[reftype/@id=115] | pointer[reftype/@id=115] | reverse-pointer[reftype/@id=115] ">
		<!-- render for collection record in sidebar -->
		
		
		
		<a class="sb_two" href="{$cocoonbase}/item/{id}/"><xsl:value-of select="title"/></a>
		
		
		<br/>
		<p><small><em>permission to publish images from this collection on this website <xsl:value-of select="detail[@id=201]"/>
			<!-- citation protocol -->
			<p>Collection (or custodian) URL: <a class="sb_two" href="{url}"><xsl:value-of select="url"/></a></p>
		</em></small></p>
	</xsl:template>
	
	<xsl:template name="related_artworks" match="reverse-pointer[@id=397]">
		<!-- leaving this blank prevents display of any related artworks in the sidebar -->
		
	</xsl:template>
	
	<xsl:template match="pointer[@id=508]">
		<!-- leaving this blank prevents display of any related media in the sidebar -->
		
		
	</xsl:template>
	
</xsl:stylesheet>
