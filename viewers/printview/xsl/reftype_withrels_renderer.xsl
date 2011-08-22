<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:param name="arg"/>
<!-- 
	<xsl:include href="course_unit.xsl"/>
	<xsl:include href="event.xsl"/>
	<xsl:include href="conference.xsl"/>
	<xsl:include href="student_project_topic.xsl"/>
	<xsl:include href="research_project.xsl"/>
	<xsl:include href="faq.xsl"/>
	<xsl:include href="person.xsl"/>
	<xsl:include href="news_item.xsl"/>
	<xsl:include href="organisation.xsl"/>
	<xsl:include href="fieldwork_opportunity.xsl"/>
	<xsl:include href="thesis.xsl"/>
	<xsl:include href="publications.xsl"/>
	<xsl:include href="grant.xsl"/>
-->


	<xsl:template match="/">
		<!-- use the following bit of code to include the stylesheet to display it in Heurist publishing wizard
			otherwise it will be ommited-->
		<!-- begin including code -->
		<xsl:comment>
			<!-- name (desc.) that will appear in dropdown list -->
			[name]Departmental website style with relations[/name]
			<!-- match the name of the stylesheet-->
			[output]rectype_withrels_renderer[/output]
		</xsl:comment>
		<!-- end including code -->

				<xsl:attribute name="pub_id">
					<xsl:value-of select="/hml/query[@pub_id]"/>
				</xsl:attribute>

					START of Document <br/>
					<xsl:choose>
						<xsl:when test="number($arg) > 0">
							<xsl:apply-templates select="hml/records/record[id=$arg]"/>ARGUMENT<br/>
						</xsl:when>
						<xsl:when test="string(number($arg)) = 'NaN'">
							<xsl:apply-templates select="hml/records/record">
								<xsl:with-param name="style" select="$arg"/>
								<!-- <xsl:with-param name="year" select="/export/date_generated/year"/> -->
								<!-- <xsl:with-param name="month" select="/export/date_generated/month"/> -->
								<!-- <xsl:with-param name="day" select="/export/date_generated/day"/> -->
								<xsl:with-param name="year" select="substring(/hml/dateStamp, 1, 4)"/>
								<xsl:with-param name="month" select="substring(/hml/dateStamp, 6, 2)"/>
								<xsl:with-param name="day" select="substring(/hml/dateStamp, 9, 2)"/>
							</xsl:apply-templates>
							<br/>
						</xsl:when>
						<xsl:otherwise>
							<xsl:apply-templates select="hml/records/record"/>
							hml/records/record <br/>
						</xsl:otherwise>
					</xsl:choose>
	</xsl:template>


	<!-- detail output template -->
	<xsl:template match="record">
	<div id="{id}" class="record  L{@depth}">
	<table>
		<tr>
			<td class="detailType">
				<img>
					<xsl:attribute name="src">../../common/images/rectype-icons/<xsl:value-of select="type/@id"/>.png</xsl:attribute>
				</img>
			</td>
			<td class="detail" style="font-weight: bold;">
				<xsl:value-of select="title"/>
			</td>
		</tr>

		<tr>
			<td class="detailType">
				<nobr>Reference type</nobr>
			</td>
			<td class="detail">
				<xsl:value-of select="type"/>
			</td>
		</tr>

		<xsl:if test="url != ''">
			<tr>
				<td class="detailType">URL</td>
				<td class="detail">
					<a href="{url}">
						<xsl:choose>
							<xsl:when test="string-length(url) &gt; 50">
								<xsl:value-of select="substring(url, 0, 50)"/> ... </xsl:when>
							<xsl:otherwise>
								<xsl:value-of select="url"/>
							</xsl:otherwise>
						</xsl:choose>
					</a>
				</td>
			</tr>
		</xsl:if>

		<!-- this calls  short summary / description -->
		<xsl:for-each select="detail[@id!=222 and @id!=223 and @id!=224]">
			<tr>
				<td class="detailType">
					<nobr>
						<xsl:choose>
							<xsl:when test="string-length(@name)">
								<xsl:value-of select="@name"/>
							</xsl:when>
							<xsl:otherwise><xsl:value-of select="@type"/></xsl:otherwise>
						</xsl:choose>
					</nobr>
				</td>
				<td class="detail">
					<xsl:choose>
						<!-- 268 = Contact details URL,  256 = Web links -->
						<xsl:when test="@id=268  or  @id=256  or  starts-with(text(), 'http')">
							<a href="{text()}">
								<xsl:choose>
									<xsl:when test="string-length() &gt; 50">
										<xsl:value-of select="substring(text(), 0, 50)"/> ... </xsl:when>
									<xsl:otherwise>
										<xsl:copy-of select="text()"/>
									</xsl:otherwise>
								</xsl:choose>
							</a>
						</xsl:when>
						<!-- 221 = AssociatedFile,  231 = Associated File -->
						<xsl:when test="@id=221  or  @id=231">
							<a href="{file/url}">
								<xsl:value-of select="file/origName"/>
							</a>
						</xsl:when>
						<xsl:otherwise><xsl:copy-of select="text()"/></xsl:otherwise>
					</xsl:choose>
				</td>
			</tr>
		</xsl:for-each>

		<tr>
			<td class="detailType">
				<xsl:value-of select="detail[@id=264]/record/@name"/>
			</td>
			<td class="detail">
				<xsl:apply-templates select="detail[@id=264]/record"></xsl:apply-templates>
			</td>
		</tr>
		
		<tr>
			<td class="detailType">
				<xsl:value-of select="detail[@id=267]/record/@name"/> 
			</td>
			<td class="detail">
				<xsl:apply-templates select="detail[@id=267]/record"></xsl:apply-templates>
			</td>
		</tr>

		<xsl:if test="notes != ''">
			<tr>
				<td class="detailType">Notes</td>
				<td class="detail">
					<xsl:value-of select="notes"/>
				</td>
			</tr>
		</xsl:if>

		<xsl:if test="detail[@id=222 or @id=223 or @id=224]">
			<tr>
				<td class="detailType">Images</td>
				<td class="detail">
					<!-- 222 = Logo image,  223 = Thumbnail,  224 = Images -->
					<xsl:for-each select="detail[@id=222 or @id=223 or @id=224]">
						<a href="{file/url}">
							<img src="{file/thumbURL}" border="0"/>
						</a> &#160;&#160; </xsl:for-each>
				</td>
			</tr>
		</xsl:if>
		</table>
		</div>
	</xsl:template>

<xsl:template match="detail/record">
	<xsl:call-template name="title_group"/>
</xsl:template>

<xsl:template name="title_group" mode="blah">
	<xsl:value-of select="title"/><br/>
</xsl:template>
</xsl:stylesheet>
