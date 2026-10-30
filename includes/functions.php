<?php
/**
 * Helpers.
 *
 * @package JFW\NoComments
 */

namespace JFW\NoComments;

defined( 'ABSPATH' ) || exit;

/**
 * Post types on which comments stay allowed. None by default.
 *
 * @return string[]
 */
function allowed_post_types(): array {
	/**
	 * Filters the post types on which comments stay allowed.
	 *
	 * @param string[] $post_types Post type names. Default empty: comments are disabled everywhere.
	 */
	return (array) apply_filters( 'jfw_no_comments_allowed_post_types', array() );
}

/**
 * Whether comments are allowed on a post.
 *
 * @param int|\WP_Post|null $post Post ID or object. Default current post.
 * @return bool
 */
function comments_allowed( $post = null ): bool {
	$post_type = get_post_type( $post );
	return $post_type && in_array( $post_type, allowed_post_types(), true );
}

/**
 * Whether comments are disabled on the whole site (no post type keeps them).
 *
 * @return bool
 */
function disabled_everywhere(): bool {
	return array() === allowed_post_types();
}

/**
 * Comment types that are blocked and purged. Block editor notes ('note') are not.
 *
 * @return string[]
 */
function blocked_comment_types(): array {
	/**
	 * Filters the comment types that are blocked and purged.
	 *
	 * @param string[] $types Comment types. '' is the legacy type of old comments.
	 */
	return (array) apply_filters( 'jfw_no_comments_blocked_types', array( '', 'comment', 'pingback', 'trackback' ) );
}
