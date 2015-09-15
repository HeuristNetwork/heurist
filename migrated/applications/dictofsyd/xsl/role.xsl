<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:exsl="http://exslt.org/common"
                exclude-result-prefixes="exsl"
                version="1.0">

	<xsl:template name="role" match="record[type/@conceptID='1084-27']">

			<!-- dc.description -->
			<xsl:if test="detail[@conceptID='2-4']">
				<div class="entity-content">
					<p>
						<xsl:value-of select="detail[@conceptID='2-4']"/>
					</p>
				</div>
			</xsl:if>


		<xsl:variable name="factoids_pointers" select="reversePointer[@conceptID='1084-88']"/>
		<xsl:variable name="factoids_recs" select="/hml/records/record[@depth=1 and id=$factoids_pointers]"/>
		
		<!-- factoids without a target -->
		<xsl:variable name="factoids_wo_targets" select="$factoids_recs[not(detail[@conceptID='1084-86'])]"/>
<!--		
		<div>
			<xsl:value-of select="count($factoids_wo_targets)"/><br/>
			<xsl:for-each select="$factoids_wo_targets">
				<xsl:value-of select="id"/><br/>
			</xsl:for-each>
		</div>
-->		
		
			<xsl:call-template name="roleFactoidGroup">
				<xsl:with-param name="factoids" select="$factoids_wo_targets"/>
				<xsl:with-param name="role" select="."/>
			</xsl:call-template>
		

			<xsl:variable name="factoids_target2" select="/hml/records/record[@depth=2 and id=$factoids_recs/detail[@conceptID='1084-86']]"/>
		
			<xsl:variable name="targets">
				<xsl:for-each select="$factoids_target2/detail[@conceptID='2-1'] | $factoids_recs/detail[@conceptID='2-97']">
					<xsl:sort/>
					<target>
						<xsl:if test="@conceptID='2-1'">
							<xsl:attribute name="id"><xsl:value-of select="../id"/></xsl:attribute>
						</xsl:if>
						<xsl:value-of select="."/>
					</target>
				</xsl:for-each>
			</xsl:variable>

			<xsl:variable name="base" select="."/>

			<xsl:for-each select="exsl:node-set($targets)/target[not(text() = preceding-sibling::*/text())]">
			
				<xsl:call-template name="roleFactoidGroup">
					<xsl:with-param name="target_id" select="@id"/>
					<xsl:with-param name="target_name" select="text()"/>
					<xsl:with-param name="factoids" select="$factoids_recs[detail[@conceptID='1084-86']=current()/@id]"/> <!--   or $factoids_recs/detail[@conceptID='2-97']=current()/text() -->
					<xsl:with-param name="role" select="$base"/>
				</xsl:call-template>
				
			</xsl:for-each>

	</xsl:template>


	<xsl:template name="roleFactoidGroup">
		<xsl:param name="factoids"/>
		<xsl:param name="role"/>
		<xsl:param name="target_id"/>
		<xsl:param name="target_name"/>
		
		<xsl:if test="$factoids">
			<div class="entity-information">
				<xsl:if test="$target_id">
					<xsl:attribute name="id">t<xsl:value-of select="$target_id"/></xsl:attribute>
				</xsl:if>

				<div class="entity-information-heading">
					<xsl:choose>
						<xsl:when test="$target_id">
							<xsl:value-of select="$factoids[1]/detail[@conceptID='1084-85']"/> <!-- factoid type -->
							<xsl:text> - </xsl:text>
							<xsl:value-of select="$role/detail[@conceptID='2-1']"/>
							<xsl:text> of </xsl:text>
							<a href="{$target_id}" class="preview-{$target_id}">
								<xsl:value-of select="$target_name"/>
							</a>
						</xsl:when>
						<xsl:otherwise>
							<xsl:value-of select="$role/detail[@conceptID='1084-95']"/>
							<xsl:text> - </xsl:text>
							<xsl:value-of select="$role/detail[@conceptID='2-1']"/>
						</xsl:otherwise>
					</xsl:choose>
				</div>
				
				<xsl:for-each select="$factoids">

					<xsl:sort select="/hml/records/record[id=(current()/detail[@conceptID='1084-87'])]/detail[@conceptID='2-1']"/>
					<xsl:sort select="detail[@conceptID='2-10']/year"/>
					<xsl:sort select="detail[@conceptID='2-10']/month"/>
					<xsl:sort select="detail[@conceptID='2-10']/day"/>
					<xsl:sort select="detail[@conceptID='2-11']/year"/>
					<xsl:sort select="detail[@conceptID='2-11']/month"/>
					<xsl:sort select="detail[@conceptID='2-11']/day"/>
					
						<xsl:call-template name="roleFactoid">
							<xsl:with-param name="factoid" select="."/>
						</xsl:call-template>

					<div class="clearfix"></div>
				</xsl:for-each>
				
			</div>
		</xsl:if>
	</xsl:template>
</xsl:stylesheet>
