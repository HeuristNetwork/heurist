<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    <!-- This template converts months numbers to their string equivalent -->
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
</xsl:stylesheet>
