<?xml version="1.0" encoding="UTF-8"?><!-- DWXMLSource="260.xml" -->
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

<xsl:param name="arg"/>


<xsl:template match="/">
	<html>
		<head>
			
			<style type="text/css">
				body { font-size: 80%; }
				td { vertical-align: top; }
			</style>
		</head>
		<body>
                        <xsl:attribute name="pub_id"><xsl:value-of select="/export/@pub_id"/></xsl:attribute>
			<table>
				
						<xsl:apply-templates select="export/references/reference"/>
					
			</table>
		</body>
	</html>
</xsl:template>



<xsl:template name="person" match="reference[reftype/@id=55]">
        <tr>
		<td align="left" valign="top">
		
					
				
			<xsl:if test="detail[@id=223]"><!-- thumbnail -->
					
						<xsl:for-each select="detail[@id=223]">
							<img src="{file_thumb_url}"/>
						</xsl:for-each>
					
				</xsl:if>
			</td>
            <td align="left" valign="top">
				<b>Name:&#160;</b>
                
            	<b><xsl:value-of select="title"/></b>
               
				<br/>
				<xsl:if test="detail[@id=255]">
				<b>Position:&#160;</b>
					<xsl:for-each select="detail[@id=255]"><!-- role -->
						<xsl:value-of select="text()"/>
						<xsl:if test="position() != last()">,&#160; </xsl:if>
					</xsl:for-each>
				</xsl:if>
				<xsl:if test="detail[@id=265]">
					<br/>
					<b>Job Description:&#160;</b>
					<xsl:value-of select="detail[@id=265]"/>
				</xsl:if>
				<xsl:if test="detail[@id=191]">
					<br/>
					<b>Background:&#160;</b>
					<xsl:value-of select="detail[@id=191]"/>
				</xsl:if>
				
			
            </td>
			
        </tr>
		<tr><td height="1"></td></tr>
    </xsl:template>

</xsl:stylesheet>
