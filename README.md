# PDFToolkit Fonts

English | [Français](README.fr.md)

Optional font program converters for the PDF objects in
[PDFToolkit Core](https://github.com/PDFToolkit/pdf).

The core represents dictionaries, streams, CIDs, widths in PDF units and
`/ToUnicode` mappings described by ISO 32000-1. This package reads the external
TrueType format and, through `pdftoolkit/opentype`, static CFF and explicit layout: the sfnt directory, `cmap`, `glyf`, `loca`, `hmtx`, `OS/2`,
`name`, `head`, `hhea`, `maxp` and `post`. It then converts the results into
explicit PDF values. A project that generates PDFs without parsing a font
file does not need this package.

## Installation

The package is available on
[Packagist](https://packagist.org/packages/pdftoolkit/pdf-fonts). In your
application:

```sh
composer require "pdftoolkit/pdf-fonts:^0.2"
```

The module uses `roukmoute/pdf` and `pdftoolkit/opentype:^0.4`, installed
by Composer automatically. PHP 8.3 or newer on a 64-bit platform and the `ctype`,
`iconv` and `zlib` extensions are required for this combination. Converters use
the `PDF\Fonts\TrueType` and `PDF\Fonts\OpenType` namespaces; PDF font objects use `PDF\Document\Fonts`.
The application's `composer.lock` records the selected versions and revisions.
Core 0.4 or newer in the supported 0.4–0.12 series is required; 0.2 and 0.3
lack PDF representations used by the new layout API.

The [shared documentation](https://pdftoolkit.github.io/) covers core objects
and modules. The [font module documentation](https://pdftoolkit.github.io/modules/fonts/)
contains its guides and API reference.

Version 0.2 adds [OpenType layout and CFF embedding](docs/opentype-layout.md),
including explicit GSUB/GPOS stages and source-cluster feature ranges for TrueType
and CFF. Existing TrueType conversion objects remain available. Output PDF version,
metadata, tagging and security belong to the core writer.

## Construction

Each step is an instantiated object and can be manipulated independently.
There is no global registry, singleton or static factory.

```php
use PDF\Document\Fonts\CIDFontType2;
use PDF\Document\Fonts\CIDToGIDMap;
use PDF\Document\Fonts\CIDWidths;
use PDF\Document\Fonts\FontDescriptorFlags;
use PDF\Document\Fonts\FontFile2;
use PDF\Document\Fonts\ToUnicodeCMap;
use PDF\Document\Fonts\Type0Font;
use PDF\Fonts\TrueType\CharacterMapFactory;
use PDF\Fonts\TrueType\CIDFontProgram;
use PDF\Fonts\TrueType\FontDescriptorFactory;
use PDF\Fonts\TrueType\FontProgram;
use PDF\Fonts\TrueType\TextEncoder;
use PDF\Fonts\TrueType\UnicodeText;
use PDF\Syntax\Filters\FlateDecode;
use PDF\Syntax\Object\NumericType;

$program = new FontProgram($fontBytes);
$cidProgram = new CIDFontProgram($program);
$fontFile = new FontFile2($cidProgram->bytes, new FlateDecode());
$repertoire = new UnicodeText('Français : été. Ελληνικά : Αθήνα.');
$map = (new CharacterMapFactory($cidProgram))->create($fontFile, $repertoire);
$descriptor = (new FontDescriptorFactory($cidProgram))->create(
    $fontFile,
    new FontDescriptorFlags(4),
    new NumericType(80),
);
$descendant = new CIDFontType2($descriptor, new CIDToGIDMap($map), new CIDWidths($map));
$font = new Type0Font($descendant, new ToUnicodeCMap($map));
$text = (new TextEncoder($map))->encode($repertoire);
```

The application supplies `$fontBytes`. The package accepts no path or URL and
performs no file access. The resulting `$text` is a PDF string of two-byte
Identity-H codes, intended for a text operator.

The [conversion tutorial](docs/true-type-to-pdf.md) explains the separation,
objects and limits. The [complete example](examples/embedded-font.php) produces
a PDF with the included Noto Sans font, distributed under the OFL licence.

## Scope and checks

PHP 8.3 or newer on a 64-bit platform is required. The original TrueType importer
accepts single static glyf programs; the OpenType path also embeds static CFF1 and
selects CFF collection faces. Explicit layout supports font-defined substitutions,
ligatures, kerning and mark positioning. All glyphs are retained. Variable PDF
embedding/static instantiation, WOFF, automatic Unicode script shaping/bidi and
vertical text composition remain absent. [Detailed mappings and limits](docs/opentype-layout.md)
include CID/GID identity, Unicode aliases, CFF container interoperability and the
independent raster oracle.

The default budget is 32 MiB of font data; `FontProgramLimits` accepts an
explicit budget. `UnicodeText` is limited to 1 MiB of UTF-8. Binary reads remain
bounded to their table, glyph references and composite cycles are checked, and
`cmap` tables remain in binary form. `fsType` embedding permissions are checked;
restricted and bitmap-only fonts are rejected.

Tests retain cases for truncation, offset overflow, overlapping tables,
invalid Unicode, invalid CID/GID values, glyph cycles and depth, and excessive
memory allocation. These checks do not guarantee the absence of vulnerabilities
for every possible input.

## Development

Tests use an original synthetic font under the MIT licence. Its
[binary and generator](tests/Fixtures/fonts/README.md) are committed; Python
and fontTools are not required to run the PHP tests.

```sh
composer install
composer check
php examples/embedded-font.php embedded-font.pdf
```

See the [changelog](CHANGELOG.md) and [security policy](SECURITY.md).
