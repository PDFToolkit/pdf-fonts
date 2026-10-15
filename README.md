# PDFToolkit Fonts

English | [Français](README.fr.md)

Optional font program converters for the PDF objects in
[PDFToolkit Core](https://github.com/PDFToolkit/pdf).

The core represents dictionaries, streams, CIDs, widths in PDF units and
`/ToUnicode` mappings described by ISO 32000-1. This package reads the external
TrueType format: the sfnt directory, `cmap`, `glyf`, `loca`, `hmtx`, `OS/2`,
`name`, `head`, `hhea`, `maxp` and `post`. It then converts the results into
explicit PDF values. A project that generates PDFs without parsing a font
file does not need this package.

## Installation

The package is available on
[Packagist](https://packagist.org/packages/pdftoolkit/pdf-fonts). In your
application:

```sh
composer require "pdftoolkit/pdf-fonts:^0.1"
```

The module uses `roukmoute/pdf:^0.2 || ^0.3 || ^0.4 || ^0.5 || ^0.6 || ^0.7 || ^0.8 || ^0.9 || ^0.10 || ^0.11`, installed
by Composer automatically. PHP 8.3 or newer on a 64-bit platform and the `ctype`,
`iconv` and `zlib` extensions are required for this combination. Converters use
the `PDF\Fonts\TrueType` namespace; PDF font objects use `PDF\Document\Fonts`.
The application's `composer.lock` records the selected versions and revisions.

The [shared documentation](https://pdftoolkit.github.io/) covers core objects
and modules. The [font module documentation](https://pdftoolkit.github.io/modules/fonts/)
contains its guides and API reference.

The same converters are compatible with core 0.6, 0.7, 0.8, 0.9 and 0.10. Static TrueType conversion
keeps its existing scope. Selecting the output version and supplying PDF 2.0
metadata, tagging or security objects belong to the core writer.

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

PHP 8.3 or newer on a 64-bit platform is required. Only single, static TrueType
programs with `glyf` outlines are supported. Collections, CFF, WOFF and variable
fonts are explicitly rejected. All glyphs are preserved: no subsetting,
contextual shaping, ligatures, kerning or vertical writing is performed.

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
