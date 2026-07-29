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
 * Milestone 6B carries the current image stage through the active request
 * via a signed flash token and a signed stage proof in the form.
 * No cookies, transients, sessions, or permanent storage.
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
	 * Flash token lifetime in seconds (result redirect only).
	 */
	private const FLASH_TTL = 60;

	/**
	 * Stage-proof lifetime in seconds (active form only; reload still resets).
	 */
	private const STAGE_PROOF_TTL = 12 * HOUR_IN_SECONDS;

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
			(string) $result['selected_choice'],
			(int) $result['image_stage']
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
			$extras['image_stage'] = 1;
			$extras['stage_token'] = $this->encode_stage_token( $game_id, 1 );
			return $extras;
		}

		$feedback = (string) $flash['feedback'];
		$choice   = (string) $flash['selected_choice'];
		$stage    = (int) $flash['image_stage'];

		$extras['feedback']             = $feedback;
		$extras['selected_choice']      = $choice;
		$extras['image_stage']          = $stage;
		$extras['stage_token']          = $this->encode_stage_token( $game_id, $stage );
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
	 * @return array{feedback: string, selected_choice: string, image_stage: int}
	 */
	private function evaluate_submission( int $game_id, int $game_number ): array {
		$result = array(
			'feedback'        => 'invalid_game',
			'selected_choice' => '',
			'image_stage'     => 1,
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

		$stage = $this->resolve_submitted_stage( $game_id );
		$result['image_stage'] = $stage;

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
			// Keep the currently displayed image.
			$result['feedback']    = 'correct';
			$result['image_stage'] = $stage;
			return $result;
		}

		// Incorrect: advance 1→2→3→4; remain on 4 thereafter.
		$result['feedback']    = 'incorrect';
		$result['image_stage'] = $stage < 4 ? $stage + 1 : 4;
		return $result;
	}

	/**
	 * Resolve the submitted image stage using the signed stage proof.
	 *
	 * The hidden field alone is not trusted: the signed proof must match.
	 *
	 * @param int $game_id Game post ID.
	 */
	private function resolve_submitted_stage( int $game_id ): int {
		$posted_stage = isset( $_POST['lk_image_stage'] )
			? absint( wp_unslash( $_POST['lk_image_stage'] ) )
			: 1;

		if ( $posted_stage < 1 || $posted_stage > 4 ) {
			return 1;
		}

		$token = isset( $_POST['lk_stage_token'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['lk_stage_token'] ) )
			: '';

		$proven = $this->decode_stage_token( $token, $game_id );

		if ( null === $proven || $proven !== $posted_stage ) {
			return 1;
		}

		return $posted_stage;
	}

	/**
	 * Encode a short-lived signed flash token (no correct-answer key).
	 *
	 * @param int    $game_id  Game post ID.
	 * @param string $feedback Feedback code.
	 * @param string $choice   Selected choice 1–4 or empty.
	 * @param int    $stage    Image stage to display (1–4).
	 */
	private function encode_flash_token( int $game_id, string $feedback, string $choice, int $stage ): string {
		$payload = array(
			'g' => $game_id,
			'f' => $feedback,
			'c' => $choice,
			'i' => max( 1, min( 4, $stage ) ),
			'e' => time() + self::FLASH_TTL,
		);

		return $this->sign_payload( $payload );
	}

	/**
	 * Decode and validate a flash token from the current GET request.
	 *
	 * @param int $game_id Expected Game post ID.
	 * @return array{feedback: string, selected_choice: string, image_stage: int}|null
	 */
	private function decode_flash_from_request( int $game_id ): ?array {
		if ( ! isset( $_GET[ self::FLASH_QUERY_VAR ] ) ) {
			return null;
		}

		$raw = sanitize_text_field( wp_unslash( (string) $_GET[ self::FLASH_QUERY_VAR ] ) );
		$data = $this->verify_signed_payload( $raw );

		if ( null === $data ) {
			return null;
		}

		$token_game_id = isset( $data['g'] ) ? absint( $data['g'] ) : 0;
		$expires       = isset( $data['e'] ) ? absint( $data['e'] ) : 0;
		$feedback      = isset( $data['f'] ) ? sanitize_key( (string) $data['f'] ) : '';
		$choice        = isset( $data['c'] ) ? sanitize_text_field( (string) $data['c'] ) : '';
		$stage         = isset( $data['i'] ) ? absint( $data['i'] ) : 1;

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

		if ( $stage < 1 || $stage > 4 ) {
			$stage = 1;
		}

		return array(
			'feedback'        => $feedback,
			'selected_choice' => $choice,
			'image_stage'     => $stage,
		);
	}

	/**
	 * Encode a signed stage proof for the playable form.
	 *
	 * @param int $game_id Game post ID.
	 * @param int $stage   Image stage 1–4.
	 */
	private function encode_stage_token( int $game_id, int $stage ): string {
		$payload = array(
			'g' => $game_id,
			'i' => max( 1, min( 4, $stage ) ),
			'e' => time() + self::STAGE_PROOF_TTL,
			't' => 'stage',
		);

		return $this->sign_payload( $payload );
	}

	/**
	 * Decode a signed stage proof.
	 *
	 * @param string $token   Raw token.
	 * @param int    $game_id Expected Game post ID.
	 */
	private function decode_stage_token( string $token, int $game_id ): ?int {
		$data = $this->verify_signed_payload( $token );

		if ( null === $data ) {
			return null;
		}

		if ( ( $data['t'] ?? '' ) !== 'stage' ) {
			return null;
		}

		$token_game_id = isset( $data['g'] ) ? absint( $data['g'] ) : 0;
		$expires       = isset( $data['e'] ) ? absint( $data['e'] ) : 0;
		$stage         = isset( $data['i'] ) ? absint( $data['i'] ) : 0;

		if ( $token_game_id !== $game_id || $expires < time() || $stage < 1 || $stage > 4 ) {
			return null;
		}

		return $stage;
	}

	/**
	 * Sign a payload array into a transport token.
	 *
	 * @param array<string, mixed> $payload Payload data.
	 */
	private function sign_payload( array $payload ): string {
		$body = wp_json_encode( $payload );

		if ( ! is_string( $body ) ) {
			$body = '{}';
		}

		$signature = hash_hmac( 'sha256', $body, $this->token_secret() );

		return base64_encode( $body ) . '.' . $signature;
	}

	/**
	 * Verify a signed transport token and return the payload.
	 *
	 * @param string $token Raw token.
	 * @return array<string, mixed>|null
	 */
	private function verify_signed_payload( string $token ): ?array {
		if ( '' === $token || ! str_contains( $token, '.' ) ) {
			return null;
		}

		[$encoded_body, $signature] = explode( '.', $token, 2 );

		if ( '' === $encoded_body || '' === $signature ) {
			return null;
		}

		$body = base64_decode( $encoded_body, true );

		if ( ! is_string( $body ) || '' === $body ) {
			return null;
		}

		$expected = hash_hmac( 'sha256', $body, $this->token_secret() );

		if ( ! hash_equals( $expected, $signature ) ) {
			return null;
		}

		$data = json_decode( $body, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Secret used to sign flash and stage tokens.
	 */
	private function token_secret(): string {
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
			'image_stage'            => 1,
			'stage_token'            => '',
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
