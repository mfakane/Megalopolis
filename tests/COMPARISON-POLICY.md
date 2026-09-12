# Compatibility comparison policy

The immutable r46 databases remain the source of truth for stored text. They are
not regenerated for application changes, and no production data is normalized or
rewritten by these rendering fixes.

## Raw JSON

Object-key order is insignificant; array order, types, nulls, empty text, Unicode
and line endings are not. All current work payloads are checked against their
pre-insertion manifest hashes, independently of the old JSON implementation.

There is one explicitly scoped historical encoder exception. In the frozen
SQLite `supplementary` cases, PHP 5.2.5 encodes these characters incorrectly:

| Original | Decoded PHP 5.2 JSON |
| --- | --- |
| 𠮷 | 𐮷 |
| 𩸽 | 𙸽 |
| 𠮟 | 𐮟 |

`LegacyJsonEncoding` accepts a raw field difference only when current text equals
the corresponding field in the **immutable SQLite archive** and old text equals
that exact field with these exact substitutions. It never reverses arbitrary
Unicode, coerces types or applies to MySQL. Subject entries are checked against
the archive too, even if current and old text agree. A current implementation
that copies the historical corruption must fail.

For formatted HTML the same character substitutions may explain a difference
only for a corresponding supplementary SQLite work. Current output must not
introduce the corrupt glyphs absent from the immutable source, and the old HTML
tree must equal the current tree with those substitutions, with no other changes.

## Formatted HTML

`HtmlContract` compares element namespaces/names, attribute names/values, text,
comments and child order. Attribute order, quote spelling, character-reference
spelling and other syntax that parses into the exact same HTML tree are accepted.
Whitespace and CR/CRLF are preserved; neither side is sanitized by the comparator.
This comparison applies only to `formattedBody[n]` and `formattedAfterword`, never
to raw `body`, titles, tags or other JSON fields.

Negative unit cases prove that escaped markup, removed text/line breaks, new event
handlers/scripts, changed URLs, reordered elements, changed attribute values,
Unicode corruption and whitespace changes still fail. Renderer tests independently
check expected safe output through the public `Visualizer` entry points.

Known parser differences that actually change the tree or text are **not** covered
by this equivalence rule. Ten [reviewed historical HTML rules](HTML-EXCEPTIONS.md)
separately cover the identified corpus defects and representation changes.
They require a frozen-payload hash, an exact source template, and independently
specified current and historical output. The current expectation is checked even
if both implementations agree on the same historical bug. All other tree/text
changes remain failures; no rule restores lost content or unsafe attributes.

Both unexpected differences and accepted differences retain original HTTP
responses in per-route artifacts. Accepted paths carry rule identifiers, so a
passing comparison is distinguishable from byte-identical output.

## Page compatibility

Page zero and null mean the whole body. Other page numbers remain one-based.
The existing JSON array layout is deliberately retained: a three-page work has
`[whole body, page 1, page 2]`, not `[page 1, page 2, page 3]`. Correcting that old
API boundary is a separate versioned contract change, not part of this repair.

The renderer uses the modern DOM API's `localName`, constructs clean elements and
copies validated attributes with `setAttribute`. `tagName` is uppercased and
readonly; see the [PHP DOM element documentation](https://www.php.net/manual/en/class.dom-element.php).
It does not import rejected attributes or run `strip_tags` on already serialized
HTML. Comments are replaced as inert text, configured tag replacements remain
supported, and URI/CSS checks run before copying attribute values.
