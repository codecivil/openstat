-- allow access to subtablemachine field for everyone
GRANT SELECT (subtablemachine) ON OS_TABLES LIKE '%_permissions' TO OS_ROLES LIKE '%';
FLUSH PRIVILEGES;

-- implement identifiers
ALTER TABLE os_tables ADD COLUMN identifiers TEXT DEFAULT NULL;
