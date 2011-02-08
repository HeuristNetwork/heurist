<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xd="http://www.oxygenxml.com/ns/doc/xsl" version="1.0">

<xsl:template match="/">
	<xsl:choose>
		<xsl:when test="hml/records/record/detail[@id=221 or @id= 222 or @id= 223 or  @id= 224]">
			<span class="icon" title="{title}" style="width:48px; display:table-cell;padding:0;background-color:{detail[@id=678]};">
					<div class="iconIMG">
						<a target="_new" href="../../search/search.html?q=ids:{hml/records/record/id}&amp;instance=' + instance;">
							<img src="{hml/records/record/detail[@id=221 or @id= 222 or @id= 223 or  @id= 224]/file/thumbURL}"/>
						</a> 
						<xsl:if test="count(hml/records/record/detail/record)&gt;0">
							<div class="iconCount" onmouseover="this.style.overflow='visible'" onmouseout="this.style.overflow='hidden'">
							<xsl:value-of select="count(hml/records/record/detail/record)"/>
									<div class="titlesList">
									<xsl:call-template name="pointerTitles">
										<xsl:with-param name="items" select="hml/records/record"/>
										<xsl:with-param name="recId" select="hml/records/record/id"/>
										</xsl:call-template>
									</div>
							</div>
						</xsl:if>
					</div>
			</span>
		</xsl:when>
		<xsl:otherwise>
			<xsl:call-template name="relationShip_record_section">
				<xsl:with-param name="items" select="hml/records/record"/>
				<xsl:with-param name="recId" select="hml/records/record/id"/>
			</xsl:call-template>
		</xsl:otherwise>
	</xsl:choose>
</xsl:template>

	<xsl:template name="relationShip_record_section">
		<xsl:param name="items"/>
		<xsl:param name="recId"/>
		<xsl:variable name="recCount" select="count($items/detail/record[not(id = $recId)])"></xsl:variable>
		<xsl:choose>
			<xsl:when test="$recCount&lt;4 and $recCount&gt;0">
				<xsl:for-each select="$items/detail/record[not(id = $recId)]">
					<xsl:sort select="type"/>
					<xsl:sort select="title"/>
						<xsl:choose>
							<xsl:when test="detail[@id= 221 or @id= 222 or @id= 223 or  @id= 224]">
								<span class="icon" title="{title}" style="width:48px; display:table-cell;padding:0;background-color:{detail[@id=678]};backgound-image:url({detail[@id= 221 or @id= 222 or @id= 223 or @id= 224]/file/thumbURL})"><a target="_new" href="../../search/search.html?q=ids:{self::node()/id}&amp;instance=' + instance;"><div class="iconIMG"><img src="{detail[@id= 222 or @id= 223 or @id= 224]/file/thumbURL}"/></div></a></span>
							</xsl:when>
							<xsl:otherwise>
								<span class="icon" title="{title}" style="width:48px; display:table-cell;padding:0;background-color:{detail[@id=678]};background-image:url(../../common/images/rectype-icons/thumb/th_{type/@id}.png)"><a target="_new" href="../../search/search.html?q=ids:{self::node()/id}&amp;instance=' + instance;"><img src="../../common/images/31x31.gif"/></a></span>
							</xsl:otherwise>
						</xsl:choose>
				</xsl:for-each>
				<div class="recInfo" onmouseover="this.style.overflow='visible'" onmouseout="this.style.overflow='hidden'">
						<img src="../../common/images/info-grey.gif"/>
						<div class="titlesList">
							<xsl:call-template name="recInfo"/>
						</div>
					</div>
			</xsl:when>
			<xsl:otherwise>
				<span class="icon" title="{title}" style="width:48px; display:table-cell;padding:0;">
					<div class="iconIMG" style="margin-left:-3px; background-image:url(../../common/images/rectype-icons/thumb/th_{hml/records/record/type/@id}.png">
						<img src="../../common/images/31x31.gif"/>
						<xsl:if test="count(hml/records/record/detail/record)&gt;0">  
						<div class="iconCount" onmouseover="this.style.overflow='visible'" onmouseout="this.style.overflow='hidden'">
							<xsl:value-of select="count($items/detail/record[not(id = $recId)])"/>
							<div class="titlesList">
							<xsl:call-template name="pointerTitles">
								<xsl:with-param name="items" select="hml/records/record"/>
								<xsl:with-param name="recId" select="hml/records/record/id"/>
								</xsl:call-template>
							</div>
						</div>
						</xsl:if>
						</div>
				</span>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	
	<xsl:template name="pointerTitles">
			<xsl:param name="items"/>
			<xsl:param name="recId"/>
				<xsl:for-each select="$items/detail/record[not(id = $recId)]">
					<xsl:sort select="type"/>
					<xsl:value-of select="title"/><p/>
				</xsl:for-each>
	</xsl:template>
	
	<xsl:template name="recInfo">
		<b><xsl:value-of select="hml/records/record/type"/>:</b><br/>
		<xsl:value-of select="hml/records/record/title"/>
	</xsl:template>

</xsl:stylesheet>
