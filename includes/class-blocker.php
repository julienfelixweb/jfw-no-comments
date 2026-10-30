<?php
/**
 * Closes every way in for comments, pingbacks and trackbacks.
 *
 * @package JFW\NoComments
 */

namespace JFW\NoComments;

defined( 'ABSPATH' ) || exit;

/**
 * Blocker.
 */
class Blocker {

	/**
	 * Comment XML-RPC methods (pingbacks included).
	 */
	const XMLRPC_METHODS = array(
		'pingback.ping',
		'pingback.extensions.getPingbacks',
		'wp.getCommentCount',
		'wp.getComment',
		'wp.getComments',
		'wp.deleteComment',
		'wp.editComment',
		'wp.newComment',
		'wp.getCommentStatusList',
	);

	/**
	 * Comment blocks hidden from the block editor.
	 */
	const BLOCKS = array(
		'core/comments',
		'core/comment-author-avatar',
		'core/comment-author-name',
		'core/comment-content',
		'core/comment-date',
		'core/comment-edit-link',
		'core/comment-reply-link',
		'core/comment-template',
		'core/comments-pagination',
		'core/comments-pagination-next',
		'core/comments-pagination-numbers',
		'core/comments-pagination-previous',
		'core/comments-title',
		'core/latest-comments',
		'core/post-comments-count',
		'core/post-comments-form',
		'core/post-comments-link',
	);

	/**
	 * Hooks.
	 */
	public static function init(): void {
		// Open / closed state, everywhere it is checked (form, wp-comments-post.php, trackbacks, REST).
		add_filter( 'comments_open', array( __CLASS__, 'filter_open' ), 20, 2 );
		add_filter( 'pings_open', array( __CLASS__, 'filter_open' ), 20, 2 );

		// Last line of defence: any new comment, pingback or trackback, whatever the entry point.
		add_filter( 'preprocess_comment', array( __CLASS__, 'block_new_comment' ), 0 );

		// Display.
		add_filter( 'comments_array', array( __CLASS__, 'filter_comments_array' ), 20, 2 );
		add_filter( 'get_comments_number', array( __CLASS__, 'filter_comments_number' ), 20, 2 );
		add_filter( 'comments_template', array( __CLASS__, 'filter_comments_template' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_comment_reply' ), 100 );

		// Comment blocks rendered on the server, from existing content, templates or the block renderer.
		add_filter( 'widget_comments_args', __NAMESPACE__ . '\visible_comments_args' );
		add_filter( 'render_block_core/comment-template', array( __CLASS__, 'filter_comment_template_block' ), 10, 3 );

		// Comment and trackback support of post types (meta boxes, list columns, editor panel).
		array_map( array( __CLASS__, 'remove_post_type_support' ), get_post_types() );
		add_action( 'registered_post_type', array( __CLASS__, 'remove_post_type_support' ) );

		// REST API (block editor notes keep working). Once the route is matched and the parameters sanitized.
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'filter_rest' ), 10, 3 );

		// Comment feeds.
		add_action( 'template_redirect', array( __CLASS__, 'comment_feed_404' ), 0 );

		// Outgoing pingbacks.
		add_action( 'pre_ping', array( __CLASS__, 'no_outgoing_pings' ), 10, 3 );

		if ( disabled_everywhere() ) {
			// Defaults for new content.
			add_filter( 'pre_option_default_comment_status', array( __CLASS__, 'closed' ) );
			add_filter( 'pre_option_default_ping_status', array( __CLASS__, 'closed' ) );
			add_filter( 'pre_option_default_pingback_flag', '__return_zero' );

			// And in the database, whatever the entry point (options.php, REST settings, update_option()).
			add_filter( 'pre_update_option_default_comment_status', array( __CLASS__, 'closed' ) );
			add_filter( 'pre_update_option_default_ping_status', array( __CLASS__, 'closed' ) );
			add_filter( 'pre_update_option_default_pingback_flag', '__return_zero' );

			add_filter( 'xmlrpc_methods', array( __CLASS__, 'filter_xmlrpc_methods' ) );
			add_filter( 'wp_headers', array( __CLASS__, 'filter_headers' ) );
			add_filter( 'feed_links_show_comments_feed', '__return_false' );
			add_action( 'widgets_init', array( __CLASS__, 'unregister_widget' ) );
			add_filter( 'render_block_core/latest-comments', '__return_empty_string' );
			add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_script' ) );
		}
	}

	/**
	 * Closes comments and pings unless the post type keeps them.
	 *
	 * @param bool $open    Whether open.
	 * @param int  $post_id Post ID.
	 * @return bool
	 */
	public static function filter_open( $open, $post_id ) {
		return $open && comments_allowed( $post_id );
	}

	/**
	 * Stops new comments, pingbacks and trackbacks before they are saved.
	 *
	 * @param array $commentdata Comment data.
	 * @return array
	 */
	public static function block_new_comment( $commentdata ) {
		$type    = isset( $commentdata['comment_type'] ) ? (string) $commentdata['comment_type'] : '';
		$post_id = isset( $commentdata['comment_post_ID'] ) ? (int) $commentdata['comment_post_ID'] : 0;

		if ( in_array( $type, blocked_comment_types(), true ) && ! comments_allowed( $post_id ) ) {
			wp_die(
				esc_html__( 'Comments are disabled on this site.', 'jfw-no-comments' ),
				esc_html__( 'Comments disabled', 'jfw-no-comments' ),
				array( 'response' => 403 )
			);
		}

		return $commentdata;
	}

	/**
	 * Hides existing comments.
	 *
	 * @param array $comments Comments.
	 * @param int   $post_id  Post ID.
	 * @return array
	 */
	public static function filter_comments_array( $comments, $post_id ) {
		return comments_allowed( $post_id ) ? $comments : array();
	}

	/**
	 * Reports no comments.
	 *
	 * @param string|int $count   Comment count.
	 * @param int        $post_id Post ID.
	 * @return string|int
	 */
	public static function filter_comments_number( $count, $post_id ) {
		return comments_allowed( $post_id ) ? $count : 0;
	}

	/**
	 * Replaces the theme comments template with an empty one.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public static function filter_comments_template( $template ) {
		return comments_allowed() ? $template : dirname( FILE ) . '/templates/comments.php';
	}

	/**
	 * Removes the threaded comments script.
	 */
	public static function dequeue_comment_reply(): void {
		if ( ! comments_allowed() ) {
			wp_dequeue_script( 'comment-reply' );
		}
	}

	/**
	 * Empties the comment template block, used alone or in the Comments block.
	 *
	 * @param string    $content  Block content.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @return string
	 */
	public static function filter_comment_template_block( $content, $block, $instance ) {
		$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : get_the_ID();
		return comments_allowed( $post_id ) ? $content : '';
	}

	/**
	 * Removes comment and trackback support from a post type.
	 *
	 * @param string $post_type Post type.
	 */
	public static function remove_post_type_support( $post_type ): void {
		if ( ! in_array( $post_type, allowed_post_types(), true ) ) {
			remove_post_type_support( $post_type, 'comments' );
			remove_post_type_support( $post_type, 'trackbacks' );
		}
	}

	/**
	 * Refuses /wp/v2/comments requests, except for block editor notes.
	 *
	 * @param mixed            $response Response so far: null, or an error.
	 * @param array            $handler  Route handler.
	 * @param \WP_REST_Request $request  Request, with sanitized parameters ('COMMENT' is now 'comment').
	 * @return mixed
	 */
	public static function filter_rest( $response, $handler, $request ) {
		// Routes match case-insensitively: /wp/v2/Comments is the same route.
		if ( null !== $response || ! preg_match( '#^/wp/v2/comments(?:/(\d+))?/?$#i', $request->get_route(), $matches ) ) {
			return $response;
		}

		if ( isset( $matches[1] ) ) {
			$comment = get_comment( (int) $matches[1] );
			if ( ! $comment ) {
				return $response;
			}
			$type     = $comment->comment_type;
			$post_ids = array( (int) $comment->comment_post_ID );
		} else {
			$type     = null === $request['type'] ? 'comment' : (string) $request['type'];
			$post_ids = array_map( 'intval', (array) $request['post'] );
			// Every post of the list, not only the first one. No post: comments of all posts.
			$post_ids = $post_ids ? $post_ids : array( 0 );
		}

		// Aliases of WP_Comment_Query: '' and 'all' are every type.
		$aliases = array(
			''         => blocked_comment_types(),
			'all'      => blocked_comment_types(),
			'comments' => array( '', 'comment' ),
			'pings'    => array( 'pingback', 'trackback' ),
		);
		$types = $aliases[ $type ] ?? array( $type );

		$disallowed = array_filter(
			$post_ids,
			function ( $post_id ) {
				return ! comments_allowed( $post_id );
			}
		);

		if ( array_intersect( $types, blocked_comment_types() ) && $disallowed ) {
			return new \WP_Error(
				'jfw_no_comments_disabled',
				__( 'Comments are disabled on this site.', 'jfw-no-comments' ),
				array( 'status' => 'GET' === $request->get_method() ? 404 : 403 )
			);
		}

		return $response;
	}

	/**
	 * Comment feeds return a 404.
	 */
	public static function comment_feed_404(): void {
		if ( ! is_comment_feed() ) {
			return;
		}

		$disabled = is_singular() ? ! comments_allowed( get_queried_object_id() ) : disabled_everywhere();

		if ( $disabled ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			// Otherwise the canonical redirect guesses a new URL for the 404 (…/feed/feed/).
			remove_action( 'template_redirect', 'redirect_canonical' );
		}
	}

	/**
	 * No pingbacks sent to the links of a post.
	 *
	 * @param string[] $post_links Links to ping, passed by reference.
	 * @param string[] $pung       Already pinged, passed by reference.
	 * @param int      $post_id    Post ID.
	 */
	public static function no_outgoing_pings( &$post_links, &$pung, $post_id = 0 ): void {
		if ( ! comments_allowed( $post_id ) ) {
			$post_links = array();
		}
	}

	/**
	 * Option value 'closed'.
	 *
	 * @return string
	 */
	public static function closed(): string {
		return 'closed';
	}

	/**
	 * Removes the comment and pingback XML-RPC methods.
	 *
	 * @param array $methods Methods.
	 * @return array
	 */
	public static function filter_xmlrpc_methods( $methods ) {
		return array_diff_key( $methods, array_flip( self::XMLRPC_METHODS ) );
	}

	/**
	 * Removes the X-Pingback header.
	 *
	 * @param array $headers Headers.
	 * @return array
	 */
	public static function filter_headers( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Removes the Recent Comments widget.
	 */
	public static function unregister_widget(): void {
		unregister_widget( 'WP_Widget_Recent_Comments' );
	}

	/**
	 * Hides the comment blocks in the block editor.
	 */
	public static function enqueue_editor_script(): void {
		wp_enqueue_script(
			'jfw-no-comments-editor',
			plugins_url( 'assets/editor.js', FILE ),
			array( 'wp-blocks', 'wp-dom-ready' ),
			VERSION,
			true
		);
		wp_add_inline_script( 'jfw-no-comments-editor', 'window.jfwNoCommentsBlocks = ' . wp_json_encode( self::BLOCKS ) . ';', 'before' );
	}
}
