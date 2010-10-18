<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
  <!--
 this style renders standard html
 author  Maria Shvedova
 last updated 10/09/2007 ms

 Modified by Maria to the DIU Bulletin format in November 2008

 Mofified further for events archive 12/12/2008 by Ian

  -->

 <xsl:include href="helpers/creator.xsl"/>

  <xsl:template match="/">
    <!-- use the following bit of code to include the stylesheet to display it in Heurist publishing wizard
      otherwise it will be ommited-->
    <!-- begin including code -->
    <xsl:comment>
      <!-- name (desc.) that will appear in dropdown list -->[name]DIU Events Archive[/name]
      <!-- match the name of the stylesheet-->[output]pub_diu-events-archive[/output]
    </xsl:comment>
    <!-- end including code -->

    <html>
      <head>

        <style type="text/css">
          body {font-family:Verdana,Helvetica,Arial,sans-serif; font-size:11px; }
          td { vertical-align: top; }
          .reftype {
          color: #999999;

          }
        </style>
      </head>

      <body>
        <xsl:attribute name="pub_id">
          <xsl:value-of select="/hml/query[@pub_id]"/>
        </xsl:attribute>
          <xsl:apply-templates select="/hml/records/record"></xsl:apply-templates>
      </body>

    </html>

  </xsl:template>
  <!-- main template -->
  <xsl:template match="/hml/records/record">

      <!-- HEADER  -->
			<!-- we don't need the edit pencil for a lsit liek this
              <a target="_new"
                href="http://heuristscholar.org/heurist/edit?bib_id={id}">
                <img style="border: none;"
                  src="/heurist/img/edit_pencil_16x16.gif"/>
              </a>
            -->

        <p style="margin-bottom: 5px; ">
            <!--output the author(s)-->
		    <xsl:for-each select="detail">
		        <xsl:if test="self::node()[@id= 249]"> <!--  249 = person reference -->
		              <!--revisit all-->
		                <xsl:value-of select="record/title"/>
		                <xsl:text> </xsl:text>
		        </xsl:if>
		        </xsl:for-each>
             <!--output the seminar title-->
	            <i><b>
	              <xsl:value-of select="title"/>
	            </b></i>
		</p>

        <p style="margin-bottom: 5px; ">

  		  <xsl:if test="url != ''">
               <xsl:text> Files in Sydney eScholarship repository: </xsl:text>
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
		</p>

       <p style="margin-bottom: 5px; ">
        <!-- DETAIL LISTING -->
        <!--put what is being grouped in a variable-->
        <xsl:variable name="details" select="detail"/>
        <!--walk through the variable-->
        <xsl:for-each select="detail">
          <xsl:if test="self::node()[@id= 303 or @id=191]">
              <!--revisit all-->
                      <xsl:value-of select="."/>
          </xsl:if>
        </xsl:for-each>
	    </p>

<!-- Remove Woot text which breaks up the list with ltos of inconsistently formatted text

        <xsl:if test="woot !=''">
       <tr>

         <td>
         </td>
         <td>
           <xsl:call-template name="woot_content"></xsl:call-template>
         </td>
       </tr>
        </xsl:if>
-->
    <!--/xsl:element-->

  <p style="margin-bottom: 5px; ">
  ----
  </p>

  </xsl:template>

 <!-- helper templates -->
  <xsl:template name="logo">
    <xsl:param name="id"></xsl:param>
    <xsl:if test="self::node()[@id =$id]">
      <xsl:element name="a">
        <xsl:attribute name="href"><xsl:value-of select="self::node()[@id =$id]/url"/></xsl:attribute>
        <xsl:element name="img">
          <xsl:attribute name="src"><xsl:value-of select="self::node()[@id =$id]/thumbUrl"/></xsl:attribute>
          <xsl:attribute name="border">0</xsl:attribute>
        </xsl:element>
      </xsl:element>
    </xsl:if>
  </xsl:template>
  <xsl:template name="file">
    <xsl:param name="id"></xsl:param>
    <xsl:if test="self::node()[@id =$id]">
      <xsl:element name="a">
        <xsl:attribute name="href"><xsl:value-of select="self::node()[@id =$id]/url"/></xsl:attribute>
        <xsl:value-of select="origName"/>
      </xsl:element>  [<xsl:value-of select="size"/>]
    </xsl:if>
  </xsl:template>
  <xsl:template name="start-date" match="detail[@id=177]">
    <xsl:if test="self::node()[@id =177]">
      <xsl:value-of select="self::node()[@id =177]/year"/>
    </xsl:if>
  </xsl:template>
  <xsl:template name="url">
    <xsl:param name="key"></xsl:param>
    <xsl:param name="value"></xsl:param>
    <xsl:element name="a">
      <xsl:attribute name="href"><xsl:value-of select="$key"/></xsl:attribute>
      <xsl:value-of select="$value"/>
    </xsl:element>
  </xsl:template>

  <!-- Woot not being used in this template as it carries ugly formatting
  <xsl:template name="woot_content">
    <xsl:if test="woot">
      <xsl:copy-of select="woot"/>
    </xsl:if>
  </xsl:template>
  -->

</xsl:stylesheet>
