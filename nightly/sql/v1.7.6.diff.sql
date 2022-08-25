-- implement user profiles
--
-- create table os_userprofiles
CREATE TABLE `os_userprofiles` (
  `userid` int(11) DEFAULT NULL,
  `_private` TEXT DEFAULT NULL,
  `_machine` TEXT DEFAULT NULL,
  `_public` TEXT DEFAULT NULL,
  PRIMARY KEY (`userid`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 `ENCRYPTED`=YES;
-- grant permissions
GRANT SELECT,UPDATE,INSERT ON os_profiles TO OS_ROLES LIKE '%';
-- register global function
INSERT  INTO `os_functions` (`iconname`,`functionmachine`,`functionreadable`,`functionscope`,`functionclasses`,`allowed_roles`,`functiontarget`) VALUES ('user','editProfile','Profil bearbeiten','GLOBAL','profile section','[0]','_popup_');
