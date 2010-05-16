<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

    <xsl:param name="pub_id">
        <xsl:value-of select="$pub_id"/>
    </xsl:param>

    <xsl:variable name="imageFunction">&amp;op=resize&amp;newWidth=200</xsl:variable>
    <xsl:variable name="baseURL">http://129.78.138.66:8080</xsl:variable>
    
    <xsl:template match="/export">
        <html>
            <head>
            <script type="text/javascript">
                    function setSelectedPID(pid) {
                        parent.toc.document.getElementById('resource_identifier').value = pid;
                    }
                </script>
                <link rel="stylesheet" type="text/css"
                    href="http://acl.arts.usyd.edu.au/generic/css/data.css"/>
                <title>JHSB Images</title>
            </head>
            <body>
                <table cellpadding="10">

                    <xsl:apply-templates select="references/reference"/>

                </table>
            </body>
        </html>
    </xsl:template>

    <xsl:template match="reference">

        <xsl:element name="tr">
            <xsl:attribute name="valign">top</xsl:attribute>
            <xsl:if test="position() mod 2 = 1">
                <xsl:attribute name="class">odd</xsl:attribute>

            </xsl:if>

            <td>
                <p><xsl:value-of select="title"/></p>
                <p>[<a href="http://heuristscholar.org/resource/{id}" target="_blank"><xsl:value-of select="id"/></a>] [<a href="http://heuristscholar.org/heurist/edit.php?bkmk_id={id}" target="_blank">edit</a>]</p>
            </td>
            <td>
                <p><xsl:value-of select="added_by"/></p>
            </td>
            <td>
                
                
             
                
                <xsl:variable name="doubleEscapedURL"><xsl:value-of select="substring-before(url,'%')"/>%25<xsl:value-of select="substring-after(url,'%')"/></xsl:variable>
								
								<xsl:element name="img">
                            <xsl:attribute name="src"><xsl:value-of select="$baseURL"
                            />/imagemanip/ImageManipulation?url=<xsl:value-of select="$doubleEscapedURL"/><xsl:value-of
                                select="$imageFunction"/></xsl:attribute>
                            <xsl:attribute name="border">0</xsl:attribute>
                        <xsl:attribute name="align">left</xsl:attribute>
                        <xsl:attribute name="hspace">10</xsl:attribute>
                        <xsl:attribute name="onClick">setSelectedPID('http://sylvester.acl.arts.usyd.edu.au/cocoon/jhsb/heurist_item/<xsl:value-of select="id"/>'); return false;</xsl:attribute>
                            
                            
                        <!-- /xsl:element -->
                    </xsl:element>
									              
              
                       <br>
                        <xsl:value-of select="notes"/>
                    </br>
                </td>
                
                
                
                
                
                
         
        </xsl:element>


    </xsl:template>


</xsl:stylesheet>
