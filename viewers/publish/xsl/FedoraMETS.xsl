<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0"
    xmlns:dc="http://dublincore.org/schemas/xmls/qdc/2006/01/06/dc.xsd"
    xmlns:METS="http://www.loc.gov/METS/"
    xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc.xsd"
    xmlns:xlink="http://www.loc.gov/standards/mets/xlink.xsd">
    <xsl:output method="xml" indent="yes"/>
    
    <xsl:template match="export">

    <!-- write out METS header -->  
        <METS:mets LABEL="test object" OBJID="dos:{@pub_id}" PROFILE="image" TYPE="FedoraObject"
            fedoraxsi:schemaLocation="http://www.loc.gov/METS/ http://www.fedora.info/definitions/1/0/mets-fedora-ext.xsd http://www.loc.gov/MODS/ http://www.loc.gov/mods/xml.xsd"
            xmlns:METS="http://www.loc.gov/METS/" xmlns:audit="info:fedora/fedora-system:def/audit#"
            xmlns:fedoraxsi="http://www.w3.org/2001/XMLSchema-instance"
            xmlns:xlink="http://www.w3.org/TR/xlink" xmlns:mods="http://www.loc.gov/mods/xml.xsd">
            <METS:metsHdr CREATEDATE="2007-06-05T05:31:18.749Z" LASTMODDATE="2007-06-05T05:32:48.220Z"
                RECORDSTATUS="A">
                <METS:agent ROLE="IPOWNER">
                    <METS:name>fedoraAdmin</METS:name>
                </METS:agent>
            </METS:metsHdr>
            
            <xsl:apply-templates select="references/reference"/>
            
        </METS:mets>
        

    </xsl:template>
    
    <xsl:template match="reference">
        <METS:amdSec ID="DC" STATUS="A" VERSIONABLE="true">
            <METS:techMD ID="DC1.0">
                <METS:mdWrap CHECKSUM="none" CHECKSUMTYPE="DISABLED" LABEL="Dublin Core Metadata"
                    MDTYPE="OTHER" MIMETYPE="text/xml" OTHERMDTYPE="UNSPECIFIED">
                    <METS:xmlData>
                        <oai_dc:dc xmlns:dc="http://purl.org/dc/elements/1.1/"
                            xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/">
                            <dc:title><xsl:value-of select="title"/></dc:title>
                            <dc:description><xsl:value-of select="notes"/></dc:description>
                            <dc:coverage.x>
                                <coord_sys name="UTM"></coord_sys>
                                <coord_sys name="WGS84"></coord_sys>
                            </dc:coverage.x>
                            <dc:coverage.y>
                                <coord_sys name="UTM"></coord_sys>
                                <coord_sys name="WGS84"></coord_sys>
                            </dc:coverage.y>
                            <dc:coverage.t.early></dc:coverage.t.early>
                            <dc:coverage.t.late></dc:coverage.t.late>
                            <dc:coverage.PlaceName></dc:coverage.PlaceName>
                            <dc:coverage.PeriodName></dc:coverage.PeriodName>
                            <dc:publisher></dc:publisher>
                            <dc:format>image/jpeg</dc:format>
                            <dc:identifier></dc:identifier>
                        </oai_dc:dc>
                    </METS:xmlData>
                </METS:mdWrap>
            </METS:techMD>
        </METS:amdSec>
        
        <METS:fileSec>
            <METS:fileGrp ID="DATASTREAMS">
               <METS:file ID="ITEM" MIMETYPE="image/jpeg" OWNERID="M">
                    <METS:FLocat LOCTYPE="URL" xlink:href="{url}"></METS:FLocat>
                </METS:file>
            </METS:fileGrp>
        </METS:fileSec>
        
        <!-- METS:fileSec>
            <METS:fileGrp ID="DATASTREAMS">
                <METS:fileGrp ID="DS1" STATUS="A" VERSIONABLE="true">
                    <METS:file CHECKSUM="none" CHECKSUMTYPE="DISABLED" CREATED="2007-06-05T05:32:48.220Z"
                        ID="DS1.0" MIMETYPE="image/jpeg" OWNERID="M" SIZE="0">
                        <METS:FLocat LOCTYPE="URL"
                            xlink:href="{url}"
                            xlink:title="byte stream"/>
                    </METS:file>
                </METS:fileGrp>
            </METS:fileGrp>
        </METS:fileSec -->
        
    </xsl:template>

</xsl:stylesheet>
