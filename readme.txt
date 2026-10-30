=== JFW No Comments ===
Contributors: julienfelixweb
Tags: comments, disable comments, spam, pingbacks, trackbacks
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Disables comments, pingbacks and trackbacks everywhere, and purges existing comments. Block editor notes keep working.

== Description ==

Activate it and comments are gone: no settings.

* Closes every way in: comment form and direct posts to `wp-comments-post.php`, pingbacks and comment methods over XML-RPC, trackbacks, the `/wp/v2/comments` REST routes, and any other code calling `wp_new_comment()`.
* Hides existing comments, the comments template, comment feeds (404) and the threaded comments script.
* Removes the comment screens: Comments menu, Settings > Discussion, admin bar bubble, dashboard widget, meta boxes and list columns, comment blocks and widget.
* Tools > Comments purge (or `wp no-comments purge`) deletes the existing comments, pingbacks and trackbacks and closes discussion on all content.

Block editor notes (comment type `note`) are not affected.

= Keeping comments on some post types =

`add_filter( 'jfw_no_comments_allowed_post_types', fn() => array( 'product' ) );`

= WP-CLI =

`wp no-comments status [--network]`
`wp no-comments purge [--dry-run] [--yes] [--network]`

== Installation ==

1. Download the zip of the latest release from https://github.com/julienfelixweb/jfw-no-comments/releases and upload it in Plugins > Add New.
2. Activate it. Updates then show up in the Plugins screen.
3. Optional: export the database, then run Tools > Comments purge.

== Frequently Asked Questions ==

= What happens if I deactivate it? =

Comments stay closed on content that was purged, and the default discussion settings stay closed. Deleted comments are not restored.

== Changelog ==

= 1.0.5 =
* Security: the /wp/v2/comments REST filter can no longer be bypassed with the all, comments, pings or empty type aliases.
* Security: the Latest Comments and Comment Template blocks no longer show existing comments when rendered on the server (content, theme templates, /wp/v2/block-renderer).
* Fix: the Recent Comments of the dashboard Activity widget no longer show disabled comments.

= 1.0.4 =
* Fix: PHP warning "Undefined variable $result" on allowed /wp/v2/comments REST requests (block editor notes, post types that keep comments), introduced in 1.0.1.

= 1.0.3 =
* Fix: the default discussion settings can no longer be reopened in the database (options.php, REST settings, update_option()), so they stay closed if the plugin is deactivated.

= 1.0.2 =
* Fix: no SQL error in the purge when the jfw_no_comments_blocked_types filter returns an empty list.

= 1.0.1 =
* Security: the /wp/v2/comments REST filter can no longer be bypassed with a different case in the route or the type parameter, or with several posts.

= 1.0.0 =
* First release.
