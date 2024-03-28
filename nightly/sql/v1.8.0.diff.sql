-- add field extras to permssion tables
-- this is needed before updating the software! so this is v1.7.99
GRANT SELECT (defaultvalue) ON OS_TABLES LIKE '%_permissions' TO OS_ROLES LIKE '%';
