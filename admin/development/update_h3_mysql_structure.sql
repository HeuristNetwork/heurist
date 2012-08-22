-- insert any SQL below you wish to run across all Heurist databases on the server

-- update sysIdentification set sys_dbSubVersion=1 where (select count(*) from defDetailTypes where (defDetailTypes.dty_id=50) and (defDetailTypes.dty_Name="Transform Pointer"));

-- use this to print the database name
-- select database() as name from sysIdentification where not (select count(*) from defDetailTypes where (defDetailTypes.dty_id=50) and (defDetailTypes.dty_Name="Transform Pointer"));
select database() as name from sysIdentification where not (select count(*) from defDetailTypes where (defDetailTypes.dty_id=50) and (defDetailTypes.dty_Name="Transform Pointer"));

-- repeat the query to do the actual work of update
-- update ??? set ??? = ??? where not (select count(*) from defDetailTypes where (defDetailTypes.dty_id=50) and (defDetailTypes.dty_Name="Transform Pointer"));
