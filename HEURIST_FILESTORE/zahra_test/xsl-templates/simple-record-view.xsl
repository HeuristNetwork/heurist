<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
	xmlns:exsl="http://exslt.org/common"
	extension-element-prefixes="exsl" version="1.0">

	<xsl:param name="standalone"/>


	<xsl:template match="/">

		
		<!-- not now needed xsl:variable name="selectedIDs">
			<ids>
			<xsl:call-template name="tokenize">
				<xsl:with-param name="delimiter">,</xsl:with-param>
				<xsl:with-param name="string" select="/hml/selectedIDs"/>
			</xsl:call-template>
			</ids>
		</xsl:variable -->
	
		<hr></hr>
			
		<xsl:apply-templates select="hml/records/record[@selected='yes']"/>
			
			
	</xsl:template>

	<xsl:template match="record">
		
		<!-- the following chunk of code is no longer required - not that it realy worked - now we have the selected
			attribute on each record -->
		<!-- xsl:param name="selectedIDs"/>
		<xsl:variable name="record" select="."/>
		<xsl:variable name="compareIDString"><xsl:value-of select="$record/id"/><xsl:text> </xsl:text></xsl:variable>
		
		
				<xsl:choose>
					<xsl:when test="contains($selectedIDs,$compareIDString)">
						<xsl:call-template name="showRecord">
							<xsl:with-param name="record" select="$record"/>
						</xsl:call-template>
					</xsl:when>
					
					
				</xsl:choose>		
	</xsl:template>
	
	<xsl:template name="showRecord" -->
		
		

		<div>
			<p><xsl:value-of select="id"/> - <xsl:value-of select="type"/></p>
			<p><xsl:value-of select="title"/></p>
			<table width="80%" align="center">
				<xsl:apply-templates select="detail"/>
			</table>
		</div>

	</xsl:template>
	
	<xsl:template match="detail">
		
		<xsl:choose>
			<xsl:when test="./file">
				<tr>
					<td>
						file detail
					</td>
				</tr>
			</xsl:when>
			<xsl:otherwise>
				<tr>
					<td cellpadding="5" bgcolor="c0c0c0"><b><xsl:value-of select="./@name"/></b></td>
					<td cellpadding="5" bgcolor="c0c0c0"><b><xsl:value-of select="./@conceptID"/></b></td>
					<td cellpadding="5"><xsl:value-of select="."/></td>
				</tr>
			</xsl:otherwise>
		</xsl:choose>
		
		
	</xsl:template>

	<!-- not now needed  xsl:template name="tokenize">
		<xsl:param name="string" />
		<xsl:param name="delimiter"/>
		<xsl:choose>
			<xsl:when test="$delimiter and contains($string, $delimiter)">
				<token><xsl:value-of select="substring-before($string, $delimiter)" /></token>
				<xsl:text> </xsl:text>
				<xsl:call-template name="tokenize">
					<xsl:with-param name="string" 
						select="substring-after($string, $delimiter)" />
					<xsl:with-param name="delimiter" select="$delimiter" />
				</xsl:call-template>
			</xsl:when>
			<xsl:otherwise>
				<token><xsl:value-of select="$string" /></token>
				<xsl:text> </xsl:text>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template -->
	

</xsl:stylesheet>
