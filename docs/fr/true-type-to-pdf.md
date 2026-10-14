# Du programme TrueType aux objets PDF

[English](../true-type-to-pdf.md) | Français

La version 0.1.8 accepte le core 0.7 en plus des versions antérieures prises en
charge. Ce convertisseur traite toujours les programmes TrueType statiques ;
le core porte les métadonnées, le balisage et la sécurité PDF 2.0. Un modèle
indépendant `pdftoolkit/opentype` fournit les mécanismes OpenType sans devenir
une dépendance de production de ce convertisseur.

## Deux formats, deux responsabilités

ISO 32000-1 décrit un `/FontFile2` comme un flux contenant un programme TrueType,
un descripteur comme un dictionnaire de métriques en unités PDF, et une police
composite comme un graphe d’objets : `/Type0`, `/CIDFontType2`, `/CIDToGIDMap`,
`/W` et `/ToUnicode`. Le core peut construire chacun de ces objets à partir de
valeurs déjà connues. Il ne décode pas les tables sfnt d’une fonte externe.

Le format TrueType définit ses tables et les règles nécessaires pour lire les
métriques et les contours. Les classes de ce package prennent en charge cette
lecture, puis transmettent seulement les valeurs normalisées au core.

## Étapes de l’exemple

1. L’application fournit les octets au constructeur `FontProgram`. Celui-ci
   valide les offsets, les tailles, les métriques et les droits d’incorporation.
2. `CIDFontProgram` conserve tous les glyphes et reconstruit le programme destiné
   à `/FontFile2`. Il retire `cmap`, conformément à la section 9.9 pour un
   `/CIDFontType2`, ainsi que la signature `DSIG` invalidée par cette conversion.
   Le répertoire et les checksums sfnt sont recalculés.
3. Le core reçoit ces octets dans `FontFile2`. Il calcule `/Length1` sans chercher
   à lire ou valider les tables TrueType ; les filtres PDF encodent le flux.
4. `UnicodeText` déclare le répertoire avant de construire le document.
   `CharacterMapFactory` cherche les GID dans `cmap`, attribue des CID distincts
   aux caractères Unicode et convertit les avances vers les 1 000 unités PDF.
   Deux caractères partageant un glyphe conservent des CID et des mappings
   `/ToUnicode` distincts.
5. `FontDescriptorFactory` convertit les dimensions, la boîte, l’angle et les
   hauteurs vers des `NumericType` et un `Rectangle` du core. `StemV` et les flags
   sont fournis explicitement, car le format ne fournit pas une mesure
   universelle de l’épaisseur des fûts. `CapHeight` peut être fourni explicitement
   si la fonte ne le déclare pas et si la hauteur du glyphe `H` ne suffit pas.
6. `CIDWidths`, `CIDToGIDMap`, `ToUnicodeCMap`, `CIDFontType2` et `Type0Font`
   composent ensuite exclusivement des objets PDF. Ils ne connaissent ni
   `FontProgram`, ni `cmap`, ni le nombre d’unités par em.
7. `TextEncoder` convertit les caractères déjà déclarés en codes de deux octets.
   Un caractère absent du répertoire provoque une exception ; l’encodeur ne
   modifie pas la police à l’insu de l’application.

Les deux fabriques vérifient que les octets du `FontFile2` fourni correspondent
au `CIDFontProgram` utilisé. Les objets du core vérifient ensuite l’identité du
flux incorporé, le nom de police et le partage du même répertoire.

## Limites

L’exemple compose du français et du grec sans composition contextuelle. Il ne
prétend pas traiter automatiquement l’arabe, les scripts nécessitant du shaping,
les ligatures, le crénage, les variations ou les écritures verticales.

La police de démonstration Noto Sans est redistribuée sous sa licence OFL dans
`examples/assets/fonts/OFL.txt`. Les fichiers sont lus par l’application ; les
classes du package ne disposent d’aucun accès implicite au système de fichiers.
