-- implement user stats
--
-- create table os_userstats
CREATE TABLE `os_userstats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` int(11) DEFAULT NULL,
  `tablemachine` VARCHAR(40) DEFAULT NULL,
  `keymachine` VARCHAR(40) DEFAULT NULL,
  `unixtimestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 `ENCRYPTED`=YES;
-- grant permissions
GRANT SELECT,UPDATE,INSERT,DELETE ON os_userstats TO OS_ROLES LIKE '%';
