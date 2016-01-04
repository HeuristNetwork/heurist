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

  <xsl:template match="/">

    <!-- use the following bit of code to include the stylesheet to display it in Heurist publishing wizard
      otherwise it will be ommited-->
    <!-- begin including code -->
    
    <!-- end including code -->
    <html>
      <head>
        <link type="text/css" href="http://www.arts.usyd.edu.au/artsdigital/styles/base_internal.css" rel="stylesheet"/>
       <link type="text/css" href="http://www.arts.usyd.edu.au/artsdigital/styles/base.css" rel="stylesheet"/>
        <link type="text/css" href="http://www.arts.usyd.edu.au/artsdigital/styles/additional.css" rel="stylesheet"/>
        <link type="text/css" href="http://www.arts.usyd.edu.au/artsdigital/styles/print.css"  media="print" rel="stylesheet"/>
        
       
      </head>
      <body>
        <xsl:attribute name="pub_id">
          <xsl:value-of select="/hml/query[@pub_id]"/>
        </xsl:attribute>
        <div id="content" class="notabs nofeature">
          <div id="w4">
          <xsl:apply-templates select="hml/records/record"/>
            </div>
      </div>
      </body>
    </html>
  </xsl:template>
  <!-- =================  55: People only =============================== -->
  <xsl:template match="record[type/@id=55]">

    <h1><xsl:value-of select="title"/></h1>
    <table width="606" height="227" border="0">
      <tbody>
        <tr>
          <td>
            <em><xsl:value-of select="detail[@id=407]"/></em>
            <br/>
            <xsl:value-of select="detail[@id=258]"/>
            <br/>
            <br/>
            <xsl:for-each select="detail[@id=268]">
              <xsl:value-of select="."/>
              <br/>
            </xsl:for-each>
          </td>
          <td>
           <img src = " {detail[@id=223]/file/url} "/>
            </td>
          
        </tr>
      </tbody>
    </table>
    <xsl:if test="detail[@id=265]">
      <xsl:call-template name="research-interests"></xsl:call-template>
    </xsl:if>
    <xsl:if test="detail[@id=264]/record">
      <xsl:call-template name="current-projects"></xsl:call-template>
    </xsl:if>
    <xsl:if test="detail[@id=191]">
      <xsl:call-template name="prof-activities"></xsl:call-template>
    </xsl:if>
        <!-- if Title are missing, don't print -->
        <!--xsl:if test="detail[@id='160']">
          
        
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
        
        </xsl:if-->
     
 
  </xsl:template>
  <xsl:template name="research-interests">
    <h2>Research areas</h2>
    <ul>
    <xsl:for-each select="detail[@id=265]">
      <li>
        <xsl:value-of select="."/>
      </li>
    </xsl:for-each>
    </ul>
  </xsl:template>

  <xsl:template name="current-projects">
    <h2>Current projects</h2>
    <ul>
      <xsl:for-each select="detail[@id=264]/record">
        <li>
          <xsl:value-of select="."/>
        </li>
      </xsl:for-each>
    </ul>
  </xsl:template>
  
  <xsl:template name="prof-activities">
    <h2>Professional activities</h2>
    <ul>
      <xsl:for-each select="detail[@id=191]">
        <li>
          <xsl:value-of select="."/>
        </li>
      </xsl:for-each>
    </ul>
  </xsl:template>
 
  <!-- =================  HELPER TEMPLATES  =============================== -->

  <!-- POINTER -->
  <xsl:template match="detail/record">
    <xsl:choose>
      <!-- replace empty series if applicable -->
      <!--
      <xsl:when test="contains(title, '(series =)')">
        <xsl:variable name="series">(series =)</xsl:variable>
        
        <xsl:value-of select="substring-before(title, $series)"/>
        <xsl:value-of select="substring-after(title, $series)"/>
      </xsl:when>
      <xsl:otherwise>
        <xsl:value-of select="title"/>
      </xsl:otherwise>
      </xsl:choose> -->
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

  <!-- CREATOR -->
  <xsl:template name="title">
    <xsl:choose>
      <xsl:when test="contains(title,',') ">
        <!-- display initials instead of a full first name, if applicable-->
        <xsl:variable name="lname">
          <xsl:value-of select="substring-before(title, ',')"/>
        </xsl:variable>
        <xsl:variable name="fname">
          <xsl:value-of select="substring-after(title, ', ')"/>
        </xsl:variable>
        <xsl:value-of select="$lname"/>&#xa0; <xsl:choose>
          <xsl:when test="contains($fname,' ') or contains($fname, '.')">
            <xsl:choose>
              <xsl:when test="string-length($fname) &gt; 4">
                <xsl:value-of select="substring($fname, 1, 1)"/>. </xsl:when>
              <xsl:otherwise>
                <xsl:value-of select="$fname"/>
              </xsl:otherwise>
            </xsl:choose>
          </xsl:when>
          <xsl:otherwise>
            <xsl:value-of select="substring($fname, 1, 1)"/>. </xsl:otherwise>
        </xsl:choose>
      </xsl:when>
      <xsl:otherwise>
        <xsl:value-of select="title"/>
      </xsl:otherwise>
    </xsl:choose>
</xsl:template>

  <xsl:template name="creator" match="detail/record" mode="creator">
    <!-- for more then 1 author, output authors in the following format: Author 1, Author 2 & Author 3 -->
    <xsl:choose>
      <xsl:when test="count(../detail[@id=158]/record) >1">
        <xsl:choose>
          <xsl:when test="position()=last()"> &amp; <xsl:call-template name="title"/>
          </xsl:when>
          <xsl:otherwise>
            <xsl:choose>
              <xsl:when test="position()=last()-1">
                <xsl:call-template name="title"/>
              </xsl:when>
              <xsl:otherwise>
                <xsl:call-template name="title"/>, </xsl:otherwise>
            </xsl:choose>
          </xsl:otherwise>
        </xsl:choose>
      </xsl:when>
      <xsl:otherwise>
        <xsl:call-template name="title"/>
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


  <xsl:template name="months">
    <!--convert month from number to a month name -->
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


</xsl:stylesheet>
