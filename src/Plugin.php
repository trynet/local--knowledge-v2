<?php
/**
 * Main plugin bootstrap class.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge;

use JoyOfCode\LocalKnowledge\Admin\GameEditor;
use JoyOfCode\LocalKnowledge\Admin\GamePostType;
use JoyOfCode\LocalKnowledge\Admin\GameValidator;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates plugin initialization.
 */
final class Plugin {

	/**
	 * Initialize the plugin.
	 */
	public function run(): void {
		$game_post_type = new GamePostType();
		add_action( 'init', array( $game_post_type, 'register' ) );

		$game_editor = new GameEditor();
		$game_editor->register();

		$game_validator = new GameValidator();
		$game_validator->register();
	}
}
