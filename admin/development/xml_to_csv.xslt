<?xml version="1.0" encoding="ISO-8859-1"?>
<!-- Edited by XMLSpy® -->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:template match="/">
      <xsl:for-each select="persons/person">
        "<xsl:value-of select="url"/>"

        <!-- loop for names -->
        ,"
        <xsl:for-each select="names/name">
            <xsl:value-of select="surname"/>,&nbsp;
            <xsl:value-of select="givenNames"/>
            <xsl:if test="position() != last()"> | </xsl:if>
        </xsl:for-each>
        "

        ,"<xsl:value-of select="gender"/>"
        ,"<xsl:value-of select="birth/date"/>"
        ,"<xsl:value-of select="birth/state"/>"
        ,"<xsl:value-of select="birth/town"/>"
        ,"<xsl:value-of select="birth/country"/>"
        ,"<xsl:value-of select="death/date"/>"
        ,"<xsl:value-of select="description"/>"

        <!-- loop for ethnicities -->
        ,"
        <xsl:for-each select="ethnicities">
            <xsl:value-of select="ethnicity"/>
        <xsl:if test="position() != last()"> | </xsl:if>
        </xsl:for-each>
        "

        <!-- loop for religions -->
        ,"
        <xsl:for-each select="religions">
            <xsl:value-of select="religion"/>"
            <xsl:if test="position() != last()"> | </xsl:if>
         </xsl:for-each>
         "

         <!-- loop for occupations -->
        ,"
         <xsl:for-each select="occupations">
            <xsl:value-of select="occupation/name"/>",&nbsp;
            <xsl:value-of select="occupation/start"/>",&nbsp;
            <xsl:value-of select="occupation/end"/>",&nbsp;
            <xsl:value-of select="occupation/state"/>",&nbsp;
            <xsl:value-of select="occupation/country"/>"&nbsp;
            <xsl:if test="position() != last()"> | </xsl:if>
          </xsl:for-each>
          "

        <!-- loop for texts -->
        ,"
        <xsl:for-each select="texts">
            <xsl:value-of select="text/html"/>"
            <xsl:if test="position() != last()"> | </xsl:if>
        </xsl:for-each>
        "

        <!-- loop for bibliographic entries -->
        ,"
        <xsl:for-each select="texts/text/bibliography/entry">
            <xsl:value-of select="."/>
            <xsl:if test="position() != last()"> | </xsl:if>
        </xsl:for-each>
        "

      </xsl:for-each>
</xsl:template>
</xsl:stylesheet>

