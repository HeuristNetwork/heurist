<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
  <!--
 this style renders a variation of  standard html (with map)
 author  Maria Shvedova
 last updated 10/09/2007 ms
  -->
  <xsl:param name="hBase"/>
  <xsl:include href="helpers/creator.xsl"/>
  <xsl:template match="/">
    <!-- use the following bit of code to include the stylesheet to display it in Heurist publishing wizard
      otherwise it will be ommited-->
    <!-- begin including code -->
    <xsl:comment>
      <!-- name (desc.) that will appear in dropdown list --> [name]Academic (compact)[/name]
      <!-- match the name of the stylesheet--> [output]academic[/output] </xsl:comment>
    <!-- end including code -->

    <html>
      <head>

        <style type="text/css"> body {font-family:Verdana,Helvetica,Arial,sans-serif;
          font-size:11px; }  .reftype { color: #999999; }
          .record-table {margin-bottom: 10px;}

        </style>
      </head>
      <body>
        <xsl:attribute name="pub_id">
          <xsl:value-of select="/export/@pub_id"/>
        </xsl:attribute>
        <xsl:apply-templates select="/export/references/reference"/>
      </body>
    </html>

  </xsl:template>
  <!-- main template -->
  <xsl:template match="/export/references/reference">

    <!-- HEADER  -->
    <table class="record-table">
      <tr>
        <td colspan="2">
          <a target="_new"
            href="{$hBase}records/editrec/heurist-edit.html?bib_id={id}">
            <img style="border: none;"
              src="{$hBase}common/images/edit_pencil_16x16.gif" align="absmiddle"/>
          </a> <b><xsl:value-of select="id"/>: &#160; <xsl:value-of select="title"/>
          </b>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <span class="reftype">
          Reference type:
          </span>

          <xsl:value-of select="reftype"/>

      <xsl:if test="modified !=''">
        &#160;
        <span class="reftype">
            Last Updated:
          </span>
            <xsl:value-of select="modified"/>

      </xsl:if>
      <xsl:if test="url != ''">
        &#160;
        <span class="reftype">URL: </span>

            <a href="{url}">
              <xsl:choose>
                <xsl:when test="string-length(url) &gt; 50">
                  <xsl:value-of select="substring(url, 0, 50)"/> ... </xsl:when>
                <xsl:otherwise>
                  <xsl:value-of select="url"/>
                </xsl:otherwise>
              </xsl:choose>
            </a>
        </xsl:if>
          </td>
      </tr>
      <tr><td>&#160;</td></tr>


      <!-- DETAIL LISTING -->

      <!--put what is being grouped in a variable-->
      <xsl:variable name="details" select="detail"/>
      <xsl:variable name="countMaps" select="count(detail[@id=230])"/>
      <!--walk through the variable-->
      <xsl:for-each select="detail">
        <!--act on the first in document order-->
        <xsl:if test="generate-id(.)=
              generate-id($details[@id=current()/@id][1])">
          <tr>
            <td class="reftype" width="150">
              <xsl:choose>
                <xsl:when test="@name !=''">
                  <xsl:value-of select="@name"/>
                </xsl:when>
                <xsl:otherwise>
                  <xsl:value-of select="@type"/>
                </xsl:otherwise>
              </xsl:choose>
            </td>
            <!--revisit all-->
            <!-- we only want a single row output for geographical objects, therefore lets only produce one table cell -->
            <xsl:choose>
              <!--geographic -->

              <xsl:when test="$details[@id=current()/@id] and @id=230">
                <xsl:for-each select="$details[@id=current()/@id]">
                  <xsl:sort select="."/>
                  <xsl:if test="position()=last()">
                    <td>
                      <xsl:choose>
                        <xsl:when test="$countMaps &gt; 1">
                          <a href="#"
                            onclick="window.open('loadmap/{parent::node()/id}', '', 'resizable=yes,width=500,height=400')"
                            >map (<xsl:value-of select="$countMaps"/> objects)</a>
                        </xsl:when>
                        <xsl:otherwise>
                          <a href="#"
                            onclick="window.open('loadmap/{parent::node()/id}', '', 'resizable=yes,width=500,height=400')"
                            >map</a>
                        </xsl:otherwise>
                      </xsl:choose>
                    </td>
                  </xsl:if>
                </xsl:for-each>
              </xsl:when>

              <!-- the rest -->
              <xsl:otherwise>
                <td>
                  <xsl:for-each select="$details[@id=current()/@id]">
                    <xsl:sort select="."/>
                    <xsl:choose>
                      <xsl:when
                        test="self::node()[@id!= 222 and @id!= 221 and @id!=177 and @id != 223 and @id != 231 and @id != 268 and @id !=256 and @id!=304 and @id != 224]">
                        <xsl:value-of select="."/>
                      </xsl:when>
                      <xsl:otherwise>
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
                    </xsl:choose>
                    <br/>
                  </xsl:for-each>
                </td>
              </xsl:otherwise>
            </xsl:choose>

          </tr>
        </xsl:if>
      </xsl:for-each>
      <!-- POINTER LISTING -->
      <xsl:variable name="pointer" select="pointer"/>
      <!--walk through the variable-->
      <xsl:for-each select="pointer">

        <!--act on the first in document order-->
        <xsl:if test="generate-id(.)=
              generate-id($pointer[@id=current()/@id][1])">
          <tr>
            <td class="reftype" width="150">
              <xsl:choose>
                <xsl:when test="@name !=''">
                  <xsl:value-of select="@name"/>
                </xsl:when>
                <xsl:otherwise>
                  <xsl:value-of select="@type"/>
                </xsl:otherwise>
              </xsl:choose>
            </td>
            <td>
              <!--revisit all-->
              <xsl:for-each select="$pointer[@id=current()/@id]">
                <xsl:choose>
                  <xsl:when test="self::node()[@id=158]">
                    <xsl:apply-templates select="." mode="creator"/>
                  </xsl:when>
                  <xsl:otherwise>
                    <xsl:value-of select="title"/>
                  </xsl:otherwise>
                </xsl:choose>
                <br/>
              </xsl:for-each>
            </td>
          </tr>
        </xsl:if>
      </xsl:for-each>
      <!-- RELATED LISTING -->
      <xsl:variable name="relation" select="related"/>
      <!--walk through the variable-->
      <xsl:for-each select="related">

        <!--act on the first in document order-->
        <xsl:if test="generate-id(.)=
        generate-id($relation[@type=current()/@type][1])">
          <tr>
            <td class="reftype" width="150">
              <xsl:value-of select="@type"/>
            </td>
            <td>
              <!--revisit all-->
              <xsl:for-each select="$relation[@type=current()/@type]">

                <xsl:value-of select="title"/>
                <br/>
              </xsl:for-each>
            </td>
          </tr>
        </xsl:if>
      </xsl:for-each>
      <xsl:if test="woot !=''">
        <tr>
          <td class="reftype"> WYSIWIG Text </td>
          <td>
            <xsl:call-template name="woot_content"/>
          </td>
        </tr>
      </xsl:if>
    </table>
    <!--/xsl:element-->

  </xsl:template>

  <!-- helper templates -->
  <xsl:template name="logo">
    <xsl:param name="id"/>
    <xsl:if test="self::node()[@id =$id]">
      <xsl:element name="a">
        <xsl:attribute name="href">
          <xsl:value-of select="self::node()[@id =$id]/file_fetch_url"/>
        </xsl:attribute>
        <xsl:element name="img">
          <xsl:attribute name="src">
            <xsl:value-of select="self::node()[@id =$id]/file_thumb_url"/>
          </xsl:attribute>
          <xsl:attribute name="border">0</xsl:attribute>
        </xsl:element>
      </xsl:element>
    </xsl:if>
  </xsl:template>
  <xsl:template name="file">
    <xsl:param name="id"/>
    <xsl:if test="self::node()[@id =$id]">
      <xsl:element name="a">
        <xsl:attribute name="href">
          <xsl:value-of select="self::node()[@id =$id]/file_fetch_url"/>
        </xsl:attribute>
        <xsl:value-of select="file_orig_name"/>
      </xsl:element> [<xsl:value-of select="file_size"/>] </xsl:if>
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
      <xsl:value-of select="$value"/>
    </xsl:element>
  </xsl:template>
  <xsl:template name="woot_content">
    <xsl:if test="woot">
      <xsl:copy-of select="woot"/>
    </xsl:if>
  </xsl:template>


</xsl:stylesheet>
