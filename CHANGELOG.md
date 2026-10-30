# Changelog

Les changements notables du plugin sont listés ici. Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), et les versions suivent le [versionnage sémantique](https://semver.org/lang/fr/).

## [1.0.4] - 2026-10-30

### Corrigé
- REST API : avertissement PHP « Undefined variable $result » sur les requêtes `/wp/v2/comments` autorisées (Notes, types de contenu qui gardent les commentaires), introduit en 1.0.1.

## [1.0.3] - 2026-10-30

### Corrigé
- Les réglages de discussion par défaut (commentaires, pings, pingbacks) ne peuvent plus être rouverts en base depuis `options.php`, l'API REST des réglages ou `update_option()` : ils restent fermés si le plugin est désactivé.

## [1.0.2] - 2026-10-30

### Corrigé
- Purge : plus d'erreur SQL quand le filtre `jfw_no_comments_blocked_types` renvoie une liste vide. Aucun commentaire n'est alors supprimé, seule la discussion est fermée.

## [1.0.1] - 2026-10-30

### Sécurité
- REST API : `/wp/v2/comments` ne peut plus être contourné en changeant la casse de la route (`/wp/v2/Comments`) ou du paramètre `type` (`COMMENT`), ni en passant plusieurs articles dans `post` (tous sont désormais vérifiés, pas seulement le premier).

## [1.0.0] - 2026-10-30

Première version.

### Ajouté
- Blocage des commentaires, pingbacks et trackbacks sur toutes les entrées : formulaire, `wp-comments-post.php`, XML-RPC, trackbacks, REST API, `wp_new_comment()`.
- Masquage des commentaires existants, du template de commentaires, du script `comment-reply` et des flux de commentaires (404).
- Nettoyage de l'admin : menu Commentaires, Réglages → Discussion, barre d'admin, tableau de bord, boîtes et colonnes, blocs et widget de commentaires.
- Page Outils → Purge des commentaires, et commandes `wp no-comments status` / `purge`, avec prise en charge du multisite.
- Les Notes de l'éditeur de blocs continuent de fonctionner.
- Filtres `jfw_no_comments_allowed_post_types` et `jfw_no_comments_blocked_types`.
- Mises à jour automatiques depuis les releases GitHub (plugin-update-checker 5.7).
- Traduction française.

[1.0.4]: https://github.com/julienfelixweb/jfw-no-comments/releases/tag/v1.0.4
[1.0.3]: https://github.com/julienfelixweb/jfw-no-comments/releases/tag/v1.0.3
[1.0.2]: https://github.com/julienfelixweb/jfw-no-comments/releases/tag/v1.0.2
[1.0.1]: https://github.com/julienfelixweb/jfw-no-comments/releases/tag/v1.0.1
[1.0.0]: https://github.com/julienfelixweb/jfw-no-comments/releases/tag/v1.0.0
