# Test suite

PHPUnit 12 runs on PHP 8.4. The application under test is **this checkout**, not a
fixed rc48 download. The historical reference is pinned r46 on PHP 5.2.5.

```sh
composer install
composer test                    # local unit tests + immutable fixture checks
bash compat/test.sh             # Docker only; no host PHP/Composer required
bash compat/test.sh db          # DB upgrade/preservation, without API comparison
bash compat/test.sh full        # compare every work's JSON API response
```

The equivalent Composer aliases are `composer test:db`, `composer test:compat` and
`composer test:compat:full`. Docker Compose v2 and Linux amd64 image support are
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

Only GET requests run in the isolated network; no host ports are published. A
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

This initial suite covers legacy work/subject reads and payload preservation,
not every application behavior. The fixture has no comments/evaluations, so their
nonempty cases, authentication, posting, search result semantics and write-after-
migration need separate tests. Passing this suite is evidence for the covered
contract, not a proof of all possible database upgrade paths.

See [the initial compatibility findings](BASELINE-STATUS.md) before interpreting
JSON failures: r46's own JSON encoder is not a universally correct oracle.
The [rendering repair verification](REPAIR-STATUS.md) records the subsequent full
run, the individually reviewed HTML differences, and the verification after
those explicit exceptions were approved.
