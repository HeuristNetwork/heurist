<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:ex="http://exslt.org/dates-and-times"
	version="1.0">

	<xsl:include href="urlmap.xsl"/>

	<xsl:template name="citationStubs">
		<xsl:variable name="root" select="/hml/records/record"/>
		<xsl:variable name="related" select="
			$root/relationships
				/record
					/detail[@id=202 or @id=199]
						/record[id != $root/id]
		"/>
		<!-- direct pointers -->
		<xsl:for-each select="$root/detail/record">
			<xsl:call-template name="citationStub">
				<xsl:with-param name="record" select="."/>
			</xsl:call-template>
		</xsl:for-each>


		<!-- annotations (in either direction) -->

		<!-- with targets -->
		<xsl:for-each select="$root/reversePointer/record[type/@id=99][detail[@id=199]/record]">
			<xsl:call-template name="citationStub">
				<xsl:with-param name="record" select="current()[../@id=199]/detail[@id=322]/record | current()[../@id=322]/detail[@id=199]/record"/>
				<xsl:with-param name="context" select="id"/>
			</xsl:call-template>
		</xsl:for-each>
		<!-- without targets (gloss annotations) -->
		<xsl:for-each select="$root/reversePointer/record[type/@id=99][not(detail[@id=199]/record)]">
			<xsl:call-template name="citationStub">
				<xsl:with-param name="record" select="."/>
			</xsl:call-template>
		</xsl:for-each>

		<!-- inverse contributor pointers -->
		<xsl:for-each select="$root/reversePointer[@id=538]/record">
			<xsl:call-template name="citationStub">
				<xsl:with-param name="record" select="."/>
			</xsl:call-template>
		</xsl:for-each>



	</xsl:template>


	<xsl:template name="citationStub">
		<xsl:param name="record"/>
		<xsl:param name="context"/>

		<xsl:variable name="id">
			<xsl:value-of select="$record/id"/>
			<xsl:if test="$context">
				<xsl:text>c</xsl:text>
				<xsl:value-of select="$context"/>
			</xsl:if>
		</xsl:variable>

		<div id="citation-{$id}" class="citation"/>
	</xsl:template>


	<xsl:template name="citation">
		<xsl:param name="record"/>
		<xsl:param name="context"/>
		<xsl:variable name="type">
			<xsl:call-template name="getRecordTypeClassName">
				<xsl:with-param name="record" select="$record"/>
			</xsl:call-template>
		</xsl:variable>

		<script type="text/javascript">

				function fulldate() {
					var d = new Date();
					var curr_date = d.getDate();
					var curr_month = d.getMonth() + 1; //months are zero based
					var curr_year = d.getFullYear();

  					var fulldate = curr_date + "-" + curr_month + "-" + curr_year;
					return fulldate;
				}
		</script>



		<div class="citation-container">
			<div class="citation-close"> <a onclick="Boxy.get(this).hide(); return false;" href="#">[close]</a> </div>
			<div class="clearfix"></div>
			<div class="citation-heading balloon-entry">
				<h2>Citation</h2>
			</div>
			<div class="citation-content">
					<xsl:call-template name="citationContent">
						<xsl:with-param name="record" select="$record"/>

					</xsl:call-template>
					<div class="clearfix"></div>
			</div>


		<div class="clearfix"></div>
		</div>




	</xsl:template>


	<xsl:template name="citationContent">
		<xsl:param name="record"/>

		<xsl:variable name="access_date" select="ex:date()"/>
		<!-- xsl:variable name="nice_date" select="ex:format-date($access_date,'dd MMM yyyy')"/ -->
		<xsl:variable name="dayofmonth" select="ex:day-in-month($access_date)"/>
		<xsl:variable name="monthname" select="ex:month-name($access_date)"/>
		<xsl:variable name="year" select="ex:year($access_date)"/>

		<xsl:variable name="pubDate" select="$record/detail[@id='166']/year"/>
		<xsl:variable name="author" select="$record/detail[@id=538]/record/detail[@id=160]"/>
		<!-- getPath template lives in urlmap.xsl - links ids to names -->
		<xsl:variable name="human_url">
			<xsl:call-template name="getPath">
				<xsl:with-param name="id" select="$record/id"/>
			</xsl:call-template>
		</xsl:variable>



				<h4>Persistent URL for this entry</h4>
				<div class="citation-text">http://www.dictionaryofsydney.org/<xsl:value-of select="$human_url"/></div>



				<h4>To cite this entry in text</h4>
		<div class="citation-text"><xsl:value-of select="$author"/>, '<xsl:value-of select="$record/detail[@id=160]"/>', Dictionary of Sydney, <xsl:value-of select="$pubDate"/>, http://www.dictionaryofsydney.org/entry/<xsl:value-of select="$human_url"/>, viewed <span class="date"></span></div>




				<h4>To cite this entry in a Wikipedia footnote citation</h4>
		<div class="citation-text">&lt;ref&gt;{{cite web |url= http://www.dictionaryofsydney.org/<xsl:value-of select="$human_url"/> |title = <xsl:value-of select="$record/detail[@id=160]"/> | author = <xsl:value-of select="$author"/> | date = <xsl:value-of select="$pubDate"/> |work = Dictionary of Sydney |publisher = Dictionary of Sydney Trust |accessdate = <span class="date"></span>}}&lt;/ref&gt;</div>




				<h4>To cite this entry as a Wikipedia External link</h4>
		<div class="citation-text">* {{cite web | url = http://www.dictionaryofsydney.org/<xsl:value-of select="$human_url"/> | title = <xsl:value-of select="$record/detail[@id=160]"/> | accessdate = <span class="date"></span> | author = <xsl:value-of select="$author"/> | date = <xsl:value-of select="$pubDate"/> | work = Dictionary of Sydney | publisher = Dictionary of Sydney Trust}}</div>



	</xsl:template>

</xsl:stylesheet>
