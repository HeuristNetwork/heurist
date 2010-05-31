<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
	<xsl:template name="news_item" match="reference[reftype/@id=48]">
        <xsl:param name="style"/>
        <xsl:param name="year"/> 
        <xsl:param name="month"/>
        <xsl:param name="day"/>

		<xsl:choose>
			<xsl:when test="$style = 'archive'">
				<xsl:choose>
					<xsl:when test="detail[@id=166]/year &lt; $year - 1"/>
					<xsl:when test="detail[@id=166]/year = $year - 1  and  detail[@id=166]/month &lt; $month"/>
					<xsl:when test="detail[@id=166]/year = $year - 1  and  detail[@id=166]/month = $month  and  detail[@id=166]/day &lt; $day"/>
					<xsl:otherwise>
						<xsl:call-template name="render_news_item"/>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:when>
			<xsl:otherwise>
				<xsl:choose>
					<xsl:when test="$month &gt; 3  and  detail[@id=166]/year &lt; $year"/>
					<xsl:when test="$month &gt; 3  and  detail[@id=166]/year = $year  and  detail[@id=166]/month &lt; $month - 3"/>
					<xsl:when test="$month &gt; 3  and  detail[@id=166]/year = $year  and  detail[@id=166]/month = $month - 3  and  detail[@id=166]/day &lt; $day"/>
					<xsl:when test="$month &lt;= 3  and  detail[@id=166]/year &lt; $year - 1"/>
					<xsl:when test="$month &lt;= 3  and  detail[@id=166]/year = $year - 1  and  detail[@id=166]/month &lt; $month + 9"/>
					<xsl:when test="$month &lt;= 3  and  detail[@id=166]/year = $year - 1  and  detail[@id=166]/month = $month + 9  and  detail[@id=166]/day &lt; $day"/>
					<xsl:otherwise>
						<xsl:call-template name="render_news_item"/>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:otherwise>
		</xsl:choose>

	</xsl:template>


	<xsl:template name="render_news_item">
		<tr>
			<td>
				<xsl:if test="detail[@id=222]"><!-- logo image -->
					<xsl:for-each select="detail[@id=222]">
						<img src="{file_fetch_url}&amp;h=50"/>
					</xsl:for-each>
					<br/>
				</xsl:if>
				<a target="_new" href="reftype_renderer/{id}">
					<xsl:value-of select="detail[@id=160]"/><!-- Title -->
				</a>
				<xsl:text>,&#160;
				</xsl:text>
				<b><xsl:value-of select="detail[@id=166]/text()"/></b><!-- Date -->
				<br/>
				<xsl:value-of select="detail[@id=303]"/><!-- Summary for web -->
				<br/><br/>
			</td>
			<td>
				<xsl:if test="detail[@id=223]"><!-- thumbnail -->
					<img src="{detail[@id=223]/file_thumb_url}"/>
				</xsl:if>
			</td>
		</tr>
	</xsl:template>
</xsl:stylesheet>
