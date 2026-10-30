<?php
/**
 * Deletes existing comments and closes discussion on existing content.
 *
 * @package JFW\NoComments
 */

namespace JFW\NoComments;

defined( 'ABSPATH' ) || exit;

// A purge works on the whole tables: direct queries, no caching (caches are cleaned after each write).
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Purge.
 */
class Purge {

	/**
	 * Comments to delete, by status, and content still open to comments or pings.
	 *
	 * @return array{comments: array<string, int>, total: int, open_posts: int}
	 */
	public static function stats(): array {
		global $wpdb;

		$types = blocked_comment_types();
		$rows  = array();

		// No blocked type (filter emptied): nothing to delete, and no empty IN () in the query.
		if ( $types ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT comment_approved AS status, COUNT(*) AS n FROM %i WHERE comment_type IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ') GROUP BY comment_approved',
					array_merge( array( $wpdb->comments ), $types )
				)
			);
		}

		$comments = array();
		foreach ( $rows as $row ) {
			$comments[ $row->status ] = (int) $row->n;
		}

		return array(
			'comments'   => $comments,
			'total'      => array_sum( $comments ),
			'open_posts' => count( self::open_post_ids() ),
		);
	}

	/**
	 * Deletes the comments and closes discussion on all content.
	 *
	 * @return array{deleted: int, closed: int}
	 */
	public static function run(): array {
		global $wpdb;

		$types       = blocked_comment_types();
		$comment_ids = array();
		$post_ids    = array();

		// No blocked type: only discussion gets closed.
		if ( $types ) {
			$comment_ids = array_map(
				'intval',
				$wpdb->get_col(
					$wpdb->prepare(
						'SELECT comment_ID FROM %i WHERE comment_type IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ')',
						array_merge( array( $wpdb->comments ), $types )
					)
				)
			);
		}

		foreach ( array_chunk( $comment_ids, 500 ) as $chunk ) {
			$post_ids = array_merge(
				$post_ids,
				$wpdb->get_col(
					$wpdb->prepare(
						'SELECT DISTINCT comment_post_ID FROM %i WHERE comment_ID IN (' . implode( ',', array_fill( 0, count( $chunk ), '%d' ) ) . ')',
						array_merge( array( $wpdb->comments ), $chunk )
					)
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE comment_id IN (' . implode( ',', array_fill( 0, count( $chunk ), '%d' ) ) . ')',
					array_merge( array( $wpdb->commentmeta ), $chunk )
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE comment_ID IN (' . implode( ',', array_fill( 0, count( $chunk ), '%d' ) ) . ')',
					array_merge( array( $wpdb->comments ), $chunk )
				)
			);
			clean_comment_cache( $chunk );
		}

		foreach ( array_unique( array_map( 'intval', $post_ids ) ) as $post_id ) {
			wp_update_comment_count_now( $post_id );
		}

		$open_ids = self::open_post_ids();
		foreach ( array_chunk( $open_ids, 500 ) as $chunk ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET comment_status = 'closed', ping_status = 'closed' WHERE ID IN (" . implode( ',', array_fill( 0, count( $chunk ), '%d' ) ) . ')',
					array_merge( array( $wpdb->posts ), $chunk )
				)
			);
			foreach ( $chunk as $post_id ) {
				clean_post_cache( $post_id );
			}
		}

		if ( disabled_everywhere() ) {
			self::save_closed_defaults();
		}

		return array(
			'deleted' => count( $comment_ids ),
			'closed'  => count( $open_ids ),
		);
	}

	/**
	 * Saves the closed defaults in the database, so they stay closed if the plugin is deactivated.
	 */
	private static function save_closed_defaults(): void {
		$defaults = array(
			'default_comment_status' => array( 'closed', array( Blocker::class, 'closed' ) ),
			'default_ping_status'    => array( 'closed', array( Blocker::class, 'closed' ) ),
			'default_pingback_flag'  => array( '0', '__return_zero' ),
		);

		foreach ( $defaults as $option => list( $value, $filter ) ) {
			// The Blocker's pre_option_ filter makes get_option() already return the value,
			// so update_option() would think nothing changed and skip the write.
			$priority = has_filter( "pre_option_{$option}", $filter );
			remove_filter( "pre_option_{$option}", $filter );
			update_option( $option, $value );
			if ( false !== $priority ) {
				add_filter( "pre_option_{$option}", $filter, $priority );
			}
		}
	}

	/**
	 * IDs of content still open to comments or pings, outside the allowed post types.
	 *
	 * @return int[]
	 */
	private static function open_post_ids(): array {
		global $wpdb;

		$allowed = allowed_post_types();

		if ( ! $allowed ) {
			$ids = $wpdb->get_col(
				$wpdb->prepare( "SELECT ID FROM %i WHERE comment_status <> 'closed' OR ping_status <> 'closed'", $wpdb->posts )
			);
		} else {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM %i WHERE ( comment_status <> 'closed' OR ping_status <> 'closed' ) AND post_type NOT IN (" . implode( ',', array_fill( 0, count( $allowed ), '%s' ) ) . ')',
					array_merge( array( $wpdb->posts ), $allowed )
				)
			);
		}

		return array_map( 'intval', $ids );
	}

	/**
	 * Label of a comment status.
	 *
	 * @param string $status comment_approved value.
	 * @return string
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			'1'            => __( 'Approved', 'jfw-no-comments' ),
			'0'            => __( 'Pending', 'jfw-no-comments' ),
			'spam'         => __( 'Spam', 'jfw-no-comments' ),
			'trash'        => __( 'Trash', 'jfw-no-comments' ),
			'post-trashed' => __( 'On trashed content', 'jfw-no-comments' ),
		);

		return $labels[ $status ] ?? $status;
	}
}
