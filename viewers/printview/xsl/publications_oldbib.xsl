<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
	<!-- old bibliography style output 
	maria shvedova, 24/05/2007 -->
	<!-- journal -->
	<xsl:template name="journal_volume" match="record[type/@id=28]">
		<tr>
			<td>
				
				
				<!-- title (from jounal reference) -->
				<xsl:value-of select="detail[@id=226]/record/detail[@id=160]"/>,&#xa0;
				<!-- volume, year  -->
				<xsl:value-of select="detail[@id=184]"/>-
				<xsl:value-of select="detail[@id=159]"/>
				
				
			</td>
		</tr>
	</xsl:template>
	<!-- journal article etc. -->
	<xsl:template name="publications" match="record[type/@id=3 or type/@id=10 ]">
		<tr>
			<td>
				<!-- creator -->
				<xsl:apply-templates select="detail[@id=158]/record" mode="creator"></xsl:apply-templates>
				<!-- year (from journal volume reference) -->
				<xsl:value-of select="detail[@id=225]/record/detail[@id=159]"/>,
				<!-- title -->
				'<xsl:value-of select="detail[@id=160]"/>',&#xa0;
				<!-- journal (from journal volume reference) -->
				<i><xsl:apply-templates select="detail[@id=225]/record/detail[@id=226]/record" /></i>,
				<!-- volume (from journal volume reference) -->
				<xsl:value-of select="detail[@id=225]/record/detail[@id=184]"/>:
				
				<xsl:if test="detail[@id=164] and detail[@id=165]">
				<!-- pages-->
				<xsl:value-of select="detail[@id=164]"/>-<xsl:value-of select="detail[@id=165]"/>
				</xsl:if>
				
			</td>
		</tr>
	</xsl:template>
	
	
	<xsl:template name="conf-paper" match="record[type/@id=31]">
		<tr>
			<td>
				<!-- creator -->
				<xsl:apply-templates select="detail[@id=158]/record" mode="creator"></xsl:apply-templates>
				<!-- year (from journal volume reference) -->
				<xsl:value-of select="detail[@id=238]/record/detail[@id=159]"/>,
				<!-- title -->
				'<xsl:value-of select="detail[@id=160]"/>',&#xa0;
				<!--from conference -->
				<i><xsl:value-of select="detail[@id=217]/record/detail[@id=160]"/></i>,
				<!--location -->
				<xsl:value-of select="detail[@id=217]/record/detail[@id=181]"/>
				
				<xsl:if test="detail[@id=164] and detail[@id=165]">
					<!-- pages-->
					,&#xa0;<xsl:value-of select="detail[@id=164]"/>-<xsl:value-of select="detail[@id=165]"/>
				</xsl:if>
				
			</td>
		</tr>
	</xsl:template>
	<!-- book etc. -->
	<xsl:template name="book" match="record[type/@id=5 ]">
		<tr>
			<td>
				<!-- creator -->
				<!--<xsl:apply-templates select="detail[@id=158]/record" />,&#xa0;-->
				<xsl:apply-templates select="detail[@id=158]/record" mode="creator"></xsl:apply-templates>
				
				<!-- year -->
				<xsl:value-of select="detail[@id=159]"/>,&#xa0;
				<!-- title -->
				<i><xsl:value-of select="detail[@id=160]"/></i>,&#xa0;
				<!--publisher, place, series -->
				<xsl:apply-templates select="detail[@id=228]/record" />
				
				
			</td>
		</tr>
	</xsl:template>
	<!-- book chapter -->
	
	<xsl:template name="book-chap" match="record[type/@id=4 ]">
		<tr>
			<td>
				<!-- creator -->
				<xsl:apply-templates select="detail[@id=158]/record" mode="creator"></xsl:apply-templates>
				<!-- year  (from book reference)-->
				<xsl:value-of select="detail[@id=227]/record/detail[@id=159]"/>,&#xa0;
				<!-- title -->
				<i><xsl:value-of select="detail[@id=160]"/></i>,&#xa0;
				<!--publisher reference  (from book reference) -->
				in <xsl:apply-templates select="detail[@id=227]/record/detail[@id=158]/record" mode="creator" /> (ed.),&#xa0;
				<i><xsl:apply-templates select="detail[@id=227]/record/detail[@id=160]" /></i>,
				<!-- publisher info, series  (from book reference) -->
				<xsl:apply-templates select="detail[@id=227]/record/detail[@id=228]/record" />
				<!-- pages-->
				<xsl:if test="detail[@id=164] and detail[@id=165]">
				,&#xa0;<xsl:value-of select="detail[@id=164]"/>-<xsl:value-of select="detail[@id=165]"/>
				</xsl:if>
				
			</td>
		</tr>
	</xsl:template>
	
	<!-- report  -->
	<xsl:template name="report" match="record[type/@id=12 ]">
		<tr>
			<td>
				<!-- creator -->
				
				<xsl:apply-templates select="detail[@id=158]/record" mode="creator"></xsl:apply-templates>
				
				<!-- year -->
				<xsl:value-of select="detail[@id=159]"/>,&#xa0;
				<!-- title -->
				<i><xsl:value-of select="detail[@id=160]"/></i>,&#xa0;
				<!--publisher reference -->
				to <xsl:apply-templates select="detail[@id=229]/record" />
				
				
			</td>
		</tr>
	</xsl:template>
	
	<!-- thesis  -->
	<xsl:template name="thesis" match="record[type/@id=13 ]">
		<tr>
			<td>
				<!-- creator -->
				
				<xsl:apply-templates select="detail[@id=158]/record" mode="creator"></xsl:apply-templates>
				
				<!-- year -->
				<xsl:value-of select="detail[@id=159]"/>,&#xa0;
				<!-- title -->
				'<xsl:value-of select="detail[@id=160]"/>',&#xa0;
				<!--thesis type -->
				<xsl:apply-templates select="detail[@id=243]" />
				
				
			</td>
		</tr>
	</xsl:template>
	
	<!-- thesis  -->
	<xsl:template name="conference_proceedings" match="record[type/@id=7 ]">
		<tr>
			<td>
				<!-- creator -->
				
				<xsl:apply-templates select="detail[@id=158]/record" mode="creator"></xsl:apply-templates>
				
				<!-- year -->
				<xsl:value-of select="detail[@id=159]"/>,&#xa0;
				<!-- title -->
				'<xsl:value-of select="detail[@id=160]"/>',&#xa0;
				<!--conference reported : -->
				<xsl:apply-templates select="detail[@id=217]/record/detail[@id=160]" />, 
				<xsl:apply-templates select="detail[@id=217]/record/detail[@id=159]" />, 
				<!-- location -->
				<xsl:value-of select="detail[@id=217]/record/detail[@id=181]"/>
				
			</td>
		</tr>
	</xsl:template>
	<!-- pointer (part of output formatting) -->
	
	<xsl:template  match="detail/record" >
		
		<xsl:choose>
			<!-- replace empty series if applicable -->
			
			<xsl:when test="contains(title, '(series =)')">
				<xsl:variable name="series">(series =)</xsl:variable>
				<xsl:value-of select="substring-before(title, $series)"/>
				<xsl:value-of select="substring-after(title, $series)"/>
				
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="title"/>
			</xsl:otherwise>
		</xsl:choose>
			
	</xsl:template>
	
	<!-- author  -->
	<!--
	<xsl:template name="creator" match="detail/record" mode="creator">
		<xsl:value-of select="title"/>,&#xa0;			
	</xsl:template>
	-->
	<xsl:template name="creator" match="detail/record" mode="creator">
		<!-- for more then 1 author, output authors in the following format:
			Author 1, Author 2 & Author 3 -->
		<xsl:choose>
			<xsl:when test="count(../detail[@id=158]/record) >1">
				
				<xsl:choose>
					<xsl:when test="position()=last()">
						&amp; <xsl:value-of select="title"/>&#xa0;</xsl:when>
					<xsl:otherwise>
						<xsl:choose>
							<xsl:when test="position()=last()-1">
								<xsl:value-of select="title"/>
							</xsl:when>
							<xsl:otherwise>
								<xsl:value-of select="title"/>,
							</xsl:otherwise>
						</xsl:choose>
						
						
					</xsl:otherwise>
				</xsl:choose>
				
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="title"/>,
			</xsl:otherwise>
		</xsl:choose>
		
		
	</xsl:template>
	<!-- audio -->
	<xsl:template name="audio" match="record[type/@id=83]">
		<tr>
			<td>
				<!-- creator -->
				<xsl:apply-templates select="detail[@id=158]/record" mode="creator"></xsl:apply-templates>
				<!--date -->
				<xsl:choose>
					<xsl:when test="detail[@id=166]/year">
						<xsl:value-of select="detail[@id=166]/year"/>,&#xa0;	
					</xsl:when>
					<xsl:otherwise>
						<xsl:if test="detail[@id=177]/year and detail[@id=178]/year"></xsl:if>
						<!-- start date - end date -->
						<xsl:value-of select="detail[@id=177]/year"/>-<xsl:value-of select="detail[@id=178]/year"/>,&#xa0;
					</xsl:otherwise>
				</xsl:choose>
				
				
				<!-- title -->
				<i>'<xsl:value-of select="detail[@id=160]"/>'</i>
				
			</td>
		</tr>
	</xsl:template>
	
	
	
	
	<xsl:template name="video" match="record[type/@id=84]">
		<tr>
			<td>
				<!-- creator -->
				<xsl:apply-templates select="detail[@id=158]/record" mode="creator"></xsl:apply-templates>
				<!--year -->
				
				<xsl:if test="detail[@id=159]">
					<xsl:value-of select="detail[@id=159]"/>,&#xa0;
				</xsl:if>
				
				
				<!-- title -->
				<i>'<xsl:value-of select="detail[@id=160]"/>'</i>,&#xa0;
				<!--publisher reference -->
				<xsl:apply-templates select="detail[@id=229]/record" />
			</td>
		</tr>
	</xsl:template>
	<!-- default template -->
	<xsl:template name="default" match="record">
		<tr>
			<td>
				<xsl:if test="detail[@id=158]/record != ''">
				<!-- creator -->
					<xsl:apply-templates select="detail/record[@id=158]" mode="creator"></xsl:apply-templates>
				</xsl:if>
				<xsl:if test="detail[@id=159] != ''">
				<!-- year -->
					<xsl:value-of select="detail[@id=159]"/>,&#xa0;
				</xsl:if>
				<!-- title -->
				<xsl:if test="detail[@id=160] != ''">
				<i><xsl:value-of select="detail[@id=160]"/></i>&#xa0;
				</xsl:if>
				[no  bibliographic data]
				
			</td>
		</tr>
	</xsl:template>
	
</xsl:stylesheet>
