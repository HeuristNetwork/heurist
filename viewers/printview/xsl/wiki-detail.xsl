<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    
    
    <!-- detail output template -->
    <xsl:template match="record[id=$arg]">
        
        <tr>
            <td class="reftype">
                <img>
                    <xsl:attribute name="src">http://heuristscholar.org/reftype/<xsl:value-of
                        select="type/@id"/>.gif</xsl:attribute>
                </img>
            </td>
            <td style="font-weight: bold;">
                <a style="float: right;" target="_new"
                    href="http://heuristscholar.org/heurist/bibedit.php?bib_id={id}">
                    <img style="border: none;"
                        src="http://heuristscholar.org/heurist/edit_pencil_16x16.gif"/>
                </a>
                <xsl:value-of select="title"/>
            </td>
        </tr>
        <tr>
            <td class="reftype">
                <nobr>Reference type</nobr>
            </td>
            <td>
                <xsl:value-of select="type"/>
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
                        <xsl:with-param name="name"><xsl:value-of select="detail[@id=203]/@name"/></xsl:with-param>
                        <xsl:with-param name="type"><xsl:value-of select="detail[@id=203]/@type"/></xsl:with-param>
                    </xsl:call-template>
                </td>
                <td>
                    <xsl:for-each select="detail[@id=203]">
                        <xsl:value-of select="."/><br/>
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
                            <xsl:when test="string-length(@name)"><xsl:value-of select="@name"/></xsl:when>
                            <xsl:otherwise> <xsl:value-of select="@type"/></xsl:otherwise>
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
                            <a href="{file/url}">
                                <xsl:value-of select="file/origName"/>
                            </a>
                        </xsl:when>
                        <xsl:otherwise> <xsl:value-of select="text()"/></xsl:otherwise>
                    </xsl:choose>
                </td>
            </tr>
            
        </xsl:for-each>
        <!-- director-->
        <tr>
            <td class="reftype">
                <xsl:call-template name="title_grouped">
                    <xsl:with-param name="name"><xsl:value-of select="detail[@id=315]/record/@name"/></xsl:with-param>
                    <xsl:with-param name="type"><xsl:value-of select="detail[@id=315]/record/@type"/></xsl:with-param>
                </xsl:call-template>
                
            </td>
            <td>
                <xsl:apply-templates select="detail[@id=315]/record"></xsl:apply-templates>
                
            </td>
        </tr>
        <!-- staff-->
        <tr>
            <td class="reftype">
                <xsl:call-template name="title_grouped">
                    <xsl:with-param name="name"><xsl:value-of select="detail[@id=316]/record/@name"/></xsl:with-param>
                    <xsl:with-param name="type"><xsl:value-of select="detail[@id=316]/record/@type"/></xsl:with-param>
                </xsl:call-template>
                
            </td>
            <td>
                <xsl:apply-templates select="detail[@id=316]/record"></xsl:apply-templates>
                
            </td>
        </tr>
        <!-- research projects-->
        <tr>
            <td class="reftype">
                <xsl:call-template name="title_grouped">
                    <xsl:with-param name="name"><xsl:value-of select="detail[@id=264]/record/@name"/></xsl:with-param>
                    <xsl:with-param name="type"><xsl:value-of select="detail[@id=264]/record/@type"/></xsl:with-param>
                </xsl:call-template>
                
            </td>
            <td>
                <xsl:apply-templates select="detail[@id=264]/record"></xsl:apply-templates>
                
            </td>
        </tr>
        
        <xsl:if test="notes != ''">
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
                        <a href="{file/url}">
                            <img src="{file/thumbURL}" border="0"/>
                        </a> &#160;&#160; </xsl:for-each>
                </td>
            </tr>
        </xsl:if>
        
    </xsl:template>
    <xsl:template match="detail/record">
        <xsl:call-template name="content_group"/>
    </xsl:template>
    
    
    <xsl:template name="content_group" >
        
        
        <xsl:choose>
            <xsl:when test="url !='' ">
                <xsl:element name="a">
                    <xsl:attribute name="href"><xsl:value-of select="url"/></xsl:attribute>
                    <xsl:attribute name="target">_blank</xsl:attribute>
                    <xsl:value-of select="title"/>
                </xsl:element>
                
            </xsl:when>
            <xsl:otherwise><xsl:value-of select="title"/></xsl:otherwise>
        </xsl:choose>
        <br></br>
        
        
    </xsl:template>
    
    <xsl:template name="title_grouped">
        <xsl:param name="name"></xsl:param>
        <xsl:param name="type"></xsl:param>
        <nobr>
            <xsl:choose>
                <xsl:when test="string-length($name)"><xsl:value-of select="$name"/></xsl:when>
                <xsl:otherwise> <xsl:value-of select="$type"/></xsl:otherwise>
            </xsl:choose>
        </nobr>             
    </xsl:template>
    
    
</xsl:stylesheet>
