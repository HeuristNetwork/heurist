/*
* Copyright (C) 2005-2016 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* DB_index_triggers.sql: SQL file to create or update triggers for Heurist_DBs_index databsae - need to be run for all databases on server
*
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.5
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/
-- these triggers with wrong names exist in some database
DROP TRIGGER IF EXISTS sysUsrGrpLinks_last_delete$$;
DROP TRIGGER IF EXISTS sysUGrps_last_delete$$;

DELIMITER $$

DROP TRIGGER IF EXISTS update_sys_index_trigger$$

    CREATE
    DEFINER=`root`@`localhost`
    TRIGGER `update_sys_index_trigger`
    AFTER UPDATE ON `sysIdentification`
    FOR EACH ROW
    begin
       delete from `Heurist_DBs_index`.`sysIdentifications` where `sys_Database`=(SELECT DATABASE());
       insert into `Heurist_DBs_index`.`sysIdentifications` select (SELECT DATABASE()) as dbName, s.* from `sysIdentification` as s;
    end$$
    
DROP TRIGGER IF EXISTS sysUsrGrpLinks_last_insert$$
DROP TRIGGER IF EXISTS sysUsrGrpLinks_last_update$$
DROP TRIGGER IF EXISTS sysUsrGrpLinks_last_delete$$
DROP TRIGGER IF EXISTS sysUGrps_last_delete$$
DROP TRIGGER IF EXISTS sysUGrps_last_update$$

    CREATE
    DEFINER=`root`@`localhost`
    TRIGGER `sysUsrGrpLinks_last_insert`
    AFTER INSERT ON `sysUsrGrpLinks`
    FOR EACH ROW
    begin
       update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUsrGrpLinks";
    
       IF (NEW.ugl_UserID<>2) THEN
               
       select ugr_Email, COALESCE(ugl_Role,'member') as role from sysUGrps 
        LEFT JOIN sysUsrGrpLinks on ugr_ID=ugl_UserID and ugl_GroupID=1 and ugl_Role='admin' 
        where ugr_ID=NEW.ugl_UserID INTO @email, @role;
       
       select `sus_ID`,`sus_Role` from `Heurist_DBs_index`.`sysUsers` 
       where `sus_Database`=(SELECT DATABASE()) AND `sus_Email`=@email 
       into @sus_id, @sus_role;
       
       IF (@sus_id>0)  THEN
        IF(@sus_role<>@role)  THEN
            update `Heurist_DBs_index`.`sysUsers` set sus_Role=@role WHERE sus_ID=@sus_id;
        END IF;
       ELSE
            insert into `Heurist_DBs_index`.`sysUsers` (sus_Email, sus_Database, sus_Role) values (@email, (SELECT DATABASE()), @role);
       END IF;
       
      END IF;
    end$$

    CREATE
    DEFINER=`root`@`localhost`
    TRIGGER `sysUsrGrpLinks_last_update`
    AFTER UPDATE ON `sysUsrGrpLinks`
    FOR EACH ROW
    begin
       update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUsrGrpLinks";
    
       IF (NEW.ugl_UserID<>2) THEN
               
       select ugr_Email, COALESCE(ugl_Role,'member') as role from sysUGrps 
        LEFT JOIN sysUsrGrpLinks on ugr_ID=ugl_UserID and ugl_GroupID=1 and ugl_Role='admin' 
        where ugr_ID=NEW.ugl_UserID INTO @email, @role;
       
       select `sus_ID`,`sus_Role` from `Heurist_DBs_index`.`sysUsers` 
       where `sus_Database`=(SELECT DATABASE()) AND `sus_Email`=@email 
       into @sus_id, @sus_role;
       
       IF(@sus_id>0)  THEN
        IF(@sus_role<>@role) THEN
            update `Heurist_DBs_index`.`sysUsers` set sus_Role=@role WHERE sus_ID=@sus_id;
        END IF;
       ELSE
            insert into `Heurist_DBs_index`.`sysUsers` (sus_Email, sus_Database, sus_Role) values (@email, (SELECT DATABASE()), @role);
       END IF;
       
      END IF;
    end$$

    CREATE
    DEFINER=`root`@`localhost`
    TRIGGER `sysUsrGrpLinks_last_delete`
    AFTER DELETE ON `sysUsrGrpLinks`
    FOR EACH ROW
    begin
      IF (OLD.ugl_UserID<>2) THEN
               
-- get new role               
     select ugr_eMail, COALESCE(ugl_Role,'member') as role from sysUGrps 
        LEFT JOIN sysUsrGrpLinks on ugr_ID=ugl_UserID and ugl_GroupID=1 and ugl_Role='admin' 
        where ugr_ID=OLD.ugl_UserID INTO @email, @role;       
       
        IF ((@email IS NOT NULL) AND (@email<>'')) THEN
            select `sus_ID`,`sus_Role` from `Heurist_DBs_index`.`sysUsers` 
            where `sus_Database`=(SELECT DATABASE()) AND `sus_Email`=@email 
            into @sus_id, @sus_role;
            
           IF ((@sus_id>0) AND (@sus_role<>@role)) THEN
              update `Heurist_DBs_index`.`sysUsers` set sus_Role=@role WHERE sus_ID=@sus_id;
           END IF;
        END IF;
      END IF;
    end$$

--
    
    CREATE
    DEFINER=`root`@`localhost`
    TRIGGER `sysUGrps_last_update`
    AFTER UPDATE ON `sysUGrps`
    FOR EACH ROW
    begin
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUGrps";
        if (OLD.ugr_eMail<>NEW.ugr_eMail) THEN
            UPDATE `Heurist_DBs_index`.`sysUsers` SET sus_Email=NEW.ugr_eMail WHERE `sus_Database`=(SELECT DATABASE()) AND `sus_Email`=OLD.ugr_eMail;  
        END IF;
    end$$
    
    CREATE
    DEFINER=`root`@`localhost`
    TRIGGER `sysUGrps_last_delete`
    AFTER DELETE ON `sysUGrps`
    FOR EACH ROW
    begin
        DELETE FROM `Heurist_DBs_index`.`sysUsers` WHERE `sus_Database`=(SELECT DATABASE()) AND `sus_Email`=OLD.ugr_eMail;  
    end$$
    
    
DELIMITER ;
    
