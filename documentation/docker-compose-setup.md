# Run Heurist with Docker Compose

This guide walks through installing Docker, starting Heurist, and creating
your first database. The Compose setup runs Heurist's PHP/Apache web app and a
MySQL database in containers. You do not need to install PHP, Apache, MySQL,
or Composer on your computer.

## 1. Install Docker and Docker Compose

Install Docker for your operating system using Docker's official instructions:

- **Windows 10/11:** [Install Docker Desktop for Windows](https://docs.docker.com/desktop/setup/install/windows-install/).
  Docker Desktop uses the WSL 2 backend for Linux containers; follow the
  installer prompts and start Docker Desktop before continuing.
- **macOS:** [Install Docker Desktop for Mac](https://docs.docker.com/desktop/setup/install/mac-install/).
  Choose the download for your Mac's processor (Apple silicon or Intel).
- **Linux desktop:** [Install Docker Desktop for Linux](https://docs.docker.com/desktop/setup/install/linux/)
  (with pages for [Ubuntu](https://docs.docker.com/desktop/setup/install/linux/ubuntu/),
  [Debian](https://docs.docker.com/desktop/setup/install/linux/debian/), and
  [Fedora](https://docs.docker.com/desktop/setup/install/linux/fedora/)).
- **Linux server:** Install Docker Engine for your distribution from the
  [Docker Engine installation guide](https://docs.docker.com/engine/install/)
  (including [Ubuntu](https://docs.docker.com/engine/install/ubuntu/),
  [Debian](https://docs.docker.com/engine/install/debian/), or
  [Fedora](https://docs.docker.com/engine/install/fedora/)), then install the
  [Docker Compose plugin](https://docs.docker.com/compose/install/linux/).

On Windows, macOS, and Linux Desktop, Docker Desktop includes Compose. On
Linux Engine, install the Compose plugin as described in Docker's instructions.
This project uses the Compose v2 command with a space: `docker compose`.

Open a terminal (PowerShell or Windows Terminal on Windows) and confirm Docker
is installed and running:

```sh
docker --version
docker compose version
docker info
```

Both version commands should print a version. `docker info` should show server
details; if it reports that it cannot connect to the daemon, start Docker
Desktop or the Docker service and try again.

## 2. Get the Heurist code

If you have not already cloned the repository, run:

```sh
git clone https://github.com/HeuristNetwork/heurist.git
cd heurist
```

If you already have a Heurist checkout, open a terminal in its top-level
folder—the folder containing `docker-compose.yml`—instead.

## 3. Start Heurist

Run:

```sh
docker compose up -d --build
```

The first run builds the web image and downloads the required packages and
support files. It can take several minutes and needs an internet connection.
Later starts reuse the built image and downloaded files. Compose starts the
database and waits for it to become healthy before starting the web app.

Check that the services are running:

```sh
docker compose ps
```

The `web` service should show `Up`, and `db` should show `Up` with a healthy
status. Open the Heurist setup page:

**[http://localhost:8080/heurist/startup/](http://localhost:8080/heurist/startup/)**

The web service is published on the local computer at port 8080. The Compose
file binds it to `127.0.0.1`, so it is not directly exposed to other computers
on the network.

## 4. Create your first Heurist database

On the startup page, choose the option to create a new database and follow the
registration steps. Choose a database name and register the initial user, who
will own and administer that database. ORCID, research interests, and personal
website URLs are optional profile fields. When setup completes, Heurist opens
the new database.

Heurist creates its databases inside the MySQL service; you do not need to
create a MySQL database yourself.

## 5. Configure passwords and data location (optional)

Compose has development defaults so the stack can start without extra setup.
To set your own database passwords and API signing secret, create a file named
`.env` beside `docker-compose.yml` before the first startup:

```dotenv
HEURIST_MYSQL_ROOT_PASSWORD=choose-a-strong-root-password
HEURIST_DB_ADMIN_PASSWORD=choose-a-strong-app-password
HEURIST_JWT_SECRET=choose-a-long-random-secret
```

Keep `.env` private; it is excluded from Git. If you change the database
password after MySQL has initialized its data directory, update the MySQL user
password to match as well—the MySQL image only applies its initialization
passwords to a new data directory.

By default, persistent files are stored in the repository's `data/` folder:
database files in `data/db`, uploads in `data/filestore`, support bundles in
`data/support`, and Composer dependencies in `data/vendor`. To store them
elsewhere, add a `HEURIST_DATA_ROOT` entry to `.env`, for example:

```dotenv
HEURIST_DATA_ROOT=/path/to/heurist-data
```

Use a path writable by Docker. On Windows, use a path in a drive shared with
Docker Desktop, such as `C:/Users/YourName/heurist-data`.

## 6. Common commands

Run these commands from the folder containing `docker-compose.yml`:

```sh
docker compose ps                 # service status
docker compose logs -f web        # follow web and PHP logs; Ctrl+C stops following
docker compose logs -f db         # follow database logs
docker compose restart web        # restart the web service
docker compose down               # stop and remove containers; keep persistent data
docker compose up -d              # start again
```

The repository is bind-mounted into the web container, so ordinary PHP,
JavaScript, CSS, and template edits appear immediately. Rebuild after changing
the `Dockerfile`, files under `docker/`, or `docker-compose.yml`:

```sh
docker compose up -d --build
```

To update the repository and rebuild, use `git pull` followed by the same
`docker compose up -d --build` command.

**Data deletion:** `docker compose down` preserves databases and uploaded
files. `docker compose down -v` removes the Compose data volumes and deletes
that persistent data. The default `./data` directory is bind-mounted and is
not removed by `down -v`; remove it yourself only if you intend to erase the
local database, uploads, support files, and dependencies.

## Troubleshooting

- **The page does not load:** Run `docker compose ps` and
  `docker compose logs --tail=100 web`. Confirm the address uses port `8080`.
- **The web service exits or waits for MySQL:** Check
  `docker compose logs --tail=100 db`; wait for the database health check and
  then run `docker compose ps` again.
- **A startup change is not showing:** Rebuild with
  `docker compose up -d --build`, then refresh the browser.
- **Port 8080 is already in use:** Stop the other service using that port, or
  change the host side of the `ports` mapping in `docker-compose.yml` (the
  number before `:8080`) and use that port in the browser address.
- **You need more detail about this development stack:** See
  [Docker development stack notes](docker-dev-stack.md).
