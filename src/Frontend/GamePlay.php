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
 * Evaluates public Game answer submissions on the server.
 *
 * Milestone 6A uses redirect-after-POST with a short-lived signed query
 * token. No cookies, transients, sessions, or permanent storage.
 */
final class GamePlay {

	/**
	 * Form action identifier posted with the Game form.
	 */
	public const FORM_ACTION = 'lk_game_submit';

	/**
	 * Query var carrying the one-time signed result token.
	 */
	public const FLASH_QUERY_VAR = 'lk_msg';

	/**
	 * Flash token lifetime in seconds.
	 */
	private const FLASH_TTL = 60;

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

		$url = GameRoute::get_public_url( $game_number );

		if ( '' === $url ) {
			$url = home_url( '/' );
		}

		$redirect = add_query_arg( self::FLASH_QUERY_VAR, $token, $url );

		wp_safe_redirect( $redirect, 303 );
		exit;
	}

	/**
	 * Build playable view extras for the current GET request.
	 *
	 * @param int $game_id     Game post ID.
	 * @param int $game_number Game Number from the route.
	 * @return array<string, mixed>
	 */
	public function get_view_extras( int $game_id, int $game_number ): array {
		$extras = $this->default_view_extras( $game_id );
		$extras['clean_game_url'] = GameRoute::get_public_url( $game_number );

		$flash = $this->decode_flash_from_request( $game_id );

		if ( null === $flash ) {
			return $extras;
		}

		$feedback = (string) $flash['feedback'];
		$choice   = (string) $flash['selected_choice'];

		$extras['feedback']            = $feedback;
		$extras['selected_choice']     = $choice;
		$extras['strip_flash_from_url'] = true;

		if ( 'correct' === $feedback ) {
			$display = new GameDisplayData();
			$key     = $display->get_correct_location_key( $game_id );

			$extras['game_locked'] = true;

			if ( '' !== $key ) {
				$extras['correct_location_label'] = $display->get_location_label( $game_id, $key );
			}
		}

		return $extras;
	}

	/**
	 * Evaluate a POST submission and return safe result fields.
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

		$choice = isset( $_POST['lk_location'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['lk_location'] ) )
			: '';

		$result['selected_choice'] = $choice;

		if ( '' === $choice ) {
			$result['feedback'] = 'missing';
			return $result;
		}

		if ( ! in_array( $choice, array( '1', '2', '3', '4' ), true ) ) {
			$result['feedback']        = 'invalid_choice';
			$result['selected_choice'] = '';
			return $result;
		}

		$correct = $display->get_correct_location_key( $game_id );

		if ( '' === $correct ) {
			$result['feedback'] = 'invalid_game';
			return $result;
		}

		if ( $choice === $correct ) {
			$result['feedback'] = 'correct';
			return $result;
		}

		$result['feedback'] = 'incorrect';
		return $result;
	}

	/**
	 * Encode a short-lived signed flash token (no correct-answer key).
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

		$signature = hash_hmac( 'sha256', $body, $this->flash_secret() );

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

		$expected = hash_hmac( 'sha256', $body, $this->flash_secret() );

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
	 * Secret used to sign flash tokens.
	 */
	private function flash_secret(): string {
		return wp_salt( 'nonce' );
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
