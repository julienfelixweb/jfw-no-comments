<?php
/**
 * Admin: removes the comment screens and adds the purge page (Tools).
 *
 * @package JFW\NoComments
 */

namespace JFW\NoComments;

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
class Admin {

	const PAGE   = 'jfw-no-comments';
	const ACTION = 'jfw_no_comments_purge';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_purge' ) );
		add_action( 'load-index.php', array( __CLASS__, 'filter_dashboard_comments' ) );

		if ( disabled_everywhere() ) {
			add_action( 'admin_menu', array( __CLASS__, 'remove_menus' ), 999 );
			add_action( 'admin_init', array( __CLASS__, 'redirect_comment_screens' ) );
			add_action( 'admin_bar_menu', array( __CLASS__, 'remove_admin_bar_nodes' ), 999 );
		}
	}

	/**
	 * Removes Comments and Settings > Discussion.
	 */
	public static function remove_menus(): void {
		remove_menu_page( 'edit-comments.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
	}

	/**
	 * Sends the comment screens to the dashboard.
	 */
	public static function redirect_comment_screens(): void {
		global $pagenow;

		if ( in_array( $pagenow, array( 'edit-comments.php', 'comment.php', 'options-discussion.php' ), true ) ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	/**
	 * Removes the comments bubble from the admin bar, and from each site of the network.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar.
	 */
	public static function remove_admin_bar_nodes( $wp_admin_bar ): void {
		$wp_admin_bar->remove_node( 'comments' );

		foreach ( (array) $wp_admin_bar->get_nodes() as $id => $node ) {
			if ( preg_match( '/^blog-\d+-c$/', $id ) ) {
				$wp_admin_bar->remove_node( $id );
			}
		}
	}

	/**
	 * Dashboard: the Recent Comments of the Activity widget only show the comments that stay visible.
	 */
	public static function filter_dashboard_comments(): void {
		add_action( 'pre_get_comments', array( __CLASS__, 'restrict_comment_query' ) );
	}

	/**
	 * Restricts a comment query to the comments that stay visible.
	 *
	 * @param \WP_Comment_Query $query Comment query.
	 */
	public static function restrict_comment_query( $query ): void {
		$query->query_vars = visible_comments_args( $query->query_vars );
	}

	/**
	 * Tools > Comments purge.
	 */
	public static function add_page(): void {
		add_management_page(
			__( 'Comments purge', 'jfw-no-comments' ),
			__( 'Comments purge', 'jfw-no-comments' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Purge page.
	 */
	public static function render_page(): void {
		$stats = Purge::stats();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Comments purge', 'jfw-no-comments' ); ?></h1>

			<?php if ( isset( $_GET['deleted'], $_GET['closed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							/* translators: 1: number of deleted comments, 2: number of closed contents */
							esc_html__( '%1$d comments deleted, %2$d contents closed to comments and pings.', 'jfw-no-comments' ),
							(int) $_GET['deleted'], // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							(int) $_GET['closed'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<p><?php esc_html_e( 'New comments, pingbacks and trackbacks are already blocked. This page deletes the existing ones and closes discussion on existing content. Block editor notes are kept.', 'jfw-no-comments' ); ?></p>

			<table class="widefat striped" style="max-width: 32rem;">
				<tbody>
					<?php foreach ( $stats['comments'] as $status => $count ) : ?>
						<tr>
							<td><?php echo esc_html( Purge::status_label( (string) $status ) ); ?></td>
							<td><?php echo (int) $count; ?></td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th><?php esc_html_e( 'Comments to delete', 'jfw-no-comments' ); ?></th>
						<th><?php echo (int) $stats['total']; ?></th>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Contents open to comments or pings', 'jfw-no-comments' ); ?></th>
						<th><?php echo (int) $stats['open_posts']; ?></th>
					</tr>
				</tbody>
			</table>

			<?php if ( $stats['total'] || $stats['open_posts'] ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm( <?php echo esc_attr( wp_json_encode( __( 'Delete all these comments for good?', 'jfw-no-comments' ) ) ); ?> );">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::ACTION ); ?>
					<p><strong><?php esc_html_e( 'This cannot be undone: export the database first.', 'jfw-no-comments' ); ?></strong></p>
					<?php submit_button( __( 'Delete comments and close discussion', 'jfw-no-comments' ), 'delete', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<p><?php esc_html_e( 'Nothing to purge.', 'jfw-no-comments' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Runs the purge from the Tools page.
	 */
	public static function handle_purge(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'jfw-no-comments' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );

		$result = Purge::run();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE,
					'deleted' => $result['deleted'],
					'closed'  => $result['closed'],
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}
}
