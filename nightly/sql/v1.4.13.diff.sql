-- add field subtablemachine to _permissions-tables and grant permissions to parentmachine in os_tables
-- this uses the 'openStat-MySQL-extension' 'OS_TABLES LIKE' (see preprocesing in function importSQL); formulate query as if for one table and replace table name by 'OS_TABLES LIKE <PATTERN>'
ALTER TABLE OS_TABLES LIKE '%_permissions' ADD COLUMN subtablemachine VARCHAR(40);

-- next step: grant permissions on column subtablemachine to all users
-- problem: @allow is overwritten before executed!
--SELECT @allow:=CONCAT('GRANT SELECT (subtablemachine) ON OS_TABLES LIKE \'%_permissions\' TO ', (SELECT GROUP_CONCAT(rolename) from os_roles where rolename != '_none_') )GRANT SELECT (subtablemachine) ON OS_TABLES LIKE '%_permussions' TO GROUP_CONCAT(rolename from os_roles);
--PREPARE stmt from @allow;
--EXECUTE stmt;
