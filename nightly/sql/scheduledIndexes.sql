DROP PROCEDURE IF EXISTS updateIndexes;

DELIMITER //

CREATE PROCEDURE updateIndexes(periodindays INT, maxindexes INT)
BEGIN
    DECLARE _indexable TEXT;
    DECLARE bDone INT;
    DECLARE _tablemachine VARCHAR(40);
    DECLARE _indexmachine VARCHAR(90);
    DECLARE _keymachine VARCHAR(40);
    DECLARE curnew CURSOR FOR 
        SELECT tablemachine,keymachine FROM os_userstats 
        WHERE unixtimestamp > CURRENT_TIMESTAMP()-periodindays*86400
        GROUP BY tablemachine,keymachine
        ORDER BY SUM(filtercount) DESC
        LIMIT maxindexes;
    DECLARE curold CURSOR FOR
        SELECT index_name,table_name FROM INFORMATION_SCHEMA.STATISTICS
        WHERE index_name LIKE 'osindex_%'
        EXCEPT 
            (SELECT CONCAT('osindex_',tablemachine,'_',keymachine) AS index_name, tablemachine AS table_name FROM os_userstats
            WHERE unixtimestamp > CURRENT_TIMESTAMP()-periodindays*86400
            GROUP BY tablemachine,keymachine
            ORDER BY SUM(filtercount) DESC
            LIMIT maxindexes);
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET bDone = 1;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SHOW ERRORS;
    END;
    
    SET _indexable = "'DATE','DATETIME','INTEGER','DECIMAL','SUGGEST','LIST','SUGGEST BEFORE LIST','EXTENSIBLE LIST'";
         
    OPEN curnew;
    SET bDone = 0;
    
    -- create new indices
    REPEAT
        FETCH curnew INTO _tablemachine,_keymachine;
        IF NOT bDone THEN
            -- test if keymachine in indexable
            SELECT CONCAT("SELECT COUNT(keymachine) FROM ",_tablemachine,"_permissions WHERE keymachine = '",_keymachine,"' AND edittype IN (",_indexable,") INTO @bIndexable") INTO @stmt;
            PREPARE stmt from @stmt;
            EXECUTE stmt;
            -- test if _keymachine is a tablename and continue only if it is not
            SELECT COUNT(tablemachine) from os_tables where tablemachine = _keymachine INTO @bIsTable;
            
            IF @bIndexable AND NOT @bIsTable THEN
                SELECT CONCAT('CREATE INDEX IF NOT EXISTS osindex_',_tablemachine,'_',_keymachine,' ON ',_tablemachine,'(',_keymachine,'(20))') INTO @stmt;
                PREPARE stmt from @stmt;
                EXECUTE stmt;
            END IF;
        END IF;
    UNTIL bDone END REPEAT;

    -- drop old indices
    OPEN curold;
    SET bDone = 0;

    REPEAT
        FETCH curold INTO _indexmachine,_tablemachine;
        IF NOT bDone THEN
            SELECT CONCAT('DROP INDEX IF EXISTS ',_indexmachine,' ON ',_tablemachine) INTO @stmt;
            PREPARE stmt from @stmt;
            EXECUTE stmt;
        END IF;
    UNTIL bDone END REPEAT;
END

//
        
DELIMITER ;

CREATE EVENT scheduledIndexes
    ON SCHEDULE EVERY 1 WEEK STARTS '2024-09-23 12:00:00'
    DO CALL updateIndexes(14,20);

