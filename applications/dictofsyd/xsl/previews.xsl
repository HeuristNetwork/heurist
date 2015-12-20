<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:template name="previewStubs">
		<xsl:variable name="root" select="/hml/records/record[@depth=0]"/>


		<!-- direct pointers -->
		<xsl:for-each select="/hml/records/record[@depth=1 and id = $root/detail[@isRecordPointer='true']]">
			<xsl:call-template name="previewStub">
				<xsl:with-param name="record" select="."/>
			</xsl:call-template>
		</xsl:for-each>

		<!-- related records and id = $root/relationship[@relatedRecordID]  -->
		<xsl:for-each select="/hml/records/record[@depth=1 and relationship[@relatedRecordID=$root/id]]">
			<div><xsl:value-of select="id"/></div>
			<xsl:call-template name="previewStub">
				<xsl:with-param name="record" select="."/>
<!-- ARTEM TODO <xsl:with-param name="context" select="$root/relationship"/> -->
			</xsl:call-template>
		</xsl:for-each>

		<!-- part moved in _tmp.txt -->

	</xsl:template>


	<xsl:template name="previewStub">
		<xsl:param name="record"/>
		<xsl:param name="context"/>

		<xsl:variable name="id">
			<xsl:value-of select="$record/id"/>
			<xsl:if test="$context">
				<xsl:text>c</xsl:text>
				<xsl:value-of select="$context"/>
			</xsl:if>
		</xsl:variable>

		<div id="preview-{$id}" class="preview" type="in_template">

			<xsl:call-template name="preview222">
				<xsl:with-param name="record" select="$record"/>
				<xsl:with-param name="context" select="$context"/>
			</xsl:call-template>

		</div>
	</xsl:template>


	<xsl:template name="preview222">
		<xsl:param name="record"/>
		<xsl:param name="context"/>

		<xsl:variable name="type">
			<xsl:call-template name="getRecordTypeClassName">
				<xsl:with-param name="record" select="$record"/>
			</xsl:call-template>
		</xsl:variable>

		<div class="balloon-container">
			<div class="balloon-top"/>
			<div class="balloon-middle">
				<div class="balloon-heading balloon-{$type}">
					<h2><xsl:value-of select="$record/detail[@conceptID='2-1']"/></h2>
				</div>

				<div class="balloon-content">
					<xsl:call-template name="previewContent">
						<xsl:with-param name="record" select="$record"/>
						<xsl:with-param name="context" select="$context"/>
					</xsl:call-template>
					<div class="clearfix"></div>
				</div>

			</div>
			<div class="balloon-bottom"/>
		</div>
	</xsl:template>


	<xsl:template name="previewContent">
		<xsl:param name="record"/>
		<xsl:param name="context"/>

		<xsl:choose>
			<xsl:when test="$record[type/@conceptID='1084-25'] and $record/detail[@conceptID='2-61']">
				<xsl:variable name="main_img_id" select="$record/detail[@conceptID='2-61']"/>
				<xsl:variable name="main_img" select="/hml/records/record[id=$main_img_id]"/>
				<xsl:variable name="thumbnail2" select="$main_img/detail[@conceptID='2-38']"/>

				<xsl:if test="$thumbnail2">
					<img class="thumbnail">
					<xsl:attribute name="alt"/><!-- FIXME -->
					<xsl:attribute name="src">
						<xsl:call-template name="getFileURL">
							<xsl:with-param name="file" select="$thumbnail2"/>
							<xsl:with-param name="size" select="'thumbnail'"/>
						</xsl:call-template>
					</xsl:attribute>
					</img>
				</xsl:if>

			</xsl:when>
			<xsl:otherwise>
				<xsl:variable name="thumbnail" select="
					$record
					[type/@conceptID='2-5']
					[starts-with(detail[@conceptID='2-29'], 'image')]
					/detail[@conceptID='2-38']
					|
					$record
					[type/@conceptID='2-11']
					/detail[@conceptID='2-38']
					"/>

				<xsl:if test="$thumbnail">
					<img class="thumbnail">
						<xsl:attribute name="alt"/><!-- FIXME -->
						<xsl:attribute name="src">
							<xsl:call-template name="getFileURL">
								<xsl:with-param name="file" select="$thumbnail"/>
								<xsl:with-param name="size" select="'thumbnail'"/>
							</xsl:call-template>
						</xsl:attribute>
					</img>
				</xsl:if>

			</xsl:otherwise>
		</xsl:choose>

		
		<p>
			<xsl:value-of select="$record/detail[@conceptID='2-4']"/>
			<!-- ARTEM TODO ??? 
			<xsl:choose>
				<xsl:when test="$context and $record/reversePointer/record[id=$context]/detail[@conceptID='2-4']"> 
					<xsl:value-of select="$record/reversePointer/record[id=$context]/detail[@conceptID='2-4']"/>
				</xsl:when>
				<xsl:otherwise>
					<xsl:value-of select="$record/detail[@conceptID='2-4']"/>
				</xsl:otherwise>
			</xsl:choose>
			-->
		</p>

	</xsl:template>

</xsl:stylesheet>
