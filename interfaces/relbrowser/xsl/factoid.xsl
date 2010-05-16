<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:str="http://exslt.org/strings" version="1.0">


	<xsl:template name="event" match="reference[reftype/@id=150]">

		<xsl:if test="detail[@id=223]">
			<!-- thumbnail -->
			<div style="float: left;">
				<xsl:for-each select="detail[@id=223]">
					<img src="{file_fetch_url}" vspace="10" hspace="10"/>
				</xsl:for-each>
			</div>
		</xsl:if>

		<table>
			<xsl:for-each select="detail[@id!=222 and @id!=223 and @id!=224 and @id!=230]">
				<tr>
					<td style="padding-right: 10px;">
					   <nobr>
						<xsl:choose>
								<xsl:when test="string-length(@name)">
									<xsl:value-of select="@name"/>
								</xsl:when>
								<xsl:otherwise>
									<xsl:value-of select="@type"/>
								</xsl:otherwise>
							</xsl:choose>
						</nobr>
					</td>
					<td>
						<xsl:choose>
							<!-- 268 = Contact details URL,  256 = Web links -->
							<xsl:when test="@id=268  or  @id=256  or  starts-with(text(), 'http')">
								<a href="{text()}">
									<xsl:choose>
										<xsl:when test="string-length() &gt; 50">
											<xsl:value-of select="substring(text(), 0, 50)"/> ... </xsl:when>
										<xsl:otherwise>
											<xsl:value-of select="text()"/>
										</xsl:otherwise>
									</xsl:choose>
								</a>
							</xsl:when>
							<!-- 221 = AssociatedFile,  231 = Associated File -->
							<xsl:when test="@id=221  or  @id=231">
								<a href="{file_fetch_url}">
									<xsl:value-of select="file_orig_name"/>
								</a>
							</xsl:when>
							<xsl:when test="@id=191">
								<xsl:call-template name="paragraphise">
									<xsl:with-param name="text">
										<xsl:value-of select="text()"/>
									</xsl:with-param>
								</xsl:call-template>
							</xsl:when>
							<xsl:otherwise>
								<xsl:value-of select="text()"/>
							</xsl:otherwise>
						</xsl:choose>
					</td>
				</tr>
			</xsl:for-each>
		</table>

		<xsl:if test="detail[@id=230]">

		<br/>
		<div id="map" style="width: 100%; height: 252px;"/>
		<!-- retrieve map data for main id and all related records -->
		<script>
			var HEURIST = {};
		</script>
		<!-- yes you do need this -->
		<xsl:element name="script">
			<xsl:attribute name="src"><xsl:value-of select="$hbase"/>/mapper/tmap-data.php?w=all&amp;q=id:<xsl:value-of select="id"/>
				<xsl:for-each select="related|pointer|reverse-pointer">
					<xsl:text>,</xsl:text>
					<xsl:value-of select="id"/>
				</xsl:for-each>
			</xsl:attribute>
		</xsl:element>
		<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;key=ABQIAAAAGZugEZOePOFa_Kc5QZ0UQRQUeYPJPN0iHdI_mpOIQDTyJGt-ARSOyMjfz0UjulQTRjpuNpjk72vQ3w"></script>
		<script src="{$hbase}/mapper/epoly.js"></script>
		<script src="{$hbase}/mapper/mapper.js"></script>

		<script>
			loadMap( { compact: true, highlight: [<xsl:value-of select="id"/>], onclick: function(record) { window.location = "<xsl:value-of select="$cocoonbase"/>/item/"+record.bibID+"/"; } } );
		</script>
		</xsl:if>

	</xsl:template>


</xsl:stylesheet>
