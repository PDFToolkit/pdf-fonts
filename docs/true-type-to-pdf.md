# From a TrueType program to PDF objects

English | [Français](fr/true-type-to-pdf.md)

Version 0.1.8 accepts core 0.7 alongside earlier supported releases. This
converter still processes static TrueType programs; the core owns PDF 2.0
metadata, tagging and security. An independent `pdftoolkit/opentype` model
provides OpenType mechanisms without becoming a runtime dependency here.

## Two formats, two responsibilities

ISO 32000-1 describes `/FontFile2` as a stream containing a TrueType program,
a descriptor as a dictionary of metrics in PDF units, and a composite font as
an object graph: `/Type0`, `/CIDFontType2`, `/CIDToGIDMap`, `/W` and `/ToUnicode`.
The core can construct each of these objects from known values. It does not
decode the sfnt tables of an external font.

The TrueType format defines its tables and the rules required to read metrics
and outlines. This package's classes perform that reading, then pass only
normalised values to the core.

## Example steps

1. The application supplies bytes to the `FontProgram` constructor, which
   validates offsets, sizes, metrics and embedding permissions.
2. `CIDFontProgram` preserves all glyphs and rebuilds the program intended for
   `/FontFile2`. It removes `cmap`, as required by section 9.9 for a
   `/CIDFontType2`, and the `DSIG` signature invalidated by the conversion.
   The sfnt directory and checksums are recalculated.
3. The core receives these bytes in `FontFile2`. It calculates `/Length1`
   without reading or validating TrueType tables; PDF filters encode the stream.
4. `UnicodeText` declares the repertoire before the document is built.
   `CharacterMapFactory` finds GIDs in `cmap`, assigns distinct CIDs to Unicode
   characters and converts advances into 1,000-unit PDF metrics. Two characters
   sharing a glyph retain distinct CIDs and `/ToUnicode` mappings.
5. `FontDescriptorFactory` converts dimensions, the bounding box, angle and
   heights into core `NumericType` values and a `Rectangle`. `StemV` and flags
   are supplied explicitly because the format does not provide a universal
   measurement of stem thickness. `CapHeight` can be supplied explicitly if
   the font does not declare it and the height of the `H` glyph is insufficient.
6. `CIDWidths`, `CIDToGIDMap`, `ToUnicodeCMap`, `CIDFontType2` and `Type0Font`
   then compose only PDF objects. They know nothing about `FontProgram`,
   `cmap` or units per em.
7. `TextEncoder` converts previously declared characters into two-byte codes.
   A character absent from the repertoire raises an exception; the encoder
   does not modify the font without the application's knowledge.

Both factories verify that the supplied `FontFile2` bytes correspond to the
`CIDFontProgram` in use. Core objects then verify the identity of the embedded
stream, the font name and the shared repertoire.

## Limits

The example sets French and Greek text without contextual shaping. It does
not claim to handle Arabic, scripts that require shaping, ligatures, kerning,
variations or vertical writing automatically.

The demonstration Noto Sans font is redistributed under its OFL licence in
`examples/assets/fonts/OFL.txt`. Files are read by the application; the
package's classes have no implicit access to the file system.
