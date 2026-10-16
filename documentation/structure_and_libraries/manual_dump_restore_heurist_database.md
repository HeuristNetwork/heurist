# Manually dump and restore a Heurist database

This guide explains how to create an SQL backup of a Heurist database with `mysqldump` and restore it with the `mysql` command-line client.

> An SQL dump contains the database only. It does **not** include uploaded files from the corresponding Heurist filestore directory.

## 1. Identify the database name

Heurist database names normally use the `hdb_` prefix, for example:

```text
hdb_MyProject
```

In the commands below, replace:

- `DB_HOST` with the MySQL server name or IP address;
- `3306` with the MySQL port, if different;
- `DB_USER` with a MySQL user that can read, create, and restore databases;
- `hdb_MyProject` with the full database name;
- `/path/to/backup.sql` with the required dump-file path.

## 2. Dump the database

```bash
mysqldump \
  -h DB_HOST \
  -P 3306 \
  -u DB_USER \
  -p \
  --single-transaction \
  --quick \
  --add-drop-table \
  --routines \
  --triggers \
  --default-character-set=utf8mb4 \
  hdb_MyProject > /path/to/backup.sql
```

The command prompts for the MySQL password. Avoid placing the password directly in the command because it may be exposed through shell history or the process list.

Check that the dump was created and is not empty:

```bash
ls -lh /path/to/backup.sql
```

## 3. Restore under the original database name

Create an empty database first:

```bash
mysql -h DB_HOST -P 3306 -u DB_USER -p \
  -e "CREATE DATABASE \`hdb_MyProject\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
```

Then import the dump:

```bash
mysql \
  -h DB_HOST \
  -P 3306 \
  -u DB_USER \
  -p \
  --default-character-set=utf8mb4 \
  hdb_MyProject < /path/to/backup.sql
```

## 4. Replace an existing database

This permanently removes the existing database. Confirm that the backup is valid before running it.

```bash
mysql -h DB_HOST -P 3306 -u DB_USER -p \
  -e "DROP DATABASE IF EXISTS \`hdb_MyProject\`; CREATE DATABASE \`hdb_MyProject\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

mysql -h DB_HOST -P 3306 -u DB_USER -p \
  --default-character-set=utf8mb4 \
  hdb_MyProject < /path/to/backup.sql
```

## 5. Restore the filestore

Restore the matching Heurist filestore directory separately. Its folder name normally matches the database name without the `hdb_` prefix. Database and filestore backups should come from the same point in time.

After restoration, verify that Heurist can open the database, display records, access uploaded files, and save a test change.
