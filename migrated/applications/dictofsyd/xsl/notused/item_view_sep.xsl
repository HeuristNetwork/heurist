<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:exsl="http://exslt.org/common"
                exclude-result-prefixes="exsl"
                version="1.0">

	<xsl:include href="myvariables.xsl"/>
	<xsl:include href="util.xsl"/>

	<xsl:include href="framework.xsl"/>
	
	<xsl:include href="media.xsl"/>
	
	<xsl:include href="previews.xsl"/>
<!--
	<xsl:include href="factoid.xsl"/>
	<xsl:include href="hi_res_image.xsl"/>
	<xsl:include href="entry.xsl"/>
	<xsl:include href="annotation.xsl"/>
	<xsl:include href="entity.xsl"/>
	<xsl:include href="role.xsl"/>
	<xsl:include href="map.xsl"/>
	<xsl:include href="term.xsl"/>
	<xsl:include href="contributor.xsl"/>
-->

	<xsl:variable name="record" select="hml/records/record"/>

	<xsl:template match="/">
		<xsl:call-template name="framework">
			<xsl:with-param name="title" select="$record/detail[@conceptID='2-1']"/>
		</xsl:call-template>
	</xsl:template>

	<xsl:template name="content">
		<xsl:call-template name="makeTitleDiv">
			<xsl:with-param name="record" select="$record"/>
		</xsl:call-template>
		<xsl:apply-templates select="$record"/>
	</xsl:template>
	
	
	<xsl:template name="sidebar">
		<xsl:apply-templates select="$record" mode="sidebar"/>
	</xsl:template>
	
	<xsl:template match="record" mode="sidebar"/>
	
<!--
	<xsl:template name="connections">
		<xsl:param name="omit"/>
		<xsl:variable name="related" select="
			relationships
				/record
					/detail[@id=202 or @id=199]
						/record[id != current()/id]
		"/>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Entries</xsl:with-param>
			<xsl:with-param name="items" select="$related[type/@id=98]"/>
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Pictures</xsl:with-param>
			<xsl:with-param name="items" select="$related[type/@id=74][starts-with(detail[@id=289], 'image')]"/>
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Sound</xsl:with-param>
			<xsl:with-param name="items" select="$related[@type='IsRelatedTo'][type/@id=74][starts-with(detail[@id=289], 'audio')]"/>
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Video</xsl:with-param>
			<xsl:with-param name="items" select="$related[@type='IsRelatedTo'][type/@id=74][starts-with(detail[@id=289], 'video')]"/>
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Maps</xsl:with-param>
			<xsl:with-param name="items" select="$related[@type='IsRelatedTo'][type/@id=103]"/>
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Subjects</xsl:with-param>
			<xsl:with-param name="items" select="$related[type/@id=152]"/>
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">Mentioned in</xsl:with-param>
			<xsl:with-param name="items" select="reversePointer[@id=199]/record[type/@id=99]"/>
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>
		<xsl:call-template name="relatedItems">
			<xsl:with-param name="label">External links</xsl:with-param>
			<xsl:with-param name="items" select="$related[@type='hasExternalLink'][type/@id=1]"/>
			<xsl:with-param name="omit" select="$omit"/>
		</xsl:call-template>
	</xsl:template>


	<xsl:template name="relatedItems">
		<xsl:param name="label"/>
		<xsl:param name="items"/>
		<xsl:param name="omit"/>

		<xsl:variable name="type">
			<xsl:call-template name="getRecordTypeClassName">
				<xsl:with-param name="record" select="$items[1]"/>
			</xsl:call-template>
		</xsl:variable>

		<xsl:if test="count($items) > 0  and  not(exsl:node-set($omit)[section=$label])">
			<div class="menu">
				<h4 class="menu-{$type}"><xsl:value-of select="$label"/></h4>
			</div>
			<div class="submenu">
				<ul>
					<xsl:apply-templates select="$items[1]">
						<xsl:with-param name="matches" select="$items"/>
					</xsl:apply-templates>
				</ul>
			</div>
		</xsl:if>

	</xsl:template>
-->

		<!-- This template is to be called in the context of just one record,
		     with the whole list in the "matches" variable.  This gives the template
		     a chance to sort the list itself, while still allowing the magic of the
		     match parameter: the single apply-templates in the relatedItems template
		     above might call this template, or another, such as the one below,
		     depending on the items in question.

	<xsl:template match="detail/record | reversePointer/record">
		<xsl:param name="matches"/>

		<xsl:for-each select="$matches">
			<xsl:sort select="detail[@conceptID='2-1']"/>

			<xsl:variable name="class">
				<xsl:if test="type/@conceptID='2-5'">
					<xsl:text>popup </xsl:text>
				</xsl:if>
				<xsl:text>preview-</xsl:text>
				<xsl:value-of select="id"/>
				<xsl:if test="local-name(../../..) = 'relationships'">
					<xsl:text>c</xsl:text>
					<xsl:value-of select="../../id"/>
				</xsl:if>
			</xsl:variable>

			<li>
				<a href="{id}" class="{$class}">
					<xsl:choose>
						<xsl:when test="detail[@conceptID='2-1']">
							<xsl:value-of select="detail[@conceptID='2-1']"/>
						</xsl:when>
						<xsl:otherwise>
							<xsl:value-of select="title"/>
						</xsl:otherwise>
					</xsl:choose>
				</a>
			</li>
		</xsl:for-each>
	</xsl:template>
-->

<!-- external links: link to external link, new window, no preview
	<xsl:template match="relationships/record/detail/record[type/@id=1]">

		<xsl:param name="matches"/>
		<xsl:for-each select="$matches">
			<xsl:sort select="detail[@id=160]"/>
			<li>
				<a href="{detail[@id=198]}" target="_blank">
					<xsl:choose>
						<xsl:when test="detail[@id=160]">
							<xsl:value-of select="detail[@id=160]"/>
						</xsl:when>
						<xsl:otherwise>
							<xsl:value-of select="title"/>
						</xsl:otherwise>
					</xsl:choose>
				</a>
			</li>
		</xsl:for-each>
	</xsl:template>
-->
	
</xsl:stylesheet>
