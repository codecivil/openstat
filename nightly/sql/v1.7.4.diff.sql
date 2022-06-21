-- create table osadm_sqlimport
CREATE TABLE `osadm_sqlimport` (
  `sqlfilename` varchar(255) NOT NULL,
  `importresult` TEXT DEFAULT NULL,
  `importtimestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`sqlfilename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 `ENCRYPTED`=YES;
