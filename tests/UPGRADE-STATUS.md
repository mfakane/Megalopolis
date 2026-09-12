# 2016 database upgrade verification

Recorded on 2026-09-12 against the working checkout after `e65499f`. This change
adds tests only: no application file or original 10,000-work fixture was changed.

The small frozen DB was created by r46 / PHP 5.2.5 on SQLite 3.15.2 and, separately,
actual MySQL 5.7.17 and 5.6.35. Both MySQL dumps and all pre-insertion work
expectations matched. Each command restored independent r46/current DBs, backed
them up before opening the application, exercised real current HTTP routes, and
then restored backups into new empty stores.

| Command | Unit | Upgrade operations | Backup restoration |
| --- | --- | --- | --- |
| `bash compat/upgrade.sh 5.7.17` | 112 passed / 20,583 assertions | 18 tests / 505 assertions, **10 failures** | 2 passed / 129 assertions |
| `bash compat/upgrade.sh 5.6.35` | 112 passed / 20,583 assertions | 18 tests / 505 assertions, **10 failures** | 2 passed / 129 assertions |

Each row includes SQLite as well as that MySQL version. There were no errors or
skips. Both commands correctly exit nonzero: the upgrade/CI gate is **not green**.
Backup checksum verification passed after restoration. Evidence is retained in
`test-results/upgrade-0QgewPBk/` (5.7.17) and
`test-results/upgrade-oTj983fq/` (5.6.35).

The actual runtime probes reported PHP **5.2.5 / SQLite 3.15.2** on the historical
side and PHP **8.4.25 / SQLite 3.40.1** on the current side. Both PHP versions
connected to the same selected 2016 MySQL server version in their isolated
stores; there was no substitution of a newer MySQL image.

The existing `bash compat/test.sh quick` also passed: **1,314 tests, 392,874
assertions**, zero errors/failures/skips, in `test-results/run-tRtrBujx/`. This
includes all 10,000 work DB payloads on each backend, every subject and the quick
JSON sample. The full 20,000-work live JSON comparison was not rerun here.
All seven checks in `compat/php52/large-fixtures.sha256` still pass.

## Verified behaviors

- Exact old values survive current model loading and repeated opening, including
  null/empty distinctions, comments, linked/standalone evaluations, counters and
  old hashes. The nonempty work JSON matches both the independent expectations
  and r46's output. Read counters increment once for a cookie-preserving visitor.
- Native r46 edit keys can edit a work, rotate the key and update text/tag search.
  The old key then fails without changing data; the new key works.
- SHA-1 keys can delete a work, its comments/evaluations and its search entries.
- Old comment keys work; adding/removing comments maintains linked evaluations
  and response/point counters. Empty comment submissions do not change the DB.
- DES keys authenticate; standalone evaluations can be added/removed, while
  duplicate evaluations and removal of another host's old evaluation fail.
- The r46 administrator hash plus the real session cookie/CSRF token permits
  new posting. Invalid administrator keys and missing tokens create no records.
- Backup restoration recovers all six original works, comments, evaluations,
  hashes and original search results, with no new work left behind. Native,
  SHA-1 and DES keys open the restored edit forms. This is restoration of a
  pre-upgrade backup, not reversal of an upgraded schema.

## Reproduced application defect; expectations are not relaxed

All ten failures in each run concern the HTTP status, not a mismatching stored
payload or failed positive write. Five scenarios fail on each backend:

| Scenario | Expected | Actual |
| --- | ---: | ---: |
| Reusing a rotated old edit key | 401 | 500 |
| Reading a deleted work | 404 | 500 |
| Submitting an incorrect work key | 401 | 500 |
| Posting without a CSRF token | 403 | 500 |
| Posting with an incorrect administrator key | 401 | 500 |

`DataStoreHandle::withTransaction()` in `req/Core/DataStore.php` catches every
Throwable and wraps it in a new `ApplicationException(..., 500, ...)`, losing
the original application HTTP code. Nested `withTransactionCombo()` propagates
that replacement. This explains the observed failures. The tests retain the
401/403/404 expectations and check that rejected requests preserve data **before**
asserting the status, so this defect does not conceal subsequent normal actions.
Application repair is a separate change; no broad exception allowance or
expected-failure annotation was added.

These results support old-data preservation and covered write/restore paths,
but do not constitute release approval. Resolve the HTTP-code defect and rerun
both upgrade commands and the full JSON comparison before a final release.
Production web-server behavior, concurrent writers, custom configuration,
other historical schemas and the previously reported static-analysis findings
remain outside this verification.
