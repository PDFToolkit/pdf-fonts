# Mise en forme OpenType et incorporation PDF

[English](../opentype-layout.md) | Français

La version 0.2 ajoute une mise en forme OpenType explicite et l'incorporation CFF
statique. L'API `TrueType` originale reste disponible. `pdftoolkit/opentype` fournit
le lecteur de fontes indépendant et les moteurs GSUB/GPOS ; le core PDF fournit
les objets et opérateurs PDF. Aucune classe de production n'ouvre de fichier,
ne découvre de fontes ni ne choisit implicitement un script.

## Choisir un programme statique et les étapes de mise en forme

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

Enregistrer `$embedding->font` sous `F1` dans les ressources de page et émettre
`$operators` hors d'un autre objet texte. [L'exemple exécutable complet](../../examples/opentype-layout.php)
construit ce graphe et écrit un PDF. Ses trois fontes de test originales sont des
fixtures, pas des caractères destinés à la production. L'application fournit une
fonte appropriée et sa licence.

Pour TrueType statique, construire `TrueTypeFontProgram(new TrueType\FontProgram($fontBytes))`,
puis employer les mêmes `TextLayout` et `PositionedText` avec `TrueTypeFontEmbedding`.
Son `$font` public est la face OpenType exacte de mise en forme ; `$embedded`
conserve le `CIDFontProgram` du convertisseur original. L'exécution GSUB/GPOS est
indépendante des contours quadratiques TrueType ou cubiques CFF.

`CffFontProgram($collectionBytes, collectionIndex: 1)` sélectionne explicitement
une face CFF dans TTC/OTC. Elle est sérialisée avec de nouvelles sommes de contrôle
et sans la signature DSIG invalidée. Le pont TrueType accepte l'importateur
existant d'une face individuelle ; sélectionner et sérialiser d'abord une face de
collection avec `CollectionReader`/`SfntSerializer` si nécessaire. Tous les glyphes
restent présents : aucun chemin ne crée de sous-ensemble ni d'instance variable.

## Distinguer codes PDF, CID, GID et Unicode

ISO 32000-1 §9.7.4.2 et Table 126 définissent les correspondances :

| Programme | Représentation PDF | Sélection du glyphe |
| --- | --- | --- |
| CFF1 à noms dans OpenType | FontFile3/OpenType, CIDFontType0 | CID égal au GID |
| CFF1 à CID | FontFile3/OpenType ou CIDFontType0C explicite, CIDFontType0 | CID résolu en GID par le charset CFF |
| TrueType statique | FontFile2, CIDFontType2, CIDToGIDMap/Identity | CID égal au GID |

Chaque `GlyphText` associe un GID à sa séquence d'extraction. Le répertoire fixe
attribue des codes PDF de deux octets distincts aux alias Unicode d'un même GID.
Une ligature peut représenter plusieurs scalaires Unicode. La CMap Encoding
sélectionne le CID ; ToUnicode préserve la séquence. `ShapedText` garde la
provenance et `PositionedText` émet aussi l'`ActualText` complet du segment,
notamment si plusieurs glyphes partagent un cluster source. Il utilise des
matrices absolues pour offsets et avances, et réinitialise espacements des
caractères/mots, échelle horizontale et décalage vertical du texte.

Aucune CIDToGIDMap n'est ajoutée au CIDFontType0. OpenType CFF incorporé conserve
le cmap requis. Le convertisseur TrueType supprime cmap et DSIG invalidée de
FontFile2 conformément au §9.9, puis reconstruit répertoire et sommes de contrôle.
Les métriques PDF verticales nécessiteraient DW2/W2 ; vhea/vmtx ne fournissent pas
à elles seules un mode d'écriture PDF vertical.

### Choix explicite du conteneur CFF

`CffContainer::OpenType` conserve le programme OpenType statique entier et exige
PDF 1.6. `CidCffTable` conserve toute la table CFF1 à CID, dont charset, FDArray et
sous-programmes, dans FontFile3/CIDFontType0C ; il exige PDF 1.3 et refuse les
fontes à noms. C'est une conversion de conteneur explicite, pas un sous-ensemble.

L'oracle Poppler 25.03 rend notre fixture à CID non identitaires via CIDFontType0C.
Son chemin OpenType CID produit en revanche une image vide malgré une extraction
textuelle correcte ; ce moteur transmet les CID à FreeType sans la carte du
charset. L'exemple sélectionne donc CIDFontType0C pour les fontes CID. La sortie
OpenType CID conserve la correspondance normative et possède des tests d'objets
et d'octets, mais **son rendu correct dans cette version de Poppler n'est pas
revendiqué**. Une extraction textuelle réussie seule ne prouverait pas le rendu.

## Limites et responsabilités

- `ReadLimits`, `OutlineLimits` et `UnicodeText` bornent données et exécution.
  Glyphes manquants, couples non déclarés au répertoire et identités de fontes
  analysées incompatibles provoquent un échec explicite. L'application fournit
  flags du descripteur, StemV et éventuellement CapHeight.
- Les droits d'incorporation suivent OS/2 : v0/v1 ignorent les bits hauts réservés ;
  v0–v2 retiennent la permission déclarée la moins restrictive ; v3+ imposent des
  bits exclusifs. Incorporation restreinte et bitmap-only là où défini sont
  refusés. Ces flags sont des déclarations de la fonte, pas un substitut à sa licence.
- Les adaptateurs PDF exigent des fontes statiques. **L'incorporation PDF CFF2/variable
  et la création d'une instance statique ne sont pas implémentées.** Fournir une
  fonte CFF1 ou TrueType statique conforme produite séparément. Évaluer un contour
  OpenType ne produit pas ce fichier.
- Les moteurs exécutent les mécanismes GSUB 1–8 et GPOS 1–9 sélectionnés et bornés.
  L'adaptateur reçoit des étapes ordonnées et des métriques horizontales par défaut.
  Il ne choisit pas normalisation Unicode, itemisation des scripts, bidi, casse,
  repli linguistique, coupure de lignes ni ordre d'étapes spécifique au script.
  Les plages de FeatureSelection activent ou désactivent les fonctions sur les
  scalaires source sans découper le contexte ; les plages ultérieures prévalent. Les seuls tags arabes ne constituent pas un shaping arabe complet.
- L'importateur TrueType original conserve son répertoire Unicode 4/12 supporté ;
  le lecteur CFF/OpenType peut sélectionner les formats cmap Unicode supplémentaires.
  Aucun adaptateur ne transcode les encodages historiques arbitraires.
- Cette couche n'exécute pas le bytecode TrueType, ne rastérise pas les hints CFF,
  n'applique pas de contexte de positionnement variable/Device, ne compose pas
  de texte vertical et ne synthétise pas les variantes, petites capitales ou faces
  optiques absentes.

## Validation indépendante reproductible

```sh
python -m pip install --require-hashes -r tools/validation-requirements.txt
python tools/build_opentype_fixtures.py --check
python tools/validate_opentype.py --php php --output /tmp/pdf-fonts-opentype
```

Installer aussi Poppler (`pdftotext`, `pdftoppm`). L'oracle vérifie indépendamment
l'identité des tables et le charset CFF par fontTools, les GID/avances/offsets par
HarfBuzz, les objets PDF et CMap par pypdf, l'extraction exacte par Poppler, puis le
placement visible face aux contours fontTools positionnés par HarfBuzz. À 576 dpi,
l'intersection-sur-union des masques binaires doit être ≥0,96, sans différence
au-delà d'un bord d'un pixel. Cela couvre la différence de scan conversion entre
fonte et tracé ; les comparaisons d'identités, coordonnées et Unicode restent
strictes. Les fixtures originales CFF simple, CFF à CID non identitaires et
TrueType exercent alias, ligatures, crénage et attachement des marques.
Elles ne certifient pas toutes les fontes, écritures complexes ou visionneuses.
