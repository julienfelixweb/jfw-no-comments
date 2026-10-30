# JFW No Comments

Plugin WordPress qui supprime et interdit les commentaires, pingbacks et trackbacks sur tout le site. Il n'a aucun réglage : on l'active et c'est fait. Les **Notes** de l'éditeur de blocs continuent de fonctionner.

Voir aussi le [CHANGELOG](CHANGELOG.md).

| | Version |
|---|---|
| WordPress | 6.2 minimum (testé avec 7.1) |
| PHP | 7.4 minimum (testé avec 7.4, 8.3 et 8.5) |

## Ce que fait le plugin

### Bloque toutes les entrées
| Entrée | Mesure |
|---|---|
| Formulaire et envoi direct à `wp-comments-post.php` | refusé (403) |
| Pingbacks et méthodes de commentaires XML-RPC | méthodes retirées, en-tête `X-Pingback` supprimé |
| Trackbacks | refusés |
| REST API `/wp/v2/comments` | 404 en lecture, 403 en écriture (sauf les Notes) |
| Tout autre code qui appelle `wp_new_comment()` | arrêté avant l'enregistrement |
| Pingbacks sortants | plus envoyés |

### Masque ce qui reste
- Les commentaires existants, le template de commentaires du thème et le script `comment-reply`.
- Les flux RSS de commentaires, qui renvoient une 404.

### Nettoie l'admin
- Retire le menu Commentaires, Réglages → Discussion, la bulle de la barre d'admin et le widget du tableau de bord. Les écrans de commentaires redirigent vers le tableau de bord.
- Retire les boîtes et colonnes de commentaires, les blocs de commentaires de l'éditeur et le widget « Commentaires récents ».
- Les nouveaux contenus sont fermés aux commentaires et aux pings.

### Supprime l'existant (action volontaire)
La page **Outils → Purge des commentaires**, ou la commande WP-CLI, fait trois choses :
- elle supprime les commentaires, pingbacks et trackbacks avec leurs métadonnées, et remet à zéro le compteur de commentaires des contenus ;
- elle ferme la discussion sur tous les contenus existants ;
- elle enregistre les réglages par défaut « fermé » en base, pour qu'ils restent fermés même si le plugin est désactivé.

Exporte la base de données avant : la suppression est définitive. Les Notes sont conservées.

```sh
wp no-comments status              # ce qui serait supprimé
wp no-comments purge --dry-run     # idem, sans rien supprimer
wp no-comments purge --yes         # supprime
wp no-comments purge --yes --network   # sur tous les sites d'un multisite
```

## Installation

1. Télécharge le zip de la dernière version dans les [releases](https://github.com/julienfelixweb/jfw-no-comments/releases) (« Source code (zip) »).
2. Dans WordPress : Extensions → Ajouter → Téléverser, puis active le plugin.
3. Facultatif : exporte la base, puis lance Outils → Purge des commentaires.

## Mises à jour

Les mises à jour arrivent dans l'écran Extensions, comme pour un plugin de wordpress.org. Elles sont gérées par [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) à partir des releases GitHub de ce dépôt.

### Publier une nouvelle version
1. Mets à jour le numéro de version dans l'en-tête de `jfw-no-comments.php`, dans la constante `VERSION`, et dans `Stable tag` de `readme.txt`.
2. Complète `CHANGELOG.md` et la section *Changelog* de `readme.txt`.
3. Commite, pousse, puis crée la release :
   ```sh
   gh release create v1.0.1 --title "1.0.1" --notes "…"
   ```
   Les sites installés voient la mise à jour dans les 12 heures, ou tout de suite avec « Vérifier les mises à jour ».

Les fichiers de développement (`phpcs.xml.dist`, `.gitattributes`…) sont exclus du zip grâce à `.gitattributes`.

## Personnalisation

```php
// Garder les commentaires sur certains types de contenu.
add_filter( 'jfw_no_comments_allowed_post_types', fn() => array( 'product' ) );

// Types de commentaires bloqués et supprimés (par défaut : '', comment, pingback, trackback).
add_filter( 'jfw_no_comments_blocked_types', fn( $types ) => array_merge( $types, array( 'review' ) ) );
```

Ces filtres peuvent être ajoutés dans le `functions.php` du thème.

## Développement

```sh
phpcs   # règles WordPress + compatibilité PHP 7.4+, voir phpcs.xml.dist
wp i18n make-pot . languages/jfw-no-comments.pot --exclude=vendor
wp i18n make-mo languages/
```

Traductions incluses : français (`fr_FR`).

## Licence

[GPL-2.0-or-later](LICENSE). plugin-update-checker est distribué sous licence MIT (`vendor/plugin-update-checker/license.txt`).
