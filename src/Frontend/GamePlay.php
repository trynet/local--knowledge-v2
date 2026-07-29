<?php
/**
 * Public Game answer submission and checking.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Frontend;

use JoyOfCode\LocalKnowledge\Admin\GamePostType;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates public Game answer submissions using server-side temporary state.
 *
 * Image stage and completion are authoritative in GameState transients.
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

		$this->redirect_with_flash( $game_number, $token );
	}

	/**
	 * Build playable view extras for the current GET request.
	 *
	 * @param int $game_id     Game post ID.
	 * @param int $game_number Game Number from the route.
	 * @return array<string, mixed>
	 */
	public function get_view_extras( int $game_id, int $game_number ): array {
		$state  = $this->state_store->get_public_state( $game_id );
		$extras = $this->default_view_extras( $game_id );

		$extras['clean_game_url'] = GameRoute::get_public_url( $game_number );
		$extras['image_stage']    = (int) $state['image_stage'];
		$extras['game_locked']    = ! empty( $state['ended'] ) && 'correct' === $state['result_type'];

		if ( $extras['game_locked'] ) {
			$display = new GameDisplayData();
			$key     = $display->get_correct_location_key( $game_id );

			if ( '' !== $key ) {
				$extras['correct_location_label'] = $display->get_location_label( $game_id, $key );
			}

			// Completed Games show the success message on every revisit.
			$extras['feedback'] = 'correct';
		}

		$flash = $this->decode_flash_from_request( $game_id );

		if ( null !== $flash ) {
			$extras['feedback']             = (string) $flash['feedback'];
			$extras['selected_choice']      = (string) $flash['selected_choice'];
			$extras['strip_flash_from_url'] = true;

			// Do not let a stale flash unlock a completed Game UI.
			if ( $extras['game_locked'] ) {
				$extras['feedback'] = 'correct';
			}
		}

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

		// Completed Games reject further submissions on the server.
		if ( ! empty( $state['ended'] ) && 'correct' === $state['result_type'] ) {
			$result['feedback'] = 'already_ended';
			$this->state_store->save_public_state( $state );
			return $result;
		}

		$stage = (int) $state['image_stage'];

		$choice = isset( $_POST['lk_location'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['lk_location'] ) )
			: '';

		$result['selected_choice'] = $choice;

		if ( '' === $choice ) {
			$result['feedback'] = 'missing';
			$this->state_store->save_public_state( $state );
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
			// Correct: keep the current stage unchanged and lock the Game.
			$state['image_stage'] = $stage;
			$state['ended']       = true;
			$state['result_type'] = 'correct';
			$this->state_store->save_public_state( $state );

			$result['feedback'] = 'correct';
			return $result;
		}

		// Incorrect: advance 1→2→3→4, then stay on 4.
		$next_stage = match ( $stage ) {
			1       => 2,
			2       => 3,
			3       => 4,
			default => 4,
		};

		$state['image_stage'] = $next_stage;
		$state['ended']       = false;
		$state['result_type'] = '';
		$this->state_store->save_public_state( $state );

		$result['feedback'] = 'incorrect';
		return $result;
	}

	/**
	 * Redirect to the public Game URL with a feedback flash token.
	 *
	 * @param int    $game_number Game Number.
	 * @param string $token       Signed flash token.
	 */
	private function redirect_with_flash( int $game_number, string $token ): void {
		$url = GameRoute::get_public_url( $game_number );

		if ( '' === $url ) {
			$url = home_url( '/' );
		}

		$redirect = add_query_arg( self::FLASH_QUERY_VAR, $token, $url );

		wp_safe_redirect( $redirect, 303 );
		exit;
	}

	/**
	 * Encode a short-lived signed flash token (feedback only).
	 *
	 * @param int    $game_id  Game post ID.
	 * @param string $feedback Feedback code.
	 * @param string $choice   Selected choice 1–4 or empty.
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
			'missing',
			'invalid_choice',
			'invalid_nonce',
			'invalid_game',
			'already_ended',
		);

		if ( ! in_array( $feedback, $allowed_feedback, true ) ) {
			return null;
		}

		if ( ! in_array( $choice, array( '', '1', '2', '3', '4' ), true ) ) {
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
			'clean_game_url'         => '',
			'strip_flash_from_url'   => false,
			'image_stage'            => 1,
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
}
