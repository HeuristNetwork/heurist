<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:str="http://exslt.org/strings" version="1.0">

	<xsl:param name="flavour"/>

	<xsl:template match="related[reftype/@id=55] | pointer[reftype/@id=55] | reverse-pointer[reftype/@id=55]">
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
					<xsl:element name="td">
						<xsl:attribute name="class">relateditem</xsl:attribute>
						<xsl:attribute name="title">{Relationship type: <xsl:value-of select="@type"/>}
							<xsl:if test="@notes">
								&#160; <xsl:value-of select="@notes"/>
							</xsl:if>
						</xsl:attribute>
						<xsl:if test="detail/file_thumb_url">
							<a href="{$base}/item/{id}/{/export/references/reference/reftype/@id}?flavour={$flavour}">
								<img src="{detail/file_thumb_url}"/>
							</a>
							<br/>
						</xsl:if>

						<a href="{$base}/item/{id}/{/export/references/reference/reftype/@id}?flavour={$flavour}">
							<xsl:value-of select="detail[@id=291]"/>
							<xsl:text> </xsl:text>
							<xsl:value-of select="detail[@id=160]"/>
						</a>
					</xsl:element>
				</tr>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>


	<xsl:template name="person" match="reference[reftype/@id=55]">
		<table>
			<tr>
				<td>
					<xsl:if test="detail[@id=223]">
						<!-- thumbnail -->
						<div style="float: left;">
							<xsl:for-each select="detail[@id=223]">
								<img src="{file_fetch_url}" vspace="10" hspace="10"/>
							</xsl:for-each>
						</div>
					</xsl:if>




					<xsl:if test="detail[@id=255]">
						<xsl:for-each select="detail[@id=255]">
							<!-- role -->
							<em><xsl:value-of select="text()"/></em>
							<xsl:if test="position() != last()">,&#160; </xsl:if>
						</xsl:for-each>
					</xsl:if>
					<xsl:if test="detail[@id=266]">
						<xsl:call-template name="paragraphise">
							<xsl:with-param name="text">
								<xsl:value-of select="detail[@id=266]"/>
							</xsl:with-param>
						</xsl:call-template>
					</xsl:if>


					<br/>
					<xsl:if test="detail[@id=191]">
						<xsl:call-template name="paragraphise">
							<xsl:with-param name="text">
								<xsl:value-of select="detail[@id=191]"/>
							</xsl:with-param>
						</xsl:call-template>
					</xsl:if>

					<br/>
				</td>
			</tr>
		</table>
	</xsl:template>


</xsl:stylesheet>
