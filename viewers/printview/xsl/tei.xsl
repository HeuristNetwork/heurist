<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:template match="/">
		<!-- use the following bit of code to include the stylesheet to display it in Heurist publishing wizard
			otherwise it will be ommited-->
		<!-- begin including code -->
		<xsl:comment>
			<!-- name (desc.) that will appear in dropdown list -->
			[name]TEI view[/name]
			<!-- match the name of the stylesheet-->
			[output]tei[/output]
		</xsl:comment>
		<!-- end including code -->
		<xsl:apply-templates select="/hml/records/record"/>
	</xsl:template>


	<!-- only use text/body/div from TEI, discard the rest -->
	<xsl:template match="record">
		<div id="content">
			
		
			[<xsl:apply-templates select="reversePointer"/>]
					
			<xsl:apply-templates select="detail/file/content/text"/>
			
			
		</div> 
	</xsl:template>

	<xsl:template match="reversePointer">
		<p><em>rp: <xsl:value-of select="."/></em></p>
		
	</xsl:template>

	<xsl:template name="addRef">
		<xsl:param name="ref"/>
		<xsl:param name="hide"/>
		<script type="text/javascript">
			if (window["refs"]) {
				refs.push( {
					startElems : [ <xsl:value-of select="detail[@id=539]"/> ],
					endElems : [ <xsl:value-of select="detail[@id=540]"/> ],
					startWord :
						<xsl:choose>
							<xsl:when test="detail[@id=329]"><xsl:value-of select="detail[@id=329]"/></xsl:when>
							<xsl:otherwise>null</xsl:otherwise>
						</xsl:choose>,
					endWord :
						<xsl:choose>
							<xsl:when test="detail[@id=330]"><xsl:value-of select="detail[@id=330]"/></xsl:when>
							<xsl:otherwise>null</xsl:otherwise>
						</xsl:choose>,
					<xsl:if test="$hide='true'">
					hide : true,
					</xsl:if>
					<xsl:if test="detail[@id=199]/record">
					targetID : <xsl:value-of select="detail[@id=199]/record/id"/>,
					<!-- href : "../<xsl:call-template name="getPath"><xsl:with-param name="id" select="detail[@id=199]/record/id"/></xsl:call-template>", -->
					</xsl:if>
					recordID : "<xsl:value-of select="id"/>"
				} );
			}
		</script>
	</xsl:template>
		

	<xsl:template match="detail/file/content/text">
		
			<!-- do an identity transform - and then leave it like that -->
		
			<xsl:apply-templates/>
		
	</xsl:template>

	<xsl:template match="div1">
		<xsl:apply-templates/>
	</xsl:template>

	<xsl:template match="div/head">
		<h2>
			<xsl:apply-templates/>
		</h2>
	</xsl:template>



	<xsl:template match="p">
		<p>
			<xsl:if test="@type">
				<xsl:attribute name="class">
					<xsl:value-of select="@type"/>
				</xsl:attribute>
			</xsl:if>
			<xsl:apply-templates/>
		</p>
	</xsl:template>


	<xsl:template match="hi">
		<span class="{@rend}">
			<xsl:apply-templates/>
		</span>
	</xsl:template>

	<xsl:template match="quote">
		<span class="quote">
			<xsl:apply-templates/>
		</span>
	</xsl:template>

	<xsl:template match="note">
		<span class="note">
			<xsl:apply-templates/>
		</span>
	</xsl:template>

	<!-- table -->
	<xsl:template match="table">
		<table cellpadding="2" cellspacing="2">
			<xsl:apply-templates/>
		</table>
	</xsl:template>
	<xsl:template match="table/row">
		<tr>
			<xsl:apply-templates/>
		</tr>
	</xsl:template>
	<xsl:template match="table/row/cell">
		<td class="teidoc">
			<xsl:apply-templates/>
		</td>
	</xsl:template>


	<!-- list -->
	<xsl:template match="list">
		<ul>
			<xsl:apply-templates/>
		</ul>
	</xsl:template>

	<xsl:template match="item">
		<li>
			<xsl:apply-templates/>
		</li>
	</xsl:template>

	<xsl:template match="lg">
		<p class="separator">
			<xsl:apply-templates/>
		</p>
	</xsl:template>

	<xsl:template match="l">
		<p class="poetry-line">
			<xsl:apply-templates/>
		</p>
	</xsl:template>


</xsl:stylesheet>
