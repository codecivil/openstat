-- add flags for functions, e.g. for autostart...
-- functionflags values are json strings
-- implemented values: AUTO (execute when scope is created), LOGIN (execute once at login), HIDDEN (do not show)
-- maybe later: CRON (look schedule up in a new os_cron table)
ALTER TABLE os_functions ADD COLUMN functionflags TEXT;
