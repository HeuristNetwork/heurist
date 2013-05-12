-- MySQL stored functions for Heurist, to replace old UDFs
-- levenshtein.c & liposuction.c by Tom Murtagh (2007)
--
-- Copyright (C) 2007-2013 University of Sydney
-- Copyright (C) 2013 by Arjen Lentz (arjen@archefact.com.au)
-- License http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0

-- Contains
-- LEVENSHSTEIN(VARCHAR(255),VARCHAR(255)) RETURNS INT NOT NULL
-- LIMITED_LEVENSHSTEIN obsoleted (modified calling code)
-- LIPOSUCTION(VARCHAR(20480)) RETURNS VARCHAR(20480)

-- Changelog
-- 2013-05-08 by Arjen Lentz (arjen@archefact.com.au) initial
-- 2013-05-12 by Arjen Lentz (arjen@archefact.com.au) removed LIMITED_LEVENSHTEIN()


-- core levenshtein function adapted from
-- function by Jason Rust (http://sushiduy.plesk3.freepgs.com/levenshtein.sql)
-- originally from http://codejanitor.com/wp/2007/02/10/levenshtein-distance-as-a-mysql-stored-function/
-- rewritten by arjen for utf8, code/logic cleanup and removing HEX()/UNHEX() in favour of ORD()/CHAR()
-- Levenshtein reference: http://en.wikipedia.org/wiki/Levenshtein_distance

-- Arjen note: because the levenshtein value is encoded in a byte array, distance cannot exceed 255;
-- thus the maximum string length this implementation can handle is also limited to 255 characters.

DELIMITER $$
DROP FUNCTION IF EXISTS LEVENSHTEIN $$
CREATE FUNCTION LEVENSHTEIN(s1 VARCHAR(255) CHARACTER SET utf8, s2 VARCHAR(255) CHARACTER SET utf8)
  RETURNS INT
  DETERMINISTIC
  BEGIN
    DECLARE s1_len, s2_len, i, j, c, c_temp, cost INT;
    DECLARE s1_char CHAR CHARACTER SET utf8;
    -- max strlen=255 for this function
    DECLARE cv0, cv1 VARBINARY(256);

    SET s1_len = CHAR_LENGTH(s1),
        s2_len = CHAR_LENGTH(s2),
        cv1 = 0x00,
        j = 1,
        i = 1,
        c = 0;

    IF (s1 = s2) THEN
      RETURN (0);
    ELSEIF (s1_len = 0) THEN
      RETURN (s2_len);
    ELSEIF (s2_len = 0) THEN
      RETURN (s1_len);
    END IF;

    WHILE (j <= s2_len) DO
      SET cv1 = CONCAT(cv1, CHAR(j)),
          j = j + 1;
    END WHILE;

    WHILE (i <= s1_len) DO
      SET s1_char = SUBSTRING(s1, i, 1),
          c = i,
          cv0 = CHAR(i),
          j = 1;

      WHILE (j <= s2_len) DO
        SET c = c + 1,
            cost = IF(s1_char = SUBSTRING(s2, j, 1), 0, 1);

        SET c_temp = ORD(SUBSTRING(cv1, j, 1)) + cost;
        IF (c > c_temp) THEN
          SET c = c_temp;
        END IF;

        SET c_temp = ORD(SUBSTRING(cv1, j+1, 1)) + 1;
        IF (c > c_temp) THEN
          SET c = c_temp;
        END IF;

        SET cv0 = CONCAT(cv0, CHAR(c)),
            j = j + 1;
      END WHILE;

      SET cv1 = cv0,
          i = i + 1;
    END WHILE;

   RETURN (c);
  END $$


DELIMITER $$
DROP FUNCTION IF EXISTS LIPOSUCTION $$
-- LIPOSUCTION returns string removing any spaces/punctuation
-- C isspace(): 0x20 SPC 0x09 TAB 0x0a LF 0x0b VT 0x0c FF 0x0d CR
-- For simplicity we just regard anything <= ASCII 32 as space
-- C ispunct(): any of ! " # % &  ' ( ) ; < = > ? [ \ ] * + , - . / : ^ _ { | } ~
CREATE FUNCTION LIPOSUCTION(s VARCHAR(20480) CHARACTER SET utf8)
  RETURNS VARCHAR(20480) CHARACTER SET utf8
  DETERMINISTIC
  BEGIN
    DECLARE i, len INT;
    DECLARE c CHAR CHARACTER SET utf8;
    DECLARE s2 VARCHAR(20480) CHARACTER SET utf8;

    IF (s IS NULL) THEN
       RETURN (NULL);
    END IF;

    SET i = 1,
        len = CHAR_LENGTH(s),
        s2 = '';

    WHILE (i <= len) DO
        SET c = SUBSTRING(s, i, 1);
        IF (ORD(c) > 32 && LOCATE(c, '!\"#%&\'();<=>?[\\]*+,-./:^_{|}~') = 0) THEN
          SET s2 = CONCAT(s2, c);
        END IF;

        SET i = i + 1;
    END WHILE;

    RETURN (s2);
  END $$


DELIMITER ;
