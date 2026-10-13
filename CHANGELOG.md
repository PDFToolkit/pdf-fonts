# Changelog

## Unreleased

## 0.1.2 — 2026-10-13

### Compatibilité

- Accepte le core stable `roukmoute/pdf:^0.2 || ^0.3 || ^0.4`.
- Conserve l'API du module et sa frontière de traitement des données externes.
- Vérifie le core stable 0.3.0 et les nouveaux objets du core 0.4 en développement,
  sous PHP 8.3, 8.4 et 8.5.

## 0.1.1 — 2026-10-13

### Compatibilité

- Accepte le core `roukmoute/pdf:^0.2 || ^0.3`, pour permettre l'installation
  avec les nouveaux objets PDF de la série 0.3 sans modifier l'API du module.
- Revalide les tests et les PDF indépendants avec le core stable `0.2.1`.
  Aucun mécanisme de lecture ou de conversion n'est ajouté par ce correctif.

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
