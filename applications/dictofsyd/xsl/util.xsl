<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:exsl="http://exslt.org/common"
                xmlns:str="http://exslt.org/strings"
                exclude-result-prefixes="exsl str"
                version="1.0">

	<xsl:template name="cleanQuote">
		<xsl:param name="string"/>
		<xsl:choose>
			<xsl:when test="contains($string, '&#x22;')">
				<xsl:value-of select="substring-before($string, '&#x22;')"/>
				<xsl:text>\"</xsl:text>
				<xsl:call-template name="cleanQuote">
					<xsl:with-param name="string" select="substring-after($string, '&#x22;')"/>
				</xsl:call-template>
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="$string"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>


	<xsl:template name="linkify">
		<xsl:param name="string"/>
		<xsl:for-each select="str:split($string,' ')">
			<xsl:choose>
				<xsl:when test="starts-with(., 'http://')">
					<a href="{.}" target="_blank">
						<xsl:value-of select="."/>
					</a>
				</xsl:when>
				<xsl:when test="starts-with(., 'www')">
					<a href="http://{.}" target="_blank">
						<xsl:value-of select="."/>
					</a>
				</xsl:when>
				<xsl:otherwise>
					<xsl:value-of select="."/>
				</xsl:otherwise>
			</xsl:choose>
			<xsl:if test="position() != last()">
				<xsl:text> </xsl:text>
			</xsl:if>
		</xsl:for-each>
	</xsl:template>


	<xsl:template name="formatDate">
		<xsl:param name="date"/>
		<xsl:if test="$date/day">
			<xsl:value-of select="$date/day"/>
			<xsl:choose>
				<xsl:when test="$date/month = 1"> Jan </xsl:when>
				<xsl:when test="$date/month = 2"> Feb </xsl:when>
				<xsl:when test="$date/month = 3"> Mar </xsl:when>
				<xsl:when test="$date/month = 4"> Apr </xsl:when>
				<xsl:when test="$date/month = 5"> May </xsl:when>
				<xsl:when test="$date/month = 6"> Jun </xsl:when>
				<xsl:when test="$date/month = 7"> Jul </xsl:when>
				<xsl:when test="$date/month = 8"> Aug </xsl:when>
				<xsl:when test="$date/month = 9"> Sep </xsl:when>
				<xsl:when test="$date/month = 10"> Oct </xsl:when>
				<xsl:when test="$date/month = 11"> Nov </xsl:when>
				<xsl:when test="$date/month = 12"> Dec </xsl:when>
			</xsl:choose>
		</xsl:if>
		<xsl:if test="$date/year">
			<xsl:value-of select="$date/year"/>
		</xsl:if>
	</xsl:template>


	<xsl:template name="getFileURL">
		<xsl:param name="file"/>
		<xsl:param name="size"/>
		<!-- static, pre-generated files -->
		<!--
		<xsl:variable name="dir">
			<xsl:choose>
				<xsl:when test="$size = 'thumbnail'">thumbnail</xsl:when>
				<xsl:when test="$size = 'small'">small</xsl:when>
				<xsl:when test="$size = 'medium'">medium</xsl:when>
				<xsl:when test="$size = 'wide'">wide</xsl:when>
				<xsl:when test="$size = 'large'">large</xsl:when>
				<xsl:otherwise>full</xsl:otherwise>
			</xsl:choose>
		</xsl:variable>

		<xsl:value-of select="$fullurlbase"/>
		<xsl:text>files/</xsl:text>
		<xsl:value-of select="$dir"/>
		<xsl:text>/</xsl:text>
		<xsl:value-of select="$file/file_id"/>
		-->

		<xsl:choose>
			<xsl:when test="$size = 'thumbnail'">
				<xsl:value-of select="concat('http://dictionaryofsydney.org/files/thumbnail/',$file/file/nonce)"/>
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="$file/file/url"/>
			</xsl:otherwise>
		</xsl:choose>


		<!--ARTEM  live from Heurist
		<xsl:choose>
			<xsl:when test="$size = 'thumbnail'">
				<xsl:value-of select="$file/file/thumbURL"/>
				<xsl:text>&amp;amp;w=148&amp;amp;h=148</xsl:text>
			</xsl:when>
			<xsl:when test="$size = 'small'">
				<xsl:value-of select="$file/file/thumbURL"/>
				<xsl:text>&amp;amp;w=148</xsl:text>
			</xsl:when>
			<xsl:when test="$size = 'medium'">
				<xsl:value-of select="$file/file/thumbURL"/>
				<xsl:text>&amp;amp;h=180</xsl:text>
			</xsl:when>
			<xsl:when test="$size = 'wide'">
				<xsl:value-of select="$file/file/thumbURL"/>
				<xsl:text>&amp;amp;maxw=800&amp;amp;maxh=400</xsl:text>
			</xsl:when>
			<xsl:when test="$size = 'large'">
				<xsl:value-of select="$file/file/thumbURL"/>
				<xsl:text>&amp;amp;maxw=698</xsl:text>
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="$file/file/url"/>
			</xsl:otherwise>
		</xsl:choose>
		-->
	</xsl:template>


	<xsl:template name="getRecordTypeClassName">
		<xsl:param name="record"/>
		<xsl:choose>
			<!-- entity -->
			<xsl:when test="$record[type/@conceptID='1084-25']">
				<xsl:call-template name="getEntityCodeName">
					<xsl:with-param name="typeName" select="$record/detail[@conceptID='1084-75']"/>
				</xsl:call-template>
			</xsl:when>
			<!-- media -->
			<xsl:when test="$record[type/@conceptID='2-5'][starts-with(detail[@conceptID='2-29'], 'image')]">image</xsl:when>
			<xsl:when test="$record[type/@conceptID='2-5'][starts-with(detail[@conceptID='2-29'], 'audio')]">audio</xsl:when>
			<xsl:when test="$record[type/@conceptID='2-5'][starts-with(detail[@conceptID='2-29'], 'video')]">video</xsl:when>
			<!-- hi-res images -->
			<xsl:when test="$record[type/@conceptID='2-11'][detail[@conceptID='2-30'] = 'image']">image</xsl:when>
			<!-- others -->
			<xsl:when test="$record/type/@conceptID = '2-2'">link</xsl:when>
			<xsl:when test="$record/type/@conceptID = '1084-27'">role</xsl:when>
			<xsl:when test="$record/type/@conceptID = '2-13'">entry</xsl:when>
			<xsl:when test="$record/type/@conceptID = '2-15'">annotation</xsl:when>
			<xsl:when test="$record/type/@conceptID = '1084-28'">map</xsl:when>
			<xsl:when test="$record/type/@conceptID = '1084-29'">term</xsl:when>
			<xsl:when test="$record/type/@conceptID = '1084-24'">contributor</xsl:when>
<!--			<xsl:when test="$record/type/@conceptID = '1084-??'">record</xsl:when> -->
		</xsl:choose>
	</xsl:template>


	<xsl:variable name="entityNames">
		<entity c="artefact"     cp="artefacts"     t="Artefact"        p="Artefacts"/>
		<entity c="building"     cp="buildings"     t="Building"        p="Buildings"/>
		<entity c="event"        cp="events"        t="Event"           p="Events"/>
		<entity c="natural"      cp="natural"       t="Natural feature" p="Natural features"/>
		<entity c="organisation" cp="organisations" t="Organisation"    p="Organisations"/>
		<entity c="person"       cp="people"        t="Person"          p="People"/>
		<entity c="place"        cp="places"        t="Place"           p="Places"/>
		<entity c="structure"    cp="structures"    t="Structure"       p="Structures"/>
	</xsl:variable>


	<xsl:template name="getEntityCodeName">
		<xsl:param name="typeName"/>
		<xsl:value-of select="exsl:node-set($entityNames)/entity[@t=$typeName or @cp=$typeName]/@c"/>
	</xsl:template>


	<xsl:template name="getEntityTypeName">
		<xsl:param name="codeName"/>
		<xsl:value-of select="exsl:node-set($entityNames)/entity[@c=$codeName]/@t"/>
	</xsl:template>


	<xsl:template name="getEntityPluralName">
		<xsl:param name="codeName"/>
		<xsl:value-of select="exsl:node-set($entityNames)/entity[@c=$codeName or @cp=$codeName]/@p"/>
	</xsl:template>


	<xsl:template name="makeEntityBrowseList">
		<xsl:param name="base" select="'../'"/>
		<xsl:for-each select="exsl:node-set($entityNames)/entity">
			<li class="browse-{@c}"><a href="{$base}browse/{@cp}"><xsl:value-of select="@p"/></a></li>
		</xsl:for-each>
	</xsl:template>

	<!-- used in makeTitleDiv -->
	<xsl:template name="getEntityTypeList">
		<xsl:param name="entity"/>

		<xsl:for-each select="/hml/records/record[@depth=1 and detail[@conceptID='1084-87']=$entity/id and detail[@conceptID='1084-85']='Type']">

			<!-- xsl:for-each select="$entity/reversePointer[@conceptID='1084-87']/record[detail[@conceptID='1084-85']='Type']">  -->
			<xsl:sort select="detail[@conceptID='2-10']/year"/>
			<xsl:sort select="detail[@conceptID='2-10']/month"/>
			<xsl:sort select="detail[@conceptID='2-10']/day"/>
			<xsl:sort select="detail[@conceptID='2-11']/year"/>
			<xsl:sort select="detail[@conceptID='2-11']/month"/>
			<xsl:sort select="detail[@conceptID='2-11']/day"/>
			<xsl:call-template name="getRoleName">
				<xsl:with-param name="factoid" select="."/>
				<xsl:with-param name="base_rec_id" select="$entity/id"/>
			</xsl:call-template>
			<xsl:if test="position() != last()">
				<xsl:text>, </xsl:text>
			</xsl:if>
		</xsl:for-each>
	</xsl:template>


	<xsl:template name="makeAuthorList">
		<xsl:param name="authors"/>
		<xsl:param name="year"/>
		<xsl:param name="link"/>
		<xsl:for-each select="$authors">
			<xsl:sort select="detail[@conceptID='1084-74']" order="ascending"/> <!-- type of contributor -->
			<xsl:sort select="detail[@conceptID='2-1']"/>

			<xsl:if test="position() > 0">
				<xsl:choose>
					<xsl:when test="position() = last()">

						<xsl:choose>
							<xsl:when test="detail[@conceptID='1084-74'] = 'supporter'">, <xsl:value-of select="$year"/><br/>supported by
								<xsl:call-template name="writename">
									<xsl:with-param name="detail" select="detail[@conceptID='2-1']"/>
									<xsl:with-param name="id" select="id"/>
									<xsl:with-param name="link" select="$link"/>
								</xsl:call-template>
							</xsl:when>
							<xsl:otherwise>

								<xsl:choose>
									<xsl:when test="count($authors) &gt; 1"> and </xsl:when>
								</xsl:choose>

								<xsl:call-template name="writename">
									<xsl:with-param name="detail" select="detail[@conceptID='2-1']"/>
									<xsl:with-param name="id" select="id"/>
									<xsl:with-param name="link" select="$link"/>
								</xsl:call-template>

								<xsl:choose>
									<xsl:when test="$year and string-length($year) &gt; 0">, <xsl:value-of select="$year"/>
									</xsl:when>
								</xsl:choose>

							</xsl:otherwise>

						</xsl:choose>
					</xsl:when>

					<xsl:otherwise>
						<xsl:choose>
							<xsl:when test="detail[@conceptID='1084-74'] != 'supporter'">
								<xsl:call-template name="writename">
									<xsl:with-param name="detail" select="detail[@conceptID='2-1']"/>
									<xsl:with-param name="id" select="id"/>
									<xsl:with-param name="link" select="$link"/>
								</xsl:call-template>
							</xsl:when>
						</xsl:choose>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:if>
<!-- ARTEM: OLD
			<xsl:if test="position() > 1">
				<xsl:choose>
					<xsl:when test="position() = last()">
						<xsl:text> and </xsl:text>
					</xsl:when>
					<xsl:otherwise>
						<xsl:text>, </xsl:text>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:if>
			<xsl:choose>
				<xsl:when test="$link = 'true'">
					<a href="{id}" class="preview-{id}">
						<xsl:value-of select="detail[@conceptID='2-1']"/>
					</a>
				</xsl:when>
				<xsl:otherwise>
					<xsl:value-of select="detail[@conceptID='2-1']"/>
				</xsl:otherwise>
			</xsl:choose>
-->
		</xsl:for-each>
	</xsl:template>

	<xsl:template name="writename">
		<xsl:param name="detail"/>
		<xsl:param name="id"/>
		<xsl:param name="link"/>

		<xsl:choose>
			<xsl:when test="$link = 'true'">
				<a href="{$id}" class="preview-{$id}">
					<xsl:value-of select="$detail"/>
				</a>
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="$detail"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template name="makeEntryByline">
		<xsl:param name="entry"/>

		<xsl:variable name="rec_ids" select="$entry/detail[@conceptID='1084-90']"/> <!-- pointer to contributor -->

		<xsl:choose>
			<xsl:when test="$rec_ids">
				<xsl:text>by </xsl:text>
				<xsl:call-template name="makeAuthorList">
					<xsl:with-param name="authors" select="/hml/records/record[id=$rec_ids]"/> <!-- ARTEM TODO -->
					<xsl:with-param name="link" select="'true'"/>
				</xsl:call-template>
				<xsl:if test="$entry/detail[@conceptID='2-9']">
					<xsl:text>, </xsl:text>
					<xsl:value-of select="$entry/detail[@conceptID='2-9']/year"/>
				</xsl:if>
			</xsl:when>
		</xsl:choose>
	</xsl:template>


	<xsl:template name="makeTitleDiv">
		<xsl:param name="record"/>
		<xsl:variable name="type">
			<xsl:call-template name="getRecordTypeClassName">
				<xsl:with-param name="record" select="$record"/>
			</xsl:call-template>
		</xsl:variable>
		<div id="heading" class="title-{$type}">
			<h1>
				<xsl:value-of select="$record/detail[@conceptID='2-1']"/>
			</h1>
			<span id="sub-title">
				<xsl:choose>
					<xsl:when test="$record/type/@conceptID='1084-25'">
						<!-- entity -->
						<xsl:call-template name="getEntityTypeList">
							<xsl:with-param name="entity" select="$record"/>
						</xsl:call-template>
					</xsl:when>
					<xsl:when test="$record/type/@conceptID='2-13'">
						<!-- entry -->
						<xsl:call-template name="makeEntryByline">
							<xsl:with-param name="entry" select="$record"/>
						</xsl:call-template>
					</xsl:when>
					<xsl:when test="$record/type/@conceptID='1084-27'">
						<!-- role -->
						<xsl:value-of select="$record/detail[@conceptID='1084-95']"/>
					</xsl:when>
					<xsl:when test="$record/type/@conceptID='1084-29'">
						<!-- term -->
						<xsl:text>Subject</xsl:text>
					</xsl:when>
					<xsl:when test="$record/type/@conceptID='1084-24'">
						<!-- contributor -->
						<xsl:text>Contributor</xsl:text>
					</xsl:when>
				</xsl:choose>
			</span>
			<span id="extra">
				<xsl:if test="$record/type/@conceptID='2-13'">
					<xsl:call-template name="makeLicenseIcon">
						<xsl:with-param name="record" select="$record"/>
					</xsl:call-template>
				</xsl:if>
			</span>

			<xsl:if test="$record/type/@conceptID='2-13'">
					<span id="citation">
						<a class="citation-link" href="{$urlbase}citation/{$record/id}">Cite this</a>
					</span>
			</xsl:if>

		</div>
	</xsl:template>


	<xsl:template name="makeMetaIDTag">
		<meta name="id">
			<xsl:attribute name="content">
				<xsl:value-of select="/hml/records/record/id"/>
			</xsl:attribute>
		</meta>
	</xsl:template>


	<xsl:template name="makeMetaClassTag">
		<meta name="class">
			<xsl:attribute name="content">
				<xsl:call-template name="getRecordTypeClassName">
					<xsl:with-param name="record" select="/hml/records/record"/>
				</xsl:call-template>
			</xsl:attribute>
		</meta>
	</xsl:template>


	<xsl:template name="makeMetaTags">
		<xsl:call-template name="makeMetaIDTag"/>
		<xsl:call-template name="makeMetaClassTag"/>
	</xsl:template>


	<xsl:template name="makeMediaAttributionStatement">
		<xsl:param name="record"/>

		<xsl:variable name="contributorid" select="$record/detail[@conceptID='1084-90']"/>
		<xsl:if test="$record/detail[@conceptID='1084-83']">  <!-- creator -->
			<xsl:text>By </xsl:text>
			<xsl:value-of select="$record/detail[@conceptID='1084-83']"/>
			<xsl:text>. </xsl:text>
		</xsl:if>

		<xsl:variable name="contributor" select="/hml/records/record[id=$contributorid]"/>

		<xsl:if test="$contributor">
			<xsl:choose>
					<xsl:when test="$contributor/detail[@conceptID='1084-92']"> <!-- attribution -->
						<xsl:value-of select="$contributor/detail[@conceptID='1084-92']"/>
						<xsl:text> </xsl:text>
					</xsl:when>
					<xsl:otherwise>
						<!-- default attribution phrase -->
						<xsl:text>Contributed by </xsl:text>
					</xsl:otherwise>
				</xsl:choose>
				<a href="{$contributor/id}" class="preview-{$contributor/id}">
					<xsl:value-of select="$contributor/detail[@conceptID='2-1']"/>
			</a>
		</xsl:if>
		<xsl:if test="$record/detail[@conceptID='1084-84']">
			<xsl:text> </xsl:text>
			<xsl:text>[</xsl:text>
			<xsl:choose>
				<xsl:when test="$record/detail[@conceptID='2-34']">
					<a href="{$record/detail[@conceptID='2-34']}{$record/detail[@conceptID='1084-84']}" target="_blank">
						<xsl:value-of select="$record/detail[@conceptID='1084-84']"/>
					</a>
				</xsl:when>
				<xsl:otherwise>
					<xsl:call-template name="linkify">
						<xsl:with-param name="string" select="$record/detail[@conceptID='1084-84']"/>
					</xsl:call-template>
				</xsl:otherwise>
			</xsl:choose>
			<xsl:text>]</xsl:text>
		</xsl:if>
		<xsl:if test="$record/detail[@conceptID='2-35']">
			<xsl:text> </xsl:text>
			<xsl:text>(</xsl:text>
			<xsl:call-template name="linkify">
				<xsl:with-param name="string" select="$record/detail[@conceptID='2-35']"/>
			</xsl:call-template>
			<xsl:text>)</xsl:text>
		</xsl:if>
	</xsl:template>


	<xsl:template name="makeLicenseIcon">
		<xsl:param name="record"/>
		<xsl:choose>
			<xsl:when test="$record/detail[@conceptID='1084-94'] = 'CC-Generic'">
				<a rel="license" target="_blank" href="http://creativecommons.org/licenses/by/2.5/au/">
					<img alt="Creative Commons License" src="http://i.creativecommons.org/l/by/2.5/au/80x15.png"/>
				</a>
			</xsl:when>
			<xsl:when test="$record/detail[@conceptID='1084-94'] = 'CC-SA'">
				<a rel="license" target="_blank" href="http://creativecommons.org/licenses/by-sa/2.5/au/">
					<img alt="Creative Commons License" src="http://i.creativecommons.org/l/by-sa/2.5/au/80x15.png"/>
				</a>
			</xsl:when>
		</xsl:choose>
	</xsl:template>


	<xsl:template name="makeBrowseMenu">
		<xsl:param name="base" select="'../'"/>
		<div id="browse-connections">
			<h3>Browse</h3>
			<ul id="menu">
				<xsl:call-template name="makeEntityBrowseList">
					<xsl:with-param name="base" select="$base"/>
				</xsl:call-template>
				<li class="browse-entry"><a href="{$base}browse/entries">Entries</a></li>
				<li class="browse-map"><a href="{$base}browse/maps">Maps</a></li>
				<li class="browse-map"><a href="{$base}browse/multimedia">Multimedia</a></li>
				<li class="browse-term"><a href="{$base}browse/subjects">Subjects</a></li>
				<li class="browse-role"><a href="{$base}browse/roles">Roles</a></li>
				<li class="browse-contributor"><a href="{$base}browse/contributors">Contributors</a></li>
			</ul>
		</div>
	</xsl:template>

	<xsl:template name="getRoleName">
		<xsl:param name="factoid"/>
		<xsl:param name="base_rec_id"/>

		<xsl:variable name="role_rec_id" select="detail[@conceptID='1084-88']"/> <!-- reference to role -->
		<xsl:variable name="role" select="/hml/records/record[id=$role_rec_id]"/>

		<xsl:choose>
			<xsl:when test="$role/detail[@conceptID='2-1'] = 'Generic'">
				<!-- generic role, use factoid title instead -->
				<xsl:value-of select="$factoid/detail[@conceptID='2-1']"/>
			</xsl:when> <!-- target is base record - this is inversiob -->
			<xsl:when test="$factoid/detail[@conceptID='1084-86']=$base_rec_id and $role/detail[@conceptID='2-2']">
				<!-- use inverse role name -->
				<xsl:value-of select="$role/detail[@conceptID='2-2']"/>
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="$role/detail[@conceptID='2-1']"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>


</xsl:stylesheet>
