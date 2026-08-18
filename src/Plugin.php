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
use JoyOfCode\LocalKnowledge\Admin\GamePreview;
use JoyOfCode\LocalKnowledge\Admin\GameValidator;
use JoyOfCode\LocalKnowledge\Frontend\AuthNav;
use JoyOfCode\LocalKnowledge\Frontend\GamePlay;
use JoyOfCode\LocalKnowledge\Frontend\GameRoute;
use JoyOfCode\LocalKnowledge\Frontend\LoginRedirect;
use JoyOfCode\LocalKnowledge\Frontend\RegistrationGateway;
use JoyOfCode\LocalKnowledge\Frontend\Shortcodes;

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

		$game_preview = new GamePreview();
		$game_preview->register();

		$game_route = new GameRoute();
		$game_route->register();

		$game_play = new GamePlay();
		$game_play->register();

		$registration = new RegistrationGateway();
		$registration->register();

		$shortcodes = new Shortcodes();
		$shortcodes->register();

		$login_redirect = new LoginRedirect();
		$login_redirect->register();

		$auth_nav = new AuthNav();
		$auth_nav->register();
	}
}
