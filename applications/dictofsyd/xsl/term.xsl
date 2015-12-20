<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:template name="term" match="record[type/@conceptID='1084-29']">
<!--
		<xsl:variable name="related" select="
			relationships
				/record
					/detail[@id=202 or @id=199]
						/record[id != current()/id]
		"/>
-->
		<xsl:variable name="related" select="/hml/records/record[@depth=1 and relationship/@relatedRecordID = current()/id]"/>

		<div id="subject-list">
			<!-- dc.description -->
			<xsl:if test="detail[@conceptID='2-4']">
				<p>
					<xsl:value-of select="detail[@conceptID='2-4']"/>
				</p>
			</xsl:if>

			<xsl:call-template name="termRelatedItems">
				<xsl:with-param name="label">Entries</xsl:with-param>
				<xsl:with-param name="items" select="$related[type/@conceptID='2-13']"/>
			</xsl:call-template>

			<xsl:call-template name="termRelatedItems">
				<xsl:with-param name="label">People</xsl:with-param>
				<xsl:with-param name="items" select="$related[type/@conceptID='1084-25'][detail[@conceptID='1084-75'] = 'Person']"/>
			</xsl:call-template>
			<xsl:call-template name="termRelatedItems">
				<xsl:with-param name="label">Artefacts</xsl:with-param>
				<xsl:with-param name="items" select="$related[type/@conceptID='1084-25'][detail[@conceptID='1084-75'] = 'Artefact']"/>
			</xsl:call-template>
			<xsl:call-template name="termRelatedItems">
				<xsl:with-param name="label">Buildings</xsl:with-param>
				<xsl:with-param name="items" select="$related[type/@conceptID='1084-25'][detail[@conceptID='1084-75'] = 'Building']"/>
			</xsl:call-template>
			<xsl:call-template name="termRelatedItems">
				<xsl:with-param name="label">Events</xsl:with-param>
				<xsl:with-param name="items" select="$related[type/@conceptID='1084-25'][detail[@conceptID='1084-75'] = 'Event']"/>
			</xsl:call-template>
			<xsl:call-template name="termRelatedItems">
				<xsl:with-param name="label">Natural features</xsl:with-param>
				<xsl:with-param name="items" select="$related[type/@conceptID='1084-25'][detail[@conceptID='1084-75'] = 'Natural feature']"/>
			</xsl:call-template>
			<xsl:call-template name="termRelatedItems">
				<xsl:with-param name="label">Organisations</xsl:with-param>
				<xsl:with-param name="items" select="$related[type/@conceptID='1084-25'][detail[@conceptID='1084-75'] = 'Organisation']"/>
			</xsl:call-template>
			<xsl:call-template name="termRelatedItems">
				<xsl:with-param name="label">Places</xsl:with-param>
				<xsl:with-param name="items" select="$related[type/@conceptID='1084-25'][detail[@conceptID='1084-75'] = 'Place']"/>
			</xsl:call-template>
			<xsl:call-template name="termRelatedItems">
				<xsl:with-param name="label">Structures</xsl:with-param>
				<xsl:with-param name="items" select="$related[type/@conceptID='1084-25'][detail[@conceptID='1084-75'] = 'Structure']"/>
			</xsl:call-template>

			<xsl:call-template name="termRelatedItems">
				<xsl:with-param name="label">Pictures</xsl:with-param>
				<xsl:with-param name="items" select="$related[type/@conceptID='2-5'][starts-with(detail[@conceptID='2-29'], 'image')]"/>
			</xsl:call-template>
			<xsl:call-template name="termRelatedItems">
				<xsl:with-param name="label">Sound</xsl:with-param>
				<xsl:with-param name="items" select="$related[type/@conceptID='2-5'][starts-with(detail[@conceptID='2-29'], 'audio')]"/>
			</xsl:call-template>
			<xsl:call-template name="termRelatedItems">
				<xsl:with-param name="label">Video</xsl:with-param>
				<xsl:with-param name="items" select="$related[type/@conceptID='2-5'][starts-with(detail[@conceptID='2-29'], 'video')]"/>
			</xsl:call-template>
			<xsl:call-template name="termRelatedItems">
				<xsl:with-param name="label">Maps</xsl:with-param>
				<xsl:with-param name="items" select="$related[type/@conceptID='1084-28']"/>
			</xsl:call-template>

			<!-- ARTEM TODO   for last tree above was [../../detail[@id=200]='IsRelatedTo']  		
			<xsl:variable name="annotations" select="/hml/records/record[@depth=1 and type/@conceptID='2-15' and detail[@conceptID='2-13'] = current()/id]"/>
			
			<xsl:call-template name="termRelatedItems">
				<xsl:with-param name="label">Mentioned in</xsl:with-param>
				<xsl:with-param name="items" select="reversePointer[@id=199]/record[type/@id=99]"/>
			</xsl:call-template>
			
			<xsl:call-template name="termRelatedItems">
				<xsl:with-param name="label">External links</xsl:with-param>
				<xsl:with-param name="items" select="
					$related[
						../../detail[@id=200]='hasExternalLink' or
						../../detail[@id=200]='isExternalLinkOf'
					][type/@id=1]"/>
			</xsl:call-template>
-->
		</div>

	</xsl:template>


	<xsl:template name="termRelatedItems">
		<xsl:param name="label"/>
		<xsl:param name="items"/>

		<xsl:variable name="type">
			<xsl:call-template name="getRecordTypeClassName">
				<xsl:with-param name="record" select="$items[1]"/>
			</xsl:call-template>
		</xsl:variable>

		<xsl:if test="count($items) > 0">
			<div class="list-left-col list-{$type}" title="{$label}"></div>
			<div class="list-right-col">
				<div class="list-right-col-browse">
					<ul>
						<xsl:for-each select="$items">
							<xsl:sort select="detail[@conceptID='2-1']"/>
							<li>
								<a href="{id}" class="preview-{id}">
									<xsl:value-of select="detail[@conceptID='2-1']"/>
								</a>
							</li>
						</xsl:for-each>
					</ul>
				</div>
			</div>
			<div class="clearfix"/>
		</xsl:if>
	</xsl:template>


	<xsl:template match="record[type/@conceptID='1084-29']" mode="sidebar">
		
		<xsl:variable name="related_broder_terms" select="relationship[(@useInverse='true' and @type='HasNarrowerTerm') or ((not(@useInverse) or @useInverse='false') and @type='HasBroaderTerm')]/@relatedRecordID"/>
		<xsl:variable name="related_narrow_terms" select="relationship[(@useInverse='true' and @type='HasBroaderTerm') or ((not(@useInverse) or @useInverse='false') and @type='HasNarrowerTerm')]/@relatedRecordID"/>
		<div id="connections">
			<h3>Connections</h3>
			<xsl:call-template name="relatedItems">
				<xsl:with-param name="label">Broader subjects</xsl:with-param>
				<xsl:with-param name="items" select="/hml/records/record[@depth=1 and type/@conceptID='1084-29' and id=$related_broder_terms]"/>
			</xsl:call-template>
			<xsl:call-template name="relatedItems">
				<xsl:with-param name="label">Narrower subjects</xsl:with-param>
				<xsl:with-param name="items" select="/hml/records/record[@depth=1 and type/@conceptID='1084-29' and id=$related_narrow_terms]"/>
			</xsl:call-template>
		</div>
	</xsl:template>

</xsl:stylesheet>
