# Fonte de test originale

[English](README.md) | Français

`PDFFixture-Regular.ttf` est une fonte synthétique originale de ce projet,
redistribuée sous sa licence MIT (`LICENSE.txt`). Les contours sont de simples
rectangles créés par `build_fixture.py`, sans reprise d'une autre fonte.

La fonte comporte six glyphes à 2 048 unités par em. `A` et `Α` (alpha grec)
partagent le même glyphe ; `🙂` permet de tester UTF-16 et les `cmap` format 12.
Les caractères U+0100–U+017F sont aussi des alias synthétiques de `A`, pour
vérifier les blocs `/ToUnicode` de plus de 100 correspondances.
Les deux derniers glyphes partagent leur avance, pour tester la répétition du
dernier enregistrement `hmtx`. Il s'agit d'un fixture, pas d'une fonte destinée
à composer un document.

Pour reproduire le binaire : installer `fontTools==4.62.0` dans un environnement
Python de développement, puis exécuter `python tests/Fixtures/fonts/build_fixture.py`.
Les horodatages sont fixés ; les tests PHP lisent le binaire déjà commité et
ne requièrent ni Python ni fontTools.

## Fixtures OpenType originales de mise en forme

`PDFToolkitCffSimple.otf`, `PDFToolkitCffCid.otf` et `PDFToolkitLayoutTrueType.ttf`
sont aussi des fixtures originales MIT, produites par `tools/build_opentype_fixtures.py`.
Elles contiennent huit glyphes polygonaux à 1 000 unités par em, les alias A/alpha
grec, la ligature fi, le crénage AV et l'attachement de l'accent aigu. Le charset
CFF CID utilise volontairement les CID 10–70 au lieu des GID 1–7. Horodatages fixes
et fontTools épinglé rendent `python tools/build_opentype_fixtures.py --check`
reproductible. Aucun contour tiers ni programme de hinting n'est inclus. L'oracle
PDF indépendant emploie les trois fontes, sans prétendre à un corpus typographique universel.
