<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<!--
		tei to html basic style sheet written by Kim Jackson
		v3 - 23 October 2008
		preserves document structure (below TEI/text/body2/div
	-->

	<!-- identity transform
	<xsl:template match="@*|node()">
		<xsl:copy>
			<xsl:apply-templates select="@*|node()"/>
		</xsl:copy>
	</xsl:template>
	-->

	<!-- this gets rid of the leftover processing-instruction from wordml
	<xsl:template match="div[@id='tei']">
		<xsl:copy>
			<xsl:apply-templates select="TEI|@*"/>
		</xsl:copy>
	</xsl:template>
	-->

	<!-- only use text/body2/div from TEI, discard the rest -->
	<xsl:template match="TEI">
		<div id="tei">
			<xsl:apply-templates select="text/body2/div"/>
		</div>
	</xsl:template>


	<xsl:template match="TEI/text/body2/div">
		<xsl:copy>
			<xsl:apply-templates/>
			<xsl:if test="position() = last()">
				<h2>Notes</h2>
				<xsl:call-template name="copy-footnotes">
					<xsl:with-param name="footnotes" select="//note"/>
				</xsl:call-template>
			</xsl:if>
		</xsl:copy>
	</xsl:template>


	<xsl:template match="TEI//div/head">
		<h2>
			<xsl:apply-templates/>
		</h2>
	</xsl:template>



	<xsl:template match="TEI//p">
		<p>
			<xsl:if test="@type">
				<xsl:attribute name="class">
					<xsl:value-of select="@type"/>
				</xsl:attribute>
			</xsl:if>
			<xsl:apply-templates/>
		</p>
	</xsl:template>


	<xsl:template match="TEI//hi">
		<span class="{@rend}">
			<xsl:apply-templates/>
		</span>
	</xsl:template>

	<xsl:template match="TEI//quote">
		<span class="quote">
			<xsl:apply-templates/>
		</span>
	</xsl:template>

	<xsl:template match="TEI//note">
		<xsl:variable name="note_number">
			<xsl:number count="TEI//note" level="any"/>
		</xsl:variable>

		<!-- move leading whitespace out of the note -->
		<xsl:if test="substring(., 1, 1) = ' '">
			<xsl:text> </xsl:text>
		</xsl:if>

		<span class="note">
			<a class="footnote note{$note_number}" href="#" onclick="showFootnotes({$note_number}); return false;" title="{normalize-space(.)}">[<xsl:value-of select="$note_number"/>]</a>
		</span>
	</xsl:template>

	<xsl:template name="copy-footnotes">
		<xsl:param name="footnotes"/>
		<div class="footnotes">
			<xsl:for-each select="$footnotes">
				<xsl:variable name="note_number">
					<xsl:number count="TEI//note" level="any"/>
				</xsl:variable>
				<div class="footnote-content fnote{$note_number}">
					<a href="#" onclick="highlightNote('{$note_number}'); return false;">[<xsl:value-of select="$note_number"/>]</a>
					<xsl:text> </xsl:text>
					<xsl:value-of select="."/>
				</div>
			</xsl:for-each>
			.
		</div>
	</xsl:template>

	<!-- table -->
	<xsl:template match="TEI//table">
		<table cellpadding="2" cellspacing="2">
			<xsl:apply-templates/>
		</table>
	</xsl:template>
	<xsl:template match="TEI//table/row">
		<tr>
			<xsl:choose>
				<xsl:when test="position() = 1">
					<xsl:apply-templates mode="first"/>
				</xsl:when>
				<xsl:otherwise>
					<xsl:apply-templates/>
				</xsl:otherwise>
			</xsl:choose>
		</tr>
	</xsl:template>
	<xsl:template match="TEI//table/row/cell" mode="first">
		<th>
			<xsl:apply-templates/>
		</th>
	</xsl:template>
	<xsl:template match="TEI//table/row/cell">
		<td>
			<xsl:apply-templates/>
		</td>
	</xsl:template>


	<!-- list -->
	<xsl:template match="TEI//list">
		<ul>
			<xsl:apply-templates/>
		</ul>
	</xsl:template>

	<xsl:template match="TEI//item">
		<li>
			<xsl:apply-templates/>
		</li>
	</xsl:template>

	<xsl:template match="TEI//lb">
		<br/>
	</xsl:template>

</xsl:stylesheet>
