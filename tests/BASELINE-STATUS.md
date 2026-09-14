# Initial compatibility findings

This is the initial checkpoint. See [the subsequent repair verification](REPAIR-STATUS.md)
for the rendering fixes, full JSON run and remaining differences.

Recorded on 2026-09-12 against the application at `a211aa9` (rc48 application
code, with fixture tooling). This test-suite change does **not** modify `req/` or
`index.php`. Runtime evidence: PHP 5.2.5 / PHP 8.4.25, SQLite 3.40.1 and MySQL
5.7.44. Future executions record their actual versions in `test-results/`.

The quick suite runs 1,222 tests:

| Group | Result |
| --- | --- |
| Unit and immutable-input checks | 20 passed |
| DB model reads: 10,000 works × 2 backends × 2 opens | 200 batch tests passed |
| MySQL subject JSON: 100 subjects | 100 passed |
| SQLite subject JSON: 100 subjects | 100 differ |
| Work JSON: 401 per backend | 802 differ |

There were **902 comparison failures, no HTTP/JSON decoding errors and no skips**.
These are failing cases, not 902 distinct bugs. Every sampled current work JSON
payload matched its pre-insertion manifest hash (802/802). The full 10,000-work
**JSON** mode is implemented but was not run at this checkpoint; DB reads already
cover all 10,000 works on each backend.

## Differences to resolve explicitly

1. **Current formatted body is empty at index zero.**
   For work `1195084801`, r46's `formattedBody[0]` contains the body, while current
   code returns `""`. `Thread::toArray()` passes page zero;
   `Visualizer::escapeBody()` formerly treated zero as the whole body, but the
   current null check calls `page(0)` instead. Decide the API's page contract and
   fix the application with focused tests; do not exclude this JSON field.

2. **HTML formatting differs.**
   For MySQL work `1195084802`, `formattedAfterword` changes from a `<p>...</p>`
   element to literal `&lt;p&gt;...&lt;/p&gt;` text. 266 sampled work responses
   differ in `formattedAfterword`. Review the new HTML parser/sanitizer separately;
   neither the old nor new output should be silently blessed wholesale.

3. **The historical JSON encoder corrupts some supplementary Unicode.**
   This is independently reproducible without Megalopolis:
   `json_encode("𠮷")` produces `"\ud802\udfb7"` on PHP 5.2.5, versus the correct
   `"\ud842\udfb7"` on PHP 8.4.25. r46's SQLite subject responses therefore
   disagree with correctly preserved current text. The stored DB bytes and current
   payload hashes are correct. Do **not** make current code reproduce the old
   corruption to turn the comparison green. A deliberately accepted historical
   encoder exception needs a separate, narrow contract decision/test.

`bash compat/test.sh db` provides the green DB preservation gate independently.
`bash compat/test.sh` and CI intentionally still fail on unaccepted JSON
differences. No ignored fields, expected-failure flags, permissive normalization
or regenerated reference snapshots conceal these results. Per-route artifacts
retain both original HTTP responses and decoded JSON field differences.
