create temporary table t (bib_id int, val varchar(1023));
delimiter //
CREATE FUNCTION hhash2(bibID int) RETURNS int
    MODIFIES SQL DATA
    DETERMINISTIC
begin
	declare reftype int;
	declare non_resource_fields text;
	declare resource_fields text;

	select bib_reftype into reftype from biblio where bib_id = bibID;

-- Look at the scalar (non resource-pointer) fields that are marked as necessary for a match;
-- uppercase them, get rid of any punctuation and whitespace,
-- then concatenate them with semicolons in-between
	select group_concat(liposuction(upper(bd_val)) order by bdt_id, upper(bd_val) separator ';')
	  into non_resource_fields
	  from bib_detail, biblio, bib_detail_types, bib_detail_requirements
	 where bd_bib_id=bib_id and bd_type=bdt_id and bib_reftype=bdr_reftype and bdr_bdt_id=bdt_id and bdr_match and bdt_type != "resource" and bib_id = bibID;

-- Look at the resource-pointer fields that are marked as required by this reftype;
-- get their h-hash values,
-- then concatenate them with beginning (^) and end ($) markers
	select group_concat(DST.bib_hhash order by bdt_id, bdt_id separator '$^')
	  into resource_fields
	  from bib_detail, biblio SRC, bib_detail_types, bib_detail_requirements, biblio DST
	 where bd_bib_id=SRC.bib_id and bd_type=bdt_id and SRC.bib_reftype=bdr_reftype and bdr_bdt_id=bdt_id and bdr_required = 'Y' and bdt_type = "resource" and bd_val = DST.bib_id and bd_bib_id=bibID;

-- Final value has the form:
-- reftype:matchvalue1;matchvalue2; ... ^resource1-hash$^resource2-hash$ ...
-- If the reftype is null, we use "N" (for "private notes") instead.
insert into t values (bibID, concat(ifnull(reftype,'N'), ':',
	              if(non_resource_fields is not null and non_resource_fields != '', concat(non_resource_fields, ';'), ''),
	              if(resource_fields is not null and resource_fields != '', concat('^', resource_fields, '$'), '')));

	return 1;
end
//
delimiter ;

-- Since hhashes are (potentially) recursive, one needs to be careful about the order of update --
-- to bulk change all the hashes, first process the reftypes that don't have any resource pointers,
-- then the ones that refer only to those types, etc, etc ... finally the pure relationship types (agape?).
-- Here's the code we used to initialise the hhash values initially -- not the tersest, but it works:
-- 

--    set @suppress_update_trigger := 1;
--    set @logged_in_user_id := 1;

--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype in (30,48,56,57,58,59,62,72,73,80,81,82,85,87,88,90,91,92,93);
--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype in (30,48,56,57,58,59,62,72,73,80,81,82,85,87,88,90,91,92,93);
--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype in (1,2,7,12,29,46,47,49,51,53,54,55,61,64,68,69,71,75,76,78,79,83,84,86);
--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype is null;
--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype = 11;
--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype in (77,74,70,63,60,65,66,67);
--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype in (44);
--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype in (42);
--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype in (13);
--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype in (9);
--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype in (31);
--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype in (28);
--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype in (3);
--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype in (5);
--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype in (4);
--    update biblio set bib_hhash = hhash(bib_id) where bib_reftype in (52);

