<?php
/**
 * Plugin Name:       Local Knowledge
 * Description:       Location-guessing contest application for WordPress.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Bud Kraus
 * Text Domain:       local-knowledge
 * Domain Path:       /languages
 *
 * @package JoyOfCode\LocalKnowledge
 */

defined( 'ABSPATH' ) || exit;

define( 'LK_VERSION', '1.0.0' );
define( 'LK_PLUGIN_FILE', __FILE__ );
define( 'LK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

$lk_autoload = LK_PLUGIN_DIR . 'vendor/autoload.php';

if ( ! file_exists( $lk_autoload ) ) {
	return;
}

require_once $lk_autoload;

register_activation_hook(
	__FILE__,
	array( \JoyOfCode\LocalKnowledge\Activator::class, 'activate' )
);

register_deactivation_hook(
	__FILE__,
	array( \JoyOfCode\LocalKnowledge\Deactivator::class, 'deactivate' )
);

/**
 * Bootstraps the Local Knowledge plugin.
 */
function local_knowledge_run(): void {
	$plugin = new \JoyOfCode\LocalKnowledge\Plugin();
	$plugin->run();
}

local_knowledge_run();
