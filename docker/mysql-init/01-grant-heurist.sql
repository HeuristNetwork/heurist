-- Grants for the Heurist admin user over all Heurist databases.
-- The official MySQL image only grants MYSQL_USER rights on MYSQL_DATABASE,
-- but Heurist creates its own hdb_* databases at runtime, so we grant on
-- the whole prefix instead.
GRANT ALL PRIVILEGES ON `hdb_%`.* TO 'heurist'@'%';
FLUSH PRIVILEGES;
