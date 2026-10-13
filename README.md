# PDFToolkit Fonts

Convertisseurs optionnels de programmes de police vers les objets PDF de
[PDFToolkit Core](https://github.com/PDFToolkit/pdf).

Le core représente les dictionnaires, les flux, les CID, les largeurs en unités
PDF et les correspondances `/ToUnicode` décrits par ISO 32000-1. Ce package lit
le format TrueType externe : répertoire sfnt, `cmap`, `glyf`, `loca`, `hmtx`,
`OS/2`, `name`, `head`, `hhea`, `maxp` et `post`. Il transforme ensuite les
résultats en valeurs PDF explicites. Un projet qui génère ses PDF sans analyser
un fichier de police n’a pas besoin de ce package.

## Installation

Les modules sont actuellement disponibles sur leur branche de développement.
Les dépôts VCS sont déclarés dans le projet consommateur, car Composer ne
reprend pas les dépôts configurés dans les dépendances.

```sh
composer config repositories.pdf vcs https://github.com/PDFToolkit/pdf
composer config repositories.pdf-fonts vcs https://github.com/PDFToolkit/pdf-fonts
composer require roukmoute/pdf:dev-master pdftoolkit/pdf-fonts:dev-master
```

Le nom Composer du core reste `roukmoute/pdf`. Le namespace des convertisseurs
est `PDF\Fonts\TrueType` ; celui des objets PDF de police est
`PDF\Document\Fonts`.

## Construction

Chaque étape est un objet instancié et peut être manipulée indépendamment.
Aucun registre global, singleton ou fabrique statique n’intervient.

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

`$fontBytes` est fourni par l’application. Le package ne reçoit aucun chemin ni
URL et n’effectue aucun accès à un fichier. Le résultat `$text` est une chaîne
PDF de codes Identity-H de deux octets, destinée à un opérateur de texte.

Le [didacticiel de conversion](docs/true-type-to-pdf.md) détaille la séparation,
les objets et les limites. [L’exemple complet](examples/embedded-font.php)
produit un PDF avec la police Noto Sans incluse sous licence OFL.

## Portée et contrôles

PHP 8.3 ou supérieur sur une plateforme 64 bits est requis. Seuls les programmes
TrueType statiques simples avec contours `glyf` sont pris en charge. Les
collections, CFF, WOFF et polices variables sont rejetés explicitement. Tous les
glyphes sont conservés : aucune réduction en sous-ensemble, composition
contextuelle, ligature, crénage ni écriture verticale n’est effectuée.

Le budget par défaut est de 32 MiB de données de police ; `FontProgramLimits`
permet de fournir un budget explicite. `UnicodeText` est limité à 1 MiB de UTF-8.
Les accès binaires restent bornés à leur table, les références de glyphes et les
cycles/composites sont contrôlés, et les `cmap` restent sous forme binaire.
Les droits d’incorporation `fsType` sont vérifiés ; les restrictions et les
polices réservées aux bitmaps sont rejetées.

Les tests conservent les cas de troncature, dépassement d’offset, chevauchement
de tables, Unicode invalide, CID/GID invalides, cycles et profondeur des glyphes
ainsi que les contre-exemples d’allocation mémoire. Ces contrôles ne constituent
pas une garantie d’absence de vulnérabilités pour toutes les entrées possibles.

## Développement

Les tests utilisent une fonte synthétique originale sous licence MIT. Le
[binaire et son générateur](tests/Fixtures/fonts/README.md) sont committés ;
Python et fontTools ne sont pas requis pour exécuter les tests PHP.

```sh
composer install
composer check
php examples/embedded-font.php embedded-font.pdf
```
