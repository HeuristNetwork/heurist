<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:template name="media" match="reference[reftype/@id=74]">
		
		<table>
			<tr>
				<td align="center">
					<xsl:if test="detail[@id=223]">
						<!-- thumbnail -->
						<div style="float: center;">
							<xsl:for-each select="detail[@id=223]">
								<img src="{file_fetch_url}" vspace="10" hspace="10" align="center"/>
							</xsl:for-each>
							<xsl:if test="detail/file_thumb_url">
								<a href="{$cocoonbase}/item/{id}/{/export/references/reference/reftype/@id}?flavour={$flavour}">
									<img src="{detail/file_thumb_url}"/>
								</a>
								<br/>
							</xsl:if>
						</div>
					</xsl:if>
					<div id = "image-div">
					<xsl:if test="detail[@id=221]">
						<div align="center"><a href="{detail/file_fetch_url}" target="_top">
							<img src="{detail/file_thumb_url}&amp;w=560" border="1"/>
						</a></div>
						<br/>
						
					</xsl:if>
					</div>
					<xsl:if test="detail[@id=191]">
						<em>
							<xsl:call-template name="paragraphise">
								<xsl:with-param name="text">
									<xsl:value-of select="detail[@id=191]"/>
								</xsl:with-param>
							</xsl:call-template>
						</em>
						<br/>
					</xsl:if>
					<xsl:if test="detail[@id=303]">
						<em>
							<xsl:call-template name="paragraphise">
								<xsl:with-param name="text">
									<xsl:value-of select="detail[@id=303]"/>
								</xsl:with-param>
							</xsl:call-template>
						</em>
						<br/>
					</xsl:if>

					<xsl:if test="detail[@id=201]">
						
							Technical notes:
							<xsl:call-template name="paragraphise">
								<xsl:with-param name="text">
									<xsl:value-of select="detail[@id=201]"/>
								</xsl:with-param>
							</xsl:call-template>
						<br/>
					</xsl:if>
					<xsl:if test="not (detail[@id=223] or detail[@id=221]) and url">					
						<div  id = "img-external">
							<img src ="{url}"></img>
						</div>
					</xsl:if>
					<xsl:variable name="ftype">
						<xsl:call-template name="get-file-extension">
							<xsl:with-param name="pstring">
								<xsl:value-of select="substring-after(detail[@id=221]/file_orig_name, '.')"/>
							</xsl:with-param>
						</xsl:call-template>
					</xsl:variable>
					<xsl:if test="$ftype = 'mp3' or $ftype ='flv' or $ftype='mov'">
						<script type='text/javascript' src='{$urlbase}/mediaplayer/swfobject.js'></script>
						<script type='text/javascript'>
							var type = "<xsl:value-of select="$ftype"/>";
							var s1 = new SWFObject('<xsl:value-of select="$urlbase"/>/mediaplayer/player.swf','ply','500','400','9','#ffffff');
							s1.addParam('allowfullscreen','true');
							s1.addParam('allowscriptaccess','always');
							s1.addParam('wmode','opaque');
							if (type != "flv"){
							s1.addVariable("logo", "<xsl:value-of select="detail[@id=221]/file_thumb_url"/>");
							}
							
							s1.addVariable("type", type);
							s1.addVariable("file", escape("<xsl:value-of select="detail[@id=221]/file_fetch_url"/>"));
							s1.write('image-div');
							s1.addVariable("logo", "");
						</script>
					</xsl:if>
				</td>
			</tr>
		</table>
	</xsl:template>
	<xsl:template name="get-file-extension">
		<!-- parse through the string and get rid of the dots until you get to the file extension -->
		<xsl:param name="pstring"/>
		<xsl:choose>
			<xsl:when test="contains($pstring,'.' )">
				<xsl:call-template name="get-file-extension">
					<xsl:with-param name="pstring"><xsl:value-of select="substring-after($pstring, '.')"/></xsl:with-param>
				</xsl:call-template>
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="$pstring"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

</xsl:stylesheet>
