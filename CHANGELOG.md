# Changelog

## Unreleased

## 0.1.0 — 2026-10-13

Première version de `pdftoolkit/pdf-fonts`, sous `PDF\Fonts\TrueType`.
Le module dépend du core `roukmoute/pdf:^0.2`, qui conserve les objets PDF
normalisés ; le core ne dépend pas des convertisseurs de polices.

### Ajouts

- Treize composants pour analyser un programme TrueType externe et produire
  explicitement ses valeurs PDF.
- Lecture des tables sfnt, des métriques horizontales, des contours `glyf`, des
  correspondances Unicode `cmap` et des noms de police.
- Validation des bornes et du chevauchement des tables, des références de
  glyphes, des cycles et de la profondeur des glyphes composites.
- Budget d'entrée configurable avec `FontProgramLimits` et validation du texte
  UTF-8 ; aucun accès implicite à un fichier ou une URL.
- Vérification des droits d'incorporation `fsType`.
- Conversion du programme pour `/CIDFontType2`, en conservant tous les glyphes,
  avec suppression de `cmap` et de `DSIG`, puis reconstruction des checksums.
- Fabriques instanciées pour les descripteurs et les correspondances CID/GID,
  avec conversion des métriques vers les unités PDF et vérification du programme
  incorporé.
- Encodage explicite Identity-H du répertoire Unicode déclaré, sans mutation
  implicite de la police.
- Exemple complet avec Noto Sans sous licence OFL et fixture synthétique MIT.
- Tests des entrées invalides, des limites et des sorties PDF ; vérifications
  sur PHP 8.3, 8.4 et 8.5.

Les programmes pris en charge sont des TrueType statiques avec contours `glyf`,
sur PHP 64 bits. Les collections, CFF, WOFF et polices variables sont refusés.
La réduction en sous-ensemble, la composition contextuelle, les ligatures,
le crénage et l'écriture verticale ne sont pas implémentés.
