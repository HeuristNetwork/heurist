-- Created by Artem Osmakov 2023-06-01

-- This file contains getEstDate function

DELIMITER $$


DROP function IF EXISTS `getEstDate`$$

-- extract estMinDate or estMaxDate from json string
CREATE DEFINER=CURRENT_USER FUNCTION `getEstDate`(sTemporal varchar(4095), typeDate tinyint) RETURNS DECIMAL(15,4)
    DETERMINISTIC
    BEGIN
            declare iBegin integer;
            declare iEnd integer;
            declare nameDate varchar(20) default '';
            declare strDate varchar(15) default '';
            declare estDate decimal(15,4);
            
-- error handler for date conversion            
            DECLARE EXIT HANDLER FOR 1292
            BEGIN
                RETURN 0;
            END;
            
-- find the temporal type might not be a temporal format, see else below
            IF (TRIM(sTemporal) REGEXP '^-?[0-9]+$') THEN
                RETURN CAST(TRIM(sTemporal) AS DECIMAL(15,4));
            END IF;

            SET iBegin = LOCATE('|VER=1|',sTemporal);
            if iBegin = 1 THEN
                SET sTemporal = getTemporalDateString(sTemporal);
            END IF;
            
            IF (typeDate = 0) THEN
                set nameDate = '"estMinDate":';
            ELSE
                set nameDate = '"estMaxDate":';
            END IF;

            SET iBegin = LOCATE(nameDate,sTemporal);
            IF iBegin = 0 THEN
-- it will work for valid CE dates only, dates without days or month will fail
                IF DATE(sTemporal) IS NULL THEN 
                    RETURN 0;
                ELSE    
                    RETURN CAST(CONCAT(YEAR(sTemporal),'.',LPAD(MONTH(sTemporal),2,'0'),LPAD(DAY(sTemporal),2,'0')) AS DECIMAL(15,4));
                END IF; 
                 
            ELSE
                SET iBegin = iBegin + 13;
                SET iEnd = LOCATE(',', sTemporal, iBegin);
                IF iEnd = 0 THEN
                    SET iEnd = LOCATE('}', sTemporal, iBegin);
                END IF;
                IF iEnd > 0 THEN
                    SET strDate =  substring(sTemporal, iBegin, iEnd - iBegin);
                    RETURN CAST(TRIM(strDate) AS DECIMAL(15,4));
                END IF;
                RETURN 0;    
            END IF;
    END$$

DELIMITER ;

