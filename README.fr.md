odtphp
======

Générateur de document ODT à partir de PHP

Projet initial (version 1.0.1) : http://www.odtphp.com/ (le site ne répond plus).

Le répertoire "tests" contient les exemples publiés sur ce site disparu.

Intégration des modifications de : http://vikasmahajan.wordpress.com/2010/12/09/odtphp-bug-solved/  
Résoud les bugs d'insertion d'image et d'insertion dans l'en-tête et le pied de page.

J'explore ce projet qui semble "à l'abandon" mais fait parfaitement ce dont j'ai besoin.

J'intégrerai ici mes modestes contributions et/ou exemples.

### Compatibilité LibreOffice / OpenDocument

Le proxy ZIP par défaut est désormais `Odtphp\Zip\PhpZipProxy` (extension PHP `ext-zip`, maintenant requise
dans `composer.json`). Cela corrige le message « document corrompu, réparation nécessaire » à l'ouverture
de fichiers générés à partir de modèles produits par LibreOffice >= 25 : `PclZipProxy` laissait un bit de
flag ZIP ("data descriptor") incohérent lors de la réécriture d'archives contenant ce type d'entrée, ce qui
produisait une archive structurellement invalide, tolérée par les anciens lecteurs mais rejetée par les
nouveaux. `PclZipProxy` reste disponible (bug corrigé) via l'option de configuration `ZIP_PROXY`, pour les
environnements sans `ext-zip`.
