# Reviewed historical HTML differences

Decision: keep correct current output and accept individually verified r46
defects. Do not reproduce historical content loss to achieve equality.

`Support/LegacyHtml.php` contains ten named rules for the 105 distinct affected
text/HTML combinations in the immutable corpus. `Unit/LegacyHtmlTest.php`
provides handwritten examples of the original input's expected current and
legacy output, plus renderer and rejection tests. Neither imports the fixture
generator nor calls the production renderer to build expected HTML.

## Scope and safeguards

A rule requires **all** of the following:

1. SQLite or MySQL, a known work ID, and the exact original payload hash from
   that backend's frozen pre-insertion manifest, including ordered tags.
2. The complete body matches that rule's literal source template and the
   fixture's subject/work prefix. Only the original text parameter varies.
3. The JSON field is exactly `formattedBody[0]`.
4. Current output has the specified tree, text, attributes and whitespace.
5. Historical output has the separately specified historical tree and text.

The current expectation is enforced even when r46 and current output agree:
copying a historical defect into current code must fail, not erase a difference.
Unchanged raw payloads remain mandatory. No rule applies to another page,
afterwords, titles or arbitrary HTML containing similar tags.

Equivalent HTML spelling is still allowed by `HtmlContract`. For affected
SQLite supplementary cases only, the separate, proven PHP 5.2 encoder
substitutions are applied to the **expected historical output**, never current
text. Full raw responses and the accepted rule identifier remain in artifacts.

## Case decisions

Rule identifiers have the prefix `r46-html-`. Counts below are for the saved
full comparison: both backends combined, 10,000 works each.

| Rule | Required current behavior | Historical difference | Cases |
| --- | --- | --- | ---: |
| `void-breaks` | Keep all three breaks at their original positions | Drops the trailing break and emits an extra `</br>` | 1,000 |
| `attribute-greater-than` | Keep `a > b & c` in the attribute; comments remain inert | Deletes `>` from the attribute | 1,000 |
| `backslash-attribute-truncation` | Keep the complete span and following elements | Truncates after the outer div's opening tag | 50 |
| `inert-image-backslash` | Keep both image dimensions in escaped markup | Eats the dimensions after a backslash-ending alt value | 50 |
| `formatting-reconstruction` | Preserve all text and the repaired italic nesting | Repairs crossed formatting differently | 1,000 |
| `nonvoid-split` | Keep both split elements in whole-body HTML | Drops the first split marker | 1,000 |
| `inert-custom-markup` | Keep escaped custom/foreign markup, including `viewBox` | Lowercases SVG spelling; differs in nested escaping | 1,000 |
| `inert-unclosed-tag` | Keep all text/newlines and the escaped closing tag | Omits the unknown element's closing tag | 50 |
| `inert-image-quotes` | Use the reviewed PHP DOM attribute spelling inside inert figure text | Retains different nested entity spelling | 50 |
| `inert-image-nbsp` | Use the reviewed `&nbsp;` spelling inside inert figure text | Retains a literal NBSP in that displayed markup | 50 |

Some rows are intentional parser/representation changes rather than text-loss
bugs. Unknown markup must remain **text**, not become active elements. Because
escaped source is visible text, its spelling is explicitly reviewed rather than
globally normalized. The image-quote spelling records PHP 8.4's DOM behavior;
it is not a claim that every runtime or serializer uses the same spelling.

HTML parsing defines formatting reconstruction, non-void element handling and
SVG attribute-name adjustment; see the [HTML parsing specification](https://html.spec.whatwg.org/multipage/parsing.html).
The API's historical whole-body/page-array boundary is unchanged by these rules.

## Adding a future exception

First choose the intended current behavior and add a small handwritten
input/output example and negative cases. Then add a source-bound historical
expectation, retaining every unrelated difference as a failure. Do not broaden
these templates or regenerate the frozen corpus to accommodate a regression.

The tests reject changed source bytes/IDs/backends, extra or missing text,
changed line endings, added scripts/events, and both sides sharing the old
defect. A coverage assertion requires all 105 reviewed combinations to remain
tested, so deleting a rule cannot silently remove its regression coverage.
