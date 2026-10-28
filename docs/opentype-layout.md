# OpenType layout and PDF embedding

English | [Français](fr/opentype-layout.md)

Version 0.2 adds explicit OpenType layout and static CFF embedding. The original
`TrueType` API remains available. `pdftoolkit/opentype` supplies the independent
font parser and GSUB/GPOS engines; the PDF core supplies PDF objects and operators.
No production class opens a file, discovers fonts or chooses a script implicitly.

## Select a static program and layout stages

```php
use PDF\Document\Fonts\FontDescriptorFlags;
use PDF\Fonts\OpenType\CffContainer;
use PDF\Fonts\OpenType\CffFontEmbedding;
use PDF\Fonts\OpenType\CffFontProgram;
use PDF\Fonts\OpenType\PositionedText;
use PDF\Fonts\OpenType\TextLayout;
use PDF\Fonts\TrueType\UnicodeText;
use PDF\Syntax\Object\NameType;
use PDF\Syntax\Object\NumericType;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Value\Tag;

$program = new CffFontProgram($fontBytes);
$features = new FeatureSelection(new Tag('latn'), features: [
    new Tag('liga'), new Tag('kern'), new Tag('mark'),
]);
$text = (new TextLayout($program->font))->create(
    new UnicodeText('AV fi'), [$features], [$features],
);
$embedding = new CffFontEmbedding(
    $program, $text->glyphs, new FontDescriptorFlags(4), new NumericType(80),
    container: $program->cff->ros === null
        ? CffContainer::OpenType : CffContainer::CidCffTable,
);
$operators = (new PositionedText($embedding, $text))->operators(
    new NameType('F1'), new NumericType(24), new NumericType(40), new NumericType(700),
);
```

Register `$embedding->font` as `F1` in the page resources and emit `$operators`
outside another text object. [The complete executable example](../examples/opentype-layout.php)
constructs this graph and writes a PDF. Its three original test fonts are fixtures,
not production typefaces. The application supplies a suitable licensed font.

For static TrueType, construct `TrueTypeFontProgram(new TrueType\FontProgram($fontBytes))`,
then use the same `TextLayout` and `PositionedText` with `TrueTypeFontEmbedding`.
Its public `$font` is the exact OpenType face used for layout; `$embedded` retains
the original converter's `CIDFontProgram`. GSUB/GPOS execution is independent of
whether the outlines are quadratic TrueType or cubic CFF.

`CffFontProgram($collectionBytes, collectionIndex: 1)` explicitly selects a CFF
face from TTC/OTC. The face is serialized with fresh checksums and without the
invalidated DSIG. The TrueType bridge accepts the existing single-face importer;
select and serialize a collection face with `CollectionReader`/`SfntSerializer`
first if required. All glyphs remain present: neither path subsets or instantiates
variable fonts.

## Keep PDF codes, CIDs, GIDs and Unicode distinct

ISO 32000-1 §9.7.4.2 and Table 126 define the mappings:

| Program | PDF representation | Glyph selection |
| --- | --- | --- |
| Name-keyed CFF1 in OpenType | FontFile3/OpenType, CIDFontType0 | CID equals GID |
| CID-keyed CFF1 | FontFile3/OpenType or explicit CIDFontType0C, CIDFontType0 | CID is resolved through the CFF charset to a GID |
| Static TrueType | FontFile2, CIDFontType2, CIDToGIDMap/Identity | CID equals GID |

Each `GlyphText` pairs a GID with its extraction sequence. The fixed repertoire
assigns distinct two-byte PDF codes to different Unicode aliases of one GID.
A ligature can map to several Unicode scalars. The Encoding CMap selects the CID;
ToUnicode preserves the sequence. `ShapedText` keeps source provenance and
`PositionedText` also emits whole-run `ActualText`, including when several output
glyphs share one source cluster. It uses absolute text matrices for both offsets
and advances, and resets character/word spacing, horizontal scale and text rise.

No CIDToGIDMap is added to CIDFontType0. Embedded OpenType CFF retains the required
cmap. The TrueType converter removes cmap and invalidated DSIG from FontFile2 as
required by §9.9, rebuilding directory and checksums. Vertical PDF metrics would
require DW2/W2; vhea/vmtx alone do not supply a vertical PDF writing mode.

### Explicit CFF container choice

`CffContainer::OpenType` retains the whole static OpenType program and requires
PDF 1.6. `CidCffTable` retains the complete CID-keyed CFF1 table, including charset,
FDArray and subroutines, in FontFile3/CIDFontType0C; it requires PDF 1.3 and rejects
name-keyed fonts. This is an explicit container conversion, not glyph subsetting.

The Poppler 25.03 oracle renders our nonidentity CID fixture through CIDFontType0C.
Its OpenType CID path instead produces a blank raster despite correct text
extraction; its renderer passes CIDs directly to FreeType without the charset map.
The example therefore selects CIDFontType0C for CID fonts. The OpenType CID output
retains the normative mapping and has object/byte tests, but is **not claimed to
render correctly in that Poppler version**. A successful text extraction alone
would not establish visual correctness.

## Limits and responsibilities

- `ReadLimits`, `OutlineLimits` and `UnicodeText` bound supplied data and execution.
  Missing glyphs, undeclared repertoire pairs and mismatched parsed-font identities
  fail explicitly. Font descriptor flags, StemV and optional CapHeight are supplied
  by the application.
- Embedding flags follow OS/2 versions: v0/v1 ignore reserved high bits; v0–v2 use
  the least restrictive declared permission; v3+ require exclusive permission bits.
  Restricted embedding and bitmap-only embedding where defined are rejected. Flags
  are font declarations, not a substitute for the licence.
- The PDF adapters require static fonts. **CFF2/variable PDF embedding and creation
  of a static instance are not implemented.** Supply a separately generated,
  compliant static CFF1 or TrueType font. OpenType outline evaluation by itself
  does not produce such a font file.
- The engines execute the selected GSUB 1–8 and GPOS 1–9 mechanisms, with their
  documented bounds. The adapter accepts ordered stages and default horizontal
  metrics. It does not choose Unicode normalization, script itemization, bidi,
  case conversion, language fallback, line breaks or script-specific stage order.
  FeatureSelection ranges enable or disable functions on source-scalar intervals while preserving the full run context; later overrides win.
  Selecting Arabic feature tags alone does not constitute complete Arabic shaping.
- The original TrueType importer still uses its supported Unicode 4/12 repertoire;
  the CFF/OpenType reader can select the additional Unicode cmap formats. Neither
  adapter transcodes arbitrary legacy platform encodings.
- This layer does not execute TrueType bytecode, rasterize CFF hints, apply a
  variable/device positioning context, provide a vertical text compositor, or
  synthesize absent alternates, small capitals or optical faces.

## Reproducible independent validation

```sh
python -m pip install --require-hashes -r tools/validation-requirements.txt
python tools/build_opentype_fixtures.py --check
python tools/validate_opentype.py --php php --output /tmp/pdf-fonts-opentype
```

Install Poppler (`pdftotext`, `pdftoppm`) as well. The oracle independently checks
fontTools table identity and CFF charset, HarfBuzz GIDs/advances/offsets, pypdf PDF
objects and CMaps, exact Poppler extraction, and visible placement against
fontTools outline paths positioned by HarfBuzz. At 576 dpi the binary masks must
have intersection-over-union ≥0.96 and no difference beyond a one-pixel edge.
This accommodates font/path scan conversion; it does not relax glyph identities,
coordinates or Unicode comparisons. Original simple CFF, nonidentity CID CFF and
TrueType fixtures exercise aliases, ligatures, kerning and mark attachment.
They do not certify every font, complex script or viewer.
