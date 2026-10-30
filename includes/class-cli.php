<?php
/**
 * WP-CLI commands.
 *
 * @package JFW\NoComments
 */

namespace JFW\NoComments;

defined( 'ABSPATH' ) || exit;

/**
 * Deletes existing comments and closes discussion.
 */
class CLI {

	/**
	 * Shows the comments to delete and the content still open to comments or pings.
	 *
	 * ## OPTIONS
	 *
	 * [--network]
	 * : On every site of the network.
	 *
	 * ## EXAMPLES
	 *
	 *     wp no-comments status
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function status( $args, $assoc_args ): void {
		self::each_site(
			$assoc_args,
			function () {
				self::print_stats( Purge::stats() );
			}
		);
	}

	/**
	 * Deletes the comments, pingbacks and trackbacks, and closes discussion on all content.
	 *
	 * Block editor notes are kept.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be deleted, without deleting anything.
	 *
	 * [--yes]
	 * : Do not ask for confirmation.
	 *
	 * [--network]
	 * : On every site of the network.
	 *
	 * ## EXAMPLES
	 *
	 *     wp no-comments purge --dry-run
	 *     wp no-comments purge --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function purge( $args, $assoc_args ): void {
		$dry_run = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		if ( ! $dry_run ) {
			\WP_CLI::confirm( 'Delete the comments for good? Export the database first.', $assoc_args );
		}

		self::each_site(
			$assoc_args,
			function () use ( $dry_run ) {
				$stats = Purge::stats();
				self::print_stats( $stats );

				if ( $dry_run ) {
					\WP_CLI::log( 'Dry run: nothing deleted.' );
					return;
				}

				$result = Purge::run();
				\WP_CLI::success( sprintf( '%d comments deleted, %d contents closed.', $result['deleted'], $result['closed'] ) );
			}
		);
	}

	/**
	 * Runs a callback on the current site, or on every site with --network.
	 *
	 * @param array    $assoc_args Options.
	 * @param callable $callback   Callback.
	 */
	private static function each_site( array $assoc_args, callable $callback ): void {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'network', false ) ) {
			$callback();
			return;
		}

		if ( ! is_multisite() ) {
			\WP_CLI::error( '--network only works on a multisite.' );
		}

		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			\WP_CLI::log( sprintf( '== %s', home_url() ) );
			$callback();
			restore_current_blog();
		}
	}

	/**
	 * Prints the stats.
	 *
	 * @param array $stats Purge::stats().
	 */
	private static function print_stats( array $stats ): void {
		foreach ( $stats['comments'] as $status => $count ) {
			\WP_CLI::log( sprintf( '%-20s %d', Purge::status_label( (string) $status ), $count ) );
		}
		\WP_CLI::log( sprintf( '%-20s %d', 'Total', $stats['total'] ) );
		\WP_CLI::log( sprintf( 'Contents open to comments or pings: %d', $stats['open_posts'] ) );
	}
}
