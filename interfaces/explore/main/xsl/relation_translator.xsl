<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
    
    <!-- this transform takes the relation type, direction and record type and translates into the desired human readable wording -->
    
    <xsl:param name="reltype"/>
    <xsl:param name="reldirection"/>
    <xsl:param name="reftype_id"/>
   
    
    <xsl:template name="translate_relations">
        
        <xsl:param name="reltype"/>
        <xsl:param name="reldirection"/>
        <xsl:param name="reftype_id"/>
        
        <xsl:choose>
            <xsl:when test="$reltype = 'Person Reference' and $reldirection = 'pointer'">
                Personal details
            </xsl:when> 
            <xsl:when test="$reltype = 'Site reference' and $reldirection = 'pointer'">
                Active as artist in
            </xsl:when> 
            <xsl:when test="$reltype = 'Artist reference' and $reldirection = 'pointer' and $reftype_id = 128">
                <!-- artist of an artwork -->
                Artist
            </xsl:when>
            
            <xsl:when test="$reltype = 'Person Reference' and $reldirection = 'reverse-pointer' and $reftype_id = 128">
                <!-- artist of an artwork -->
                Artist specific details
            </xsl:when>
            
            <xsl:when test="$reltype = 'IsTeacherOf' and $reldirection = 'related' and $reftype_id = 128">
               Teacher of
            </xsl:when>
            
            <xsl:when test="$reltype = 'IsTeacherOf' and $reldirection = 'related' and $reftype_id = 55">
                Teacher of
            </xsl:when>
            
            
            <xsl:when test="$reltype = 'IsParentOf' and $reldirection = 'related' and $reftype_id = 128">
                Parent of
            </xsl:when>
            
            <xsl:when test="$reltype = 'IsParentOf' and $reldirection = 'related' and $reftype_id = 55">
                Parent of
            </xsl:when>
            
            <xsl:when test="$reltype = 'IsCousinOf' and $reldirection = 'related' and $reftype_id = 128">
                Cousin of
            </xsl:when>
            
            <xsl:when test="$reltype = 'IsCousinOf' and $reldirection = 'related' and $reftype_id = 55">
                Cousin of
            </xsl:when>
            
            
            <xsl:when test="$reltype = 'IsChildOf' and $reldirection = 'related' and $reftype_id = 128">
                Child of
            </xsl:when>
            
            <xsl:when test="$reltype = 'IsChildOf' and $reldirection = 'related' and $reftype_id = 55">
                Child of
            </xsl:when>
           
            <xsl:when test="$reltype = 'IsStudentOf' and $reldirection = 'related' and $reftype_id = 128">
                Student of
            </xsl:when>
            
            <xsl:when test="$reltype = 'IsStudentOf' and $reldirection = 'related' and $reftype_id = 55">
                Student of
            </xsl:when>
           
            <xsl:when test="$reltype = 'Site reference' and $reldirection = 'reverse-pointer' and $reftype_id = 128">
                Artists
            </xsl:when> 
            <xsl:when test="$reltype = 'Site reference' and $reldirection = 'reverse-pointer' and $reftype_id = 102">
                Artists 
            </xsl:when> 
            
            <xsl:when test="$reltype = 'Artist reference' and $reldirection = 'reverse-pointer'">
                Artworks
            </xsl:when>
            
            <xsl:when test="$reltype = 'IsPartOf' and $reldirection = 'related' and $reftype_id =74">
                Appears in
            </xsl:when>
            
            <xsl:when test="$reltype = 'Creator' and $reldirection = 'reverse-pointer' and $reftype_id =3">
               Publications
            </xsl:when>
            
            <xsl:when test="$reltype = 'Creator' and $reldirection = 'pointer' and $reftype_id =75">
                Author / Contributor
            </xsl:when>
            
            
            <xsl:when test="$reltype = 'Contains' and $reldirection = 'related' and $reftype_id =74">
                More detailed parts
            </xsl:when>
            
            <xsl:when test="$reltype = 'Contains' and $reldirection = 'related' and $reftype_id =55">
                People mentioned
            </xsl:when>
            
            <xsl:when test="$reltype = 'Collection' and $reldirection = 'pointer' and $reftype_id = 115">
                Part of Collection
            </xsl:when>
            
            <xsl:when test="$reltype = 'Collection' and $reldirection = 'reverse-pointer' and $reftype_id = 129">
               <!-- for collections dont show the word collection -->
            </xsl:when>
            
            <xsl:when test="$reltype = 'Collection' and $reldirection = 'reverse-pointer' and $reftype_id = 74">
                <!-- for collections dont show the word collection -->
            </xsl:when>
            
            <xsl:when test="$reltype = 'Contributor reference' and $reldirection = 'pointer' and $reftype_id = 55">
                Collection owner
            </xsl:when>
            
            <xsl:when test="$reltype = 'Contributor reference' and $reldirection = 'reverse-pointer' and $reftype_id = 115">
                Collections
            </xsl:when>
            
            
            
            <xsl:when test="$reldirection = 'related' and $reftype_id = 129">
                Artworks painted here
            </xsl:when>
            
            <xsl:when test="$reltype = 'Media reference' and $reldirection = 'reverse-pointer' and $reftype_id = 129">
                Part of artwork
            </xsl:when>
            
            <xsl:when test="$reltype = 'Media reference' and $reldirection = 'pointer' and $reftype_id = 74">
                <!-- dont show media reference heading for artworks -->
            </xsl:when>
          
            
            <xsl:when test="$reltype = 'IsRelatedTo'">
                Related to
            </xsl:when>
            
            <xsl:otherwise>
                <xsl:value-of select="$reltype"/>
                
                <!-- small><em><font color="light-grey"> <xsl:value-of select="$reftype_id"/> 
                    <xsl:choose>
                        <xsl:when test="$reldirection = 'pointer'">
                            p
                        </xsl:when>
                        <xsl:when test="$reldirection = 'reverse-pointer'">
                            rp
                        </xsl:when>
                        <xsl:when test="$reldirection = 'related'">
                            r
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:value-of select="$reldirection"/>
                        </xsl:otherwise>
                    </xsl:choose>
                    </font></em></small -->
            </xsl:otherwise>
        </xsl:choose>
        
        
        
        
    </xsl:template>
    
</xsl:stylesheet>
