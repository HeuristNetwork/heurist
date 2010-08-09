<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

    <xsl:param name="hBase"/>
    <xsl:param name="arg"/>

    <xsl:template match="/">

        <html>
            <head>

                <style type="text/css"> body {font-family:Verdana,Helvetica,Arial,sans-serif;
                    font-size:12px; background: #EAF3D9;} td { vertical-align: top; } a:hover {
                    text-decoration:none; } a { color:#0367AD; text-decoration:underline; } .reftype
                    { color: #999999; padding-right: 10px; } </style>
            </head>
            <body>
                <xsl:attribute name="pub_id">
                    <xsl:value-of select="/export/@pub_id"/>
                </xsl:attribute>
                <table border="0">

                    <!--<xsl:apply-templates select="export/references/reference"/>-->
                    <xsl:choose>
                        <xsl:when test="number($arg) > 0">

                            <xsl:apply-templates select="export/references/reference[id=$arg]"/>
                        </xsl:when>
                        <xsl:when test="string(number($arg)) = 'NaN'">
                            <xsl:apply-templates select="export/references/reference">
                                <xsl:with-param name="style" select="$arg"/>
                            </xsl:apply-templates>
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:apply-templates select="export/references/reference"/>
                        </xsl:otherwise>
                    </xsl:choose>

                </table>
            </body>
        </html>
    </xsl:template>
    <xsl:template name="print_title">
        <xsl:choose>
            <xsl:when test="detail[@id='179']">
                <xsl:choose>
                    <!-- output acronym if its not already part of the title -->
                    <xsl:when test="contains(detail[@id='160'], detail[@id='179'])">
                        <xsl:value-of select="detail[@id='160']"/>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:value-of select="detail[@id='160']"/> (<xsl:value-of
                            select="detail[@id = '179']"/>) </xsl:otherwise>
                </xsl:choose>


            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="detail[@id = '160']"/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    <xsl:template name="organisation">
        <xsl:param name="parent-org"/>
        <xsl:param name="location"/>
        <xsl:param name="country"/>

        <xsl:if test="$parent-org">
            <xsl:value-of select="$parent-org"/>,&#160; </xsl:if>
        <xsl:if test="$location">
            <xsl:value-of select="$location"/>,&#160; </xsl:if>
        <xsl:if test="$country">
            <xsl:value-of select="$country"/>,&#160; </xsl:if>
    </xsl:template>

    <xsl:template match="reference">
        <tr>
            <td>

                <xsl:if test="detail[@id='160']">
                    <!--logo image -->
                    <xsl:if test="detail[@id=222]">
                        <div style="float: right;">
                            <xsl:for-each select="detail[@id=222]">
                                <img src="{file_thumb_url}"/>
                            </xsl:for-each>
                        </div>
                    </xsl:if>
                    <b>
                        <xsl:choose>
                            <xsl:when test="url != ''">
                                <xsl:element name="a">
                                    <xsl:attribute name="href">
                                        <xsl:value-of select="url"/>
                                    </xsl:attribute>
                                    <xsl:attribute name="target">_blank</xsl:attribute>
                                    <xsl:call-template name="print_title"/>
                                </xsl:element>
                            </xsl:when>
                            <xsl:otherwise>
                                <xsl:call-template name="print_title"/>
                            </xsl:otherwise>
                        </xsl:choose>
                    </b>
                    <br/>
                    <a style=" font-family:Verdana,Helvetica,Arial,sans-serif; font-size:10px;"
                        target="_new" href="wiki/{id}">[view details]</a>&#160;<a
                        style=" font-family:Verdana,Helvetica,Arial,sans-serif; font-size:10px;"
                        target="_new" href="{$hBase}records/editrec/edit.html?bib_id={id}">
                        [edit] </a>
                    <br/>
                    <xsl:if
                        test="pointer[@id=244]/detail[@id=160] or detail[@id='181'] or detail[@id=277]">
                        <xsl:call-template name="organisation">
                            <xsl:with-param name="parent-org">
                                <xsl:value-of select="pointer[@id=244]/detail[@id=160]"/>
                            </xsl:with-param>
                            <xsl:with-param name="location">
                                <xsl:value-of select="detail[@id='181']"/>
                            </xsl:with-param>
                            <xsl:with-param name="country">
                                <xsl:value-of select="detail[@id=277]"/>
                            </xsl:with-param>
                        </xsl:call-template>
                        <br/>
                    </xsl:if>
                    <xsl:value-of select="detail[@id='303']"/>
                </xsl:if>

            </td>
        </tr>
        <tr>
            <td>&#160; </td>
        </tr>
    </xsl:template>




    <!-- detail output template -->
    <xsl:template match="reference[id=$arg]">

        <tr>
            <td class="reftype">
                <img>
                    <xsl:attribute name="src"><xsl:value-of select="$hBase"/>common/images/reftype-icons/<xsl:value-of
                            select="reftype/@id"/>.png</xsl:attribute>
                </img>
            </td>
            <td style="font-weight: bold;">
                <a style="float: right;" target="_new"
                    href="{$hBase}records/editrec/edit.html?bib_id={id}">
                    <img style="border: none;"
                        src="{$hBase}common/images/edit_pencil_16x16.gif"/>
                </a>
                <xsl:value-of select="title"/>
            </td>
        </tr>
        <tr>
            <td class="reftype">
                <nobr>Reference type</nobr>
            </td>
            <td>
                <xsl:value-of select="reftype"/>
            </td>
        </tr>
        <xsl:if test="url != ''">
            <tr>
                <td class="reftype">URL</td>
                <td>
                    <a href="{url}">
                        <xsl:choose>
                            <xsl:when test="string-length(url) &gt; 50">
                                <xsl:value-of select="substring(url, 0, 50)"/> ... </xsl:when>
                            <xsl:otherwise>
                                <xsl:value-of select="url"/>
                            </xsl:otherwise>
                        </xsl:choose>
                    </a>
                </td>
            </tr>
        </xsl:if>
        <xsl:if test="detail[@id=203]">
            <!--organisation type -->
            <tr>
                <td class="reftype">
                    <xsl:call-template name="title_grouped">
                        <xsl:with-param name="name">
                            <xsl:value-of select="detail[@id=203]/@name"/>
                        </xsl:with-param>
                        <xsl:with-param name="type">
                            <xsl:value-of select="detail[@id=203]/@type"/>
                        </xsl:with-param>
                    </xsl:call-template>
                </td>
                <td>
                    <xsl:for-each select="detail[@id=203]">
                        <xsl:value-of select="."/>
                        <br/>
                    </xsl:for-each>
                </td>
            </tr>
        </xsl:if>
        <!-- list reftypes and data-->
        <xsl:for-each select="detail[@id!=222 and @id!=223 and @id!=224 and @id!=203]">

            <tr>
                <td class="reftype">
                    <nobr>
                        <xsl:choose>
                            <xsl:when test="string-length(@name)">
                                <xsl:value-of select="@name"/>
                            </xsl:when>
                            <xsl:otherwise>
                                <xsl:value-of select="@type"/>
                            </xsl:otherwise>
                        </xsl:choose>
                    </nobr>
                </td>
                <td>
                    <xsl:choose>
                        <!-- 268 = Contact details URL,  256 = Web links -->
                        <xsl:when test="@id=268  or  @id=256  or  starts-with(text(), 'http')">
                            <a href="{text()}">
                                <xsl:choose>
                                    <xsl:when test="string-length() &gt; 50">
                                        <xsl:value-of select="substring(text(), 0, 50)"/> ... </xsl:when>
                                    <xsl:otherwise>
                                        <xsl:value-of select="text()"/>
                                    </xsl:otherwise>
                                </xsl:choose>
                            </a>
                        </xsl:when>
                        <!-- 221 = AssociatedFile,  231 = Associated File -->
                        <xsl:when test="@id=221  or  @id=231">
                            <a href="{file_fetch_url}">
                                <xsl:value-of select="file_orig_name"/>
                            </a>
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:value-of select="text()"/>
                        </xsl:otherwise>
                    </xsl:choose>
                </td>
            </tr>

        </xsl:for-each>
        <!-- director-->
        <tr>
            <td class="reftype">
                <xsl:call-template name="title_grouped">
                    <xsl:with-param name="name">
                        <xsl:value-of select="pointer[@id=315]/@name"/>
                    </xsl:with-param>
                    <xsl:with-param name="type">
                        <xsl:value-of select="pointer[@id=315]/@type"/>
                    </xsl:with-param>
                </xsl:call-template>

            </td>
            <td>
                <xsl:apply-templates select="pointer[@id=315]"/>

            </td>
        </tr>
        <!-- staff-->
        <tr>
            <td class="reftype">
                <xsl:call-template name="title_grouped">
                    <xsl:with-param name="name">
                        <xsl:value-of select="pointer[@id=316]/@name"/>
                    </xsl:with-param>
                    <xsl:with-param name="type">
                        <xsl:value-of select="pointer[@id=316]/@type"/>
                    </xsl:with-param>
                </xsl:call-template>

            </td>
            <td>
                <xsl:apply-templates select="pointer[@id=316]"/>

            </td>
        </tr>
        <!-- research projects-->
        <tr>
            <td class="reftype">
                <xsl:call-template name="title_grouped">
                    <xsl:with-param name="name">
                        <xsl:value-of select="pointer[@id=264]/@name"/>
                    </xsl:with-param>
                    <xsl:with-param name="type">
                        <xsl:value-of select="pointer[@id=264]/@type"/>
                    </xsl:with-param>
                </xsl:call-template>

            </td>
            <td>
                <xsl:apply-templates select="pointer[@id=264]"/>

            </td>
        </tr>

        <xsl:if test="notes != ' ' ">
            <tr>
                <td class="reftype">Notes</td>
                <td>
                    <xsl:value-of select="notes"/>
                </td>
            </tr>
        </xsl:if>

        <xsl:if test="detail[@id=222 or @id=223 or @id=224]">
            <tr>
                <td class="reftype">Images</td>
                <td>
                    <!-- 222 = Logo image,  223 = Thumbnail,  224 = Images -->
                    <xsl:for-each select="detail[@id=222 or @id=223 or @id=224]">
                        <a href="{file_fetch_url}">
                            <img src="{file_thumb_url}" border="0"/>
                        </a> &#160;&#160; </xsl:for-each>
                </td>
            </tr>
        </xsl:if>

    </xsl:template>
    <xsl:template match="pointer">
        <xsl:call-template name="content_group"/>
    </xsl:template>


    <xsl:template name="content_group">


        <xsl:choose>
            <xsl:when test="url !='' ">
                <xsl:element name="a">
                    <xsl:attribute name="href">
                        <xsl:value-of select="url"/>
                    </xsl:attribute>
                    <xsl:attribute name="target">_blank</xsl:attribute>
                    <xsl:value-of select="title"/>
                </xsl:element>

            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="title"/>
            </xsl:otherwise>
        </xsl:choose>
        <br/>


    </xsl:template>

    <xsl:template name="title_grouped">
        <xsl:param name="name"/>
        <xsl:param name="type"/>
        <nobr>
            <xsl:choose>
                <xsl:when test="string-length($name)">
                    <xsl:value-of select="$name"/>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:value-of select="$type"/>
                </xsl:otherwise>
            </xsl:choose>
        </nobr>
    </xsl:template>

</xsl:stylesheet>
