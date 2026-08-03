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
	 * Lowest individual view.
	 */
	public const VIEW_MIN = 1;

	/**
	 * Highest individual image view.
	 */
	public const VIEW_IMAGE_MAX = 4;

	/**
	 * Comparison (fifth) view.
	 */
	public const VIEW_COMPARISON = 5;

	/**
	 * Visitor ID resolved for the current request (request-local memo).
	 */
	private string $visitor_id = '';

	/**
	 * Load or initialize public gameplay state for a Game.
	 *
	 * @param int $game_id Game post ID.
	 * @return array{
	 *     game_id: int,
	 *     mode: string,
	 *     current_view: int,
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
	 * Build the initial View 1 unfinished state.
	 *
	 * @param int $game_id Game post ID.
	 * @return array{
	 *     game_id: int,
	 *     mode: string,
	 *     current_view: int,
	 *     ended: bool,
	 *     result_type: string
	 * }
	 */
	public function initial_state( int $game_id ): array {
		return array(
			'game_id'      => absint( $game_id ),
			'mode'         => self::MODE_PUBLIC,
			'current_view' => self::VIEW_MIN,
			'ended'        => false,
			'result_type'  => '',
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

		if ( ! isset( $raw['mode'] ) || self::MODE_PUBLIC !== sanitize_key( (string) $raw['mode'] ) ) {
			return null;
		}

		if ( ! isset( $raw['game_id'] ) || absint( $raw['game_id'] ) !== $game_id ) {
			return null;
		}

		$view = $this->read_view( $raw );

		if ( $view < self::VIEW_MIN || $view > self::VIEW_COMPARISON ) {
			return null;
		}

		$ended  = ! empty( $raw['ended'] );
		$result = isset( $raw['result_type'] ) ? sanitize_key( (string) $raw['result_type'] ) : '';

		if ( $ended && ! in_array( $result, array( 'correct', 'idk' ), true ) ) {
			return null;
		}

		return $this->normalize_state( $raw );
	}

	/**
	 * Resolve current_view, migrating legacy image_stage (1–4).
	 *
	 * @param array<string, mixed> $state Raw or normalized state.
	 */
	private function read_view( array $state ): int {
		if ( isset( $state['current_view'] ) ) {
			return absint( $state['current_view'] );
		}

		// Pre-6D-2 (revised) states stored image_stage for Views 1–4 only.
		if ( isset( $state['image_stage'] ) ) {
			return absint( $state['image_stage'] );
		}

		return self::VIEW_MIN;
	}

	/**
	 * Force a known shape for state arrays.
	 *
	 * @param array<string, mixed> $state Raw state.
	 * @return array{
	 *     game_id: int,
	 *     mode: string,
	 *     current_view: int,
	 *     ended: bool,
	 *     result_type: string
	 * }
	 */
	private function normalize_state( array $state ): array {
		$game_id = isset( $state['game_id'] ) ? absint( $state['game_id'] ) : 0;
		$ended   = ! empty( $state['ended'] );
		$result  = isset( $state['result_type'] ) ? sanitize_key( (string) $state['result_type'] ) : '';
		$view    = $this->read_view( $state );

		$view = max( self::VIEW_MIN, min( self::VIEW_COMPARISON, $view ) );

		if ( ! in_array( $result, array( 'correct', 'idk' ), true ) ) {
			$result = '';
		}

		return array(
			'game_id'      => $game_id,
			'mode'         => self::MODE_PUBLIC,
			'current_view' => $view,
			'ended'        => $ended,
			'result_type'  => $ended ? $result : '',
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
	 * @param string $visitor_id Opaque visitor ID.
	 * @param int    $game_id    Game post ID.
	 * @param string $mode       Rendering mode.
	 */
	private function transient_key( string $visitor_id, int $game_id, string $mode ): string {
		return self::TRANSIENT_PREFIX . md5( $visitor_id . '|' . $game_id . '|' . $mode );
	}

	/**
	 * Resolve the anonymous visitor identifier for this request.
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

		$_COOKIE[ self::COOKIE_NAME ] = $visitor_id;

		if ( headers_sent() ) {
			return;
		}

		$domain = '';

		if ( defined( 'COOKIE_DOMAIN' ) && is_string( COOKIE_DOMAIN ) && '' !== COOKIE_DOMAIN ) {
			$domain = COOKIE_DOMAIN;
		}

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
