<?php
/**
 * Uninstall: removes the update checker state. Comments stay closed.
 *
 * @package JFW\NoComments
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_site_option( 'external_updates-jfw-no-comments' );
delete_option( 'external_updates-jfw-no-comments' );
wp_clear_scheduled_hook( 'puc_cron_check_updates-jfw-no-comments' );
