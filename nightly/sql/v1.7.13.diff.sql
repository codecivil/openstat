-- implement user stats
--
-- alter table os_userstats
ALTER TABLE `os_userstats` ADD COLUMN `filtercount` int(11) DEFAULT 1;
-- grant permissions
GRANT SELECT,UPDATE,INSERT,DELETE ON os_userstats TO OS_ROLES LIKE '%';
FLUSH PRIVILEGES;
