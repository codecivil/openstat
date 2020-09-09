-- add field subtablemachine to _permissions-tables and grant permissions to parentmachine in os_tables
-- this uses the 'openStat-MySQL-extension' 'OS_TABLES LIKE' (see preprocesing in function importSQL); formulate query as if for one table and replace table name by 'OS_TABLES LIKE <PATTERN>'
ALTER TABLE OS_TABLES LIKE '%_permissions' ADD COLUMN subtablemachine VARCHAR(40);
