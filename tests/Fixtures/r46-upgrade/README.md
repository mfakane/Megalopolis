# Small r46 upgrade fixture

These immutable inputs supplement, not replace, the 10,000-work corpus. They
were generated with the pinned r46 code and PHP 5.2.5 by
`bash compat/generate-upgrade.sh 5.7.17` and `bash compat/generate-upgrade.sh 5.6.35`.
The generator uses r46 models and original schema creation, not current models
or hand-written INSERT statements. The sole collection metadata row uses r46's
original schema-aware save helper. No production data is included.

- SQLite data and FTS3 search files: SQLite **3.15.2** (2016-11-28).
- MySQL logical dumps: actual **5.7.17** and **5.6.35** servers (2016-12-12),
  using their native mysqldump clients and the original utf8 schema. The dumps
  from both versions are byte-identical after excluding dump comments.
- Six works, twelve comments and twelve evaluations, including six evaluations
  attached to comments. Each work starts with 50 points, three responses and
  seven reads. Old null and empty strings are intentionally distinct.
- Native r46 stretched hashes, historical SHA-1 and DES edit keys, native old
  comment keys and a native old administrator hash. All keys are synthetic test
  credentials, not secrets. DES's original eight-byte limitation is not changed.

The stored author links include https: URLs to test raw preservation. The old
and current form validator accepts only http: author links; the write scenarios
explicitly replace that field with an http: URL when submitting an edit. The
test does not claim that unchanged https: links pass that existing validator.

`source.json` records field expectations **before database insertion**, with
fixed IDs/times/salts, on PHP 5.2.5 / SQLite 3.15.2. Both MySQL generations were
checked against exactly the same works and administrator hash. It is not a
snapshot of current code's output. Decompressed SHA-256 values are pinned in
`tests/Support/UpgradeFixtures.php` and checked before any import.

The generator always creates a new directory and isolated database; it never
updates these checked-in files. Do not regenerate them to accept regressions.
The original generator artifacts are in the ignored directories
`test-results/generate-upgrade-iqIQ3AIV/` (5.7.17) and
`test-results/generate-upgrade-DDMdkUWG/` (5.6.35).

Release dates: [SQLite 3.15.2](https://www.sqlite.org/releaselog/3_15_2.html),
[MySQL 5.7.17 / 5.6.35 announcement](https://dev.mysql.com/blog-archive/announcing-mysql-server-5-7-17-5-6-35-and-5-5-54/).
