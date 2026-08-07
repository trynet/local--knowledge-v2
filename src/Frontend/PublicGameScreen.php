<?php
/**
 * Shared public Game process + view builder for routes and shortcodes.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Processes POST and builds prepared view data for a published Game.
 */
final class PublicGameScreen {

	/**
	 * Temporary state.
	 */
	private GameState $state_store;

	/**
	 * Answer submission handler.
	 */
	private GamePlay $play;

	/**
	 * Registration handler.
	 */
	private RegistrationGateway $registration;

	/**
	 * Display data helper.
	 */
	private GameDisplayData $display;

	/**
	 * Constructor.
	 */
	public function __construct(
		?GameState $state_store = null,
		?GamePlay $play = null,
		?RegistrationGateway $registration = null,
		?GameDisplayData $display = null
	) {
		$this->state_store  = $state_store ?? new GameState();
		$this->play         = $play ?? new GamePlay( $this->state_store );
		$this->registration = $registration ?? new RegistrationGateway( $this->state_store );
		$this->display      = $display ?? new GameDisplayData();
	}

	/**
	 * Handle answer / registration POSTs for this Game (may redirect and exit).
	 *
	 * @param int $game_id     Published Game post ID.
	 * @param int $game_number Game Number.
	 */
	public function process_request( int $game_id, int $game_number ): void {
		$this->play->maybe_redirect_after_post( $game_id, $game_number );
		$this->registration->maybe_process( $game_id, $game_number );
	}

	/**
	 * Build full view data for rendering.
	 *
	 * @param int                  $game_id     Game post ID.
	 * @param int                  $game_number Game Number.
	 * @param array<string, mixed> $overlay     Extra view flags (handoff, etc.).
	 * @return array<string, mixed>
	 */
	public function build_view( int $game_id, int $game_number, array $overlay = array() ): array {
		$state  = $this->state_store->get_public_state( $game_id );
		$extras = $this->play->get_view_extras( $game_id, $game_number );
		$extras = array_merge( $extras, $this->registration->get_view_extras( $game_id, $game_number, $state ) );
		$extras = array_merge( $extras, $overlay );

		$view_n = isset( $extras['current_view'] ) ? absint( $extras['current_view'] ) : 1;
		$view_n = max( 1, min( GameState::VIEW_COMPARISON, $view_n ) );

		$is_comparison = GameState::VIEW_COMPARISON === $view_n;

		if ( $is_comparison ) {
			$view                      = $this->display->build_view( $game_id, false, 1 );
			$view['image_id']          = 0;
			$view['image_url']         = '';
			$view['image_alt']         = '';
			$view['image_stage']       = 0;
			$view                      = array_merge( $view, $extras );
			$view['current_view']      = GameState::VIEW_COMPARISON;
			$view['show_comparison']   = true;
			$view['show_large_image']  = false;
			$view['comparison_images'] = isset( $extras['comparison_images'] ) && is_array( $extras['comparison_images'] )
				? $extras['comparison_images']
				: $this->display->get_comparison_images( $game_id );
		} else {
			$view                     = $this->display->build_view( $game_id, false, $view_n );
			$view                     = array_merge( $view, $extras );
			$view['current_view']     = $view_n;
			$view['image_stage']      = $view_n;
			$view['show_comparison']  = false;
			$view['show_large_image'] = true;
		}

		// Prefer Play page for clean URLs / flash strip when available.
		$play_url = PlayPage::get_url();

		if ( '' !== $play_url ) {
			$view['clean_game_url'] = $play_url;
		}

		return $view;
	}
}
