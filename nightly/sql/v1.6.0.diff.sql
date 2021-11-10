-- ALTER TABLE os_tables ADD COLUMN IF NOT EXISTS parentmachine VARCHAR(40) DEFAULT NULL;
-- Add calendar fields JSON
ALTER TABLE os_tables ADD COLUMN IF NOT EXISTS calendarfields TEXT DEFAULT NULL;
-- Change icsid from int to 40-char string
ALTER TABLE os_caldav MODIFY COLUMN icsid VARCHAR(40);

-- 
-- Create new SECRETS structure
-- 
-- Table os_secrets
DROP TABLE IF EXISTS `os_secrets`;
CREATE TABLE `os_secrets` (`id` int(11) NOT NULL AUTO_INCREMENT,`secretmachine` varchar(40) DEFAULT NULL,`secretreadable` varchar(100) DEFAULT NULL,`secretcomment` TEXT, `allowed_roles` varchar(255) DEFAULT NULL,`allowed_users` varchar(255) DEFAULT NULL,`pwdhash` varchar(255) DEFAULT NULL,PRIMARY KEY (`id`),UNIQUE KEY `secretmachine` (`secretmachine`)) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 `ENCRYPTED`=YES;
INSERT INTO os_secrets VALUES ();
-- Table os_passwords
ALTER TABLE `os_passwords` ADD COLUMN `secretname` varchar(40) DEFAULT NULL;
-- Table os_calendars
ALTER TABLE `os_calendars` DROP COLUMN `calendarpwd`;
ALTER TABLE `os_calendars` ADD COLUMN `secretname` varchar(40) DEFAULT NULL;
UPDATE `os_calendars_permissions` SET keymachine = 'secretname' WHERE keymachine = 'calendarpwd';
-- ROLE user to ROLE secret; no, we dont need to do that
-- INSERT INTO `os_secrets` (secretmachine,secretreadable,allowed_roles) SELECT CONCAT('role_',rolename),CONCAT('Rolle ',rolename),CONCAT('[',os_roles.id,']') FROM os_roles LEFT JOIN ( os_users LEFT JOIN os_passwords AS T2 ON ( os_users.id = userid ) ) ON ( os_roles.id = roleid AND rolename = username);
-- GRANT SELECT to OS_LOGIN user: adapt if name differs in your installation!!!
GRANT SELECT ON os_secrets TO OS_LOGIN;
GRANT SELECT (secretname) ON os_passwords to OS_LOGIN;
FLUSH PRIVILEGES;
-- 
-- 
-- 


-- fix current pwd structure
ALTER TABLE os_roles MODIFY COLUMN pwdhash TEXT;
ALTER TABLE os_users MODIFY COLUMN pwdhash TEXT;
ALTER TABLE os_passwords MODIFY COLUMN `password` TEXT;
