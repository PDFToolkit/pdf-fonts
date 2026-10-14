# Politique de sécurité

[English](SECURITY.md) | Français

Signaler une vulnérabilité en privé via les
[GitHub Security Advisories](https://github.com/PDFToolkit/pdf-fonts/security/advisories/new).
Préciser le commit touché, une entrée minimale, les limites configurées et le résultat observé.

Les convertisseurs acceptent les octets fournis par l'application et n'ouvrent
jamais de chemin ni d'URL. `FontProgramLimits` borne les octets d'entrée avant
l'analyse. Les lectures binaires restent bornées à leur table ; les plages des
tables, les références de glyphes, les cycles de composites et la profondeur
récursive sont contrôlés. Les entrées Unicode et les correspondances de
caractères ont des limites explicites. Les entrées invalides et non prises en
charge sont rejetées explicitement ; les tests documentés vérifient ces contrôles.
Configurer les limites pour la charge attendue et appliquer des quotas de
mémoire et de durée d'exécution à la frontière de l'application. La réussite
de cette suite ne démontre pas l'absence de toute vulnérabilité possible.

## Versions prises en charge

La série `0.1.x` reçoit les corrections de sécurité. PHP 8.3 ou supérieur sur
une plateforme 64 bits est requis ; la CI vérifie PHP 8.3, 8.4 et 8.5.
Utiliser une version PHP maintenue avec ses correctifs de sécurité.

Le modèle PDF partagé possède sa propre
[politique de sécurité](https://github.com/PDFToolkit/pdf/blob/master/SECURITY.fr.md).
