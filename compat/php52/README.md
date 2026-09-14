# PHP 5.2.5 / Megalopolis r46 fixtures

This environment generates legacy database fixtures for compatibility tests.
PHP 5.2.5 and MySQL 5.7 are obsolete; use only disposable test data here. The
Compose services publish no ports and use dedicated named volumes.

The 10,000-work corpus is generated and verified. The separate
[PHPUnit compatibility suite](../../tests/README.md) restores committed r46 DB
snapshots and tests the current checkout's models and JSON API:
`bash compat/test.sh` (or `bash compat/test.sh full`). The
[original handoff](API-COMPARISON.md) records the fixture-generation checkpoint.

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

The dump includes r46's schema, triggers and sample data. These smoke fixtures contain
one sample thread and session; they are not a comprehensive migration dataset.

## Generate 10,000 works

Use a separate Compose project so its databases and volumes are independent of
the smoke fixtures. The generator requires empty application tables and refuses
to overwrite an existing corpus, including an interrupted generation.

```sh
docker compose -p megalopolis-r46-large -f compose.compat.yml run --build \
  --name megalopolis-r46-large-generator php52 index.php generate all
```

This creates **100 subjects (作品集), exactly 100 works each**, on both SQLite
and MySQL, using r46's `Thread::save()` and `SearchIndex::register()`. Each work
has a unique ID/title, three tags, style settings and a search document. There
are 200 synthetic authors. Comments, evaluations and sessions are empty.

The corpus is deterministic: IDs `1195084801`–`1195094800`, timestamps, text,
and distribution are fixed. `corpus.php` defines 20 character cases and 20 HTML
cases. Every combination appears 25 times. Coverage includes:

- Japanese, half/full width, variant kanji, combining characters, Latin, Greek,
  Cyrillic, Arabic, Hebrew, Devanagari, Hangul, Chinese, symbols, quotes,
  entities, spaces and invisible/directional characters.
- SQLite includes emoji, supplementary kanji (`𠮷` etc.), ZWJ emoji and other
  four-byte UTF-8 characters. MySQL uses explicit BMP substitutes for that case
  because r46's tables and connection use legacy `utf8`. The schema is not
  converted to `utf8mb4`; the two corpora intentionally differ in these 500
  works. The other 9,500 payload hashes match between the two databases.
- Paragraphs, inline formatting, ruby, headings, lists, quotations, pre/code,
  tables, links, images, styles/legacy tags, entities, comments, malformed HTML,
  custom/SVG/MathML tags, forms, and script/event-handler markers.
- Mixed LF/CRLF/CR, NULL versus empty summaries/afterwords, long bodies over
  64 KiB, and 500 three-page works using r46's `<split/>` delimiters.

These are raw **database** fixtures: web form validation and HTML sanitization
are not invoked. The script/event-handler examples contain only test markers;
consuming UI tests should treat the contents as untrusted HTML. Links use
`example.invalid`; image paths refer to nonexistent local fixture files.

One PHP process writes each subject to bound r46's `create_function()` memory
growth and reset its per-request caches. Generation uses transactions per
subject (MySQL's MyISAM search index remains nontransactional). Interrupted
output is preserved and is not marked complete; start with fresh volumes to
regenerate rather than resuming a partially written subject.

After generation, a fresh process checks every work's stored bytes against a
SHA-256 computed **before insertion**, including title, name, summary, body,
afterword and ordered tags. It also checks all subject/table counts, trigger-
maintained author/tag counts, style/page settings, all search document IDs,
the 400-case distribution and SQLite integrity.

Export the verified corpus (the generator container is retained for copying):

```sh
mkdir -p r46-fixtures/large
docker cp megalopolis-r46-large-generator:/fixtures/. r46-fixtures/large/
docker compose -p megalopolis-r46-large -f compose.compat.yml exec -T mysql sh -c \
  'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump --user="$MYSQL_USER" --no-tablespaces --set-gtid-purged=OFF --triggers "$MYSQL_DATABASE"' \
  > r46-fixtures/large/mysql.sql
docker compose -p megalopolis-r46-large -f compose.compat.yml stop mysql
```

`data.sqlite`, `search.sqlite` and `mysql.sql` contain the datasets.
`large-{sqlite,mysql}.tsv` records each work's ID, subject, case names, body byte
length, page count and payload SHA-256. A `.partial` suffix means verification
has not completed. The matching `large-*-summary.txt` files describe the
verified dimensions, Unicode profile and manifest checksum.

For independent PHP 8.4 checks, hash the following ordered values: title, name,
summary, body, afterword, tag at position 0, tag at position 1, tag at position 2.
Feed each NULL as ASCII `N;`; feed each UTF-8 byte string as
`S<byte-length>:<bytes>;` into SHA-256, with no separator between fields beyond
this framing. SQL NULL and an empty string (`S0:;`) are different.

To verify again without regenerating works:

```sh
docker compose -p megalopolis-r46-large -f compose.compat.yml run --rm --no-deps php52 index.php generate sqlite verify
docker compose -p megalopolis-r46-large -f compose.compat.yml run --rm php52 index.php generate mysql verify
docker compose -p megalopolis-r46-large -f compose.compat.yml stop mysql
```

Use `generate sqlite` or `generate mysql` to generate only one database. A new
generation needs a fresh project/container name, or removal of the previous
project's generated data after export with
`docker compose -p megalopolis-r46-large -f compose.compat.yml down --remove-orphans -v`.

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
