-- register function showEmptyFields
INSERT INTO `os_functions` (`iconname`,`functionmachine`,`functionreadable`,`functionscope`,`allowed_roles`) SELECT 'hand-point-down','showEmptyFields','leere Felder zeigen','DETAILS','[0]' FROM DUAL WHERE  (SELECT count(functionmachine) FROM `os_functions` where functionmachine = 'showEmptyFields') = 0;
-- register function cleanDB
INSERT INTO `os_functions` (`iconname`,`functionmachine`,`functionreadable`,`functionscope`,`functionclasses`,`allowed_roles`,`functiontarget`) SELECT 'broom','cleanDB','Aufräumen','GLOBAL','cleanup','[0]','_popup_' FROM DUAL WHERE  (SELECT count(functionmachine) FROM `os_functions` where functionmachine = 'cleanDB') = 0;
-- autoexecute trafficLight at login
UPDATE `os_functions` SET `functionflags`= '["LOGIN"]' WHERE `functionmachine`= 'trafficLight';
