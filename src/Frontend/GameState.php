<?php
/**
 * Anonymous visitor temporary gameplay state.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Manages opaque visitor cookies and per-Game public transients.
 *
 * Preview mode is intentionally unsupported: this class must never be used
 * for administrator Preview requests.
 */
final class GameState {

	/**
	 * Cookie name for the opaque visitor identifier.
	 */
	private const COOKIE_NAME = 'lk_vid';

	/**
	 * Transient key prefix.
	 */
	private const TRANSIENT_PREFIX = 'lk_gs_';

	/**
	 * State lifetime in seconds.
	 */
	public const TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Public gameplay mode key.
	 */
	public const MODE_PUBLIC = 'public';

	/**
	 * Visitor ID resolved for the current request (request-local memo).
	 */
	private string $visitor_id = '';

	/**
	 * Load or initialize public gameplay state for a Game.
	 *
	 * Does not overwrite an existing unfinished/completed stage with Image 1.
	 *
	 * @param int $game_id Game post ID.
	 * @return array{
	 *     game_id: int,
	 *     mode: string,
	 *     image_stage: int,
	 *     ended: bool,
	 *     result_type: string
	 * }
	 */
	public function get_public_state( int $game_id ): array {
		$visitor_id = $this->get_visitor_id();
		$stored     = $this->read_transient( $visitor_id, $game_id, self::MODE_PUBLIC );

		if ( null === $stored ) {
			$state = $this->initial_state( $game_id );
			$this->write_transient( $visitor_id, $state );
			$this->ensure_visitor_cookie( $visitor_id );
			return $state;
		}

		// Refresh expiration on legitimate public Game activity (load/continue).
		$this->write_transient( $visitor_id, $stored );
		$this->ensure_visitor_cookie( $visitor_id );

		return $stored;
	}

	/**
	 * Persist public gameplay state and refresh expiration.
	 *
	 * Guarantees the visitor cookie is written before any redirect-after-POST.
	 *
	 * @param array<string, mixed> $state Normalized state.
	 */
	public function save_public_state( array $state ): void {
		$state = $this->normalize_state( $state );

		if ( $state['game_id'] < 1 ) {
			return;
		}

		$visitor_id = $this->get_visitor_id();
		$this->write_transient( $visitor_id, $state );
		$this->ensure_visitor_cookie( $visitor_id );
	}

	/**
	 * Build the initial Image 1 unfinished state.
	 *
	 * @param int $game_id Game post ID.
	 * @return array{
	 *     game_id: int,
	 *     mode: string,
	 *     image_stage: int,
	 *     ended: bool,
	 *     result_type: string
	 * }
	 */
	public function initial_state( int $game_id ): array {
		return array(
			'game_id'     => absint( $game_id ),
			'mode'        => self::MODE_PUBLIC,
			'image_stage' => 1,
			'ended'       => false,
			'result_type' => '',
		);
	}

	/**
	 * Normalize and validate stored state, or null if unusable.
	 *
	 * @param mixed $raw     Raw transient value.
	 * @param int   $game_id Expected Game post ID.
	 * @return array<string, mixed>|null
	 */
	private function sanitize_stored_state( $raw, int $game_id ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$state = $this->normalize_state( $raw );

		if ( $state['game_id'] !== $game_id ) {
			return null;
		}

		if ( self::MODE_PUBLIC !== $state['mode'] ) {
			return null;
		}

		if ( $state['image_stage'] < 1 || $state['image_stage'] > 4 ) {
			return null;
		}

		if ( $state['ended'] && 'correct' !== $state['result_type'] ) {
			return null;
		}

		if ( ! $state['ended'] && '' !== $state['result_type'] ) {
			$state['result_type'] = '';
		}

		return $state;
	}

	/**
	 * Force a known shape for state arrays.
	 *
	 * @param array<string, mixed> $state Raw state.
	 * @return array{
	 *     game_id: int,
	 *     mode: string,
	 *     image_stage: int,
	 *     ended: bool,
	 *     result_type: string
	 * }
	 */
	private function normalize_state( array $state ): array {
		$game_id = isset( $state['game_id'] ) ? absint( $state['game_id'] ) : 0;
		$stage   = isset( $state['image_stage'] ) ? absint( $state['image_stage'] ) : 1;
		$ended   = ! empty( $state['ended'] );
		$result  = isset( $state['result_type'] ) ? sanitize_key( (string) $state['result_type'] ) : '';

		if ( $stage < 1 || $stage > 4 ) {
			$stage = 1;
		}

		if ( 'correct' !== $result ) {
			$result = '';
		}

		return array(
			'game_id'     => $game_id,
			'mode'        => self::MODE_PUBLIC,
			'image_stage' => $stage,
			'ended'       => $ended,
			'result_type' => $ended ? $result : '',
		);
	}

	/**
	 * Read and validate a transient for visitor/game/mode.
	 *
	 * @param string $visitor_id Opaque visitor ID.
	 * @param int    $game_id    Game post ID.
	 * @param string $mode       Rendering mode.
	 * @return array<string, mixed>|null
	 */
	private function read_transient( string $visitor_id, int $game_id, string $mode ): ?array {
		$key  = $this->transient_key( $visitor_id, $game_id, $mode );
		$raw  = get_transient( $key );
		$safe = $this->sanitize_stored_state( $raw, $game_id );

		if ( null === $safe ) {
			if ( false !== $raw ) {
				delete_transient( $key );
			}
			return null;
		}

		return $safe;
	}

	/**
	 * Write state to the transient key for this visitor/Game/mode.
	 *
	 * @param string               $visitor_id Opaque visitor ID.
	 * @param array<string, mixed> $state      Normalized state.
	 */
	private function write_transient( string $visitor_id, array $state ): void {
		$state = $this->normalize_state( $state );
		$key   = $this->transient_key( $visitor_id, $state['game_id'], self::MODE_PUBLIC );

		set_transient( $key, $state, self::TTL );
	}

	/**
	 * Build a transient key scoped by visitor, Game, and mode.
	 *
	 * Uses a stable hash of the scope values only (not a rotating salt) so
	 * save and load always share the same key for the same visitor/Game.
	 *
	 * @param string $visitor_id Opaque visitor ID.
	 * @param int    $game_id    Game post ID.
	 * @param string $mode       Rendering mode.
	 */
	private function transient_key( string $visitor_id, int $game_id, string $mode ): string {
		return self::TRANSIENT_PREFIX . md5( $visitor_id . '|' . $game_id . '|' . $mode );
	}

	/**
	 * Resolve the anonymous visitor identifier for this request.
	 *
	 * Reuses the cookie value when present. Creates a new ID only once per
	 * request when the cookie is missing — never on every call.
	 */
	private function get_visitor_id(): string {
		if ( '' !== $this->visitor_id ) {
			return $this->visitor_id;
		}

		$existing = $this->read_visitor_cookie();

		if ( '' !== $existing ) {
			$this->visitor_id = $existing;
			return $this->visitor_id;
		}

		$this->visitor_id = bin2hex( random_bytes( 32 ) );

		return $this->visitor_id;
	}

	/**
	 * Read a valid visitor ID from the cookie.
	 */
	private function read_visitor_cookie(): string {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return '';
		}

		$value = sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE_NAME ] ) );

		return preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';
	}

	/**
	 * Ensure the visitor cookie is present for subsequent requests.
	 *
	 * @param string $visitor_id Opaque visitor ID.
	 */
	private function ensure_visitor_cookie( string $visitor_id ): void {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $visitor_id ) ) {
			return;
		}

		// Keep the request-local copy readable immediately.
		$_COOKIE[ self::COOKIE_NAME ] = $visitor_id;

		if ( headers_sent() ) {
			return;
		}

		$domain = '';

		if ( defined( 'COOKIE_DOMAIN' ) && is_string( COOKIE_DOMAIN ) && '' !== COOKIE_DOMAIN ) {
			$domain = COOKIE_DOMAIN;
		}

		// Site-root path so the cookie is sent for /local-knowledge/game/... .
		$path = '/';

		setcookie(
			self::COOKIE_NAME,
			$visitor_id,
			array(
				'expires'  => time() + self::TTL,
				'path'     => $path,
				'domain'   => $domain,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}
}
