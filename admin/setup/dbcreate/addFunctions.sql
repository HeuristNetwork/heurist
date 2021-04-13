-- MySQL stored functions for Heurist, to replace old UDFs
-- levenshtein.c & liposuction.c by Tom Murtagh (2007)
--
-- Copyright (C) 2007-2013 University of Sydney
-- Copyright (C) 2013 by Arjen Lentz (arjen@archefact.com.au)
-- License http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0

-- Contains
-- NEW_LEVENSHSTEIN(VARCHAR(255),VARCHAR(255)) RETURNS INT NOT NULL
-- LIMITED_LEVENSHSTEIN obsoleted (modified calling code)
-- NEW_LIPOSUCTION(VARCHAR(20480)) RETURNS VARCHAR(31)

-- Changelog
-- 2013-05-08 by Arjen Lentz (arjen@archefact.com.au) initial
-- 2013-05-12 by Arjen Lentz (arjen@archefact.com.au) removed LIMITED_LEVENSHTEIN()
-- 2013-05-14 by Arjen Lentz (arjen@archefact.com.au) added NEW_ prefix to ease upgrade

-- ------------------------------------------------------------------------------
-- The functions contained in this file have been merged with addProceduresTriggers.sql
-- so new installations are automatically handled and may thus ignore this file
--
-- Existing installations must execute the following upgrade steps:
-- 1) for each existing Heurist database, run
--    USE dbname
--    SOURCE addFunctions.sql
-- 2) at a convenient later time, disable/remove old UDFs from MySQL installation
--    DROP FUNCTION LEVENSHTEIN;
--    DROP FUNCTION LIMITED_LEVENSHTEIN;
--    DROP FUNCTION LIPOSUCTION;
-- ------------------------------------------------------------------------------

-- core levenshtein function adapted from
-- function by Jason Rust (http://sushiduy.plesk3.freepgs.com/levenshtein.sql)
-- originally from http://codejanitor.com/wp/2007/02/10/levenshtein-distance-as-a-mysql-stored-function/
-- rewritten by arjen for utf8, code/logic cleanup and removing HEX()/UNHEX() in favour of ORD()/CHAR()
-- Levenshtein reference: http://en.wikipedia.org/wiki/Levenshtein_distance

-- Arjen note: because the levenshtein value is encoded in a byte array, distance cannot exceed 255;
-- thus the maximum string length this implementation can handle is also limited to 255 characters.

DELIMITER $$

DROP FUNCTION IF EXISTS `NEW_LIPOSUCTION_255`$$
-- LIPOSUCTION returns string removing any spaces/punctuation
-- C isspace(): 0x20 SPC 0x09 TAB 0x0a LF 0x0b VT 0x0c FF 0x0d CR
-- For simplicity we just regard anything <= ASCII 32 as space
-- C ispunct(): any of ! " # % &  ' ( ) ; < = > ? [ \ ] * + , - . / : ^ _ { | } ~
CREATE DEFINER=CURRENT_USER FUNCTION `NEW_LIPOSUCTION_255`(s VARCHAR(16383) CHARACTER SET utf8mb4)
  RETURNS VARCHAR(255) CHARACTER SET utf8mb4
  DETERMINISTIC
  BEGIN
    DECLARE i, len INT;
    DECLARE c CHAR CHARACTER SET utf8mb4;
    DECLARE s2 VARCHAR(16383) CHARACTER SET utf8mb4;

    IF (s IS NULL) THEN
       RETURN (NULL);
    END IF;

    SET i = 1,
        len = CHAR_LENGTH(s),
        s2 = '';

    WHILE (i <= len) DO
        SET c = SUBSTRING(s, i, 1);
        IF (ORD(c) > 32 AND LOCATE(c, '!\"#%&\'();<=>?[\\]*+,-./:^_{|}~') = 0) THEN
          SET s2 = CONCAT(s2, c);
        END IF;

        SET i = i + 1;
    END WHILE;

    RETURN SUBSTRING(s2,1,254);
  END$$
  
  DELIMITER ;
