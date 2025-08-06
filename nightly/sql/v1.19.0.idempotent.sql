-- openStatAdmin-log started 04.08.2025 12:26:20
-- register function loadPublicTemplates
-- makes template fields available as soon as template name is typed in "tags" field
INSERT  INTO `os_functions` (`iconname`,`functionmachine`,`functionreadable`,`functionscope`,`allowed_roles`,`functiontarget`,`functionflags`) SELECT '_none_','loadPublicTemplates','Lade öffentliche Templates','TABLES','[0]','publicTemplates','["AUTO","HIDDEN"]' FROM DUAL WHERE  (SELECT count(functionmachine) FROM `os_functions` where functionmachine = 'loadPublicTemplates') = 0;
-- openStatAdmin-log finished 04.08.2025 12:31:26
