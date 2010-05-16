<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    <!-- CREATOR -->
    <xsl:template name="creator" match="pointer" mode="creator">
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
</xsl:stylesheet>
