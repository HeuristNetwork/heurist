<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:w="http://schemas.microsoft.com/office/word/2003/wordml"
  xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:aml ="http://schemas.microsoft.com/aml/2001/core"
  xmlns:wx="http://schemas.microsoft.com/office/word/2003/auxHint" exclude-result-prefixes="w o wx ">
<!-- NOTES
  This stylesheet attempts to convert wordML to TEI to the best of its capacities. To facilitate its capacities, in the original word document you should:
  -> Eliminate the footer on the document
  -> Be VERY careful and precise with nested lists formatting - empty paragraphs etc. immediately before or after the list items can make lists render in an unpredictable fashion.
  -> Rather then using "heading" styles  in document format, use "Strong" and "Emphasis" - this will render appropriate elements


-->
  <xsl:include href="myvariables.xsl"/>
  <xsl:output indent="yes"/>

  <!-- xsl:template match="wx:sub-section[parent::wx:sect]" -->
  <!-- parameters -->

  <xsl:param name="authorityText"/>
  <!--  set to the text to be included as the publishing authority in the TEI Header -->
  <xsl:param name="authorsName"/>
  <!--  set to the authors name  in the TEI Header -->
  <xsl:param name="dateCreated"/>
  <!--  set to the document date in the  TEI Header -->
  <xsl:param name="revisionItemText"/>
  <!-- to be dropped into the <item> element of the revision description in the TEI Header. -->

  <!-- URL root: the root of the URL to which image links should point. Should correspond to the directory contained in $fileRoot. -->
	<xsl:param name="urlroot"><xsl:value-of select="$hbase"/></xsl:param>
  <!-- File root: absolute path to the directory into which images are to be written -->
  <xsl:param name="fileRoot">
    <xsl:value-of select="$urlroot"/>
  </xsl:param>

  <xsl:key name="getListDef" match="w:listDef"
    use="../w:list[w:ilst/@w:val=current()/@w:listDefId]/@w:ilfo"/>
  <xsl:key name="listStarts"
    match="w:p[w:pPr/w:listPr][not(w:pPr/w:listPr/w:ilfo/@w:val=preceding-sibling::w:*[1]/w:pPr/w:listPr/w:ilfo/@w:val and w:pPr/w:listPr/w:ilvl/@w:val=preceding-sibling::w:*[1]/w:pPr/w:listPr/w:ilvl/@w:val)]"
    use="concat(w:pPr/w:listPr/w:ilfo/@w:val,':',w:pPr/w:listPr/w:ilvl/@w:val)"/>


  <!-- identity transform - copy everything -->
  <xsl:template match="@*|node()">
    <xsl:copy>
      <xsl:apply-templates select="@*|node()"/>
    </xsl:copy>
  </xsl:template>



  <xsl:template match="w:wordDocument">
    <TEI>
      <xsl:apply-templates select="o:DocumentProperties"/>
      <xsl:choose>
        <xsl:when test="w:body/wx:sect">
         <xsl:apply-templates select="w:body/wx:sect" />
        </xsl:when>
        <xsl:otherwise><xsl:apply-templates select="w:body"/></xsl:otherwise>
      </xsl:choose>
    </TEI>
  </xsl:template>

  <xsl:template name="getTitle">
    <xsl:value-of select="//w:wordDocument/o:DocumentProperties/o:Title"/>
  </xsl:template>


  <xsl:template name="getLastAuthor">
    <xsl:value-of select=" //w:wordDocument/o:DocumentProperties/o:LastAuthor"/>
  </xsl:template>

  <xsl:template name="getLastDate">
    <xsl:value-of select="//w:wordDocument/o:DocumentProperties/o:LastSaved"/>
  </xsl:template>

  <xsl:template match="o:DocumentProperties">
    <teiHeader>
      <fileDesc>
        <titleStmt>
          <title>
            <xsl:call-template name="getTitle"/>
          </title>
          <author>
            <xsl:value-of select="$authorsName"/>
          </author>
        </titleStmt>
        <editionStmt>

          <edition>
            <date>
              <xsl:value-of select="$dateCreated"/>
            </date>
          </edition>

        </editionStmt>
        <publicationStmt>
          <availability status="restricted">
            <p>Freely available for non-commercial use provided that this header is included in its
              entirety with any copy distributed</p>
          </availability>
        </publicationStmt>
        <sourceDesc>
          <p>Compiled from existing internal documents.</p>
        </sourceDesc>
      </fileDesc>
      <revisionDesc>
        <change>
          <date>
            <xsl:call-template name="getLastDate"/>
          </date>
          <respStmt>
            <name>
              <xsl:call-template name="getLastAuthor"/>
            </name>
          </respStmt>
          <item>
            <xsl:value-of select="$revisionItemText"/>
          </item>
        </change>
      </revisionDesc>
    </teiHeader>
  </xsl:template>



  <xsl:template match="w:body|wx:sect">
    <text>
      <front/>
      <body>
        <xsl:apply-templates/>
      </body>
    </text>
  </xsl:template>


  <!-- Convert <wx:sub-section> elements to <section> elements -->
  <xsl:template match="wx:sub-section">
    <div>
      <xsl:apply-templates/>
    </div>
  </xsl:template>

  <xsl:template match="w:sectPr">
    <!-- don't do anything -->
  </xsl:template>

  <!-- Convert <w:p> paragraphs to <p> paragraphs -->
  <xsl:template match="w:p">
    <p>
      <xsl:if test="w:pPr/w:pStyle">
        <xsl:attribute name="type">
          <xsl:value-of select="w:pPr/w:pStyle/@w:val"/>
        </xsl:attribute>
	  </xsl:if>
      <xsl:apply-templates mode="para-content"/>
      <xsl:apply-templates select="aml:annotation"/>
    </p>
  </xsl:template>

<xsl:template match="aml:annotation"></xsl:template>

  <!-- Convert <w:tbl> paragraphs to <table> and render table  rows and cells -->

  <xsl:template match="w:tbl">
    <table>
      <xsl:apply-templates select="w:tr"/>
    </table>
  </xsl:template>
  <xsl:template match="w:tr">
    <row>
      <xsl:apply-templates select="w:tc"/>
    </row>
  </xsl:template>
  <xsl:template match="w:tc">
    <cell>
      <xsl:value-of select="."/>
    </cell>
  </xsl:template>


  <!-- ...except for the first paragraph in a sub-section (Heading 1,2,3,...);
       the heading will be the <title> of the section -->
  <xsl:template match="wx:sub-section/w:p[1]">
    <head>
      <xsl:apply-templates mode="para-content"/>
    </head>
  </xsl:template>


  <!-- Don't copy text nodes except when explicitly requested (see below) -->
  <xsl:template match="w:wordDocument//text()"/>
  <xsl:template match="w:wordDocument//text()" mode="para-content"></xsl:template>



  <!-- default behavior for text runs; copy the text through -->
  <xsl:template match="w:r" mode="para-content">
    <xsl:choose>
      <xsl:when test="w:endnote or w:footnote">
        <xsl:apply-templates mode="endnote"/>
      </xsl:when>
      <xsl:otherwise>
        <xsl:choose>
          <xsl:when test="w:pict">
            <xsl:apply-templates mode="pict"/>
          </xsl:when>
          <xsl:otherwise>
            <xsl:copy-of select="w:t/text()"/>
          </xsl:otherwise>
        </xsl:choose>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>


  <xsl:template match="w:endnote|w:footnote" mode="endnote">
    <note>
      <xsl:attribute name="place">
        <xsl:choose>
          <xsl:when test="self::w:endnote">
            <xsl:text>end</xsl:text>
          </xsl:when>
          <xsl:otherwise>
            <xsl:text>foot</xsl:text>
          </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <xsl:value-of select="."/>
    </note>
  </xsl:template>

<!-- graphic elements -->
  <xsl:template match="w:pict" mode="pict">
    <xsl:variable name="pictid" select="concat($fileRoot,substring-after(w:binData/@w:name,'//'))"/>
    <figure>
      <graphic url="{concat($urlroot,substring-after($pictid,$fileRoot))}"/>
    </figure>
  </xsl:template>

  <!-- turn a run with the "Emphasis" character style into <emphasis> -->
  <xsl:template match="w:r[w:rPr/w:rStyle/@w:val='Emphasis']" mode="para-content">
    <emphasis>
      <xsl:copy-of select="w:t/text()"/>
    </emphasis>
  </xsl:template>

  <!-- itallics -->
  <xsl:template match="w:r[w:rPr/w:i]" mode="para-content">
    <hi rend="italics"><xsl:copy-of select="w:t/text()"/></hi>
  </xsl:template>

  <!-- bold -->
  <xsl:template match="w:r[w:rPr/w:b]" mode="para-content">
    <hi rend="bold"><xsl:copy-of select="w:t/text()"/></hi>
  </xsl:template>

  <!-- turn a run with the "Strong" character style into <strong> -->
  <xsl:template match="w:r[w:rPr/w:rStyle/@w:val='Strong']" mode="para-content">
    <strong>
      <xsl:copy-of select="w:t/text()"/>
    </strong>
  </xsl:template>



  <!-- Turn a w:hlink element into an HTML-style hyperlink -->
  <xsl:template match="w:hlink" mode="para-content">
    <a href="{@w:dest}">
      <xsl:apply-templates mode="para-content"/>
    </a>
  </xsl:template>

  <!-- List generation, adopted from Source Forge and modified. Handels nested lists, VERY NICE INDEED -->
  <xsl:template match="w:p[w:pPr/w:listPr]">
    <xsl:variable name="ilfo" select="w:pPr/w:listPr/w:ilfo/@w:val"/>
    <xsl:variable name="ilvl" select="w:pPr/w:listPr/w:ilvl/@w:val"/>

    <xsl:variable name="listStarts" select="key('listStarts',concat($ilfo,':',$ilvl))"/>
    <xsl:choose>
      <xsl:when test="preceding-sibling::w:*[1][self::w:p]/w:pPr/w:listPr/w:ilfo/@w:val=$ilfo">
        <!-- This is midway through a list: ignore it -->
      </xsl:when>
      <xsl:otherwise>
        <list>
          <!-- attributes "id" and "next" commented out although originally present they seem not to be valid in end TEI output. MS -->
          <xsl:if test="count($listStarts)&gt;1">
            <!-- This list is split -->

            <xsl:choose>
              <xsl:when test="count($listStarts[1]|.)=1">

              </xsl:when>
              <xsl:when test="count($listStarts[position()=last()]|.)=1">
                <!-- This is the last part of the list; output prev only -->
                <xsl:attribute name="prev">
                  <xsl:value-of select="generate-id($listStarts[position()=last()-1])"/>
                </xsl:attribute>
              </xsl:when>
              <xsl:otherwise>
                <!-- This is midway through the list; output prev and next -->
                <xsl:attribute name="prev">
                  <xsl:for-each select="preceding::w:p[count($listStarts|.)=count($listStarts)][1]">
                    <xsl:value-of select="generate-id(.)"/>
                  </xsl:for-each>
                </xsl:attribute>

              </xsl:otherwise>
            </xsl:choose>
          </xsl:if>
          <xsl:call-template name="writeListItem"/>
          <xsl:call-template name="iterateListItems">
            <xsl:with-param name="ilfo" select="$ilfo"/>
            <xsl:with-param name="ilvl" select="$ilvl"/>
          </xsl:call-template>
        </list>
      </xsl:otherwise>
    </xsl:choose>
    <xsl:apply-templates select="w:pPr"></xsl:apply-templates>
    <xsl:apply-templates select="w:r" ></xsl:apply-templates>
  </xsl:template>

<xsl:template match="w:pPr" ></xsl:template>
 <xsl:template match="w:r" ></xsl:template>

  <xsl:template name="writeListItem">
    <xsl:variable name="rendValues">
      <xsl:value-of select="."/>
    </xsl:variable>
    <xsl:variable name="ilfo" select="w:pPr/w:listPr/w:ilfo/@w:val"/>
    <xsl:variable name="ilvl" select="w:pPr/w:listPr/w:ilvl/@w:val"/>
    <item>
      <xsl:if test="string-length(normalize-space($rendValues))&gt;0">
        <xsl:value-of select="normalize-space($rendValues)"/>
      </xsl:if>
      <xsl:apply-templates/>
    </item>
  </xsl:template>

  <xsl:template name="iterateListItems">
    <xsl:param name="ilfo"/>
    <xsl:param name="ilvl"/>
    <xsl:param name="pos" select="count(preceding::w:p[not(w:pPr/w:listPr)]|preceding::w:tbl)"/>
    <xsl:for-each select="following-sibling::w:*[1][self::w:p][w:pPr/w:listPr/w:ilfo/@w:val=$ilfo]">
      <xsl:choose>
        <xsl:when test="w:pPr/w:listPr/w:ilvl/@w:val=$ilvl">
          <!-- Same level just output an item -->
          <xsl:call-template name="writeListItem"/>
          <xsl:call-template name="iterateListItems">
            <xsl:with-param name="ilfo" select="$ilfo"/>
            <xsl:with-param name="ilvl" select="$ilvl"/>
          </xsl:call-template>
        </xsl:when>
        <xsl:when test="number(w:pPr/w:listPr/w:ilvl/@w:val) &gt; number($ilvl)">
          <!-- Subsidiary list -->
          <xsl:variable name="newIlvl" select="w:pPr/w:listPr/w:ilvl/@w:val"/>
          <xsl:variable name="listType"> </xsl:variable>
          <xsl:variable name="listStarts" select="key('listStarts',concat($ilfo,':',$newIlvl))"/>
          <item>
            <list>
              <xsl:if test="string-length($listType)&gt;0">
                <xsl:attribute name="type">
                  <xsl:value-of select="normalize-space($listType)"/>
                </xsl:attribute>
              </xsl:if>
              <xsl:if test="count($listStarts)&gt;1">
                <!-- This list is split -->
                <!--xsl:attribute name="id">
                  <xsl:value-of select="generate-id(.)"/>
                </xsl:attribute-->
                <xsl:choose>
                  <xsl:when test="count($listStarts[1]|.)=1">
                    <!-- This is the first part of the list; output next only -->
                    <xsl:attribute name="next">
                      <xsl:value-of select="generate-id($listStarts[2])"/>
                    </xsl:attribute>
                  </xsl:when>
                  <xsl:when test="count($listStarts[position()=last()]|.)=1">
                    <!-- This is the last part of the list; output prev only -->
                    <xsl:attribute name="prev">
                      <xsl:value-of select="generate-id($listStarts[position()=last()-1])"/>
                    </xsl:attribute>
                  </xsl:when>
                </xsl:choose>
              </xsl:if>
              <xsl:call-template name="writeListItem"/>
              <xsl:call-template name="iterateListItems">
                <xsl:with-param name="ilfo" select="$ilfo"/>
                <xsl:with-param name="ilvl" select="$newIlvl"/>
              </xsl:call-template>
            </list>
          </item>
          <!-- Now check to see if this level is continued after the sublist -->
          <xsl:call-template name="findListLevelContinuation">
            <xsl:with-param name="ilfo" select="$ilfo"/>
            <xsl:with-param name="ilvl" select="$ilvl"/>
          </xsl:call-template>
        </xsl:when>
        <xsl:otherwise>
          <!-- Do nothing -->
        </xsl:otherwise>
      </xsl:choose>
    </xsl:for-each>
  </xsl:template>

  <xsl:template name="findListLevelContinuation">
    <!-- This template is called *after* a subsidiary list, and searches through following siblings to
      find a potential continuation of the original list. As it is called from the last known item
      of the original list, it must iterate doing nothing through items of the same list fo but of
      a higher level. -->
    <xsl:param name="ilfo"/>
    <xsl:param name="ilvl"/>
    <!-- If this for-each fails it's the end of the entire list group, so do nuffink -->
    <xsl:for-each select="following-sibling::w:*[1][self::w:p/w:pPr/w:listPr]">
      <xsl:choose>
        <xsl:when test="w:pPr/w:listPr/w:ilfo/@w:val=$ilfo and w:pPr/w:listPr/w:ilvl/@w:val=$ilvl">
          <!-- We have found the next item in the original list, so start again with an item and
            the list walker. -->
          <xsl:call-template name="writeListItem"/>
          <xsl:call-template name="iterateListItems">
            <xsl:with-param name="ilfo" select="$ilfo"/>
            <xsl:with-param name="ilvl" select="$ilvl"/>
          </xsl:call-template>
        </xsl:when>
        <xsl:when
          test="w:pPr/w:listPr/w:ilfo/@w:val=$ilfo and number(w:pPr/w:listPr/w:ilvl/@w:val) &gt; number($ilvl)">
          <!-- Same list, higher level. Iterate again. -->
          <xsl:call-template name="findListLevelContinuation">
            <xsl:with-param name="ilfo" select="$ilfo"/>
            <xsl:with-param name="ilvl" select="$ilvl"/>
          </xsl:call-template>
        </xsl:when>
        <xsl:when
          test="w:pPr/w:listPr/w:ilfo/@w:val=$ilfo and number(w:pPr/w:listPr/w:ilvl/@w:val) &lt; number($ilvl)">
          <!-- Same list, lower level. This has happened because we've had a, a.a, a.a.a, a. . Terminate anyway -->
        </xsl:when>
        <xsl:otherwise>
          <!-- Something wrong -->
        </xsl:otherwise>
      </xsl:choose>
    </xsl:for-each>
  </xsl:template>

  <!-- Sentence separation templates, adopted from exslt.org -->
  <!-- the code below splits paragraphs into sentences based on an assumption that everything with a dot at the end is a sentence -->
  <!-- <xsl:call-template name="strSsplit">
    <xsl:with-param name="pattern">.</xsl:with-param>
    <xsl:with-param name="string"><xsl:value-of select="."/></xsl:with-param>
    </xsl:call-template>-->
  <xsl:template name="strSsplit">
    <xsl:param name="string" select="''"/>
    <xsl:param name="pattern" select="' '"/>
    <xsl:choose>
      <xsl:when test="not($string)"/>
      <xsl:when test="not($pattern)">
        <xsl:call-template name="strS_split-characters">
          <xsl:with-param name="string" select="$string"/>
        </xsl:call-template>
      </xsl:when>
      <xsl:otherwise>
        <xsl:call-template name="strS_split-pattern">
          <xsl:with-param name="string" select="$string"/>
          <xsl:with-param name="pattern" select="$pattern"/>
        </xsl:call-template>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <xsl:template name="strS_split-characters">
    <xsl:param name="string"/>
    <xsl:if test="$string">
      <s>
        <xsl:value-of select="substring($string, 1, 1)"/>
      </s>
      <xsl:call-template name="strS_split-characters">
        <xsl:with-param name="string" select="substring($string, 2)"/>
      </xsl:call-template>
    </xsl:if>
  </xsl:template>

  <xsl:template name="strS_split-pattern">
    <xsl:param name="string"/>
    <xsl:param name="pattern"/>
    <xsl:choose>
      <xsl:when test="contains($string, $pattern)">
        <xsl:if test="not(starts-with($string, $pattern))">
          <s>
            <xsl:value-of select="substring-before($string, $pattern)"/>
          </s>
        </xsl:if>
        <xsl:call-template name="strS_split-pattern">
          <xsl:with-param name="string" select="substring-after($string, $pattern)"/>
          <xsl:with-param name="pattern" select="$pattern"/>
        </xsl:call-template>
      </xsl:when>
      <xsl:otherwise>
        <s>
          <xsl:value-of select="$string"/>
        </s>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <xsl:template match="w:i">
    [italic]
  </xsl:template>

</xsl:stylesheet>
