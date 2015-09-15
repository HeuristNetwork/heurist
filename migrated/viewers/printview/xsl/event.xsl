<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    <xsl:template name="event" match="record[type/@id=64]">
        <xsl:param name="style"/>
        <xsl:param name="year"/>
        <xsl:param name="month"/>
        <xsl:param name="day"/>

		<xsl:choose>
			<!-- skip if before today -->
			<xsl:when test="detail[@id=177]/year &lt; $year"/>
			<xsl:when test="detail[@id=177]/year = $year  and  detail[@id=177]/month &lt; $month"/>
			<xsl:when test="detail[@id=177]/year = $year  and  detail[@id=177]/month = $month  and  detail[@id=177]/day &lt; $day"/>
			<xsl:otherwise>

		<tr><td>

		<a name="{id}"></a>

        <xsl:choose>
            <xsl:when test="$style = 'compressed'"/>
            <xsl:otherwise>
				<xsl:if test="detail[@id=222]"><!-- logo image -->
					<div>
						<xsl:for-each select="detail[@id=222]">
							<img src="{file/thumbURL}&amp;h=50"/>
						</xsl:for-each>
					</div>
				</xsl:if>
				<xsl:if test="detail[@id=223]"><!-- thumbnail -->
					<div style="float: right;">
						<xsl:for-each select="detail[@id=223]">
							<img src="{file/thumbURL}"/>
						</xsl:for-each>
					</div>
				</xsl:if>
            	
            </xsl:otherwise>
        </xsl:choose>

		<div>
			<xsl:value-of select="detail[@id=177]/text()"/>,<!-- Start Date -->
			<xsl:value-of select="detail[@id=233]"/><!-- Start time -->
			<xsl:if test="detail[@id=234]"> - 
				<xsl:value-of select="detail[@id=234]"/><!-- End time -->
			</xsl:if>
			<br/>
			<a target="_new" href="rectype_renderer/{id}" style="text-decoration:none;"><span style="font-weight: bold;"><xsl:value-of select="detail[@id=160]"/></span></a>
			<!-- Title -->
		</div>

        <xsl:choose>
            <xsl:when test="$style = 'compressed'"/>
            <xsl:otherwise>
                <xsl:if test="detail[@id=249]/record">
                    <div>Speaker(s):
                    
						<xsl:for-each select="detail[@id=249]/record">
							<!-- Person -->
							<xsl:value-of select="title"/>
							<xsl:if test="position() != last()">, </xsl:if>
						</xsl:for-each>
                    </div>
                </xsl:if>
                
                <xsl:if test="detail[@id=172]">
                    <div>
						<!-- Place published -->
						<xsl:value-of select="detail[@id=172]"/>
                    </div>
                    
                </xsl:if>
                
                <xsl:if test="detail[@id=309]">
                    <div>
						<!-- Contact information -->
						<xsl:value-of select="detail[@id=309]"/>
                    </div>
                </xsl:if>
                
                <xsl:if test="url != ''">
                    <div>
						<!-- URL -->
						<a href="{url}"><xsl:value-of select="url"/></a>
                    </div>
                </xsl:if>
                
                 
                <xsl:if test="detail[@id=303]">
                    <div>
						<!-- Detail for web -->
						<xsl:value-of select="detail[@id=303]"/>
                    </div>
                </xsl:if>
            	
            	<xsl:for-each select="detail[@id = 221]">
            		<div>
            			<a href="{file/url}" target="_blank" style="font-size: 90%;">download: <xsl:value-of select="file/origName"/></a>
            		</div>
            	</xsl:for-each>
                
            </xsl:otherwise>
            
            
            
        </xsl:choose>

        <div>
			<xsl:choose>
				<xsl:when test="$style = 'compressed'"/>
				<xsl:otherwise>
					<xsl:value-of select="detail[@id=308]"/>
					<br/>
					<!-- Booking details -->
				</xsl:otherwise>
			</xsl:choose><br/>
        </div>
       
			
	
		</td></tr>

			</xsl:otherwise>
		</xsl:choose>

    </xsl:template>
</xsl:stylesheet>
