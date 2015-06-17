<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
	<xsl:template name="course_unit" match="record[type/@id=70]">
		<xsl:param name="style"/>
		<div id="{id}" class="record  L{@depth}">
			<xsl:if test="detail[@id=223]"><!-- thumbnail -->
				<img src="{detail[@id=223]/file/thumbURL}" class="thumbnail"/>
			</xsl:if>

				<b>
					<xsl:value-of select="detail[@id=269]"/>
				</b>
				<!-- Course Code -->
				<br/>
				<a target="_new" href="rectype_renderer/{id}">
					<xsl:value-of select="detail[@id=160]"/>
					<!-- Title -->
				</a>
				<xsl:if test="detail[@id=304]">
					<!-- Handbook entry URL -->
					<br/>
					<a target="_new" href="{detail[@id=304]}">Handbook entry</a>
				</xsl:if>
				<xsl:if test="detail[@id=257]">
					<!-- Semester -->
					<br/>
					<xsl:value-of select="detail[@id=257]"/>
				</xsl:if>
				<xsl:choose>
					<xsl:when test="$style = 'timetable'">
						<xsl:if test="detail[@id=305]"><!-- Lecture times -->
							<br/> Lectures: <xsl:value-of select="detail[@id=305]"/>
						</xsl:if>
						<xsl:if test="detail[@id=306]"><!-- Tutorial times -->
							<br/> Tutorials: <xsl:value-of select="detail[@id=306]"/>
						</xsl:if>
					</xsl:when>

					<xsl:otherwise>
						<br/>
						<xsl:for-each select="detail[@id=249]/record">
							<!-- Person -->
							<xsl:value-of select="title"/>
							<xsl:if test="position() != last()">, </xsl:if>
						</xsl:for-each>
						<xsl:if test="detail[@id=303]">
							<!-- Web summary -->
							<br/>
							<xsl:value-of select="detail[@id=303]"/>
						</xsl:if>
					</xsl:otherwise>
				</xsl:choose>
		</div>
	</xsl:template>
</xsl:stylesheet>
