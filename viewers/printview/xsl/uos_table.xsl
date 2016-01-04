<?xml version="1.0" encoding="UTF-8"?>

<!-- stylesheet created by Steven Hayes to render unit of study timetable from Heurist query -->

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

    <xsl:template match="/">
        <html>
            <head/>
            <body>
                <table width="100%" cellpadding="20">
                    <xsl:apply-templates select="hml/records"/>
                </table>
            </body>
        </html>
    </xsl:template>

    <xsl:template match="records">

        <tr>
            <td colspan="3">
                <h1>Semester 1</h1>
            </td>
        </tr>

        <tr>
            <td colspan="3">
                <h2>Junior Units of Study</h2>
            </td>
        </tr>
        <xsl:apply-templates select="record" mode="sem1-junior"/>

        <tr>
            <td colspan="3">
                <h2>Senior Units of Study</h2>
            </td>
        </tr>
        <xsl:apply-templates select="record" mode="sem1-senior"/>

        <tr>
            <td colspan="3">
                <h2>Advanced Senior Units of Study</h2>
            </td>
        </tr>
        <xsl:apply-templates select="record" mode="sem1-advanced"/>
        
        <tr>
            <td colspan="3">
                <h2>Honours Units of Study</h2>
            </td>
        </tr>
        <xsl:apply-templates select="record" mode="sem1-honours"/>

        <tr>
            <td colspan="3">
                <h1>Semester 2</h1>
            </td>
        </tr>

        <tr>
            <td colspan="3">
                <h2>Junior Units of Study</h2>
            </td>
        </tr>
        <xsl:apply-templates select="record" mode="sem2-junior"/>

        <tr>
            <td colspan="3">
                <h2>Senior Units of Study</h2>
            </td>
        </tr>
        <xsl:apply-templates select="record" mode="sem2-senior"/>
        
        <tr>
            <td colspan="3">
                <h2>Advanced Senior Units of Study</h2>
            </td>
        </tr>
        <xsl:apply-templates select="record" mode="sem2-advanced"/>

        <tr>
            <td colspan="3">
                <h2>Honours Units of Study</h2>
            </td>
        </tr>
        <xsl:apply-templates select="record" mode="sem2-honours"/>


    </xsl:template>


    <xsl:template match="record" mode="sem1-junior">
        <xsl:choose>
            <xsl:when
                test="detail[@id=278] = 'Junior / First year' and (detail[@id=257] = 'Semester 1' or detail[@id=257] = 'Full year')">
                <xsl:call-template name="uos-item"/>
            </xsl:when>
        </xsl:choose>
    </xsl:template>
    
    <xsl:template match="record" mode="sem1-senior">
        <xsl:choose>
            <xsl:when
                test="detail[@id=278] = 'Senior / years 2 - 3' and (detail[@id=257] = 'Semester 1' or detail[@id=257] = 'Full year')">
                <xsl:call-template name="uos-item"/>
            </xsl:when>
        </xsl:choose>
    </xsl:template>
    
    <xsl:template match="record" mode="sem1-advanced">
        <xsl:choose>
            <xsl:when
                test="detail[@id=278] = 'Advanced' and (detail[@id=257] = 'Semester 1' or detail[@id=257] = 'Full year')">
                <xsl:call-template name="uos-item"/>
            </xsl:when>
        </xsl:choose>
    </xsl:template>

    <xsl:template match="record" mode="sem1-honours">
        <xsl:choose>
            <xsl:when
                test="detail[@id=278] = 'Honours' and (detail[@id=257] = 'Semester 1' or detail[@id=257] = 'Full year')">
                <xsl:call-template name="uos-item"/>
            </xsl:when>
        </xsl:choose>
    </xsl:template>
    

    <xsl:template match="record" mode="sem2-junior">
        <xsl:choose>
            <xsl:when
                test="detail[@id=278] = 'Junior / First year' and (detail[@id=257] = 'Semester 2' or detail[@id=257] = 'Full year')">

                <xsl:call-template name="uos-item"/>
            </xsl:when>
        </xsl:choose>
    </xsl:template>
    
    <xsl:template match="record" mode="sem2-senior">
        <xsl:choose>
            <xsl:when
                test="detail[@id=278] = 'Senior / years 2 - 3' and (detail[@id=257] = 'Semester 2' or detail[@id=257] = 'Full year')">
                
                <xsl:call-template name="uos-item"/>
            </xsl:when>
        </xsl:choose>
    </xsl:template>
    
    <xsl:template match="record" mode="sem2-advanced">
        <xsl:choose>
            <xsl:when
                test="detail[@id=278] = 'Advanced' and (detail[@id=257] = 'Semester 2' or detail[@id=257] = 'Full year')">
                
                <xsl:call-template name="uos-item"/>
            </xsl:when>
        </xsl:choose>
    </xsl:template>
    
    <xsl:template match="record" mode="sem2-honours">
        <xsl:choose>
            <xsl:when
                test="detail[@id=278] = 'Honours' and (detail[@id=257] = 'Semester 2' or detail[@id=257] = 'Full year')">
                
                <xsl:call-template name="uos-item"/>
            </xsl:when>
        </xsl:choose>
    </xsl:template>

    <xsl:template name="uos-item">
        <tr>
            <td>
                <xsl:value-of select="detail[@id=269]"/>
            </td>
            <td>
                <xsl:value-of select="detail[@id=160]"/>
            </td>
            <td>
                <xsl:value-of select="detail[@id=305]"/>
                <br/>
                <xsl:value-of select="detail[@id=181]"/>
            </td>
        </tr>
    </xsl:template>

</xsl:stylesheet>
