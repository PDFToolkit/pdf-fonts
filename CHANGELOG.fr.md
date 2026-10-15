# Changelog

[English](CHANGELOG.md) | Français

## Unreleased

## 0.1.10 - 2026-10-15

- Accepter le core stable `^0.9` en conservant les séries précédemment prises en charge.
- Conserver le verrou de validation du core publié `0.7.1` ainsi que toutes les versions et références des dépendances ; actualiser son empreinte avec Composer.
- Vérifier le module inchangé avec les véritables classes du candidat core 0.9 sur PHP 8.3, 8.4 et 8.5, par un bootstrap de validation explicite hors des manifestes de production.
- Conserver API publique, limites d’analyse et exigences PHP ; actualiser l’installation en français et en anglais.

## 0.1.9 - 2026-10-15

- Accepter le core stable ^0.8 en conservant les versions précédemment prises en charge.
- Verrouiller la validation sur le core publié 0.7.1 ; vérifier la compatibilité avec le candidat core 0.8 et conserver l’API de conversion et l’analyse bornée.
- Mettre à jour l’installation en français et en anglais ; PHP 8.3–8.5.


## 0.1.8 — 2026-10-14

### Compatibilité et périmètre

- Accepte le core stable `roukmoute/pdf:^0.2 || ^0.3 || ^0.4 || ^0.5 || ^0.6 || ^0.7`.
- Verrouille les vérifications de publication sur le core publié `0.6.2`, avec des dépendances de production stables et les mêmes versions d’outils de développement.
- Conserve la conversion bornée TrueType statique et les correspondances CID/Unicode existantes. Les métadonnées, le balisage et la sécurité PDF 2.0 restent des mécanismes du writer du core ; la composition typographique n’est pas ajoutée.
- Exige PHP 8.3 ou supérieur ; la CI valide PHP 8.3, 8.4 et 8.5 ainsi que les oracles PDF indépendants.

## 0.1.7 — 2026-10-14

### Compatibilité

- Accepte le core stable `roukmoute/pdf:^0.2 || ^0.3 || ^0.4 || ^0.5 || ^0.6`.
- Verrouille les validations de publication sur le core publié `0.5.5`, sans modifier les versions ou commits des outils de développement.
- Vérifie le module inchangé avec le candidat du core 0.6 sous PHP 8.3, 8.4 et 8.5.
- Conserve l’API de conversion TrueType, les programmes pris en charge et les limites ; les règles de version PDF de sortie restent dans le core.

## 0.1.6 — 2026-10-14

### Documentation bilingue

- Utilise l'anglais par défaut pour le README, les guides et l'historique complet.
- Ajoute leurs versions françaises intégrales avec liens de langue réciproques et entrées documentaires françaises.
- Traduit les commentaires pédagogiques des exemples en conservant les tokens exécutés et données de sortie.
- Verrouille les validations sur le core publié 0.5.4 sans changer les autres versions ou commits de dépendances.
- Conserve les identifiants d'API, fixtures originales et comportement du runtime.
## 0.1.5 — 2026-10-14

### Installation et publication

- Publie le package sur Packagist et simplifie son installation en une commande
  `composer require` dans un projet consommateur.
- Utilise les dépôts Composer par défaut et relie les métadonnées du package à
  sa documentation sur `https://pdftoolkit.github.io/fr/`.
- Verrouille les vérifications sur le core stable `roukmoute/pdf:0.5.3`, sans
  modifier les versions des outils de développement.
- Vérifie l'indexation des versions publiées et leur installation sans dépôts
  VCS ou locaux. Conserve l'API PHP, les mécanismes et les limites existants.

## 0.1.4 — 2026-10-14

### Documentation

- Relie la documentation commune à `https://pdftoolkit.github.io/fr/` et ajoute
  l'entrée stable des polices à `/modules/fonts/`.
- Confie les guides communs et la publication du site à
  `PDFToolkit/pdftoolkit.github.io`, avec des sources de modules épinglées sur
  leurs versions publiées et leurs commits exacts.
- Corrige la compatibilité décrite dans le README pour refléter la contrainte
  existante `roukmoute/pdf:^0.2 || ^0.3 || ^0.4 || ^0.5`.
- Conserve l'API, les conversions, les dépendances et leurs versions
  verrouillées ; cette version modifie uniquement la documentation.

## 0.1.3 — 2026-10-13

### Compatibilité

- Accepte le core stable `roukmoute/pdf:^0.2 || ^0.3 || ^0.4 || ^0.5`.
- Verrouille les vérifications sur le core publié `0.4.0`, sous PHP 8.3, 8.4 et 8.5.
- Conserve l'API et les limites du convertisseur ; aucun traitement de format de police
  supplémentaire n'est ajouté par ce correctif de compatibilité.

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
