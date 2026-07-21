<?php
/**
 * Plugin activation handler.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Runs environment checks and safe activation setup.
 */
final class Activator {

	/**
	 * Minimum supported PHP version.
	 */
	private const MIN_PHP_VERSION = '8.1';

	/**
	 * Minimum supported WordPress version.
	 */
	private const MIN_WP_VERSION = '6.6';

	/**
	 * Handle plugin activation.
	 */
	public static function activate(): void {
		self::verify_environment();

		// Rewrite rules are not flushed here until custom rewrite-capable
		// content types are registered in a later milestone.
	}

	/**
	 * Verify the hosting environment meets plugin requirements.
	 */
	private static function verify_environment(): void {
		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			self::fail_activation(
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					__( 'Local Knowledge requires PHP %1$s or later. This site is running PHP %2$s.', 'local-knowledge' ),
					self::MIN_PHP_VERSION,
					PHP_VERSION
				)
			);
		}

		global $wp_version;

		if ( version_compare( (string) $wp_version, self::MIN_WP_VERSION, '<' ) ) {
			self::fail_activation(
				sprintf(
					/* translators: 1: required WordPress version, 2: current WordPress version */
					__( 'Local Knowledge requires WordPress %1$s or later. This site is running WordPress %2$s.', 'local-knowledge' ),
					self::MIN_WP_VERSION,
					(string) $wp_version
				)
			);
		}
	}

	/**
	 * Deactivate the plugin and stop activation with an admin-visible error.
	 *
	 * @param string $message Human-readable failure reason.
	 */
	private static function fail_activation( string $message ): void {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( LK_PLUGIN_BASENAME );

		wp_die(
			esc_html( $message ),
			esc_html__( 'Plugin Activation Error', 'local-knowledge' ),
			array( 'back_link' => true )
		);
	}
}
