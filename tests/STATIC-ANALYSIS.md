# Static analysis and retained compatibility APIs

Run `composer analyse` after installing locked dependencies with Composer, or
`bash compat/analyse.sh` without host PHP. The Docker command builds the same
current PHP 8.4 image as the compatibility suite and mounts the checkout's real
`config.php` read-only instead of analyzing the test-only DB configuration.
Analysis runs without container network access or a database. Building the image
may download dependencies. CI runs `composer analyse` before unit tests.

`psalm.xml` still uses error level 3 and the same project scope: `config.php`,
`index.php`, and `req/`. No baseline file, directory exclusion, global unused-code
disablement, or lower error level was added. Passing Psalm is not a security
audit or proof of release readiness.

At the current error level there are zero errors. With `--show-info=true`, 22
informational findings remain: 20 `ClassMustBeFinal` suggestions, one
`RiskyTruthyFalsyComparison` for the renderer's optional tag filter, and one
`UnresolvableInclude` for the notice template's runtime-selected file. These are
not silently suppressed. Blanket `final` additions would restrict inheritance;
the filter's null/empty semantics and notice inclusion policy merit separate
review rather than a behavior change merely to hide an informational message.

## Decisions for the 72 findings after ffff452

Six findings were resolved by code changes:

- RSS/Atom templates explicitly call `Visualizer::converted()`. Their previous
  `self` referred to the scope inherited from `Visualizer::visualize()` at runtime;
  this is a scope clarification, not a reproduced HTTP crash fix.
- `App::callHandler()` assigns the selected class's `$instance` directly instead
  of evaluating PHP source. This also removes three unused-parameter false
  positives without changing argument names, handler selection or return values.
- The private, uncalled `App::stripSlashesRecursive()` helper was removed. Only
  its own recursive call remained; it was not a public compatibility API.

The other 66 findings concern deliberate public surfaces or analysis limitations.
They are documented at the declaration instead of changing application behavior:

| Surface | Reason retained |
| --- | --- |
| Index/Read/Megalith/Util handlers | URL and handler-name dispatch selects actions dynamically; `@api` marks these controller entry points. |
| SessionStore | PHP calls registered SessionHandlerInterface methods. The optional adapter remains available to custom bootstrap code; it was **not re-enabled** in Auth. |
| MySQLDataStore constructor | Selected by user configuration, while the checked-in default selects SQLite. |
| App::resolve, Auth::createToken/ensureToken, Util::createFullTextTableIfNotExists | Existing public return values remain available even when built-in callers discard them. |
| Auth::ensureSessionID, Util::hasLength, SearchIndex attachment hooks, Statistics, ThreadEntity, Thread::getThreadsBySubject, ThreadEntry::getMegalithEntryIDsBySubject | Legacy public helpers/data carriers are retained conservatively for custom handlers, templates and migration tools. This does not assert that external consumers were found or that every helper has been tested. |
| Configuration::storeSessionIntoDataStore/subjectOrder | Existing config keys are retained. Both are currently ignored by the relevant built-in behavior; annotations do not activate those features. |
| Comment/Evaluation/Thread/ThreadEntry::loaded | Public model state is retained for external consumers. |
| Thread comment/evaluation lookups and Template entryInfo helpers | Existing parameters, including their names for PHP named arguments, remain compatible even where unused by the current implementation. |
| DataStore::registerHandle and Thread constructor | The parameters are used by storing references to the caller's variables. Narrow unused-parameter annotations preserve that behavior. |

`@api` identifies callable public surfaces, not a general suppression of type
errors. Property and parameter exceptions name only the unused issue at the
relevant declaration. Do not copy these annotations onto new unused code without
checking its callers and documenting a concrete retention reason. Removing
legacy public APIs should be a separate, explicitly reviewed compatibility change.

## Regression coverage

`ReferenceContractTest` reassigns actual ThreadEntry/PDO variables after passing
them to the application, then checks model IDs and the tables visible through
the retained connection. Removing the reference assignments fails these tests.

The `feeds` suite requests real current RSS/Atom routes against restored r46
SQLite and MySQL databases, checking HTTP 200, media type, strict XML parsing,
generator text and all 100 work IDs in order for subjects 1 and 100. Title, author
and ordered categories are checked against the independently frozen SQLite
fixture; shared BMP cases also apply to MySQL. The intentionally different MySQL
supplementary-character case is not compared against SQLite text. XML-defined
line-ending/attribute whitespace normalization is accounted for explicitly.
Generator expectations deliberately name the current release; update them with
the App version at release time. These checks do not claim full RSS/Atom standards
compliance or compare feeds against r46's historical rendering.

The existing DB/JSON and 2016 write/auth/search/restore suites exercise the
dispatcher after removal of `eval`, without changing any prior expectations.

The new feed assertions also exposed a runtime defect beyond the original Psalm
findings: the shared HTML whitespace compactor removed trailing spaces from
author names (for example frozen work 1195084895). RSS/Atom now bypass that
compactor so XML text preserves source whitespace. The failing assertion was
kept; no trim allowance was added. HTML, JSON and database paths are unchanged.

## Verification on 2026-09-12

Verified against the working checkout after `ffff452`, using locked Psalm 6.10.2,
PHPUnit 12.5.35 and PHP 8.4.25. All final commands exited successfully:

| Command | Result |
| --- | --- |
| `bash compat/analyse.sh` | 0 errors at unchanged error level 3; 22 informational findings as listed above |
| Unit suite | 125 tests / 20,672 assertions |
| `bash compat/test.sh quick` | 1,335 tests / 397,767 assertions, including 8 feed tests / 4,804 assertions |
| `bash compat/upgrade.sh 5.7.17` | Unit 125 / 20,672; operations 18 / 505; restoration 2 / 129 |
| `bash compat/upgrade.sh 5.6.35` | Unit 125 / 20,672; operations 18 / 505; restoration 2 / 129 |
| `sha256sum -c compat/php52/large-fixtures.sha256` | All 7 original exports unchanged |

Final HTTP/JUnit/container evidence is in `test-results/run-SjozYJcc/`,
`test-results/upgrade-vqvd19vS/` (5.7.17), and
`test-results/upgrade-7IenxwLu/` (5.6.35). Both upgrade rows also include SQLite.
No failures, errors or skips occurred in the final suites. All generated
containers/volumes were cleaned up; backups and source fixtures remain intact.

An earlier large-DB attempt (`test-results/run-5npxtCUj/`) hit the MySQL startup
health-check deadline while several DB suites were initializing concurrently.
The successful retry used fresh stores after the competing initialization had
finished; no timeout threshold or assertion was relaxed. The direct SQLite feed
check initially failed on the author-name trailing space and passed after the
XML-only compactor fix.

The initial checkpoint above used the quick JSON sample. The full comparison was
subsequently completed before committing, as recorded below. Production
configuration and concurrent-write release rehearsals remain separate work.

## Full JSON comparison before commit

On 2026-09-12, the full `unit,database,json,feeds` suite with `COMPAT_FULL=1`
passed: **20,533 tests / 557,626 assertions**, zero errors, failures or skips,
in 24 minutes 56 seconds. JUnit independently records 20,000 work comparisons
(10,000 per backend) and 200 subject comparisons. Each JSON comparison calls
both r46/PHP 5.2.5 and the current PHP 8.4.25 application. This large-fixture run
uses SQLite 3.40.1 and MySQL 5.7.44; the 2016-server operation/restore results
are recorded separately above.

The ordinary `bash compat/test.sh full` build was attempted twice, but Docker Hub
metadata requests timed out before tests started. The successful run reused the
previously verified `megalopolis-test-run-sjozyjcc-current` and `-legacy` images,
with current application code, tests and HTTP harness mounted read-only. The
cached dependency lockfile matched the checkout. Only image building/pulling was
bypassed: fresh isolated stores, seeding, health checks, unit/DB/JSON/feed suites,
runtime probes and cleanup followed the existing runner. Neither fixtures nor
comparison rules nor timeout thresholds were changed.

Evidence is retained in `test-results/full-cached-BC1UIN8R/`: the cached Compose
override and runner, resolved configuration, image IDs, runtime versions, source
patch, JUnit, full log, per-route responses, audit summary and cleanup log.
The application's diff still matched the recorded patch after the run.
The two registry-failure logs remain in `test-results/run-dewgpNRW/` and
`test-results/run-NUWpUwB5/`.

Auditing all 11,384 recorded comparison-route artifacts found **zero unaccepted
fields**. Accepted field differences used only the existing rules:

| Existing rule group | Accepted fields (not response counts) |
| --- | ---: |
| Equivalent HTML trees | 6,391 |
| PHP 5.2 supplementary-character JSON defect, raw fields | 4,501 |
| Same historical encoder defect in formatted HTML | 542 |
| Ten individually reviewed r46 HTML rules | 5,250 |

Thus the outputs satisfy the reviewed compatibility contract; they are not all
byte-identical. No new allowance was introduced. All seven original-export
checksums still pass and the run's containers/volumes have been removed. The
PHPDoc-only consolidation in Template was also checked with Psalm: zero errors
at the unchanged error level.
