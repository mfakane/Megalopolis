# Handoff: r46 versus rc48 JSON API comparison

The fixture generation phase is complete. HTTP serving and API comparison have
not been implemented or run. This document records the inputs, existing evidence
and requirements for that next phase.

## Fixed inputs

| Application | Commit | PHP runtime |
| --- | --- | --- |
| r46 | `dff38a8ab25b758e8bb7ea1a621cf33983e9e54e` | 5.2.5 |
| rc48 | `59cac606baf2b407df047b382b9e70e8982122b9` | 8.4 |

Use the rc48 commit above when measuring release compatibility; the working
branch also contains fixture tooling commits and may acquire later app changes.
Record the exact PHP patch versions and database/library versions in each run.

The existing exports are under `r46-fixtures/large/` (ignored by Git):

- `data.sqlite` and `search.sqlite`: SQLite data and FTS search databases.
- `mysql.sql`: MySQL 5.7.44 schema, triggers and data dump.
- `large-{sqlite,mysql}.tsv`: pre-insertion payload hashes and per-work cases.
- `large-{sqlite,mysql}-summary.txt`: verified dimensions and manifest hashes.

Check that these local exports still match this checkpoint, from the repo root:

```sh
sha256sum -c compat/php52/large-fixtures.sha256
```

The checksum file identifies this particular export, not the byte layout of
every future generation. SQLite file layouts and MySQL dump metadata may differ
after regeneration. The payload manifests describe the logical corpus. For a
fresh clone, use the [generation/export instructions](README.md#generate-10000-works),
verify the regenerated corpus, and record its artifact hashes separately.

The retained local Docker project is `megalopolis-r46-large`; its generator
container is `megalopolis-r46-large-generator`. The generator and MySQL container
were stopped at handoff, and their volumes were retained.

## Evidence already obtained

- PHP 5.2.5 CLI, required extensions, SQLite query and separate MySQL connection
  passed the smoke checks; the smoke check still passed after adding generation.
- Both backends contain 100 subjects with exactly 100 works each, 200 authors,
  60 distinct tags, 30,000 work/tag links and 10,000 search documents.
- All 10,000 payload hashes passed per backend, along with table/subject counts,
  trigger-maintained counts, page/style checks, all search IDs, 400 corpus
  combinations and SQLite integrity checks.
- SQLite has supplementary Unicode; MySQL uses BMP substitutes for those 500
  works. The other 9,500 payload hashes match across backends.
- PHP 8.4.21 PDO opened the exported SQLite data/search databases and confirmed
  work/subject counts and 500 bodies each containing `𠮷`, `😀` and `<script>`.
  This was a PDO check, not execution of rc48's application code.
- Reinitializing an existing generated corpus failed without overwriting it.

The corpus covers reads of works and subjects. Comments, evaluations and sessions
are empty. It was written through r46 models, without HTTP form validation or
HTML sanitization. MySQL dump import into a fresh database is still unverified.

## Isolation and comparison contract

Run two independent comparisons: r46 versus rc48 on SQLite, and r46 versus rc48
on MySQL. Within each comparison, seed both applications from the same backend's
export. Do not treat the SQLite/MySQL Unicode differences as release regressions.

Keep the exported baseline unchanged. Give each application its own writable
database copy; restore fresh copies for repeatable runs. The read handler updates
read counts and cookies even for JSON requests, and opening a datastore can run
schema setup. Sharing one live database or mounting the sole baseline read-only
would not reproduce normal application behavior. SQLite needs a writable
directory for journals as well as writable database files.

Align semantically equivalent configuration: visibility, sorting, search, character
encoding, timezone and URL settings. Start with equivalent empty cookie jars and
send the same request sequence to each version. Initially run requests serially
per application so read counts and session state cannot race. Avoid conditional
cache headers when a full JSON response is expected.

Check HTTP status, content type and successful JSON decoding before comparing
payloads. Compare decoded values: object key order and JSON escape spelling may
differ, but preserve array order, numeric/string/boolean types, NULL versus empty
strings, Unicode code points and line endings. Do not normalize away raw content
differences or drop read counts merely because they are stateful.

Separate raw fields (`body`, `afterword`, title, author, tags) from generated HTML
(`formattedBody`, `formattedAfterword`). Both matter. Record HTML differences for
review of intended changes, parser behavior and sanitization; r46 output is a
reference, not proof of correct behavior. Use the manifests as an additional
oracle for fields exposed unchanged by the API. Explicitly account for documented
API transformations and visibility settings when mapping DB fields to responses.

Any normalization or accepted difference must name the exact field/case and
reason. Keep both original responses. A whole-field exclusion for formatted HTML,
or coercing all values to strings, would hide the differences this corpus targets.

## Remaining validation work

- [ ] Provide actual HTTP execution for both fixed application versions. The
  current PHP 5.2 image is CLI-only and lacks JSON/session extensions and CGI.
  Preserve the minimal fixture generation environment; establish the HTTP
  runtime's dependencies separately. Check rc48's dependencies as well.
- [ ] Use normal application configuration and request dispatch. The current
  image installs `fixture.php` as `config.php`, which exits before web dispatch;
  it cannot serve API requests unchanged.
- [ ] Automate seeding/restoring independent data and search stores. Verify that
  MySQL import restores all tables, triggers, charset settings and corpus counts
  before sending requests. Use separate databases/containers for the two versions.
- [ ] Compare subject lists and work details first. IDs `1195084801` through
  `1195085200` cover all 400 text/HTML combinations once. Follow with all 100
  subject lists and all 10,000 work details per backend, including page cases.
- [ ] Save failures by backend, endpoint, work ID, text/HTML case and JSON field
  path, along with HTTP status and both raw responses. Store run artifacts outside
  the baseline, for example under `r46-fixtures/comparison/<run-id>/`.
- [ ] Report tested request counts, decoding/HTTP failures, field differences,
  reviewed exceptions and untouched baseline checksums. Unexplained differences
  or missing cases must fail the comparison; identical error responses are not
  successful work reads.
- [ ] Extend coverage to search/author/tag APIs after the initial comparison.
  Populated comments/evaluations and API-based posting/editing require additional
  fixtures and tests; the current corpus does not cover those workflows.

Useful code entry points in the current tree are
[ReadHandler::index()](../../req/Handler/Read.php),
[IndexHandler](../../req/Handler/Index.php),
[Thread::toArray()](../../req/Model/Thread.php), and
[Visualizer::json()](../../req/Core/Visualizer.php).
