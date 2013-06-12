<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:variable name="root" select="/hml/records/record[@depth=0]"/>

	<xsl:template name="factoids">
		<xsl:param name="factoid_recs"/>
		
		<xsl:if test="count($factoid_recs[detail[@conceptID='1084-85']='Type']) > 1">
			<xsl:call-template name="factoidGroup">
				<xsl:with-param name="heading">Types</xsl:with-param>
				<xsl:with-param name="factoids" select="$factoid_recs[detail[@conceptID='1084-85']='Type']"/>
			</xsl:call-template>
		</xsl:if>
		<xsl:call-template name="factoidGroup">
			<xsl:with-param name="heading">Names</xsl:with-param>
			<xsl:with-param name="factoids" select="$factoid_recs[detail[@conceptID='1084-85']='Name']"/>
		</xsl:call-template>
		<xsl:call-template name="factoidGroup">
			<xsl:with-param name="heading">Milestones</xsl:with-param>
			<xsl:with-param name="factoids" select="$factoid_recs[detail[@conceptID='1084-85']='Milestone']"/>
		</xsl:call-template>
		<xsl:call-template name="factoidGroup">
			<xsl:with-param name="heading">Relationships</xsl:with-param>
			<xsl:with-param name="factoids" select="$factoid_recs[detail[@conceptID='1084-85']='Relationship']"/>
		</xsl:call-template>
		<xsl:call-template name="factoidGroup">
			<xsl:with-param name="heading">Occupations</xsl:with-param>
			<xsl:with-param name="factoids" select="$factoid_recs[detail[@conceptID='1084-85']='Occupation']"/>
		</xsl:call-template>
		<xsl:call-template name="factoidGroup">
			<xsl:with-param name="heading">Positions</xsl:with-param>
			<xsl:with-param name="factoids" select="$factoid_recs[detail[@conceptID='1084-85']='Position']"/>
		</xsl:call-template>
		<xsl:call-template name="factoidGroup">
			<xsl:with-param name="heading">Property</xsl:with-param>
			<xsl:with-param name="factoids" select="$factoid_recs[detail[@conceptID='1084-85']='Property']"/>
		</xsl:call-template>
	</xsl:template>


	<xsl:template name="factoidGroup">
		<xsl:param name="heading"/>
		<xsl:param name="factoids"/>

		<xsl:if test="$factoids">
			<div class="entity-information">
				<div class="entity-information-heading">
					<xsl:value-of select="$heading"/>
				</div>
				<xsl:for-each select="$factoids">
					<xsl:sort select="detail[@conceptID='2-10']/year"/>
					<xsl:sort select="detail[@conceptID='2-10']/month"/>
					<xsl:sort select="detail[@conceptID='2-10']/day"/>
					<xsl:sort select="detail[@conceptID='2-11']/year"/>
					<xsl:sort select="detail[@conceptID='2-11']/month"/>
					<xsl:sort select="detail[@conceptID='2-11']/day"/>
					<xsl:sort select="/hml/records/record[id= current()/detail[@conceptID='1084-88']]/detail[@conceptID='2-1']"/>
			
					<!-- pass heading param for conditional logic on type of factoid -->
					<xsl:call-template name="factoid">
						<xsl:with-param name="heading" select="$heading"/>
						<xsl:with-param name="factoid_rec" select="."/>
					</xsl:call-template>
					
					<div class="clearfix"></div>
				</xsl:for-each>
			</div>
		</xsl:if>
		
	</xsl:template>

	<xsl:template name="factoid"> <!-- ARTEM TODO  match="reversePointer/record[type/@id=150]" -->
		<xsl:param name="heading"/>
		<xsl:param name="factoid_rec"/>

		<xsl:variable name="role_rec_id" select="$factoid_rec/detail[@conceptID='1084-88']"/> <!-- reference to role -->
		<xsl:variable name="role" select="/hml/records/record[id=$role_rec_id]"/>
		<xsl:variable name="factoid_type" select="$factoid_rec/detail[@conceptID='1084-85']"/>
		
		<xsl:variable name="roleLink">
			<xsl:if test="$role">
				<xsl:value-of select="$role_rec_id"/>
				<xsl:if test="$factoid_rec/detail[@conceptID='1084-86']">
					<xsl:text>#t</xsl:text>
					<xsl:value-of select="$factoid_rec/detail[@conceptID='1084-86']"/>
				</xsl:if>
			</xsl:if>
		</xsl:variable>


		<xsl:choose>
			<!-- generic and type factoids can span two columns -->
			<xsl:when test="$role/detail[@conceptID='2-1'] = 'Generic'">
				<div class="entity-information-col01-02">
					<xsl:value-of select="$role/detail[@conceptID='2-1']"/>
				</div>
			</xsl:when>
			<xsl:when test="$factoid_type = 'Type'">
				<div class="entity-information-col01-02">
					<a href="{$roleLink}" class="preview-{$role_rec_id}">
						<xsl:call-template name="getRoleName">
							<xsl:with-param name="factoid" select="."/>
							<xsl:with-param name="base_rec_id" select="$root/id"/>
						</xsl:call-template>
					</a>
				</div>
			</xsl:when>
			<xsl:otherwise>
				<div class="entity-information-col01">
					<xsl:choose>
						<xsl:when test="$factoid_type = 'Occupation' or $factoid_type = 'Position'">

							<a href="{$roleLink}" class="preview-{$role_rec_id}">
									<xsl:call-template name="getRoleName">
										<xsl:with-param name="factoid" select="."/>
										<xsl:with-param name="base_rec_id" select="$root/id"/>
									</xsl:call-template>
							</a>
						</xsl:when>
						<xsl:otherwise>
							<xsl:call-template name="getRoleName">
								<xsl:with-param name="factoid" select="."/>
								<xsl:with-param name="base_rec_id" select="$root/id"/>
							</xsl:call-template>
						</xsl:otherwise>
					</xsl:choose>
				</div>
				<div class="entity-information-col02">
					
					<xsl:choose>
						<!-- direct -->
						<xsl:when test="$root/id=$factoid_rec/detail[@conceptID='1084-86'] and $factoid_rec/detail[@conceptID='1084-87']">
							
							<xsl:variable name="entity_source" select="/hml/records/record[id=$factoid_rec/detail[@conceptID='1084-87']]"/>
							
							<a href="{$entity_source/id}" class="preview-{$entity_source/id}">
								<xsl:value-of select="$entity_source/detail[@conceptID='2-1']"/>
							</a>
						</xsl:when>
						<!-- inverse -->
						<xsl:when test="$root/id=$factoid_rec/detail[@conceptID='1084-87']  and $factoid_rec/detail[@conceptID='1084-86']">
							<xsl:variable name="entity_target" select="/hml/records/record[id=$factoid_rec/detail[@conceptID='1084-86']]"/>
							
							<a href="{$entity_target/id}" class="preview-{$entity_target/id}">
								<xsl:value-of select="$entity_target/detail[@conceptID='2-1']"/>
							</a>
						</xsl:when>
						<xsl:when test="$factoid_rec/detail[@conceptID='1084-97']"> <!-- factoid target -->
							<xsl:value-of select="$factoid_rec/detail[@conceptID='1084-97']"/>
						</xsl:when>
					</xsl:choose>
				</div>
			</xsl:otherwise>
		</xsl:choose>


		<div class="entity-information-col03">
			<xsl:call-template name="formatDate">
				<xsl:with-param name="date" select="$factoid_rec/detail[@conceptID='2-10']"/>
			</xsl:call-template>
		</div>
		<xsl:if test="$factoid_rec/detail[@conceptID='2-11']/year != $factoid_rec/detail[@conceptID='2-10']/year or
			$factoid_rec/detail[@conceptID='2-11']/month != $factoid_rec/detail[@conceptID='2-10']/month or
			$factoid_rec/detail[@conceptID='2-11']/day != $factoid_rec/detail[@conceptID='2-10']/day">
			<div class="entity-information-col04">
				<xsl:text> - </xsl:text>
			</div>
			<div class="entity-information-col05">
				<xsl:call-template name="formatDate">
					<xsl:with-param name="date" select="$factoid_rec/detail[@conceptID='2-11']"/>
				</xsl:call-template>
			</div>
		</xsl:if>
	</xsl:template>

	<!-- called from role -->
	<xsl:template name="roleFactoid">

		<xsl:param name="factoid"/>
		
		<xsl:variable name="factoid_source_id" select="$factoid/detail[@conceptID='1084-87']"/>
		<xsl:variable name="factoids_source" select="/hml/records/record[@depth=2 and id=$factoid_source_id]"/> 
		
		<div class="entity-information-col01-02">
			<a href="{$factoid_source_id}" class="preview-{$factoid_source_id}">
				<xsl:value-of select="$factoids_source/detail[@conceptID='2-1']"/>
			</a>
		</div>

		<div class="entity-information-col03">
			<xsl:call-template name="formatDate">
				<xsl:with-param name="date" select="$factoid/detail[@conceptID='2-10']"/>
			</xsl:call-template>
		</div>
		<xsl:if test="$factoid/detail[@conceptID='2-11']/year != $factoid/detail[@conceptID='2-10']/year or
					$factoid/detail[@conceptID='2-11']/month != $factoid/detail[@conceptID='2-10']/month or
					$factoid/detail[@conceptID='2-11']/day != $factoid/detail[@conceptID='2-10']/day">
			<div class="entity-information-col04">
				<xsl:text> - </xsl:text>
			</div>
			<div class="entity-information-col05">
				<xsl:call-template name="formatDate">
					<xsl:with-param name="date" select="$factoid/detail[@conceptID='2-11']"/>
				</xsl:call-template>
			</div>
		</xsl:if>
	</xsl:template>
	
</xsl:stylesheet>
