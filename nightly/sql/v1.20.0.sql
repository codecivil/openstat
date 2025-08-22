-- realid needs to support compounds and this is now TEXT instead of DECIMAL(6,3)
ALTER TABLE OS_TABLES LIKE '%_permissions' MODIFY COLUMN realid TEXT;
