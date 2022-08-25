-- MySQL dump 10.17  Distrib 10.3.22-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: opsz_template
-- ------------------------------------------------------
-- Server version	10.3.22-MariaDB-0+deb10u1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `os_caldav`
--

DROP TABLE IF EXISTS `os_caldav`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `os_caldav` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tablemachine` varchar(40) DEFAULT NULL,
  `id_table` int(11) DEFAULT NULL,
  `id_os_calendars` int(11) DEFAULT NULL,
  `icsid` int(11) DEFAULT NULL,
  `etag` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 `ENCRYPTED`=YES;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `os_caldav`
--

LOCK TABLES `os_caldav` WRITE;
/*!40000 ALTER TABLE `os_caldav` DISABLE KEYS */;
/*!40000 ALTER TABLE `os_caldav` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `os_calendars`
--

DROP TABLE IF EXISTS `os_calendars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `os_calendars` (
  `id_os_calendars` int(11) NOT NULL AUTO_INCREMENT,
  `changedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `changedby` int(11) DEFAULT NULL,
  `id_opsz_aufnahme` int(11) DEFAULT NULL,
  `id_opsz_termine` int(11) DEFAULT NULL,
  `id_opsz_db` int(11) DEFAULT NULL,
  `id_opsz_kosten` int(11) DEFAULT NULL,
  `calendarmachine` varchar(40) DEFAULT NULL,
  `calendarreadable` varchar(40) DEFAULT NULL,
  `calendarurl` varchar(255) DEFAULT NULL,
  `calendaruser` varchar(40) DEFAULT NULL,
  `calendarpwd` varchar(40) DEFAULT NULL,
  `allowed_roles` text DEFAULT NULL,
  `allowed_users` text DEFAULT NULL,
  `id` int(11) DEFAULT NULL,
  `id_opsz_vermittlungslisten` int(11) DEFAULT NULL,
  `code` varchar(8) DEFAULT replace(replace(replace(ucase(left(to_base64(unhex(sha(concat(current_timestamp(),rand())))),8)),'/','1'),'+','2'),'=','3'),
  PRIMARY KEY (`id_os_calendars`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 `ENCRYPTED`=YES;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `os_calendars`
--

LOCK TABLES `os_calendars` WRITE;
/*!40000 ALTER TABLE `os_calendars` DISABLE KEYS */;
/*!40000 ALTER TABLE `os_calendars` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `os_calendars_permissions`
--

DROP TABLE IF EXISTS `os_calendars_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `os_calendars_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `keymachine` varchar(40) DEFAULT NULL,
  `keyreadable` varchar(255) DEFAULT NULL,
  `realid` decimal(6,3) DEFAULT NULL,
  `typelist` varchar(40) DEFAULT NULL,
  `edittype` varchar(60) DEFAULT NULL,
  `defaultvalue` text DEFAULT NULL,
  `referencetag` varchar(40) DEFAULT NULL,
  `role_0` int(11) DEFAULT 0,
  `restrictrole_0` text DEFAULT NULL,
  `role_1` int(11) DEFAULT 0,
  `restrictrole_1` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 `ENCRYPTED`=YES;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `os_calendars_permissions`
--

LOCK TABLES `os_calendars_permissions` WRITE;
/*!40000 ALTER TABLE `os_calendars_permissions` DISABLE KEYS */;
INSERT INTO `os_calendars_permissions` VALUES (1,'changedat','_none_',1.000,'TIMESTAMP','NONE',NULL,'_none_',0,NULL,0,NULL),(2,'changedby','_none_',2.000,'VARCHAR(40)','NONE',NULL,'_none_',0,NULL,0,NULL),(3,'calendarmachine','_none_',3.000,'VARCHAR(40)','NONE',NULL,'_none_',0,NULL,0,NULL),(4,'calendarreadable','Kalender',4.000,'VARCHAR(40)','EXTENSIBLE LIST',NULL,'',6,NULL,0,NULL),(5,'calendarurl','_none_',5.000,'VARCHAR(255)','NONE',NULL,'_none_',0,NULL,0,NULL),(6,'calendaruser','_none_',6.000,'VARCHAR(40)','NONE',NULL,'_none_',0,NULL,0,NULL),(7,'calendarpwd','_none_',7.000,'VARCHAR(40)','NONE',NULL,'_none_',0,NULL,0,NULL),(8,'allowed_roles','_none_',8.000,'TEXT','NONE',NULL,'_none_',0,NULL,0,NULL),(9,'allowed_users','_none_',9.000,'TEXT','NONE',NULL,'_none_',0,NULL,0,NULL),(10,'id','_none_',10.000,'INT','NONE',NULL,'_none_',0,NULL,0,NULL),(11,'code','Code',0.100,'VARCHAR(8)','ID','(REPLACE(REPLACE(REPLACE(upper(LEFT(to_base64(UNHEX(sha1(CONCAT(NOW(),RAND())))),8)),\'/\',\'1\'),\'+\',\'2\'),\'=\',\'3\'))',NULL,6,NULL,0,NULL);
/*!40000 ALTER TABLE `os_calendars_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `os_calendars_references`
--

DROP TABLE IF EXISTS `os_calendars_references`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `os_calendars_references` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `referencetag` varchar(40) DEFAULT NULL,
  `depends_on_key` varchar(80) DEFAULT NULL,
  `depends_on_value` varchar(80) DEFAULT NULL,
  `allowed_values` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 `ENCRYPTED`=YES;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `os_calendars_references`
--

LOCK TABLES `os_calendars_references` WRITE;
/*!40000 ALTER TABLE `os_calendars_references` DISABLE KEYS */;
/*!40000 ALTER TABLE `os_calendars_references` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `os_functions`
--

DROP TABLE IF EXISTS `os_functions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `os_functions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `iconname` varchar(40) DEFAULT NULL,
  `functionmachine` varchar(40) DEFAULT NULL,
  `functionreadable` varchar(255) DEFAULT NULL,
  `functionscope` varchar(40) DEFAULT NULL,
  `functionclasses` varchar(40) DEFAULT NULL,
  `allowed_roles` text DEFAULT NULL,
  `functiontarget` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 `ENCRYPTED`=YES;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `os_functions`
--

LOCK TABLES `os_functions` WRITE;
/*!40000 ALTER TABLE `os_functions` DISABLE KEYS */;
INSERT INTO `os_functions` VALUES (2,'edit','newEntry','Neuer Eintrag','TABLES','details new','[0]','_popup_'),(3,'print','printResults','Ergebnisse drucken','RESULTS',NULL,'[0]',NULL),(4,'print','printResults','Eintrag drucken','DETAILS',NULL,'[0]',NULL),(5,'file-import','importCSV','CSV-import','TABLES','details import','[0]','_popup_'),(6,'puzzle-piece','applyComplement','Komplement zu Filtern suchen','FILTERS',NULL,'[0]','results_wrapper'),(7,'file-export','exportCSV','CSV-Export','RESULTDETAILS','details import','[0]','_popup_');
/*!40000 ALTER TABLE `os_functions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `os_passwords`
--

DROP TABLE IF EXISTS `os_passwords`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `os_passwords` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` int(11) DEFAULT NULL,
  `password` varchar(200) DEFAULT NULL,
  `salt` varchar(100) DEFAULT NULL,
  `nonce` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 `ENCRYPTED`=YES;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `os_passwords`
--

LOCK TABLES `os_passwords` WRITE;
/*!40000 ALTER TABLE `os_passwords` DISABLE KEYS */;
/*!40000 ALTER TABLE `os_passwords` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `os_roles`
--

DROP TABLE IF EXISTS `os_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `os_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parentid` int(11) NOT NULL DEFAULT 0,
  `rolename` varchar(100) DEFAULT NULL,
  `pwdhash` varchar(100) DEFAULT NULL,
  `defaultconfig` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rolename` (`rolename`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 `ENCRYPTED`=YES;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `os_roles`
--

LOCK TABLES `os_roles` WRITE;
/*!40000 ALTER TABLE `os_roles` DISABLE KEYS */;
INSERT INTO `os_roles` VALUES (1,0,'_none_',NULL,NULL);
/*!40000 ALTER TABLE `os_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `os_tables`
--

DROP TABLE IF EXISTS `os_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `os_tables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `iconname` varchar(40) DEFAULT NULL,
  `tablemachine` varchar(40) DEFAULT NULL,
  `tablereadable` varchar(255) DEFAULT NULL,
  `allowed_roles` text DEFAULT NULL,
  `delete_roles` text DEFAULT NULL,
  `displayforeign` text DEFAULT NULL,
  `tietotables` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 `ENCRYPTED`=YES;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `os_tables`
--

LOCK TABLES `os_tables` WRITE;
/*!40000 ALTER TABLE `os_tables` DISABLE KEYS */;
INSERT INTO `os_tables` VALUES (1,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `os_tables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `os_userconfig`
--

DROP TABLE IF EXISTS `os_userconfig`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `os_userconfig` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` int(11) DEFAULT NULL,
  `config` text DEFAULT NULL,
  `configname` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 `ENCRYPTED`=YES;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `os_userconfig`
--

LOCK TABLES `os_userconfig` WRITE;
/*!40000 ALTER TABLE `os_userconfig` DISABLE KEYS */;
/*!40000 ALTER TABLE `os_userconfig` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `os_users`
--

DROP TABLE IF EXISTS `os_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `os_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(40) DEFAULT NULL,
  `pwdhash` varchar(200) DEFAULT NULL,
  `roleid` int(11) DEFAULT NULL,
  `rolepwd` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 `ENCRYPTED`=YES;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `os_users`
--

LOCK TABLES `os_users` WRITE;
/*!40000 ALTER TABLE `os_users` DISABLE KEYS */;
INSERT INTO `os_users` VALUES (1,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `os_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `os_userprofiles`
-- profile fields are currently defined in os_function updateProfile; the fields here store json of the profile fields to the respective access level

DROP TABLE IF EXISTS `os_userprofiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `os_userprofiles` (
  `userid` int(11) DEFAULT NULL,
  `_private` TEXT DEFAULT NULL,
  `_machine` TEXT DEFAULT NULL,
  `_public` TEXT DEFAULT NULL,
  PRIMARY KEY (`userid`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 `ENCRYPTED`=YES;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

/* set the password for the login user here; also put it into /core/data/logindata.php ! */
SET @pwd_login = 'password';

/* set the password for the password changing user here; also put it into /core/data/chpwddata.php ! */
SET @pwd_chpwd = 'passwd';

/* create login user */
SET @stmt = CONCAT("CREATE USER OS_LOGIN IDENTIFIED BY '",@pwd_login,"'");
PREPARE stmt FROM @stmt;
EXECUTE stmt;
REVOKE ALL PRIVILEGES, GRANT OPTION FROM OS_LOGIN;
GRANT SELECT (id,roleid,pwdhash,username) ON os_users TO OS_LOGIN;
GRANT SELECT (id,rolename,parentid) ON os_roles TO OS_LOGIN;
GRANT SELECT (userid,password,salt,nonce) ON os_passwords TO OS_LOGIN;
FLUSH PRIVILEGES;

/* create password changing user */
SET @s1 = CONCAT("CREATE USER IF NOT EXISTS OS_CHPWD IDENTIFIED BY '",@pwd_chpwd,"'");
PREPARE stmt1 from @s1;
EXECUTE stmt1; 
GRANT SELECT (parentid, id, rolename) ON `os_roles` TO OS_CHPWD;
GRANT SELECT (roleid, id, username, pwdhash), UPDATE (pwdhash) ON `os_users` TO OS_CHPWD;
GRANT SELECT (userid, password, salt, nonce), UPDATE (password, salt, nonce) ON `os_passwords` TO OS_CHPWD;
FLUSH PRIVILEGES;

/* flush logs to prevent password leakage */
FLUSH LOGS;
-- Dump completed on 2020-05-03 14:42:00
