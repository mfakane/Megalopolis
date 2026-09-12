# PHP 5.2.5 / Megalopolis r46 fixtures

This environment generates legacy database fixtures for compatibility tests.
PHP 5.2.5 and MySQL 5.7 are obsolete; use only disposable test data here. The
Compose services publish no ports and use dedicated named volumes.

From the repository root, with Docker Engine and Docker Compose v2 or later:

```sh
docker compose -f compose.compat.yml up --build --abort-on-container-exit --exit-code-from php52
```

The first build compiles PHP from source. Both services use `linux/amd64`; ARM
hosts need Docker's amd64 emulation. The command waits for an authenticated
MySQL query to succeed, runs the checks, stops the services, and returns the
PHP test's exit status. Success ends with `PASS all requested compatibility checks`.

## What is tested

- Exactly PHP **5.2.5 CLI**, PDO SQLite, PDO MySQL, mbstring, and Japanese
  UTF-8 / SJIS-win conversion.
- An in-memory SQLite PDO query (`SELECT 20 + 22` returns `42`).
- Megalopolis **r46**, commit `dff38a8ab25b758e8bb7ea1a621cf33983e9e54e`:
  its original `index.php` and `req/` bootstrap, `App::openDB()`, schema/version
  creation, `SessionStore`, `Thread::save()`, and `SearchIndex::register()`.
- SQLite data and search databases, and a TCP connection to the separate
  **MySQL 5.7.44** container at `mysql:3306`.
- The 13 application tables, search index, a Japanese thread and session,
  persisted title/author/body after reopening, and SQLite integrity checks.

`fixture.php` becomes the image's r46 `config.php`. It runs after r46 loads its
classes and exits before web dispatch. The r46 application code is unmodified;
the current checkout's PHP 8 code and configuration are not used. Failures exit
nonzero. Repeating the check replaces the test thread with ID `1195084800` and
the `php52-fixture` session in these dedicated fixture databases.

The runtime contains only the CLI executable and its libraries. PHP is built
with `--disable-all` plus PDO, PDO SQLite/MySQL, mbstring, hash (required by
r46's preconditions/password hashing), and PCRE (used by r46). There is no
Apache, CGI, GD, Composer, or compiler in the runtime image.

This reproduces the PHP interpreter and r46 schema, not an entire historical
OS: Debian Bookworm supplies SQLite (including FTS4 and recursive triggers)
and the MariaDB client library used by PDO MySQL. MySQL runs with
`NO_ENGINE_SUBSTITUTION` SQL mode for r46's legacy queries. PHP and r46 source
archives are pinned by SHA-256 in the Dockerfile; Debian package updates can
change library patch versions. Source downloads come from the
[PHP archive](https://museum.php.net/php5/) and
[r46 commit](https://github.com/mfakane/Megalopolis/tree/dff38a8ab25b758e8bb7ea1a621cf33983e9e54e).

## Export fixtures

After the full check above, the stopped PHP container still provides both
SQLite files. Export into a new directory:

```sh
mkdir r46-fixtures
docker compose -f compose.compat.yml cp php52:/fixtures/data.sqlite r46-fixtures/data.sqlite
docker compose -f compose.compat.yml cp php52:/fixtures/search.sqlite r46-fixtures/search.sqlite
```

To export MySQL, restart its container and wait for readiness:

```sh
docker compose -f compose.compat.yml up -d --wait mysql
docker compose -f compose.compat.yml exec -T mysql sh -c \
  'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump --user="$MYSQL_USER" --no-tablespaces --set-gtid-purged=OFF --triggers "$MYSQL_DATABASE"' \
  > r46-fixtures/mysql.sql
docker compose -f compose.compat.yml stop mysql
```

The dump includes r46's schema, triggers and sample data. These fixtures contain
one sample thread and session; they are not a comprehensive migration dataset.

## Individual checks and cleanup

```sh
docker compose -f compose.compat.yml run --rm --no-deps php52 -v
docker compose -f compose.compat.yml run --rm --no-deps php52 -m
docker compose -f compose.compat.yml run --rm --no-deps php52 index.php sqlite
docker compose -f compose.compat.yml run --rm php52 index.php mysql
docker compose -f compose.compat.yml down
```

`down` retains both named volumes. To discard **all generated fixture data**
and start with empty databases, use `docker compose -f compose.compat.yml down -v`.
Export anything you need first. The MySQL-only `run` command leaves MySQL running
until it is stopped or taken down.
