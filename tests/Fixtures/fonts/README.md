# Original test font

English | [Français](README.fr.md)

`PDFFixture-Regular.ttf` is this project's original synthetic font, redistributed
under its MIT licence (`LICENSE.txt`). Outlines are simple rectangles created
by `build_fixture.py`, without reusing another font.

The font contains six glyphs at 2,048 units per em. `A` and `Α` (Greek alpha)
share the same glyph; `🙂` tests UTF-16 and format-12 `cmap` tables. Characters
U+0100–U+017F are also synthetic aliases of `A`, to test `/ToUnicode` blocks
with more than 100 mappings. The last two glyphs share their advance, to test
repetition of the final `hmtx` record. This is a fixture, not a font intended
for typesetting a document.

To reproduce the binary, install `fontTools==4.62.0` in a Python development
environment, then run `python tests/Fixtures/fonts/build_fixture.py`.
Timestamps are fixed; PHP tests read the committed binary and require neither
Python nor fontTools.

## Original OpenType layout fixtures

`PDFToolkitCffSimple.otf`, `PDFToolkitCffCid.otf` and `PDFToolkitLayoutTrueType.ttf`
are also original MIT fixtures, built by `tools/build_opentype_fixtures.py`.
They contain eight polygon glyphs at 1,000 units per em, A/Greek alpha aliases,
fi ligature, AV kerning and acute-mark attachment. The CID CFF charset deliberately
uses CIDs 10–70 instead of GIDs 1–7. Fixed timestamps and pinned fontTools make
`python tools/build_opentype_fixtures.py --check` reproducible. These fonts contain
no third-party outlines and no hinting program. The independent PDF oracle uses
all three fonts; it does not turn them into a universal typography corpus.
