<?php
/**
 * Plugin Name:       JFW No Comments
 * Plugin URI:        https://github.com/julienfelixweb/jfw-no-comments
 * Description:       Disables comments, pingbacks and trackbacks everywhere, and purges existing comments. Block editor notes keep working.
 * Version:           1.0.5
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Julien Félix Web
 * Author URI:        https://www.julienfelix.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jfw-no-comments
 * Domain Path:       /languages
 * Update URI:        https://github.com/julienfelixweb/jfw-no-comments
 *
 * @package JFW\NoComments
 */

namespace JFW\NoComments;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.0.5';
const FILE    = __FILE__;

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/class-blocker.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-purge.php';

// After the theme, so its functions.php can use the jfw_no_comments_* filters.
add_action(
	'after_setup_theme',
	function () {
		Blocker::init();
		Admin::init();
	},
	100
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/includes/class-cli.php';
	\WP_CLI::add_command( 'no-comments', __NAMESPACE__ . '\CLI' );
}

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'jfw-no-comments', false, dirname( plugin_basename( FILE ) ) . '/languages' );
	}
);

// Updates from the GitHub releases of the repository.
require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';

\YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/julienfelixweb/jfw-no-comments/',
	FILE,
	'jfw-no-comments'
)->setBranch( 'main' );
