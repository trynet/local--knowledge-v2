<?php
/**
 * Plugin deactivation handler.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge;

use JoyOfCode\LocalKnowledge\Frontend\GameRoute;

defined( 'ABSPATH' ) || exit;

/**
 * Performs safe deactivation cleanup without deleting plugin data.
 */
final class Deactivator {

	/**
	 * Handle plugin deactivation.
	 *
	 * Flushes rewrite rules so plugin routes are removed. Does not delete
	 * Game content or other plugin data.
	 */
	public static function deactivate(): void {
		GameRoute::deactivate_rewrites();
	}
}
