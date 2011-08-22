<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:template match="/">
		<!-- use the following bit of code to include the stylesheet to display it in Heurist publishing wizard
			otherwise it will be ommited-->
		<!-- begin including code -->
		<xsl:comment>
			<!-- name (desc.) that will appear in dropdown list -->
			[name]PBwiki[/name]
			<!-- match the name of the stylesheet-->
			[output]wiki-simple[/output]
		</xsl:comment>
		<!-- end including code -->

		<xsl:apply-templates select="hml/records/record"/>

	</xsl:template>

	<xsl:template name="print_consolidated_title">
	<xsl:value-of select="title"/>
	</xsl:template>

	<xsl:template match="record">
		<div id="{id}" class="record  L{@depth}">
				<xsl:if test="detail[@id='160']">
					<b>
						<xsl:choose>
							<xsl:when test="url != ''">
								<xsl:element name="a">
									<xsl:attribute name="href"><xsl:value-of select="url"/></xsl:attribute>
									<xsl:attribute name="target">_blank</xsl:attribute>
									<xsl:call-template name="print_consolidated_title"></xsl:call-template>
								</xsl:element>
							</xsl:when>
							<xsl:otherwise>
								<xsl:call-template name="print_consolidated_title"></xsl:call-template>
							</xsl:otherwise>
						</xsl:choose>
					</b>
					<br></br>
					
					<a style=" font-family:Verdana,Helvetica,Arial,sans-serif; font-size:10px;"  target="_new" href="wiki/{id}">[view details]</a>&#160;<a style=" font-family:Verdana,Helvetica,Arial,sans-serif; font-size:10px;"  target="_new" href="http://heuristscholar.org/heurist/edit?bib_id={id}">[edit]</a>
				</xsl:if>
		</div>
	</xsl:template>

</xsl:stylesheet>
