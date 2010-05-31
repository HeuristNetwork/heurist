<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

	<xsl:param name="flavour"/>

	<xsl:template match="breadcrumbs">
		<html>
			<head>
				<xsl:choose>
					<xsl:when test="$flavour='jhsb'">
						<link rel="stylesheet" type="text/css" href="http://acl.arts.usyd.edu.au/jhsb/css/template_jhsb.css"/>
						<style type="text/css">
							body { vertical-align: middle; }
							.navigation { background-color: #fffcdf; }
							.navigation a, a:link, a:visited, a:active, a:hover  {
								font-size: 11px;
								color: #636466;
								text-decoration: none;
							}
						</style>
					</xsl:when>
					<xsl:otherwise>
						<style type="text/css">
							A.sb_two:link { font-family: Arial, Verdana, Helvetica; color:#0065CF; text-decoration:none; font-weight: normal; font-size: 10pt }
							A.sb_two:visited { font-family: Arial, Verdana, Helvetica; color:#0065CF; text-decoration:none; font-weight: normal; font-size: 10pt }
							A.sb_two:active { font-family: Arial, Verdana, Helvetica; color:#0096E3; text-decoration:none; font-weight: normal; font-size: 10pt }
							A.sb_two:hover { font-family: Arial, Verdana, Helvetica; color:#0096E3; text-decoration:none; font-weight: normal; font-size: 10pt }

						</style>
					</xsl:otherwise>
				</xsl:choose>
			</head>
			<body>
				<!-- use of last() means that the currently selected item can be found at the end of the list and highlighted -->
				<xsl:for-each select="breadcrumb">
					<xsl:sort select="id"/>
					<xsl:if test="id > 0"><!-- xsl:if test="id = 4"><br/></xsl:if --><span style="padding: 0 10px;"> > </span></xsl:if>
					<xsl:if test="position()=last()">
						<nobr><a target="_parent" href="{url}" class="sb_two"><b><xsl:value-of select="title"/></b></a></nobr>
					</xsl:if>
					<xsl:if test="position()!=last()">
						<nobr><a target="_parent" href="{url}" class="sb_two"><xsl:value-of select="title"/></a></nobr>
					</xsl:if>
				</xsl:for-each>
			</body>
		</html>
	</xsl:template>

</xsl:stylesheet>

