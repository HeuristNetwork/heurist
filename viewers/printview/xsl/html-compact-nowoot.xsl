<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:str="http://exslt.org/strings" version="1.0">
  <!-- 
 this style renders standard html
 author  Maria Shvedova
 last updated 10/09/2007 ms
  -->

  <xsl:template match="/">

    <!-- use the following bit of code to include the stylesheet to display it in Heurist publishing wizard
      otherwise it will be ommited-->
    <!-- begin including code -->
    <xsl:comment>
      <!-- name (desc.) that will appear in dropdown list --> [name]HTML (compact)[/name]
      <!-- match the name of the stylesheet--> [output]html-compact[/output] </xsl:comment>
    <!-- end including code -->

    <html>
      <head>
        <style type="text/css">
          body {font-family:Verdana,Helvetica,Arial,sans-serif; font-size:10px; }
          td { vertical-align: top; }
          .rectype {color: #999999;  }
         
        </style>
        <script>
          function displaymore(id) 
          { 
          document.getElementById(id).style.display = ''; 
          document.getElementById('a'+id).style.display = 'none';
          } 
          
          function hidemore(id){
          document.getElementById(id).style.display="none";
          document.getElementById('a'+id).style.display = '';
          }
        </script>
      </head>
      <body>
        <xsl:attribute name="pub_id">
          <xsl:value-of select="/hml/query[@pub_id]"/>
        </xsl:attribute>
        <table>
          <xsl:apply-templates select="/hml/records/record"/>
        </table>
      </body>
    </html>
  </xsl:template>


  <!-- MONTHS -->
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

  <!-- MODIFIED DATE conversion template -->
  <xsl:template match="modified">
    <xsl:variable name="datepart">
      <xsl:value-of select="substring-before(., ' ')"/>
    </xsl:variable>
    <xsl:value-of select="str:split($datepart, '-')[3]"/>-<xsl:call-template name="months">
      <xsl:with-param name="mnth">
        <xsl:value-of select="str:split($datepart, '-')[2]"/>
      </xsl:with-param>
    </xsl:call-template>-<xsl:value-of select="str:split($datepart, '-')[1]"/> ; </xsl:template>

  <!-- HEADER  -->
  <xsl:template name="header">
    <tr>
      <td colspan="2" style="font-size: 11px; font-weight: bold;">
        <img>
          <xsl:attribute name="align">abstop</xsl:attribute>
          <xsl:attribute name="src">http://heuristscholar.org/rectype/<xsl:value-of
              select="type/@id"/>.gif</xsl:attribute>
        </img> &#160; <xsl:value-of select="title"/> &#160; <span style="font-weight:normal"
          >[id: <xsl:value-of select="id"/>]</span></td>
    </tr>
    <tr>
      <td style="font-size: 11px;">
        <xsl:value-of select="type"/> ; <xsl:if test="modified !=''"> &#160; <span
            class="rectype">updated:</span>&#160; <xsl:apply-templates select="modified"/>
        </xsl:if>
        <xsl:if test="url != ''"> &#160; <a href="{url}">
            <xsl:choose>
              <xsl:when test="string-length(url) &gt; 50">
                <xsl:value-of select="substring(url, 0, 50)"/>...</xsl:when>
              <xsl:otherwise>
                <xsl:value-of select="url"/></xsl:otherwise>
            </xsl:choose>
          </a> ;
        </xsl:if>
      </td>
    </tr>
  </xsl:template>

  <!-- url  rectype only -->
  <xsl:template match="record[type/@id=1]">
    <xsl:call-template name="header"/>
    <tr>
      <td colspan="2" style="padding-bottom: 7px;">
        <xsl:call-template name="body-detail-1"/>
        <xsl:call-template name="body-pointer"/>
        <xsl:call-template name="body-related"/>
      </td>
    </tr>
  </xsl:template>

  <!-- all rectypes template -->
  <xsl:template match="record">
    <xsl:call-template name="header"/>
    <tr>
      <td colspan="2" style="padding-bottom: 7px;">
        <xsl:call-template name="body-detail"/>
        <xsl:call-template name="body-pointer"/>
        <xsl:call-template name="body-related"/>
      </td>
    </tr>
  </xsl:template>

  <xsl:template name="body-detail">
    <!-- DETAIL LISTING -->
    <!--put what is being grouped in a variable-->
    <xsl:variable name="details" select="detail"/>
    <!--walk through the variable-->
    <xsl:for-each select="detail">

      <!--act on the first in document order-->
      <xsl:if test="generate-id(.)=
          generate-id($details[@id=current()/@id][1]) and self::node()[@id!= 249]">

        <span class="rectype">
          <xsl:choose>
            <xsl:when test="@name !=''">
              <xsl:value-of select="@name"/>
            </xsl:when>
            <xsl:otherwise>
              <xsl:value-of select="@type"/>
            </xsl:otherwise>
          </xsl:choose> : &#160; </span>
        <!--revisit all-->

        <xsl:for-each select="$details[@id=current()/@id]">
          <xsl:sort select="."/>
          <xsl:choose>
            <xsl:when
              test="self::node()[@id != 166 and @id!= 222 and @id!= 221 and @id!=177 and @id != 223 and @id != 231 and @id != 268 and @id !=256 and @id!=304 and @id != 224]">

              <xsl:variable name="spanid">
                <xsl:value-of select="../type/@id"/>
                <xsl:value-of select="@id"/>
              </xsl:variable>


              <xsl:choose>
                <xsl:when test="string-length(.) &gt; 100">

                  <xsl:value-of select="substring(., 0, 100)"/>
                  <span id="a{$spanid}">... <xsl:element name="a">
                      <xsl:attribute name="class">morelink</xsl:attribute>
                      <xsl:attribute name="href">#</xsl:attribute>
                      <xsl:attribute name="onClick">displaymore(<xsl:value-of select="$spanid"
                      />)</xsl:attribute>more</xsl:element>
                  </span>
                  <span id="{$spanid}" style="display: none;">
                    <xsl:value-of select="substring(., 100)"/>&#160; <xsl:element name="a">
                      <xsl:attribute name="class">morelink</xsl:attribute>
                      <xsl:attribute name="id">a<xsl:value-of select="$spanid"/></xsl:attribute>
                      <xsl:attribute name="href">#</xsl:attribute>
                      <xsl:attribute name="onClick">hidemore(<xsl:value-of select="$spanid"
                      />)</xsl:attribute>hide</xsl:element>
                  </span>
                </xsl:when>
                <xsl:otherwise>
                  <xsl:value-of select="."/>
                </xsl:otherwise>
              </xsl:choose>

            </xsl:when>
            <xsl:otherwise>
              <xsl:if test="self::node()[@id= 166]">
                <xsl:call-template name="date"/>
              </xsl:if>
              <xsl:if test="self::node()[@id= 177]">
                <xsl:call-template name="start-date"/>
              </xsl:if>
              <xsl:if test="self::node()[@id= 222 or @id= 223 or  @id= 224]">
                <xsl:call-template name="logo">
                  <xsl:with-param name="id">
                    <xsl:value-of select="@id"/>
                  </xsl:with-param>
                </xsl:call-template>
              </xsl:if>
              <xsl:if test="self::node()[@id= 231 or @id=221]">
                <xsl:call-template name="file">
                  <xsl:with-param name="id">
                    <xsl:value-of select="@id"/>
                  </xsl:with-param>
                </xsl:call-template>
              </xsl:if>
              <xsl:if test="self::node()[@id= 268 or @id=304]">
                <xsl:call-template name="url">
                  <xsl:with-param name="key">
                    <xsl:value-of select="."/>
                  </xsl:with-param>
                  <xsl:with-param name="value">
                    <xsl:value-of select="."/>
                  </xsl:with-param>
                </xsl:call-template>
              </xsl:if>
              <xsl:if test="self::node()[@id= 256]">
                <xsl:call-template name="url">
                  <xsl:with-param name="key">
                    <xsl:value-of select="."/>
                  </xsl:with-param>
                  <xsl:with-param name="value">
                    <xsl:value-of select="."/>
                  </xsl:with-param>
                </xsl:call-template>
              </xsl:if>
            </xsl:otherwise>
          </xsl:choose> ; &#160; </xsl:for-each>
      </xsl:if>

    </xsl:for-each>
  </xsl:template>

  <xsl:template name="body-detail-1">
    <!-- DETAIL LISTING rectype 1 -->
    <!--put what is being grouped in a variable-->
    <xsl:variable name="details" select="detail"/>
    <!--walk through the variable-->
    <xsl:for-each select="detail">
      <!--act on the first in document order-->
      <xsl:if test="generate-id(.)=
        generate-id($details[@id=current()/@id][1])">
        <!-- do not display page title, since its a repetition of the heading-->
        <xsl:if test="@id !=160">
          <span class="rectype">
            <xsl:choose>
              <xsl:when test="@name !=''">
                <xsl:value-of select="@name"/>
              </xsl:when>
              <xsl:otherwise>
                <xsl:value-of select="@type"/>
              </xsl:otherwise>
            </xsl:choose> : &#160; </span>
          <!--revisit all-->

          <xsl:for-each select="$details[@id=current()/@id]">
            <xsl:sort select="."/>
            <xsl:choose>
              <xsl:when
                test="self::node()[@id !=166 and @id!= 222 and @id!= 221 and @id!=177 and @id != 223 and @id != 231 and @id != 268 and @id !=256 and @id!=304 and @id != 224]">
                <xsl:variable name="spanid">
                  <xsl:value-of select="../type/@id"/>
                  <xsl:value-of select="@id"/>
                </xsl:variable>
                <xsl:choose>
                  <xsl:when test="string-length(.) &gt; 100">

                    <xsl:value-of select="substring(., 0, 100)"/>
                    <span id="a{$spanid}">... <xsl:element name="a">
                        <xsl:attribute name="class">morelink</xsl:attribute>
                        <xsl:attribute name="href">#</xsl:attribute>
                        <xsl:attribute name="onClick">displaymore(<xsl:value-of select="$spanid"
                        />)</xsl:attribute>more</xsl:element>
                    </span>
                    <span id="{$spanid}" style="display: none;">
                      <xsl:value-of select="substring(., 100)"/>&#160; <xsl:element name="a">
                        <xsl:attribute name="class">morelink</xsl:attribute>
                        <xsl:attribute name="id">a<xsl:value-of select="$spanid"/></xsl:attribute>
                        <xsl:attribute name="href">#</xsl:attribute>
                        <xsl:attribute name="onClick">hidemore(<xsl:value-of select="$spanid"
                        />)</xsl:attribute>hide</xsl:element>
                    </span>
                  </xsl:when>
                  <xsl:otherwise>
                    <xsl:value-of select="."/>
                  </xsl:otherwise>
                </xsl:choose>

              </xsl:when>
              <xsl:otherwise>
                <xsl:if test="self::node()[@id= 166]">
                  <xsl:call-template name="date"/>
                </xsl:if>
                <xsl:if test="self::node()[@id= 177]">
                  <xsl:call-template name="start-date"/>
                </xsl:if>
                <xsl:if test="self::node()[@id= 222 or @id= 223 or  @id= 224]">
                  <xsl:call-template name="logo">
                    <xsl:with-param name="id">
                      <xsl:value-of select="@id"/>
                    </xsl:with-param>
                  </xsl:call-template>
                </xsl:if>
                <xsl:if test="self::node()[@id= 231 or @id=221]">
                  <xsl:call-template name="file">
                    <xsl:with-param name="id">
                      <xsl:value-of select="@id"/>
                    </xsl:with-param>
                  </xsl:call-template>
                </xsl:if>
                <xsl:if test="self::node()[@id= 268 or @id=304]">
                  <xsl:call-template name="url">
                    <xsl:with-param name="key">
                      <xsl:value-of select="."/>
                    </xsl:with-param>
                    <xsl:with-param name="value">
                      <xsl:value-of select="."/>
                    </xsl:with-param>
                  </xsl:call-template>
                </xsl:if>
                <xsl:if test="self::node()[@id= 256]">
                  <xsl:call-template name="url">
                    <xsl:with-param name="key">
                      <xsl:value-of select="."/>
                    </xsl:with-param>
                    <xsl:with-param name="value">
                      <xsl:value-of select="."/>
                    </xsl:with-param>
                  </xsl:call-template>
                </xsl:if>
              </xsl:otherwise>
            </xsl:choose> ; &#160; </xsl:for-each>
        </xsl:if>
      </xsl:if>
    </xsl:for-each>
  </xsl:template>

  <xsl:template name="body-pointer">
    <!-- POINTER LISTING -->
    <xsl:variable name="pointer" select="detail"/>
    <!--walk through the variable-->
    <xsl:for-each select="detail">
      <!--act on the first in document order-->
      <xsl:if test="generate-id(.)=generate-id($pointer[@id=current()/@id][1]) and self::node()[@id= 249]">
        <span class="rectype">
          <xsl:choose>
            <xsl:when test="@name !=''">
              <xsl:value-of select="@name"/>
            </xsl:when>
            <xsl:otherwise>
              <xsl:value-of select="@type"/>
            </xsl:otherwise>
          </xsl:choose> : &#160; </span>
        <!--revisit all-->
        <xsl:for-each select="$pointer[@id=current()/@id]">
          <xsl:choose>
            <xsl:when test="self::node()[@id=158]">
              <xsl:apply-templates select="." mode="creator"/>
            </xsl:when>
            <xsl:otherwise>
              <xsl:value-of select="record/title"/> ; &#160; 
            </xsl:otherwise>
          </xsl:choose>
          </xsl:for-each>
      </xsl:if>
    </xsl:for-each>
  </xsl:template>

  <xsl:template name="body-related">
    <!-- RELATED LISTING -->
    <xsl:variable name="relation" select="relationships"/>
    <!--walk through the variable-->
    <xsl:for-each select="related">
      <!--act on the first in document order-->
      <xsl:if test="generate-id(.)=
          generate-id($relation[@type=current()/@type][1])">
        <span class="rectype">
          <xsl:value-of select="@type"/>: &#160; </span>
        <!--revisit all-->
        <xsl:for-each select="$relation[@type=current()/@type]">
          <xsl:value-of select="record/title"/> ;&#160; </xsl:for-each>
      </xsl:if>
    </xsl:for-each>
  </xsl:template>

  <!-- helper templates -->
  <xsl:template name="logo">
    <xsl:param name="id"/>
    <xsl:if test="self::node()[@id =$id]">
      <xsl:element name="a">
        <xsl:attribute name="target">_blank</xsl:attribute>
        <xsl:attribute name="href">
          <xsl:value-of select="self::node()[@id =$id]/file/url"/>
        </xsl:attribute> view image</xsl:element>
    </xsl:if>
  </xsl:template>

  <xsl:template name="file">
    <xsl:param name="id"/>
    <xsl:if test="self::node()[@id =$id]">
      <xsl:element name="a">
        <xsl:attribute name="href">
          <xsl:value-of select="self::node()[@id =$id]/file/url"/>
        </xsl:attribute>
        <xsl:value-of select="file/origName"/>
      </xsl:element> [<xsl:value-of select="file/size"/>] </xsl:if>
  </xsl:template>

  <xsl:template name="start-date" match="detail[@id=177]">
    <xsl:if test="self::node()[@id =177]">
      <xsl:value-of select="self::node()[@id =177]/year"/>
    </xsl:if>
  </xsl:template>

  <xsl:template name="url">
    <xsl:param name="key"/>
    <xsl:param name="value"/>
    <xsl:element name="a">
      <xsl:attribute name="href">
        <xsl:value-of select="$key"/>
      </xsl:attribute>
      <xsl:choose>
        <xsl:when test="string-length($value) &gt; 50">
          <xsl:value-of select="substring($value, 0, 50)"/>...</xsl:when>
        <xsl:otherwise>
          <xsl:value-of select="$value"/></xsl:otherwise>
      </xsl:choose>
    </xsl:element>
  </xsl:template>

  <xsl:template name="date">
    <xsl:value-of select="day"/>-<xsl:call-template name="months">
      <xsl:with-param name="mnth">
        <xsl:value-of select="month"/>
      </xsl:with-param>
    </xsl:call-template>-<xsl:value-of select="year"/>
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
        <xsl:call-template name="title"/>;
  </xsl:template>
  
</xsl:stylesheet>
