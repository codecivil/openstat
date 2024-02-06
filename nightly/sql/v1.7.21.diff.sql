-- add field extras to permssion tables
ALTER TABLE OS_TABLES LIKE '%_permissions' ADD COLUMN extras TEXT;
GRANT SELECT (extras) ON OS_TABLES LIKE '%_permissions' TO OS_ROLES LIKE '%';
