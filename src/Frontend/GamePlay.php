<?php
/**
 * Public Game answer submission and checking.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Frontend;

use JoyOfCode\LocalKnowledge\Admin\GamePostType;
use JoyOfCode\LocalKnowledge\Player\Game2ResultPersister;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates public Game answer submissions using server-side temporary state.
 *
 * current_view and completion are authoritative in GameState transients.
 * A short-lived signed flash token carries feedback across redirect-after-POST only.
 */
final class GamePlay {

	/**
	 * Form action identifier posted with the Game form.
	 */
	public const FORM_ACTION = 'lk_game_submit';

	/**
	 * Query var carrying the one-time signed feedback token.
	 */
	public const FLASH_QUERY_VAR = 'lk_msg';

	/**
	 * Query flag: show a logged-in player's just-completed game before resolver advances.
	 */
	public const COMPLETE_QUERY = 'lk_game_complete';

	/**
	 * Flash token lifetime in seconds.
	 */
	private const FLASH_TTL = 60;

	/**
	 * Temporary state manager.
	 */
	private GameState $state_store;

	/**
	 * Constructor.
	 */
	public function __construct( ?GameState $state_store = null ) {
		$this->state_store = $state_store ?? new GameState();
	}

	/**
	 * Wire hooks. Submission is handled during public Game routing.
	 */
	public function register(): void {
		// Intentionally empty: GameRoute calls into this class while rendering.
	}

	/**
	 * If this request is a Game form POST, evaluate it and redirect (303).
	 *
	 * @param int $game_id     Published Game post ID.
	 * @param int $game_number Expected Game Number from the route.
	 */
	public function maybe_redirect_after_post( int $game_id, int $game_number ): void {
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}

		$posted_action = isset( $_POST['lk_game_action'] )
			? sanitize_key( wp_unslash( (string) $_POST['lk_game_action'] ) )
			: '';

		if ( self::FORM_ACTION !== $posted_action ) {
			return;
		}

		$result = $this->evaluate_submission( $game_id, $game_number );
		$token  = $this->encode_flash_token(
			$game_id,
			(string) $result['feedback'],
			(string) $result['selected_choice']
		);

		$this->redirect_with_flash( $game_number, $token, (string) $result['feedback'] );
	}

	/**
	 * Build playable view extras for the current GET request.
	 *
	 * @param int $game_id     Game post ID.
	 * @param int $game_number Game Number from the route.
	 * @return array<string, mixed>
	 */
	public function get_view_extras( int $game_id, int $game_number ): array {
		$state   = $this->state_store->get_public_state( $game_id );
		$extras  = $this->default_view_extras( $game_id );
		$display = new GameDisplayData();
		$view    = (int) $state['current_view'];
		$result  = (string) $state['result_type'];
		$ended   = ! empty( $state['ended'] );

		$locked      = $ended && in_array( $result, array( 'correct', 'idk' ), true );
		$comparison  = GameState::VIEW_COMPARISON === $view;

		$extras['clean_game_url']   = PlayPage::get_url();
		if ( '' === $extras['clean_game_url'] ) {
			$extras['clean_game_url'] = GameRoute::get_public_url( $game_number );
		}
		$extras['current_view']     = $view;
		$extras['image_stage']      = $comparison ? 0 : $view;
		$extras['game_locked']      = $locked;
		$extras['show_comparison']  = $comparison;
		$extras['show_idk']         = $comparison && ! $locked;
		$extras['show_large_image'] = ! $comparison;

		if ( $comparison ) {
			$extras['comparison_images'] = $display->get_comparison_images( $game_id );
		}

		if ( $locked ) {
			$key = $display->get_correct_location_key( $game_id );

			if ( '' !== $key ) {
				$extras['correct_location_label'] = $display->get_location_label( $game_id, $key );
			}

			$extras['feedback']          = $result;
			$extras['show_completion']   = true;
			$extras['completion_result'] = $result;

			if ( in_array( $result, array( 'correct', 'idk' ), true ) ) {
				$extras = $this->append_correct_completion_extras( $extras, $game_id, $game_number );
			}
		}

		$flash = $this->decode_flash_from_request( $game_id );

		if ( null !== $flash ) {
			$extras['feedback']             = (string) $flash['feedback'];
			$extras['selected_choice']      = (string) $flash['selected_choice'];
			$extras['strip_flash_from_url'] = true;

			if ( $extras['game_locked'] ) {
				$extras['feedback'] = $result;
			}
		}

		// Flash never drives the authoritative view.
		$extras['current_view']     = $view;
		$extras['image_stage']      = $comparison ? 0 : $view;
		$extras['show_comparison']  = $comparison;
		$extras['show_large_image'] = ! $comparison;
		$extras['show_idk']         = $comparison && ! $locked;

		return $extras;
	}

	/**
	 * Evaluate a POST submission against server-side temporary state.
	 *
	 * @param int $game_id     Published Game post ID.
	 * @param int $game_number Expected Game Number from the route.
	 * @return array{feedback: string, selected_choice: string}
	 */
	private function evaluate_submission( int $game_id, int $game_number ): array {
		$result = array(
			'feedback'        => 'invalid_game',
			'selected_choice' => '',
		);

		$posted_game_id = isset( $_POST['lk_game_id'] ) ? absint( wp_unslash( $_POST['lk_game_id'] ) ) : 0;

		if ( $posted_game_id !== $game_id || $game_id < 1 ) {
			return $result;
		}

		$nonce = isset( $_POST['lk_game_submit_nonce'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['lk_game_submit_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, $this->nonce_action( $game_id ) ) ) {
			$result['feedback'] = 'invalid_nonce';
			return $result;
		}

		$post = get_post( $game_id );

		if ( ! $post instanceof \WP_Post
			|| GamePostType::POST_TYPE !== $post->post_type
			|| 'publish' !== $post->post_status
		) {
			return $result;
		}

		$stored_number = absint( get_post_meta( $game_id, GameDisplayData::META_KEYS['game_number'], true ) );

		if ( $stored_number !== $game_number || $game_number < 1 ) {
			return $result;
		}

		$display = new GameDisplayData();

		if ( array() !== $display->get_completeness_errors( $game_id ) ) {
			return $result;
		}

		$state = $this->state_store->get_public_state( $game_id );

		if ( ! empty( $state['ended'] ) && in_array( (string) $state['result_type'], array( 'correct', 'idk' ), true ) ) {
			$result['feedback'] = 'already_ended';
			$this->state_store->save_public_state( $state );
			return $result;
		}

		$view = (int) $state['current_view'];

		$choice = isset( $_POST['lk_location'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['lk_location'] ) )
			: '';

		$result['selected_choice'] = $choice;

		if ( '' === $choice ) {
			$result['feedback'] = 'missing';
			$this->state_store->save_public_state( $state );
			return $result;
		}

		// IDK is only valid on the comparison view.
		if ( 'idk' === $choice ) {
			if ( GameState::VIEW_COMPARISON !== $view ) {
				$result['feedback']        = 'invalid_choice';
				$result['selected_choice'] = '';
				$this->state_store->save_public_state( $state );
				return $result;
			}

			$correct = $display->get_correct_location_key( $game_id );

			if ( '' === $correct ) {
				$result['feedback'] = 'invalid_game';
				return $result;
			}

			$state['current_view'] = GameState::VIEW_COMPARISON;
			$state['ended']        = true;
			$state['result_type']  = 'idk';
			$this->state_store->save_public_state( $state );
			( new Game2ResultPersister() )->maybe_persist( $game_id, $game_number, $state );

			$result['feedback'] = 'idk';
			return $result;
		}

		if ( ! in_array( $choice, array( '1', '2', '3', '4' ), true ) ) {
			$result['feedback']        = 'invalid_choice';
			$result['selected_choice'] = '';
			$this->state_store->save_public_state( $state );
			return $result;
		}

		$correct = $display->get_correct_location_key( $game_id );

		if ( '' === $correct ) {
			$result['feedback'] = 'invalid_game';
			return $result;
		}

		if ( $choice === $correct ) {
			// Correct: keep the current view and lock (Views 1–5).
			$state['current_view'] = $view;
			$state['ended']        = true;
			$state['result_type']  = 'correct';
			$this->state_store->save_public_state( $state );
			( new Game2ResultPersister() )->maybe_persist( $game_id, $game_number, $state );

			$result['feedback'] = 'correct';
			return $result;
		}

		// Incorrect: advance Views 1→2→3→4→5, then remain on View 5.
		$next_view = match ( $view ) {
			1       => 2,
			2       => 3,
			3       => 4,
			4       => GameState::VIEW_COMPARISON,
			default => GameState::VIEW_COMPARISON,
		};

		$state['current_view'] = $next_view;
		$state['ended']        = false;
		$state['result_type']  = '';
		$this->state_store->save_public_state( $state );

		$result['feedback'] = 'incorrect';
		return $result;
	}

	/**
	 * Redirect to the Play page (preferred) or public Game URL with a feedback flash token.
	 *
	 * @param int    $game_number Game Number.
	 * @param string $token       Signed flash token.
	 * @param string $feedback    Submission feedback code.
	 */
	private function redirect_with_flash( int $game_number, string $token, string $feedback ): void {
		$url = PlayPage::get_url();

		if ( '' === $url ) {
			$url = GameRoute::get_public_url( $game_number );
		}

		if ( '' === $url ) {
			$url = home_url( '/' );
		}

		$redirect = add_query_arg( self::FLASH_QUERY_VAR, $token, $url );

		if ( in_array( $feedback, array( 'correct', 'idk' ), true ) && is_user_logged_in() && $game_number >= 2 && $game_number <= 10 ) {
			$redirect = add_query_arg( self::COMPLETE_QUERY, (string) $game_number, $redirect );
		}

		wp_safe_redirect( $redirect, 303 );
		exit;
	}

	/**
	 * Encode a short-lived signed flash token (feedback only).
	 *
	 * @param int    $game_id  Game post ID.
	 * @param string $feedback Feedback code.
	 * @param string $choice   Selected choice 1–4, idk, or empty.
	 */
	private function encode_flash_token( int $game_id, string $feedback, string $choice ): string {
		$payload = array(
			'g' => $game_id,
			'f' => $feedback,
			'c' => $choice,
			'e' => time() + self::FLASH_TTL,
		);

		$body = wp_json_encode( $payload );

		if ( ! is_string( $body ) ) {
			$body = '{}';
		}

		$signature = hash_hmac( 'sha256', $body, wp_salt( 'nonce' ) );

		return base64_encode( $body ) . '.' . $signature;
	}

	/**
	 * Decode and validate a flash token from the current GET request.
	 *
	 * @param int $game_id Expected Game post ID.
	 * @return array{feedback: string, selected_choice: string}|null
	 */
	private function decode_flash_from_request( int $game_id ): ?array {
		if ( ! isset( $_GET[ self::FLASH_QUERY_VAR ] ) ) {
			return null;
		}

		$raw = sanitize_text_field( wp_unslash( (string) $_GET[ self::FLASH_QUERY_VAR ] ) );

		if ( '' === $raw || ! str_contains( $raw, '.' ) ) {
			return null;
		}

		[$encoded_body, $signature] = explode( '.', $raw, 2 );

		if ( '' === $encoded_body || '' === $signature ) {
			return null;
		}

		$body = base64_decode( $encoded_body, true );

		if ( ! is_string( $body ) || '' === $body ) {
			return null;
		}

		$expected = hash_hmac( 'sha256', $body, wp_salt( 'nonce' ) );

		if ( ! hash_equals( $expected, $signature ) ) {
			return null;
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		$token_game_id = isset( $data['g'] ) ? absint( $data['g'] ) : 0;
		$expires       = isset( $data['e'] ) ? absint( $data['e'] ) : 0;
		$feedback      = isset( $data['f'] ) ? sanitize_key( (string) $data['f'] ) : '';
		$choice        = isset( $data['c'] ) ? sanitize_text_field( (string) $data['c'] ) : '';

		if ( $token_game_id !== $game_id || $expires < time() || '' === $feedback ) {
			return null;
		}

		$allowed_feedback = array(
			'correct',
			'incorrect',
			'idk',
			'missing',
			'invalid_choice',
			'invalid_nonce',
			'invalid_game',
			'already_ended',
		);

		if ( ! in_array( $feedback, $allowed_feedback, true ) ) {
			return null;
		}

		if ( ! in_array( $choice, array( '', '1', '2', '3', '4', 'idk' ), true ) ) {
			$choice = '';
		}

		return array(
			'feedback'        => $feedback,
			'selected_choice' => $choice,
		);
	}

	/**
	 * Default playable view extras.
	 *
	 * @param int $game_id Game post ID.
	 * @return array<string, mixed>
	 */
	public function default_view_extras( int $game_id ): array {
		return array(
			'playable'               => true,
			'game_id'                => $game_id,
			'nonce_action'           => $this->nonce_action( $game_id ),
			'nonce_field'            => 'lk_game_submit_nonce',
			'form_action_value'      => self::FORM_ACTION,
			'feedback'               => '',
			'selected_choice'        => '',
			'game_locked'            => false,
			'correct_location_label' => '',
			'show_completion'        => false,
			'completion_result'      => '',
			'clean_game_url'         => '',
			'strip_flash_from_url'   => false,
			'current_view'           => 1,
			'image_stage'            => 1,
			'show_large_image'       => true,
			'show_comparison'        => false,
			'show_idk'               => false,
			'comparison_images'      => array(),
		);
	}

	/**
	 * Nonce action for a Game submission.
	 *
	 * @param int $game_id Game post ID.
	 */
	private function nonce_action( int $game_id ): string {
		return 'lk_game_submit_' . $game_id;
	}

	/**
	 * Historical information and Proceed control after a locked completion (correct or IDK).
	 *
	 * @param array<string, mixed> $extras      View extras.
	 * @param int                  $game_id     Game post ID.
	 * @param int                  $game_number Game Number.
	 * @return array<string, mixed>
	 */
	private function append_correct_completion_extras( array $extras, int $game_id, int $game_number ): array {
		$display    = new GameDisplayData();
		$historical = $display->get_historical_information( $game_id );

		if ( '' !== $historical ) {
			$extras['historical_information'] = $historical;
		}

		if ( is_user_logged_in() && $game_number >= 2 && $game_number <= 9 ) {
			$play = PlayPage::get_url();

			$extras['show_proceed_next_game'] = true;
			$extras['proceed_next_game_url']  = '' !== $play ? $play : home_url( '/' );
		}

		return $extras;
	}
}
