<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:str="http://exslt.org/strings" xmlns:xi="http://www.w3.org/2001/XInclude" version="1.0">


	<xsl:template name="xmldoc" match="reference[reftype/@id=98]">

		<script src="{$urlbase}/js/highlight.js"/>
		<script src="{$urlbase}/js/selection.js"/>

		<style>
			#tei a { text-decoration: none; }
		</style>

		<xsl:choose>
			<!-- detail 231 is associated WordML file -->
			<xsl:when test="detail[@id=231]">
				<div id="tei" style="padding-right: 80px">
					<xi:include href="{detail[@id=231]/file_fetch_url}"/>
				</div>
			</xsl:when>
			<!-- detail 221 is associated TEI file -->
			<xsl:when test="detail[@id=221]">
				<div id="tei" style="padding-right: 10px">
					<xi:include href="{detail[@id=221]/file_fetch_url}"/>
				</div>
			</xsl:when>
		</xsl:choose>

		<table>
			<xsl:choose>
				<!-- render TEI document by a separate transform where the source document is WordML rather then TEI document -->
				<xsl:when test="detail[@id=221]"></xsl:when>
				<xsl:otherwise>
					<tr>
						<td>
							<nobr>TEI</nobr>
						</td>
						<td>
							<a href="{$cocoonbase}/item/{//id}/tei">
								[TEI document]
							</a>
						</td>
					</tr>
				</xsl:otherwise>
				</xsl:choose>



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
							<!-- 231 = WordML File! -->
							<xsl:when test="@id=231 or @id=221">
								<a href="{file_fetch_url}">
									[<xsl:value-of select="file_orig_name"/>]
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


		<xsl:call-template name="render_refs"/>

	</xsl:template>

	<xsl:template match="related | pointer | reverse-pointer">
		<!-- this is where the display work is done summarising the related items of various types - pictures, events etc -->
		<!-- reftype-specific templates take precedence over this one -->
		<xsl:param name="matches"/>

		<!-- trickiness!
			First off, this template will catch a single related (/ pointer / reverse-pointer) record,
			with the full list as a parameter ("matches").  This gives the template a chance to sort the records
			and call itself with those sorted records
		-->
		<xsl:choose>
			<xsl:when test="$matches">
				<xsl:apply-templates select="$matches">
					<xsl:sort select="detail[@id=160]"/>
				</xsl:apply-templates>
			</xsl:when>
			<xsl:otherwise>

				<tr>
					<td>
						<xsl:if test="detail[@id = 222 or @id=223 or @id=224]">
							<xsl:if test="detail/file_thumb_url">
								<a href="{$cocoonbase}/item/{id}">

									<img src="{detail/file_thumb_url}"/>


								</a> <em>(Entry)</em>
								<br/>

							</xsl:if>
						</xsl:if>

						<a href="{$cocoonbase}/item/{id}/" class="sb_two">
							<xsl:choose>
								<!-- related / notes -->
								<xsl:when test="@notes">
									<xsl:attribute name="title">
										<xsl:value-of select="@notes"/>
									</xsl:attribute>
								</xsl:when>
							</xsl:choose>
							<xsl:choose>
								<xsl:when test="detail[@id=160]">
									<xsl:value-of select="detail[@id=160]"/>
								</xsl:when>
								<xsl:otherwise>
									<xsl:value-of select="title"/>
								</xsl:otherwise>
							</xsl:choose>
						</a>
					</td>
					<td align="right">
						<!-- change this to pick up the actuall system name of the reftye or to use the mapping method as in JHSB that calls human-readable-names.xml -->
						<img style="vertical-align: middle;horizontal-align: right" src="{$hbase}/img/reftype/{reftype/@id}.gif"/>
					</td>
				</tr>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template match="related[reftype/@id=98] | pointer[reftype/@id=98] | reverse-pointer[reftype/@id=98]">
		<!-- this is where the display work is done summarising the related items of various types - pictures, events etc -->
		<!-- reftype-specific templates take precedence over this one -->
		<xsl:param name="matches"/>

		<!-- trickiness!
			First off, this template will catch a single related (/ pointer / reverse-pointer) record,
			with the full list as a parameter ("matches").  This gives the template a chance to sort the records
			and call itself with those sorted records
		-->
		<xsl:choose>
			<xsl:when test="$matches">
				<xsl:apply-templates select="$matches">
					<xsl:sort select="detail[@id=169]"/>
				</xsl:apply-templates>
			</xsl:when>
			<xsl:otherwise>

				<tr>
					<td>
					     <a href="{$urlbase}/edit.html?id={id}"
							onclick="window.open(this.href,'','status=0,scrollbars=1,resizable=1,width=800,height=600'); return false;"
							title="edit">
						<img src="{$hbase}/img/edit-pencil.gif"/>
						</a>
						<a href="{$cocoonbase}/item/{id}" class="sb_two"><xsl:value-of select="detail[@id=160]"/></a>
					</td>
					<td align="right">
						<!-- change this to pick up the actuall system name of the reftye or to use the mapping method as in JHSB that calls human-readable-names.xml -->
						<img style="vertical-align: middle;horizontal-align: right" src="{$hbase}/img/reftype/{reftype/@id}.gif"/>
					</td>
				</tr>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>


</xsl:stylesheet>
