<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:template name="contributor" match="record[type/@conceptID='1084-24']">

		<xsl:variable name="rev_pointers" select="reversePointer[@conceptID='1084-90']"/>
		<xsl:variable name="contributors_recs" select="/hml/records/record[@depth=1 and id=$rev_pointers]"/>

		<xsl:variable name="entries" select="$contributors_recs[type/@conceptID='2-13']"/>

		<xsl:variable name="images" select="$contributors_recs[type/@conceptID='2-5'][starts-with(detail[@conceptID='2-29'], 'image')] |
											$contributors_recs[type/@conceptID='2-11'][detail[@conceptID='2-30']='image']"/>
		
		<xsl:variable name="audio" select="$contributors_recs[type/@conceptID='2-5'][starts-with(detail[@conceptID='2-29'], 'audio')]"/>

		<xsl:variable name="video" select="$contributors_recs[type/@conceptID='2-5'][starts-with(detail[@conceptID='2-29'], 'video')]"/>
		

		<div id="subject-list">
			<!-- dc.description -->
			<xsl:if test="detail[@conceptID='2-4']">
				<p>
					<xsl:value-of select="detail[@conceptID='2-4']"/>
				</p>
			</xsl:if>

			<!-- contributor.link -->
			<xsl:variable name="link" select="detail[@conceptID='2-17']"/>
			<xsl:if test="$link">
				<p>
					<xsl:text>Click </xsl:text>
					<a target="_blank">
						<xsl:attribute name="href">
							<xsl:call-template name="linkify">
								<xsl:with-param name="string" select="$link"/>
							</xsl:call-template>
						</xsl:attribute>
						<xsl:text>here</xsl:text>
					</a>
					<xsl:text> to visit this contributor.</xsl:text>
				</p>
			</xsl:if>

			<xsl:if test="$entries">
				<div class="list-left-col list-entry" title="Entries"></div>
				<div class="list-right-col">
					<div class="list-right-col-browse">
						<ul>
							<xsl:for-each select="$entries">
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

			<xsl:if test="$images">
				<div class="list-left-col list-image" title="Pictures"></div>
				<div class="list-right-col">
					<div class="list-right-col-browse">
						<ul>
							<xsl:for-each select="$images">
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

			<xsl:if test="$audio">
				<div class="list-left-col list-audio" title="Sound"></div>
				<div class="list-right-col">
					<div class="list-right-col-browse">
						<ul>
							<xsl:for-each select="$audio">
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

			<xsl:if test="$video">
				<div class="list-left-col list-video" title="Video"></div>
				<div class="list-right-col">
					<div class="list-right-col-browse">
						<ul>
							<xsl:for-each select="$video">
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
		</div>

	</xsl:template>

</xsl:stylesheet>
