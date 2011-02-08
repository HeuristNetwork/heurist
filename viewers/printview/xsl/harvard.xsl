<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

  <!--

    Modified: Maria Shvedova.
    Version/Date: 28/05/2007.

    Supports the following BIBLIOGRAPHICAL rectypes:
    -> 42: Archive Record
    -> 5  : Book
    -> 4  : Book Chapter
    -> 31: Conference Paper
    -> 7  : Conference Proceedings
    -> 1  : Internet Bookmark
    -> 29: Journal
    -> 3  : Journal Article
    -> 28: Journal Volume
    -> 68: Magazine
    -> 10: Magazine Article
    -> 67: Magazine Issue - {run through default template}
    -> 69: Newspaper
    -> 9  : Newspaper Article
    -> 66: Newspaper Issue
    -> 2  : Notes  - {run through default template}
    -> 46: Other Document - {run through default template}
    -> 76: Performance - {run through default template}
    -> 11: Pers. Comm. - {run through default template}
    -> 44: Publication Series
    -> 30: Publisher
    -> 12: Report
    -> 13: Thesis

  -->
    <!-- This template converts months numbers to their string equivalent -->
    <xsl:template name="months">
        <xsl:param name="mnth"/>
        <xsl:choose>
            <xsl:when test="$mnth = '1' or $mnth = '01'">January</xsl:when>
            <xsl:when test="$mnth = '2' or $mnth = '02'">February</xsl:when>
            <xsl:when test="$mnth = '3' or $mnth = '03'">March</xsl:when>
            <xsl:when test="$mnth = '4' or $mnth = '04'">April</xsl:when>
            <xsl:when test="$mnth = '5' or $mnth = '05'">May</xsl:when>
            <xsl:when test="$mnth = '6' or $mnth = '06'">June</xsl:when>
            <xsl:when test="$mnth = '7' or $mnth = '07'">July</xsl:when>
            <xsl:when test="$mnth = '8' or $mnth = '08'">August</xsl:when>
            <xsl:when test="$mnth = '9' or $mnth = '09'">September</xsl:when>
            <xsl:when test="$mnth = '10'">October</xsl:when>
            <xsl:when test="$mnth = '11'">November</xsl:when>
            <xsl:when test="$mnth = '12'">December</xsl:when>
        </xsl:choose>
    </xsl:template>

<xsl:template match="/">
	<!-- use the following bit of code to include the stylesheet to display it in Heurist publishing wizard otherwise it will be ommited-->
	<!-- begin including code -->
	<xsl:comment>
	<!-- name (desc.) that will appear in dropdown list -->
		[name]Harvard bibliography[/name]
		<!-- match the filename of the stylesheet-->
		[output]harvard[/output] </xsl:comment>
		<!-- end including code -->
	<xsl:attribute name="pub_id">
		<xsl:value-of select="/hml/query[@pub_id]"/>
	</xsl:attribute>
	<xsl:apply-templates select="hml/records/record"/>
</xsl:template>

  <!-- =================  42: ARCHIVE RECORD =============================== -->
  <xsl:template name="archive" match="record[type/@id=42]">
   <div id="{id}" class="record">
        <!-- if Title are missing, don't print -->
        <xsl:if test="detail[@id=160]">
          <!-- author(s), year and title -->
          <xsl:apply-templates select="detail[@id=158]/record" mode="process-creator"/>
          <xsl:if test="detail[@id='159']"> &#160;<xsl:value-of select="detail[@id = '159']"/>, </xsl:if>
          <i> &#160;<xsl:value-of select="detail[@id = '160']"/></i>
          <!-- type of work -->
          <xsl:if test="detail[@id = '175']"> [<xsl:value-of select="detail[@id = '175']"/>].
          </xsl:if>
          <xsl:if test="detail[@id=187]">
            &#160;<xsl:value-of select="detail[@id=187]/@type"/>: <xsl:value-of select="detail[@id=187]"/>.
          </xsl:if>
          <xsl:if test="detail[@id=198]">
            &#160;<xsl:value-of select="detail[@id=198]/@type"/>: <xsl:value-of select="detail[@id=198]"/>.
          </xsl:if>
          <xsl:if  test="url!=''">
            &#160;<a href="{url}" target="_blank">web</a>
          </xsl:if>
          <xsl:call-template name="output_weblink"/>
        </xsl:if>
	</div>
  </xsl:template>

<!-- =================  5: BOOK =============================== -->
<xsl:template name="publications" match="record[type/@id=5]">
	<div id="{id}" class="record" title="{title}">
		<!-- if Title are missing, don't print -->
		<xsl:if test="detail[@id=160]">
		<!-- author(s), year and title -->
		<xsl:variable name="authorsCount" select="count(detail[@id=158]/record)"/>
		<xsl:apply-templates select="detail[@id=158]/record" mode="process-creator">
			<xsl:with-param name="auth" select="count"/>
		</xsl:apply-templates>

			<xsl:if test="detail[@id=159]"><xsl:value-of select="detail[@id=159]/raw"/>,&#160;</xsl:if>
		<i><xsl:value-of select="detail[@id = 160]"/>,&#160;</i>
		<!-- Publisher name, Place published -->
		<xsl:choose>
			<xsl:when test="detail[@id = 228]/record/detail[@id = 229]/record">
				<xsl:apply-templates select="detail[@id = 228]/record/detail[@id = 229]/record"/>
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="detail[@id = 228]/record/title"/>
			</xsl:otherwise>
		</xsl:choose>
		</xsl:if>
		<xsl:if test="detail[@id=187]">
			<xsl:value-of select="detail[@id=187]/@type"/>:&#160; <xsl:value-of select="detail[@id=187]"/>.
		</xsl:if>
		<xsl:if test="detail[@id=198]">
			<xsl:value-of select="detail[@id=198]/@type"/>:&#160; <xsl:value-of select="detail[@id=198]"/>.
		</xsl:if>
		<xsl:if  test="url!=''">
			<a href="{url}" target="_blank">web</a>
		</xsl:if>
		<xsl:call-template name="output_weblink"/>
	</div>
</xsl:template>

  <!-- =================  4: BOOK CHAPTER  =============================== -->
  <xsl:template name="book_chap" match="record[type/@id=4]">
    <div id="{id}" class="record">
    <table>
    <tr>
      <td>
        <!-- if one of the details Author or Title are missing, don't print -->
        <xsl:if test="detail[@id='160']">
          <!-- author(s) and chapetr title -->
          <xsl:apply-templates select="detail[@id=158]/record" mode="process-creator"/>
          <i> &#160;<xsl:value-of select="detail[@id = '160']"/>
          </i>. <!-- in this book (from book reference) --> &#xa0;<i>In:&#xa0;</i>
          <xsl:apply-templates select="detail[@id=227]/record/detail[@id=158]/record" mode="process-creator"/>(ed.)
            &#xa0;<xsl:value-of select="detail[@id=227]/record/detail[@id = '159']"/>, <i>
              &#xa0;<xsl:value-of select="detail[@id=227]/record/detail[@id = '160']"/>. </i>
          <!-- edition or version -->
          <xsl:if test="detail/record/detail[@id = '176']"> &#xa0;<xsl:value-of
              select="detail/record/detail[@id = '176']"/>, </xsl:if>
          <!-- Volume -->
          <xsl:if test="detail/record/detail[@id = '184']"> &#xa0;<xsl:value-of
              select="detail/record/detail[@id = '184']"/>, </xsl:if>
          <!-- Publisher name, Place published -->
          <xsl:if test="detail[@id=227]/record/detail[@id = 228]/record"> &#xa0;<xsl:apply-templates
              select="detail[@id=227]/record/detail[@id = 228]/record"/>, </xsl:if>
          <!-- Pages -->
          <xsl:if test="detail[@id = '164'] and detail[@id = '165']"> &#xa0;pages: <xsl:value-of
              select="./detail[@id = '164']"/> - <xsl:value-of select="./detail[@id = '165']"/>
            .</xsl:if> </xsl:if>
        <xsl:if test="detail[@id=187]">
          &#160;<xsl:value-of select="detail[@id=187]/@type"/>: <xsl:value-of select="detail[@id=187]"/>.
        </xsl:if>
        <xsl:if test="detail[@id=198]">
          &#160;<xsl:value-of select="detail[@id=198]/@type"/>: <xsl:value-of select="detail[@id=198]"/>.
        </xsl:if>
        <xsl:if  test="url!=''">
          &#160;<a href="{url}" target="_blank">web</a>
        </xsl:if>
        <xsl:call-template name="output_weblink"/>
      </td>
    </tr>
    </table>
    </div>

  </xsl:template>

  <!-- =================  1: HYPERLINK  =============================== -->
  <xsl:template name="hyperlink" match="record[type/@id=1]">
   <div id="{id}" class="record">
   <table>
    <tr>
      <td>

        <xsl:if test="detail[@id='160'] and url[.!='']">
          <xsl:element name="a">
            <xsl:attribute name="href">
              <xsl:value-of select="url"/>
            </xsl:attribute>
            <xsl:attribute name="target">_blank</xsl:attribute>
            <xsl:value-of select="detail[@id = '160']"/>
          </xsl:element>
          <!-- author -->
          <xsl:if test="detail[@id = '158']/record">
            &#xa0;<xsl:apply-templates select="detail[@id=158]/record" mode="process-creator"/></xsl:if>
          <!-- abstract -->
          <xsl:if test="detail[@id = '191']"> &#xa0;<xsl:value-of select="detail[@id = '191']"/>
          </xsl:if>
          <xsl:call-template name="output_weblink"/>
        </xsl:if>
      </td>
    </tr>
    </table>
    </div>

  </xsl:template>
  <!-- =================  29: JOURNAL | 68: MAGAZINE | 69: NEWSPAPER =============================== -->
  <xsl:template name="journal" match="record[type/@id=29 or type/@id=68 or type/@id=69]">
  <div id="{id}" class="record">
  <table>
    <tr>
      <td>

        <xsl:if test="detail[@id='160']">
          <!-- title -->
          <xsl:value-of select="detail[@id = '160']"/>
        </xsl:if>
        <xsl:if test="detail[@id=187]">
          &#160;<xsl:value-of select="detail[@id=187]/@type"/>: <xsl:value-of select="detail[@id=187]"/>.
        </xsl:if>
        <xsl:if test="detail[@id=198]">
          &#160;<xsl:value-of select="detail[@id=198]/@type"/>: <xsl:value-of select="detail[@id=198]"/>.
        </xsl:if>
        <xsl:if  test="url!=''">
          &#160;<a href="{url}" target="_blank">web</a>
        </xsl:if>
        <xsl:call-template name="output_weblink"/>
      </td>
    </tr>
	</table>
	</div>
  </xsl:template>
  <!-- =================  31: CONFERENCE PAPER =============================== -->
  <xsl:template name="conf-paper" match="record[type/@id=31]">
  <div id="{id}" class="record">
    <table>
    <tr>
      <td>
        <xsl:if test="detail[@id='160']">
          <!-- creator -->
          <xsl:apply-templates select="detail[@id=158]/record" mode="process-creator"/>
          <xsl:if test="detail[@id=238]/record/detail[@id=159]">
            <!-- year (from journal volume reference) --> &#160;<xsl:value-of
              select="detail[@id=238]/record/detail[@id=159]"/>, </xsl:if>
          <!-- title --> &#160;<xsl:value-of select="detail[@id=160]"/>. <!--from conference -->
          <xsl:if test="detail[@id=217]/record/detail[@id=160] and detail[@id=217]/record/detail[@id=181]">
            &#xa0;In: <i>
              <xsl:value-of select="detail[@id=217]/record/detail[@id=160]"/>
            </i>, <!--location, pages --> &#xa0;<xsl:value-of
              select="detail[@id=217]/record/detail[@id=181]"/>. </xsl:if>
          <xsl:if test="detail[@id=164] and detail[@id=165]"> &#xa0;<xsl:value-of
              select="detail[@id=164]"/>-<xsl:value-of select="detail[@id=165]"/>
          </xsl:if>
          <xsl:if test="detail[@id=187]">
            &#160;<xsl:value-of select="detail[@id=187]/@type"/>: <xsl:value-of select="detail[@id=187]"/>.
          </xsl:if>
          <xsl:if test="detail[@id=198]">
            &#160;<xsl:value-of select="detail[@id=198]/@type"/>: <xsl:value-of select="detail[@id=198]"/>.
          </xsl:if>
          <xsl:if  test="url!=''">
            &#160;<a href="{url}" target="_blank">web</a>
          </xsl:if>
          <xsl:call-template name="output_weblink"/>
        </xsl:if>
      </td>
    </tr>
	</table>
	</div>
  </xsl:template>

  <!-- =================  7: CONFERENCE PROCEEDINGS =============================== -->

  <xsl:template name="conference_proceedings" match="record[type/@id=7 ]">
  <div id="{id}" class="record">
  <table>
    <tr>
      <td>
        <!-- creator -->
        <xsl:apply-templates select="detail[@id=158]/record" mode="process-creator"/>
        <!-- year -->
        <xsl:if test="detail[@id=159]">
          <!-- formating: if the creator exists, put &nbsp; before year -->
          <xsl:choose>
            <xsl:when test="detail[@id=158]/record"> &#xa0;<xsl:value-of select="detail[@id=159]"/>, </xsl:when>
            <xsl:otherwise>
              <xsl:value-of select="detail[@id=159]"/>, </xsl:otherwise>
          </xsl:choose>
        </xsl:if>
        <!-- title -->
        <!-- formating: if the creator exists, put &nbsp; before year -->
        <xsl:choose>
          <xsl:when test="detail[@id=159]"> &#xa0;'<xsl:value-of select="detail[@id=160]"/>'. </xsl:when>
          <xsl:otherwise> '<xsl:value-of select="detail[@id=160]"/>'. </xsl:otherwise>
        </xsl:choose>
        <xsl:if test="detail[@id=217]/record/detail[@id=160]">
          <!--conference reported : --> &#xa0;<xsl:apply-templates
            select="detail[@id=217]/record/detail[@id=160]"/>. </xsl:if>
        <xsl:if test="detail[@id=217]/record/detail[@id=159]"> &#xa0;<xsl:apply-templates
            select="detail[@id=217]/record/detail[@id=159]"/>, </xsl:if>
        <!-- location -->
        <xsl:if test="detail[@id=217]/record/detail[@id=181]"> &#xa0;<xsl:value-of
            select="detail[@id=217]/record/detail[@id=181]"/>
        </xsl:if>
        <xsl:if test="detail[@id=187]">
          &#160;<xsl:value-of select="detail[@id=187]/@type"/>: <xsl:value-of select="detail[@id=187]"/>.
        </xsl:if>
        <xsl:if test="detail[@id=198]">
          &#160;<xsl:value-of select="detail[@id=198]/@type"/>: <xsl:value-of select="detail[@id=198]"/>.
        </xsl:if>
        <xsl:if  test="url!=''">
          &#160;<a href="{url}" target="_blank">web</a>
        </xsl:if>
        <xsl:call-template name="output_weblink"/>
      </td>
    </tr>
    </table>
    </div>

  </xsl:template>

  <!-- =================  9: NEWSPAPER ARTICLE && 10: MAGAZINE  ARTICLE =============================== -->

  <xsl:template name="article" match="record[type/@id=9 or type/@id=10]">
    <div id="{id}" class="record">
    <table>
    <tr>
      <td>
        <xsl:if test="detail[@id='160']">
          <!-- creator -->
          <xsl:apply-templates select="detail[@id=158]/record" mode="process-creator"/>
          <!-- year (from journal volume reference) -->
          <xsl:if test="detail[@id=237]/record/detail[@id=166]/year"> &#xa0;<xsl:value-of
              select="detail[@id=237]/record/detail[@id=166]/year"/>, </xsl:if>
          <!-- title --> &#xa0;<xsl:value-of select="detail[@id=160]"/>. <!--newspaper title -->
          <xsl:if test="detail[@id=237]/record/detail[@id=242]/record "> &#xa0; <i>
              <xsl:apply-templates select="detail[@id=237]/record/detail[@id=242]/record"/>, </i>
            <xsl:if test="detail[@id=237]/record/detail[@id=166]">
              <!-- date --> &#xa0;<xsl:apply-templates mode="date"
                select="detail[@id=237]/record/detail[@id=166]"/>. </xsl:if>
          </xsl:if>
          <!-- pages-->
          <xsl:if test="detail[@id=164] and detail[@id=165]"> &#xa0;<xsl:value-of
              select="detail[@id=164]"/>-<xsl:value-of select="detail[@id=165]"/>
          </xsl:if>
          <xsl:if test="detail[@id=187]">
            &#160;<xsl:value-of select="detail[@id=187]/@type"/>: <xsl:value-of select="detail[@id=187]"/>.
          </xsl:if>
          <xsl:if test="detail[@id=198]">
            &#160;<xsl:value-of select="detail[@id=198]/@type"/>: <xsl:value-of select="detail[@id=198]"/>.
          </xsl:if>
          <xsl:if  test="url!=''">
            &#160;<a href="{url}" target="_blank">web</a>
          </xsl:if>
          <xsl:call-template name="output_weblink"/>
        </xsl:if>
      </td>
    </tr>
    </table>
    </div>

  </xsl:template>


  <!-- =================  DEFAULT =============================== -->

  <xsl:template name="default" match="record">
  <div id="{id}" class="record">
  <table>
    <tr>
      <td>
        <xsl:if test="detail[@id=158]/record != ''">
          <!-- creator -->
          <xsl:apply-templates select="detail[@id=158]/record" mode="process-creator"/>
        </xsl:if>
        <xsl:if test="detail[@id=159]">
          <!-- year --> &#xa0;<xsl:value-of select="detail[@id=159]"/>, </xsl:if>
        <!-- title --> &#xa0;<xsl:if test="detail[@id=160] != ''">
          <i>
            <xsl:value-of select="detail[@id=160]"/>
          </i>&#xa0;</xsl:if>
        <xsl:if test="detail[@id=187]">
          &#160;<xsl:value-of select="detail[@id=187]/@type"/>: <xsl:value-of select="detail[@id=187]"/>.
        </xsl:if>
        <xsl:if test="detail[@id=198]">
          &#160;<xsl:value-of select="detail[@id=198]/@type"/>: <xsl:value-of select="detail[@id=198]"/>.
        </xsl:if>
        <xsl:if  test="url!=''">
          &#160;<a href="{url}" target="_blank">web</a>
        </xsl:if>
        <xsl:call-template name="output_weblink"/> [no bibliographic data] </td>
    </tr>
    </table>
    </div>

  </xsl:template>

  <!-- =================  28: JOURNAL VOLUME  =============================== -->
  <xsl:template name="journal_volume" match="record[type/@id=28 ] ">
  <div id="{id}" class="record">
  <table>
    <tr>
      <td>
        <xsl:choose>
          <xsl:when test="detail[@id=160]">
            <!-- title (from jounal reference) -->
            <xsl:value-of select="detail[@id=160]"/>.&#xa0; </xsl:when>
          <xsl:otherwise>
            <!-- title (from jounal reference) -->
            <xsl:value-of select="detail[@id=226]/record/detail[@id=160]"/>.&#xa0; </xsl:otherwise>
        </xsl:choose>

        <!-- volume, year  -->
        <xsl:if test="detail[@id=184]">
          <xsl:value-of select="detail[@id=184]"/>
        </xsl:if>
        <!-- year-->
        <xsl:if test="detail[@id=159]"> - <xsl:value-of select="detail[@id=159]"/>
        </xsl:if>
        <xsl:if test="detail[@id=187]">
          &#160;<xsl:value-of select="detail[@id=187]/@type"/>: <xsl:value-of select="detail[@id=187]"/>.
        </xsl:if>
        <xsl:if test="detail[@id=198]">
          &#160;<xsl:value-of select="detail[@id=198]/@type"/>: <xsl:value-of select="detail[@id=198]"/>.
        </xsl:if>
        <xsl:if  test="url!=''">
          &#160;<a href="{url}" target="_blank">web</a>
        </xsl:if>
        <xsl:call-template name="output_weblink"/>
      </td>
    </tr>
    </table>
    </div>

  </xsl:template>
  <!-- =================  30: PUBLISHER  =============================== -->
  <xsl:template name="publisher" match="record[type/@id=30 ] ">
  <div id="{id}" class="record">
  <table>
    <tr>
      <td>
        <xsl:if test="detail[@id=160]">
          <xsl:choose>
            <xsl:when test="detail[@id=172]"> &#160;<xsl:value-of select="detail[@id=160]"/>:
                <xsl:value-of select="detail[@id=172]"/>
            </xsl:when>
            <xsl:otherwise> &#160;<xsl:value-of select="detail[@id=160]"/>
            </xsl:otherwise>
          </xsl:choose>
          <xsl:if test="detail[@id=187]">
            &#160;<xsl:value-of select="detail[@id=187]/@type"/>: <xsl:value-of select="detail[@id=187]"/>.
          </xsl:if>
          <xsl:if test="detail[@id=198]">
            &#160;<xsl:value-of select="detail[@id=198]/@type"/>: <xsl:value-of select="detail[@id=198]"/>.
          </xsl:if>
          <xsl:if  test="url!=''">
            &#160;<a href="{url}" target="_blank">web</a>
          </xsl:if>
        </xsl:if>
        <xsl:call-template name="output_weblink"/>
      </td>
    </tr>
    </table>
    </div>

  </xsl:template>
  <!-- =================  44: PUBLICATION SERIES  =============================== -->
  <xsl:template name="pub_series" match="record[type/@id=44 ] ">
    <xsl:choose>
      <!-- only display series if series title exists -->
      <xsl:when
        test="detail[@id=160] and detail[@id=160] != 'Unknown Series' and detail[@id =160] != 000">
      <div id="{id}" class="record">
  	  <table>
        <tr>
          <td>

            <!-- title of series if applicable -->
            <i>
              <xsl:choose>
                <xsl:when test="contains(., '.')">
                  <xsl:value-of select="detail[@id=160]"/>
                </xsl:when>
                <xsl:otherwise>
                  <xsl:value-of select="detail[@id=160]"/>. </xsl:otherwise>
              </xsl:choose>
            </i>

            <xsl:if test="detail[@id=229]/record/detail[@id=160]">
              <xsl:choose>
                <xsl:when test="detail[@id=229]/record/detail[@id=172]"> &#160;<xsl:value-of
                    select="detail[@id=229]/record/detail[@id=160]"/>: <xsl:value-of
                    select="detail[@id=229]/record/detail[@id=172]"/>
                </xsl:when>
                <xsl:otherwise> &#160;<xsl:value-of select="detail[@id=229]/record/detail[@id=160]"/>
                </xsl:otherwise>
              </xsl:choose>
            </xsl:if>
            <xsl:if test="detail[@id=187]">
              &#160;<xsl:value-of select="detail[@id=187]/@type"/>: <xsl:value-of select="detail[@id=187]"/>.
            </xsl:if>
            <xsl:if test="detail[@id=198]">
              &#160;<xsl:value-of select="detail[@id=198]/@type"/>: <xsl:value-of select="detail[@id=198]"/>.
            </xsl:if>
            <xsl:if  test="url!=''">
              &#160;<a href="{url}" target="_blank">web</a>
            </xsl:if>
            <xsl:call-template name="output_weblink"/>
          </td>
        </tr>
        </table>
        </div>

      </xsl:when>
    </xsl:choose>
  </xsl:template>

  <!-- =================  3: JOURNAL ARTICLE  && 10: MAGAZINE ARTICLE =============================== -->
  <xsl:template name="jour_article" match="record[type/@id=3 or type/@id=10 ]">
  <div id="{id}" class="record">
  <table>
    <tr>
      <td>
        <!-- creator -->
        <xsl:apply-templates select="detail[@id=158]/record" mode="process-creator"/>

        <!-- year (from journal volume reference) -->
		<!--
        <xsl:if test="detail[@id=225]/record/detail[@id=159]"> &#160;<xsl:value-of
            select="detail[@id=225]/record/detail[@id=159]"/>, </xsl:if>-->
        <!-- title --> &#160;<xsl:value-of select="detail[@id=160]"/>.&#xa0;
		<!-- journal (from journal volume reference) -->
        <!--<xsl:if test="detail[@id=225]/record/detail[@id=226]/record">
          <i>
            <xsl:choose>
              <xsl:when test="detail[@id=225]/record/detail[@id=184]">
                <xsl:apply-templates select="detail[@id=225]/record/detail[@id=226]/record"/>, </xsl:when>
              <xsl:otherwise>
                <xsl:apply-templates select="detail[@id=225]/record/detail[@id=226]/record"/>
              </xsl:otherwise>
            </xsl:choose>
          </i>
        </xsl:if>-->
        <!-- volume (from journal volume reference), formatting: if pages exist, put :  -->
        <!--<xsl:if test="detail[@id=225]/record/detail[@id=184]">
          <xsl:choose>
            <xsl:when test="detail[@id=164] and detail[@id=165]"> &#xa0;<xsl:value-of
                select="detail[@id=225]/record/detail[@id=184]"/>: </xsl:when>
            <xsl:otherwise> &#xa0;<xsl:value-of select="detail[@id=225]/record/detail[@id=184]"/>
            </xsl:otherwise>
          </xsl:choose>
        </xsl:if>-->
		<xsl:if test="detail[@id=225]/record">
		<i><xsl:value-of
                select="detail[@id=225]/record/title"/></i>
		</xsl:if>
        <!-- pages-->
        <xsl:if test="detail[@id=164] and detail[@id=165]"> :&#xa0;<xsl:value-of
            select="detail[@id=164]"/>-<xsl:value-of select="detail[@id=165]"/>
        </xsl:if>
        <xsl:if test="detail[@id=187]">
          &#160;<xsl:value-of select="detail[@id=187]/@type"/>: <xsl:value-of select="detail[@id=187]"/>.
        </xsl:if>
        <xsl:if test="detail[@id=198]">
          &#160;<xsl:value-of select="detail[@id=198]/@type"/>: <xsl:value-of select="detail[@id=198]"/>.
        </xsl:if>
        <xsl:if  test="url!=''">
          &#160;<a href="{url}" target="_blank">web</a>
       </xsl:if>
        <xsl:call-template name="output_weblink"/>
      </td>
    </tr>
    </table>
    </div>

  </xsl:template>
  <!-- =================  12: REPORT =============================== -->
  <xsl:template name="report" match="record[type/@id=12 ]">
  <div id="{id}" class="record">
  <table>
    <tr>
      <td>
        <!-- creator -->
        <xsl:apply-templates select="detail[@id=158]/record" mode="process-creator"/>
        <!-- year -->
        <xsl:if test="detail[@id=159]"> &#xa0;<xsl:value-of select="detail[@id=159]"/>, </xsl:if>
        <!-- title -->
        <i>
          <xsl:choose>
            <xsl:when test="detail[@id=229]/record"> &#xa0;<xsl:value-of select="detail[@id=160]"/>, </xsl:when>
            <xsl:otherwise> &#xa0;<xsl:value-of select="detail[@id=160]"/>
            </xsl:otherwise>
          </xsl:choose>

        </i>
        <xsl:if test="detail[@id=229]/record">
          <!--publisher reference --> to <xsl:apply-templates select="detail[@id=229]/record"/>
        </xsl:if>
        <xsl:if test="detail[@id=187]">
          &#160;<xsl:value-of select="detail[@id=187]/@type"/>: <xsl:value-of select="detail[@id=187]"/>.
        </xsl:if>
        <xsl:if test="detail[@id=198]">
          &#160;<xsl:value-of select="detail[@id=198]/@type"/>: <xsl:value-of select="detail[@id=198]"/>.
        </xsl:if>
        <xsl:call-template name="output_weblink"/>
      </td>
    </tr>
    </table>
    </div>

  </xsl:template>

  <!-- =================  13: THESIS =============================== -->
  <xsl:template name="thesis" match="record[type/@id=13 ]">
  <div id="{id}" class="record">
  <table>
    <tr>
      <td>
        <!-- creator -->
        <xsl:apply-templates select="detail[@id=158]/record" mode="process-creator"/>
        <!-- year -->
        <xsl:if test="detail[@id=159]">
          <!-- formating: if the creator exists, put &nbsp; before year -->
          <xsl:choose>
            <xsl:when test="detail[@id=158]/record"> &#xa0;<xsl:value-of select="detail[@id=159]"/>, </xsl:when>
            <xsl:otherwise>
              <xsl:value-of select="detail[@id=159]"/>. </xsl:otherwise>
          </xsl:choose>
        </xsl:if>
        <!-- title -->
        <!-- formating: if the creator exists, put &nbsp; before year -->
        <i>
          <xsl:choose>

            <xsl:when test="detail[@id=159]"> &#xa0;<xsl:value-of select="detail[@id=160]"/>. </xsl:when>
            <xsl:otherwise>
              <xsl:value-of select="detail[@id=160]"/>. </xsl:otherwise>

          </xsl:choose>
        </i>
        <!--thesis type -->
        <xsl:if test="detail[@id=243]">
          <xsl:apply-templates select="detail[@id=243]"/>
        </xsl:if>
        <xsl:if test="detail[@id=198]"> &#xa0;<xsl:apply-templates select="detail[@id=198]"/>
        </xsl:if>
        <xsl:if test="detail[@id=187]">
          &#160;<xsl:value-of select="detail[@id=187]/@type"/>: <xsl:value-of select="detail[@id=187]"/>.
        </xsl:if>
        <xsl:if test="detail[@id=198]">
          &#160;<xsl:value-of select="detail[@id=198]/@type"/>: <xsl:value-of select="detail[@id=198]"/>.
        </xsl:if>
        <xsl:call-template name="output_weblink"/>
      </td>
    </tr>
    </table>
    </div>
  </xsl:template>

  <!-- =================  HELPER TEMPLATES  =============================== -->

  <!-- modify series that have no value with more appropriate "unknown series" -->
  <xsl:template match="detail/record">
    <xsl:choose>
      <xsl:when test="contains(title, '(series =)')">
        <xsl:variable name="series">(series =)</xsl:variable>
        <xsl:variable name="newseries">(series = Unknown Series)</xsl:variable>

        <xsl:value-of select="substring-before(title, $series)"/>
        <xsl:value-of select="$newseries"/>
        <xsl:value-of select="substring-after(title, $series)"/>
      </xsl:when>
      <xsl:otherwise>
        <xsl:value-of select="title"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>



  <xsl:template  match="detail/record" mode="process-creator">
    <!-- for more then 1 author, output authors in the following format: Author 1, Author 2 & Author 3 -->
    <xsl:choose>
      <xsl:when test="count(../detail[@id=158]/record) >1">
        <xsl:choose>
          <xsl:when test="position()=last()"> &amp; <xsl:call-template name="creator"/>
          </xsl:when>
          <xsl:otherwise>
            <xsl:choose>
              <xsl:when test="position()=last()-1">
                <xsl:call-template name="creator"/>
              </xsl:when>
              <xsl:otherwise>
                <xsl:call-template name="creator"/>, </xsl:otherwise>
            </xsl:choose>
          </xsl:otherwise>
        </xsl:choose>
      </xsl:when>
      <xsl:otherwise>
        <xsl:call-template name="creator"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>



  <xsl:template name="date">
    <!-- template for date format -->
    <xsl:param name="num_var"/>
    <xsl:value-of select="format-number($num_var, '##-##-####')"/>
  </xsl:template>

  <xsl:template name="date_form" mode="date" match="detail">
    <xsl:value-of select="day"/>&#xa0;<xsl:call-template name="months">
      <xsl:with-param name="mnth">
        <xsl:value-of select="month"/>
      </xsl:with-param>
    </xsl:call-template>&#xa0;<xsl:value-of select="year"/>
  </xsl:template>


  <xsl:template name="output_weblink">
    <!--  this template chooses to apply  weblink rendering tempate (see below) for images, files and links, based on the appropriate detail types -->
    <xsl:if test="detail[@id=221] ">
      <xsl:apply-templates mode="weblink_content" select="detail[@id=221]"/>
    </xsl:if>
    <xsl:if test="detail[@id=222] ">
      <xsl:apply-templates mode="weblink_content" select="detail[@id=222]"/>
    </xsl:if>
    <xsl:if test="detail[@id=223] ">
      <xsl:apply-templates mode="weblink_content" select="detail[@id=223]"/>
    </xsl:if>
    <xsl:if test="detail[@id=224] ">
      <xsl:apply-templates mode="weblink_content" select="detail[@id=224]"/>
    </xsl:if>
    <xsl:if test="detail[@id=231] ">
      <xsl:apply-templates mode="weblink_content" select="detail[@id=231]"/>
    </xsl:if>
    <xsl:if test="detail[@id=256] ">
      <xsl:apply-templates mode="weblink_content" select="detail[@id=256]"/>
    </xsl:if>
    <xsl:if test="detail[@id=268] ">
      <xsl:apply-templates mode="weblink_content" select="detail[@id=268]"/>
    </xsl:if>
    <xsl:if test="detail[@id=304] ">
      <xsl:apply-templates mode="weblink_content" select="detail[@id=304]"/>
    </xsl:if>
  </xsl:template>

  <xsl:template name="weblink_content" match="detail" mode="weblink_content">
    <!-- this  template renders web links for images, pdf files and other files -->
    <xsl:variable name="lcletters">abcdefghijklmnopqrstuvwxyz</xsl:variable>
    <xsl:variable name="ucletters">ABCDEFGHIJKLMNOPQRSTUVWXYZ</xsl:variable>
    <xsl:variable name="filename">
      <xsl:value-of select="translate(file/origName, $ucletters, $lcletters)"/>
    </xsl:variable>
    <xsl:if test="$filename">
      <xsl:choose>
        <xsl:when
          test="contains($filename, '.png') or  contains($filename, '.gif') or contains($filename, '.jpg') or contains($filename, '.jpeg') or contains($filename, '.bmp')">
          &#160;<a href="{file/url}" target="_blank">image</a> </xsl:when>
        <xsl:otherwise>
          <xsl:choose>
            <xsl:when test="contains($filename, '.pdf')">
              &#160;<a href="{file/url}" target="_blank">pdf</a></xsl:when>
            <xsl:otherwise>
              &#160;<a href="{file/url}" target="_blank">file</a> </xsl:otherwise>
          </xsl:choose>
        </xsl:otherwise>
      </xsl:choose>
    </xsl:if>
  </xsl:template>


      <xsl:template name="creator" match="detail/record" mode="creator">
      <xsl:param name="auth" select="count"/>
      <xsl:value-of select="$auth"/>COUNT
        <xsl:choose>
            <xsl:when test="contains(title,',') ">
                <!-- display initials instead of a full first name, if applicable-->
                <xsl:variable name="lname">
                    <xsl:value-of select="substring-before(title, ',')"/>,
                </xsl:variable>
                <xsl:variable name="fname">
                    <xsl:value-of select="substring-after(title, ', ')"/>,
                </xsl:variable>
                <xsl:value-of select="$lname"/>
                <xsl:choose>
                    <xsl:when test="contains($fname,' ') or contains($fname, '.')">
                        <xsl:choose>
                            <xsl:when test="string-length($fname) &gt; 4">
                                <xsl:value-of select="substring($fname, 1, 1)"/>&#160;
                            </xsl:when>
                            <xsl:otherwise>
                                <xsl:value-of select="$fname"/>&#160;
                            </xsl:otherwise>
                        </xsl:choose>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:value-of select="substring($fname, 1, 1)"/>,&#160;</xsl:otherwise>
                </xsl:choose>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="title"/>,
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>


</xsl:stylesheet>
