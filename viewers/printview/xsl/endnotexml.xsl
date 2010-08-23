
<xsl:stylesheet  version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    
    <xsl:output method="xml"/>
    <!-- Go through the export XML and make a endnote xml record for every reference found in the xml.
           This document consists of 3 parts, the main loop, the reftype templates and helper fucntions.
           If a reference type is not recognized, it will use a default template with EndNote type 'Generic'.
           
           The first part goes through the list, calling the right template per reference.
           > insert more reftypes here and call their template (or add their template).
           
           The reftype templates describe how the translation takes place.
           > add and define translations per reftype (templates) here.
           
           The helper functions are used for mutual use between reftypes.
           > add reusable functions here .
        
        Author: Erik Baaij, Marco Springer.
        Version/Date: 12 Jan 2007.
    -->
    
    <xsl:template match="/">
        <!-- use the following bit of code to include the stylesheet to display it in Heurist publishing wizard
            otherwise it will be ommited-->
        <!-- begin including code -->
        <xsl:comment>
            <!-- name (desc.) that will appear in dropdown list -->
            [name]EndNote XML[/name]
            <!-- match the name of the stylesheet-->
            [output]endnotexml[/output]
        </xsl:comment>
        <!-- end including code -->
      
        <xsl:element name="xml">
        <!-- start of endnote xml document -->
        <records>

            <!-- main loop -->
            <xsl:for-each select="hml/records/record">
                <record>
                    <database name="SHSSERI_Bookmarks" path="">SHSSERI Bookmarks</database>
                    <source-app name="Heurist" version="Heurist">Heurist</source-app>
                    <rec-number>
                        <xsl:value-of select="id"/>
                    </rec-number>
                    <xsl:choose>
                        <xsl:when test="type='Book'">
                            <xsl:call-template name="Book"/>
                        </xsl:when>
                        <xsl:when test="type='Internet bookmark'">
                            <xsl:call-template name="Internet_bookmark"/>
                        </xsl:when>
                        <xsl:when test="type='Journal Article'">
                            <xsl:call-template name="Journal_Article"/>
                        </xsl:when>
                        <xsl:when test="type='Book chapter'">
                            <xsl:call-template name="Book_Section"/>
                        </xsl:when>
                        <xsl:when test="type='Conferene Proceedings'">
                            <xsl:call-template name="Conference_Proceeding"/>
                        </xsl:when>
                        <xsl:when test="type='Newspaper Article'">
                            <xsl:call-template name="Newspaper_Article"/>
                        </xsl:when>
                        <xsl:when test="type='Magazine Article'">
                            <xsl:call-template name="Magazine_Article"/>
                        </xsl:when>
                        <xsl:when test="type='Pers. Comm.'">
                            <xsl:call-template name="Personal_Communication"/>
                        </xsl:when>
                        <xsl:when test="type='Report'">
                            <xsl:call-template name="Report"/>
                        </xsl:when>
                        <xsl:when test="type='Thesis'">
                            <xsl:call-template name="Thesis"/>
                        </xsl:when>
                        <xsl:when test="type='Conference Paper'">
                            <xsl:call-template name="Conference_Paper"/>
                        </xsl:when>
                        <!-- add more reftypes here, write a template for each -->
                        <xsl:otherwise>
                            <xsl:call-template name="default"/>
                        </xsl:otherwise>
                    </xsl:choose>
                </record>
            </xsl:for-each>
        </records>
        </xsl:element>
        <!-- end of endnote xml document -->
    </xsl:template>

    <!-- REFTYPE TEMPLATES -->

    <!-- template for books -->
    <xsl:template name="Book">
        <xsl:element name="ref-type"><xsl:attribute name="name">Book</xsl:attribute>6</xsl:element>
        <xsl:call-template name="default_data"/>
    </xsl:template>

    <!-- template for internet bookmarks -->
    <xsl:template name="Internet_bookmark">
        <xsl:element name="ref-type"><xsl:attribute name="name">Electronic Source</xsl:attribute>12</xsl:element>
        <xsl:call-template name="default_data"/>
    </xsl:template>

    <!-- template for journal articles -->
    <xsl:template name="Journal_Article">
        <xsl:element name="ref-type"><xsl:attribute name="name">Journal Article</xsl:attribute>17</xsl:element>
        <xsl:call-template name="default_data"/>
    </xsl:template>

    <!-- template for book chapters -->
    <xsl:template name="Book_Section">
        <xsl:element name="ref-type"><xsl:attribute name="name">Book Chapter</xsl:attribute>5</xsl:element>
        <xsl:call-template name="default_data"/>
    </xsl:template>

    <!-- template for conference proceedings -->
    <xsl:template name="Conference_Proceeding">
        <xsl:element name="ref-type"><xsl:attribute name="name">Conference Proceeding</xsl:attribute>10</xsl:element>
        <xsl:call-template name="default_data"/>
    </xsl:template>

    <!-- template for newspaper articles -->
    <xsl:template name="Newspaper_Article">
        <xsl:element name="ref-type"><xsl:attribute name="name">Newspaper Article</xsl:attribute>23</xsl:element>
        <xsl:call-template name="default_data"/>
    </xsl:template>

    <!-- template for magazine articles -->
    <xsl:template name="Magazine_Article">
        <xsl:element name="ref-type"><xsl:attribute name="name">Magazine Article</xsl:attribute>19</xsl:element>
        <xsl:call-template name="default_data"/>
    </xsl:template>

    <!-- template for personal communications -->
    <xsl:template name="Personal_Communication">
        <xsl:element name="ref-type"><xsl:attribute name="name">Personal Communication</xsl:attribute>26</xsl:element>
        <xsl:call-template name="default_data"/>
    </xsl:template>

    <!-- template for reports -->
    <xsl:template name="Report">
        <xsl:element name="ref-type"><xsl:attribute name="name">Report</xsl:attribute>27</xsl:element>
        <xsl:call-template name="default_data"/>
    </xsl:template>

    <!-- template for theses -->
    <xsl:template name="Thesis">
        <xsl:element name="ref-type"><xsl:attribute name="name">Thesis</xsl:attribute>32</xsl:element>
        <xsl:call-template name="default_data"/>
    </xsl:template>

    <!-- template for conference papers -->
    <xsl:template name="Conference_Paper">
        <xsl:element name="ref-type"><xsl:attribute name="name">Conference Paper</xsl:attribute>47</xsl:element>
        <xsl:call-template name="default_data"/>
    </xsl:template>

    <!-- default template -->
    <xsl:template name="default">
        <!-- the ref-type tag gets an attribute called name with the Heurist name of the reference and value 13, which is EndNote type 'Generic' -->
        <xsl:element name="ref-type"><xsl:attribute name="name">
                <xsl:value-of select="type"/>
            </xsl:attribute>13</xsl:element>
        <xsl:call-template name="default_data"/>
    </xsl:template>

    <!-- HELPER TEMPLATES -->

    <!-- template for getting all the authors -->
    <xsl:template name="Author">
        <xsl:apply-templates select="detail[@id='158']"/>
    </xsl:template>

    <!-- helper function for author function, printing the author tag -->
    <xsl:template match="detail[@id='158']">
        <author>
            <style face="normal" font="default" size="100%"><xsl:value-of select="firstname"/>&#160;<xsl:value-of select="othernames"/>&#160;<xsl:value-of select="surname"/></style>
        </author>
    </xsl:template>

    <!-- this prints the default template, with all applicable details in the right tag -->
    <xsl:template name="default_data">
        <xsl:if test="detail[@id = '158']">
            <contributors>
                <authors>
                    <xsl:call-template name="Author"/>
                </authors>
            </contributors>
        </xsl:if>
        <xsl:if
            test="title[.!=''] or detail[@id = '174'] or detail[@id = '173'] or detail[@id = '160']">
            <titles>
                <xsl:if test="detail[@id = '160']">
                    <title>
                        <style face="normal" font="default" size="100%"><xsl:value-of select="detail[@id='160']"/></style>
                    </title>
                </xsl:if>
                <xsl:if test="title[.!='']">
                    <secondary-title>
                        <style face="normal" font="default" size="100%"><xsl:value-of select="title"/></style>
                    </secondary-title>
                </xsl:if>
                <xsl:if test="detail[@id = '174']">
                    <alt-title>
                        <style face="normal" font="default" size="100%"><xsl:value-of select="detail[@id='174']"/></style>
                    </alt-title>
                </xsl:if>
                <xsl:if test="detail[@id = '173']">
                    <short-title>
                        <style face="normal" font="default" size="100%"><xsl:value-of select="detail[@id='173']"/></style>
                    </short-title>
                </xsl:if>
            </titles>
        </xsl:if>
        <xsl:if test="detail[@id = '165'] or detail[@id = '164'] or detail[@id = '163']">
            <xsl:element name="pages">
                <xsl:attribute name="end">
                    <xsl:value-of select="detail[@id='165']"/>
                </xsl:attribute>
                <xsl:attribute name="start">
                    <xsl:value-of select="detail[@id='164']"/>
                </xsl:attribute>
                <style face="normal" font="default" size="100%"><xsl:value-of select="detail[@id='163']"/></style>
            </xsl:element>
        </xsl:if>
        <xsl:if test="detail[@id = '184']">
            <volume>
                <style face="normal" font="default" size="100%"><xsl:value-of select="detail[@id='184']"/></style>
            </volume>
        </xsl:if>
        <xsl:if test="detail[@id = '176']">
            <edition>
                <style face="normal" font="default" size="100%"><xsl:value-of select="detail[@id='176']"/></style>
            </edition>
        </xsl:if>
        <xsl:if test="detail[@id = '159']">
            <dates>
                <year>
                    <style face="normal" font="default" size="100%"><xsl:value-of select="detail[@id='159']"/></style>
                </year>
            </dates>
        </xsl:if>
        <xsl:if test="detail[@id = '172']">
            <pub-location>
                <style face="normal" font="default" size="100%"><xsl:value-of select="detail[@id='172']"/></style>
            </pub-location>
        </xsl:if>
        <xsl:if test="detail[@id = '171']">
            <publisher>
                <style face="normal" font="default" size="100%"><xsl:value-of select="detail[@id='171']"/></style>
            </publisher>
        </xsl:if>
        <xsl:if test="detail[@id = '187']">
            <isbn>
                <style face="normal" font="default" size="100%"><xsl:value-of select="detail[@id='187']"/></style>
            </isbn>
        </xsl:if>
        <xsl:if test="detail[@id = '191']">
            <abstract>
                <style face="normal" font="default" size="100%"><xsl:value-of select="detail[@id='191']"/></style>
            </abstract>
        </xsl:if>
        <xsl:if test="detail[@id = '201']">
            <notes>
                <style face="normal" font="default" size="100%"><xsl:value-of select="detail[@id='201']"/></style>
            </notes>
        </xsl:if>
        <xsl:if test="detail[@id = '193']">
            <language>
                <style face="normal" font="default" size="100%"><xsl:value-of select="detail[@id='193']"/></style>
            </language>
        </xsl:if>
        <xsl:if test="url[.!=''] or blog_url[.!=''] or import_log[.!='']">
            <urls>
                <xsl:if test="url[.!='']">
                    <web-urls>
                        <url>
                            <style face="normal" font="default" size="100%"><xsl:value-of select="url"/></style>
                        </url>
                    </web-urls>
                </xsl:if>
                <xsl:if test="import_log[.!='']">
                    <textd-urls>
                        <url>
                            <style face="normal" font="default" size="100%"><xsl:value-of select="import_log"/></style>
                        </url>
                    </textd-urls>
                </xsl:if>
                <xsl:if test="blog_url[.!='']">
                    <related-urls>
                        <url>
                            <style face="normal" font="default" size="100%"><xsl:value-of select="blog_url"/></style>
                        </url>
                    </related-urls>
                </xsl:if>
            </urls>
        </xsl:if>
        <xsl:if test="added[.!='']">
            <access-date>
                <style face="normal" font="default" size="100%"><xsl:value-of select="added"/></style>
            </access-date>
        </xsl:if>
        <xsl:if test="modified[.!='']">
            <modified-date>
                <style face="normal" font="default" size="100%"><xsl:value-of select="modified"/></style>
            </modified-date>
        </xsl:if>
    </xsl:template>

</xsl:stylesheet>
