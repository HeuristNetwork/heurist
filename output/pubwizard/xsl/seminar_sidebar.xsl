<?xml version="1.0" encoding="UTF-8"?>

<!-- stylesheet created by Steven Hayes to render sidebar showing upcoming seminars -->

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:variable name="limit" select="3"/>

	<xsl:template match="/">

		<table>
			<xsl:call-template name="ref_loop">
				<xsl:with-param name="i" select="2"/>
				<xsl:with-param name="c" select="0"/>
			</xsl:call-template>
		</table>
	</xsl:template>

    <xsl:template name="ref_loop">
		<xsl:param name="i"/>
		<xsl:param name="c"/>

		<xsl:variable name="refs" select="//export/references/reference"/>

		<xsl:if test="$c &lt; $limit  and  $i &lt;= count($refs)">

			<xsl:variable name="ref" select="$refs[$i]"/>
			<xsl:variable name="refdate" select="$ref/detail[@id=177]"/>

			<xsl:variable name="y" select="//export/date_generated/year"/>
			<xsl:variable name="m" select="//export/date_generated/month"/>
			<xsl:variable name="d" select="//export/date_generated/day"/>

			<xsl:choose>
				<xsl:when test="($refdate/year &gt; $y)
							or
								($refdate/year   =   $y  and
								 $refdate/month &gt; $m)
							or
								($refdate/year   =  $y  and
								 $refdate/month  =  $m  and
								 $refdate/day &gt;= $d)">
					<tr><td>
					<a href="events/public.shtml#{$ref/id}"><xsl:value-of select="$ref/detail[@id=160]"/></a> -
					<xsl:apply-templates select="$ref/detail[@id=177]"/>
						<xsl:if test="$ref/detail[@id = 223]">
							<div style="text-align: center;">
							<img src="{$ref/detail[@id = 223]/file_thumb_url}" alt="" align="middle"/>
							</div>
						</xsl:if>
						<xsl:if test="$ref/detail[@id = 221]">
							<div style="text-align: center;">
							<a href="{$ref/detail[@id = 221]/file_fetch_url}" target="_blank" style="font-size: 80%;">download pdf</a>
						              </div>
						</xsl:if>
					<hr/>
					</td></tr>

					<xsl:call-template name="ref_loop">
						<xsl:with-param name="i" select="$i + 1"/>
						<xsl:with-param name="c" select="$c + 1"/>
					</xsl:call-template>
				</xsl:when>

				<xsl:otherwise>
					<xsl:call-template name="ref_loop">
						<xsl:with-param name="i" select="$i + 1"/>
						<xsl:with-param name="c" select="$c"/>
					</xsl:call-template>
				</xsl:otherwise>
			</xsl:choose>
		</xsl:if>
	</xsl:template>


    <xsl:template name="detail">
        <xsl:value-of select="."/>

        <br/>
        <xsl:apply-templates select="year"/>
        <xsl:apply-templates select="month"/>
        <xsl:apply-templates select="day"/>
    </xsl:template>

    <xsl:template match="year"/>
    <xsl:template match="month"/>
    <xsl:template match="day"/>

</xsl:stylesheet>
