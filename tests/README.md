# Test suite

PHPUnit 12 runs on PHP 8.4. The application under test is **this checkout**, not a
fixed rc48 download. The historical reference is pinned r46 on PHP 5.2.5.

```sh
composer install
composer test                    # local unit tests + immutable fixture checks
bash compat/test.sh             # Docker only; no host PHP/Composer required
bash compat/test.sh db          # DB upgrade/preservation, without API comparison
bash compat/test.sh full        # compare every work's JSON API response
bash compat/upgrade.sh          # writes/auth/search/restore, MySQL 5.7.17 + old SQLite
bash compat/upgrade.sh 5.6.35    # same scenarios, MySQL 5.6.35 + old SQLite
```

The equivalent Composer aliases are `composer test:db`, `composer test:compat` and
`composer test:compat:full`, and `composer test:upgrade`. Docker Compose v2 and Linux amd64 image support are
required. The first run builds PHP 5.2.5 and downloads dependencies; later builds
reuse Docker's cache. Run from any directory using the script's absolute path.

| Suite | Quick | Full |
| --- | --- | --- |
| Unit | Frozen input SHA-256, manifest coverage, JSON/HTML negative cases, renderer and reviewed-exception contracts | Same |
| Database | All 10,000 works, both backends, opened twice through current models | Same |
| JSON subjects | All 100 subjects, both versions and backends | Same |
| JSON works | First 400 (all text/HTML pairs) plus last work, both backends | All 10,000, both backends |

`database` and `json` are integration suites; invoking them without the Docker
harness is an error, not a skip. Plain `vendor/bin/phpunit` includes these suites;
use `composer test` for the local-only checks. GitHub Actions runs quick coverage
on pushes/PRs; a manual run can select full coverage.

## Isolation and evidence

The checked-in [r46 fixtures](Fixtures/r46/README.md) are decompressed/imported into
four independent disposable stores: SQLite/MySQL × r46/current. The baseline
archives are never mounted writable. Each invocation uses a new Compose project;
the EXIT trap removes **only that project's** containers and volumes, including
on test failure. Original `r46-fixtures/` exports and fixture-generation volumes
are not used or modified. `test-results/run-*/` retains JUnit, mismatch JSON and
container logs. A killed host or `kill -9` cannot run the cleanup trap; use the
project name in the log to remove that specific abandoned project.

The read-comparison suite sends only GET requests; the separate upgrade suite
also sends form POSTs to the current application. No host ports are published. A
small CGI gateway serves the original r46 front controller. Current code uses
PHP's development server. Apache and GD are not installed. JSON/session/CGI are
enabled only in the optional historical HTTP build; the default fixture-only
image remains CLI-only. Never expose these obsolete runtimes publicly.

The database probe boots the real current application, opens data and search
stores, and loads each work with `Thread::load`. Each raw payload must match its
**pre-insertion** r46 manifest hash, including nulls, empty text, ordered tags,
Unicode bytes and line endings. Physical schema names/versions are not asserted
for the current store, allowing real migrations to run. Two independent requests
check that reopening the upgraded database preserves the payload again.

JSON tests call real HTTP routes, require HTTP 200 and `application/json`, decode
strictly, and compare all returned fields. Object-key order is ignored;
array order and number/string/null types are compared strictly. Current raw
strings must preserve the source bytes; comparison permits only the explicitly
identified historical PHP 5.2 JSON encoder defect described in
[the comparison policy](COMPARISON-POLICY.md). Formatted HTML is
checked for exact DOM structure, text and attribute values, allowing equivalent
HTML syntax spellings, not arbitrary missing content or tags. Individually
[reviewed r46 HTML defects](HTML-EXCEPTIONS.md) require exact source templates
and separate expected outputs for both versions; current output is checked even
when both versions agree on an old defect. Current raw work
payloads additionally have an independent manifest oracle, checked before the
comparison assertion, so two equally broken
implementations cannot pass merely by agreeing. Subject coverage checks all IDs
and counts. No cookies/cache validators are sent, so each work GET must increment
readCount from zero to one on each isolated database. Re-running PHPUnit against
already-used services is not supported: use the script for a fresh restore.

## Future application/schema changes

Keep the r46 archives and hashes fixed. Add migration code in the application,
then run this suite against the working checkout. Do not re-record fixtures or
silently normalize a failing field. Intentional API changes require an explicitly
reviewed contract change with a narrow regression test. Failed historical HTTP
requests are errors, never accepted as an empty or matching JSON result.

The large fixture covers legacy work/subject reads and payload preservation.
The separate small fixture adds the nonempty response and write scenarios below.
Passing these suites is evidence for the covered contracts, not a proof of every
possible database upgrade path.

See [the initial compatibility findings](BASELINE-STATUS.md) before interpreting
JSON failures: r46's own JSON encoder is not a universally correct oracle.
The [rendering repair verification](REPAIR-STATUS.md) records the subsequent full
run, the individually reviewed HTML differences, and the verification after
those explicit exceptions were approved.

## Upgrade operations and restoration on 2016 databases

`compose.upgrade.yml` isolates destructive test operations from both the large
corpus and the user's exports. Every invocation restores the frozen
[small r46 fixture](Fixtures/r46-upgrade/README.md), using SQLite 3.15.2 for the
historical runtime and actual MySQL 5.7.17 or 5.6.35 for both application versions.
The current PHP 8.4 image uses its current bundled SQLite library: the old
SQLite *file* is carried forward, not an old SQLite server. These are specific
late-2016 versions, not coverage of all 2016 installations or MySQL 5.5.

The `upgrade` suite checks old work/comment/evaluation values, counters and hashes
against pre-insertion expectations; repeated opening; nonempty JSON responses;
native, SHA-1 and DES work keys; native comment and administrator keys; real
CSRF/session-cookie handling; new posting, editing, key rotation and deletion;
comment/evaluation addition and removal; duplicate evaluation rejection; and
full-text/tag searches after changes. Wrong credentials and invalid submissions
must not change stored data. Each mutation scenario owns a different work.

Only fixture seeding and a read-only model projection use test adapters. **Every
operation under test goes through the real front controller and HTTP routes**;
no direct SQL mutation or authentication bypass substitutes for these actions.
The projection is installed only by the isolated Docker configuration and is
never a production route. Current successful output is not used to bless old
inputs or weaken expectations.

Before either application opens a store, the runner copies the closed SQLite
data/search files and takes a real MySQL logical backup. After the operation
suite it restores those backups into **separate empty volumes**, then executes
the `restore` suite even if an operation failed. All six original works,
comments, evaluations and hashes must return unchanged; original search terms
and old editing credentials must work, while new terms must not be present.
Restoration is a pre-upgrade rollback rehearsal, not reverse schema migration
or a claim that r46 can read a database already changed by future versions.

Use the script again for every run; rerunning these stateful suites against used
volumes is unsupported. `upgrade` and `restore` cannot run standalone without
the harness. CI runs both MySQL versions on pushes/PRs. Local artifacts under
`test-results/upgrade-*/` include JUnit, HTTP request/response records (synthetic
test keys included), runtime versions in projection responses, pre-upgrade
backups and their checksums, and container/cleanup logs. Only the generated
project's containers and volumes are removed; backups and frozen inputs remain.

This does not test a production web server, custom configuration, online backups
under concurrent writes, multi-user races, external plugins, or arbitrary
historical schema variants. The obsolete runtimes are offline compatibility
tools and must not be exposed publicly or used as production recommendations.

See [the recorded upgrade results and remaining HTTP-status defect](UPGRADE-STATUS.md)
before interpreting CI failures. This new gate intentionally fails until the
application preserves the expected 401/403/404 responses.
