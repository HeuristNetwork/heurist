<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE stylesheet [
<!ENTITY raquo  "&#187;" ><!-- double right arrow -->
]>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:str="http://exslt.org/strings" version="1.0">

	<!-- stylesheet modified by Steven Hayes from persons template
		in order to deal with collections of multiple images
		should layout associated thumbnails in nice format in
		main window -->




	<xsl:template name="collectionInFocus" match="reference[rectype/@id=115]">

		<h1><xsl:value-of select="title"/></h1>


		<xsl:call-template name="tableOfArtworkThumbnails">
			<xsl:with-param name="artworks" select="reverse-pointer[@id=397]"/>
		</xsl:call-template>

	</xsl:template>

	<xsl:template name="tableOfArtworkThumbnails">
		<xsl:param name="artworks"/>

		<!-- table align="center" name="artworkthumbnails" width="80%" -->
        <div class="detail">
			<xsl:for-each select="$artworks">
				<!-- a hack to dynamically display images in 4 coulumns
				<xsl:if test="position() mod 4 = 1">
					<script type="text/javascript">document.write('&lt;tr&gt; ');</script>
				</xsl:if>
				<xsl:element name="td">
					<xsl:attribute name="class">group-td</xsl:attribute>
					<xsl:attribute name="width">25%</xsl:attribute>
					<xsl:attribute name="valign">top</xsl:attribute>
					<xsl:attribute name="align">center</xsl:attribute> -->

					<div style="display:inline-block; padding:10px; margin:10px 10px 0 0; border:1px solid #CCC; background-color:#FFF" class="thumbnail" align="center">
					<!-- div style="background-color: white; text-align:center; border:1px solid white; margin:20px; padding-top:10px;" -->

						<!-- xsl:choose>
						<xsl:when test="detail[@id=224]">
							<img  src="{detail[@id=224]/file_thumb_url}"/>
						</xsl:when>
						<xsl:otherwise>
							<img  src="{$hBase}common/php/resizeImage.php?file_url={detail[@id=603]}&amp;w=150"/>
						</xsl:otherwise>
						</xsl:choose -->
						<div class="artwork" style="display:block; width:130px; height:130px; overflow:hidden">
						<xsl:choose>

							<xsl:when test="detail[@id=224]">
                            <a href="{$cocoonBase}item/{id}/?instance={$instanceName}" class="bodynav" >
							<img  src="{detail[@id=224]/file_thumb_url}" style="width:130px;vertical-align:middle"/>
                            </a>
							</xsl:when>
							<xsl:when test="detail[@id=606]">
                            <a href="{$cocoonBase}item/{id}/?instance={$instanceName}" class="bodynav">
								<img  src="{detail[@id=606]}" style="width:130px; vertical-align:middle"/>
                             </a>
							</xsl:when>
							<xsl:otherwise>
                            <a href="{$cocoonBase}item/{id}/?instance={$instanceName}" class="bodynav" style="display:block; width:130px;">
							<img  src="{$hBase}common/php/resizeImage.php?file_url={detail[@id=603]}&amp;w=130"/>
							</a>
                            </xsl:otherwise>
                          	</xsl:choose>
						</div>

					<p><a href="{$cocoonBase}item/{id}/?instance={$instanceName}" class="bodynav"><xsl:value-of select="title"/></a></p>

					</div>



				<!-- /xsl:element>
				<xsl:if test="position() mod 4= 0">
					<script type="text/javascript">document.write('&lt;/tr&gt; ');</script>
				</xsl:if -->

			</xsl:for-each>



		</div>

		<!-- /table -->

	</xsl:template>



	<xsl:template match="related[rectype/@id=115] | pointer[rectype/@id=115] | reverse-pointer[rectype/@id=115] ">
		<!-- render for collection record in sidebar -->
	    <div class="relatedItem">
           <div class="editIcon">
				<a href="{$appBase}edit.html?id={id}/?instance={$instanceName}" onclick="window.open(this.href,'','status=0,scrollbars=1,resizable=1,width=800,height=600'); return false;" title="edit">
					<img src="{$hBase}common/images/edit-pencil.png" class="editPencil"/>
				</a>
			</div>

        	<div class="link">
				<a class="sb_two" href="{$cocoonBase}item/{id}/?instance={$instanceName}"><xsl:value-of select="title"/></a>
				<!-- <br/>
				<p><small><em>permission to publish images from this collection on this website <xsl:value-of select="detail[@id=201]"/>
					 citation protocol
					<p>Collection (or custodian) URL: <a class="sb_two" href="{url}"><xsl:value-of select="url"/></a></p>
					</em></small></p>   -->
			</div>
			<div class="rectypeIcon">
				<!-- change this to pick up the actuall system name of the rectype or to use the mapping method as in JHSB that calls human-readable-names.xml -->
				<img style="vertical-align: middle;horizontal-align: right" src="{$hBase}common/images/rectype-icons/{rectype/@id}.png"/>
			</div>

	   </div>
	</xsl:template>

	<xsl:template name="related_artworks" match="reverse-pointer[@id=397]">
		<!-- leaving this blank prevents display of any related artworks in the sidebar -->

	</xsl:template>

	<xsl:template match="pointer[@id=508]">
		<!-- leaving this blank prevents display of any related media in the sidebar -->


	</xsl:template>

</xsl:stylesheet>
