<?php
/**
 * Plugin deactivation handler.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Performs safe deactivation cleanup without deleting plugin data.
 */
final class Deactivator {

	/**
	 * Handle plugin deactivation.
	 *
	 * Currently performs no cleanup. Reserved for future reversible
	 * teardown that does not delete plugin or user data.
	 */
	public static function deactivate(): void {
	}
}
