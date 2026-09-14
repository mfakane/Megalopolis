# Rendering repair verification

The full-run table below records the checkpoint **before** approval of the
historical HTML exceptions. Those differences now have explicit contracts in
[the reviewed case catalog](HTML-EXCEPTIONS.md); see the subsequent verification
section at the end of this report for the current status.

Recorded on 2026-09-12 for the rendering/comparison changes after `7511d12`.
The [initial checkpoint](BASELINE-STATUS.md) remains a historical record; this
report does not replace the immutable r46 fixtures or bless new snapshots.

## Implemented contract

- Page zero once again renders the whole body. The existing JSON page array
  layout is unchanged.
- The current renderer constructs clean DOM elements and copies only validated
  attributes. Allowed HTML remains markup, while rejected elements, event
  handlers, dangerous URI schemes and unsafe CSS have explicit regression tests.
- Raw supplementary Unicode must match the frozen SQLite source. Only the three
  proven PHP 5.2 JSON encoding substitutions are accepted on the historical side.
- Equivalent HTML syntax can compare equal, but changes to text, attributes,
  whitespace, line breaks, element order or the parsed tree remain failures.

See [the comparison policy](COMPARISON-POLICY.md) for the precise boundaries.

## Executed checks

`bash compat/test.sh full` compared all 10,000 works on **each** backend through
both real HTTP JSON APIs (r46/PHP 5.2.5 and current/PHP 8.4.25):

| Group | Passed | Failed |
| --- | ---: | ---: |
| Unit tests in the full-run image | 67 | 0 |
| DB reads: all works, both backends, two independent opens | 200 | 0 |
| Subject JSON: 100 subjects per backend | 200 | 0 |
| SQLite work JSON | 7,375 | 2,625 |
| MySQL work JSON | 7,375 | 2,625 |
| Total | 15,217 | 5,250 |

The run executed 20,467 tests and 552,271 assertions, with **zero errors and
zero skips**, in approximately 24 minutes. All 20,000 current work JSON raw
payloads matched their pre-insertion manifest hashes. Every unaccepted JSON
difference was at `$.formattedBody[0]`; other returned fields passed the stated
comparison policy. A passing comparison may include an explicitly accepted rule,
not necessarily byte-identical serialized HTML.

The final local unit run passed **69 tests and 20,123 assertions**. It additionally
covers backslash-ending attribute values and trailing line breaks. Those two
tests and a behavior-neutral string cast added for static analysis were checked
after building the full-run image; the full integration run was not repeated for
these additions.

JUnit, original HTTP responses, accepted-rule identifiers and container logs are
in the ignored local directory `test-results/run-57NZgzUL/`. The temporary
Compose project's containers and volumes were removed by the harness. Original
fixture exports still pass all seven checks in `compat/php52/large-fixtures.sha256`.

`git diff --check` passed. Project-wide Psalm analysis reports no findings in
the changed renderer; it still reports 72 findings in unchanged application files.

## Remaining decisions, not accepted exceptions

The 5,250 failures are repeated corpus cases, not 5,250 distinct defects. The
same distribution occurs on both databases:

| HTML fixture case | Failures per backend |
| --- | ---: |
| comments | 500 |
| custom | 500 |
| malformed | 500 |
| pages | 500 |
| paragraphs | 500 |
| image | 75 |
| plain | 25 |
| styles | 25 |

Examples include r46 dropping a trailing `<br>`, truncating following content at
a backslash-ending attribute, losing `>` inside an attribute, and differing in
malformed/unknown-tag parsing or escaped SVG attribute spelling. The page-array
contract is fixed; the remaining `pages` cases concern the representation of
split markers within the whole-body HTML.

These are not all interchangeable with harmless serialization differences. They
remain test failures until each intended behavior has an explicit expectation
and a narrowly scoped comparison rule. Do not restore lost content, remove
sanitization, exclude `formattedBody`, or regenerate the frozen DB to make the
suite pass. Quick/full compatibility commands and CI are therefore **not green**
at this checkpoint; the independent DB preservation gate is green.

## Verification after approval of the individual HTML exceptions

On 2026-09-12 the historical HTML differences were explicitly approved with the
condition that current output remain independently tested. Ten source-bound
rules now cover all 105 affected text/HTML combinations. Application rendering
code and frozen fixture archives were not changed in this follow-up.

- Local unit suite: **92 tests, 20,426 assertions, all passed**. Handwritten
  output examples, all 105 combinations, and negative tests cover each rule.
- Fresh `bash compat/test.sh quick`: **1,294 tests, 392,717 assertions, all
  passed**, with no errors or skips. It restored independent r46/current stores
  for both backends, read every work twice through the current models, compared
  all 100 subjects per backend, and called 401 work routes per backend.
- Saved responses from the prior full run were rechecked with the new rules:
  all **5,250** previously unaccepted differences matched both independently
  specified outputs; **zero** remaining differences and **zero** violations of
  the current HTML expectations. Counts by rule are in
  [the case catalog](HTML-EXCEPTIONS.md).

The fresh-run artifacts are in `test-results/run-vDJUgvHf/`. Its Compose project
and disposable volumes were cleaned up normally. The original exports still
pass all seven SHA-256 checks. No fixtures or historical response snapshots
were regenerated.

This follow-up reran live HTTP in quick mode, **not** the 20,000-work full HTTP
mode. The full-response recheck used the saved output of the unchanged renderer;
it is not a second fresh full run. `bash compat/test.sh full` remains the command
for repeating that complete live comparison after future application changes.
