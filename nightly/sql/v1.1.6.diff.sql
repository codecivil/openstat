# mysql user to change passwords
# create user OS_CHPWD and grant privileges
SET @pwd = 'passwd';
SET @os = 'openstat_database';
SET @s1 = CONCAT("CREATE USER IF NOT EXISTS OS_CHPWD IDENTIFIED BY '",@pwd,"'");
SET @s2 = CONCAT('GRANT SELECT (parentid, id, rolename) ON ',@os,'.`os_roles` TO OS_CHPWD');
SET @s3 = CONCAT('GRANT SELECT (roleid, id, username, pwdhash), UPDATE (pwdhash) ON ',@os,'.`os_users` TO OS_CHPWD');
SET @s4 = CONCAT('GRANT SELECT (userid, password, salt, nonce), UPDATE (password, salt, nonce) ON ',@os,'.`os_passwords` TO OS_CHPWD');
PREPARE stmt1 from @s1;
PREPARE stmt2 from @s2;
PREPARE stmt3 from @s3;
PREPARE stmt4 from @s4;
EXECUTE stmt1; 
EXECUTE stmt2;
EXECUTE stmt3;
EXECUTE stmt4;
FLUSH PRIVILEGES;
