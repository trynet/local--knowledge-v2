<?php
/**
 * Permanent per-player Game result storage (user meta).
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Player;

use JoyOfCode\LocalKnowledge\Frontend\GameState;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and reads structured Game results on the WordPress user.
 *
 * Meta key `_lk_player_results` holds an array keyed by Game Number string.
 * Designed to hold multiple Games later without schema changes.
 */
final class PlayerResultRepository {

	/**
	 * User meta key for all permanent Game results.
	 */
	public const META_KEY = '_lk_player_results';

	/**
	 * Whether the user already has a completed result for a Game Number.
	 *
	 * @param int $user_id     WordPress user ID.
	 * @param int $game_number Game Number (1–10).
	 */
	public function has_result( int $user_id, int $game_number ): bool {
		return null !== $this->get_result( $user_id, $game_number );
	}

	/**
	 * Read a single Game result, or null if missing.
	 *
	 * @param int $user_id     WordPress user ID.
	 * @param int $game_number Game Number.
	 * @return array{
	 *     game_id: int,
	 *     game_number: int,
	 *     completed_view: int,
	 *     result_type: string,
	 *     points: int,
	 *     completed_at: string,
	 *     status: string
	 * }|null
	 */
	public function get_result( int $user_id, int $game_number ): ?array {
		if ( $user_id < 1 || $game_number < 1 ) {
			return null;
		}

		$all = $this->get_all_results( $user_id );
		$key = (string) $game_number;

		if ( ! isset( $all[ $key ] ) || ! is_array( $all[ $key ] ) ) {
			return null;
		}

		return $this->normalize_result( $all[ $key ], $game_number );
	}

	/**
	 * Persist a Game result once. Refuses to overwrite an existing valid result.
	 *
	 * @param int                  $user_id WordPress user ID.
	 * @param array<string, mixed> $result  Result payload.
	 * @return true|\WP_Error
	 */
	public function save_result( int $user_id, array $result ) {
		if ( $user_id < 1 ) {
			return new \WP_Error( 'lk_invalid_user', __( 'Invalid player account.', 'local-knowledge' ) );
		}

		$normalized = $this->normalize_result( $result );

		if ( null === $normalized ) {
			return new \WP_Error( 'lk_invalid_result', __( 'The Game result could not be saved because it is invalid.', 'local-knowledge' ) );
		}

		$existing = $this->get_result( $user_id, $normalized['game_number'] );

		if ( null !== $existing ) {
			// Idempotent success: same attempt already stored.
			return true;
		}

		$all                                 = $this->get_all_results( $user_id );
		$all[ (string) $normalized['game_number'] ] = $normalized;

		$updated = update_user_meta( $user_id, self::META_KEY, $all );

		// update_user_meta returns false when value is unchanged; treat existing write as OK.
		if ( false === $updated && null === $this->get_result( $user_id, $normalized['game_number'] ) ) {
			return new \WP_Error( 'lk_result_save_failed', __( 'The Game result could not be saved. Please try again.', 'local-knowledge' ) );
		}

		return true;
	}

	/**
	 * @param int $user_id User ID.
	 * @return array<string, array<string, mixed>>
	 */
	public function get_all_results( int $user_id ): array {
		if ( $user_id < 1 ) {
			return array();
		}

		$raw = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();

		foreach ( $raw as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$normalized = $this->normalize_result( $row );

			if ( null === $normalized ) {
				continue;
			}

			$out[ (string) $normalized['game_number'] ] = $normalized;
		}

		return $out;
	}

	/**
	 * Sorted list of completed Game Numbers.
	 *
	 * @param int $user_id User ID.
	 * @return list<int>
	 */
	public function get_completed_game_numbers( int $user_id ): array {
		$numbers = array();

		foreach ( $this->get_all_results( $user_id ) as $row ) {
			$numbers[] = absint( $row['game_number'] );
		}

		$numbers = array_values( array_unique( $numbers ) );
		sort( $numbers, SORT_NUMERIC );

		return $numbers;
	}

	/**
	 * Sum of points across all permanent results.
	 *
	 * @param int $user_id User ID.
	 */
	public function get_total_points( int $user_id ): int {
		$total = 0;

		foreach ( $this->get_all_results( $user_id ) as $row ) {
			$total += absint( $row['points'] );
		}

		return $total;
	}

	/**
	 * Count of completed Games.
	 *
	 * @param int $user_id User ID.
	 */
	public function count_completed( int $user_id ): int {
		return count( $this->get_completed_game_numbers( $user_id ) );
	}

	/**
	 * Normalize and validate a result row.
	 *
	 * @param array<string, mixed> $raw         Raw result.
	 * @param int                  $expect_num  Optional expected Game Number.
	 * @return array{
	 *     game_id: int,
	 *     game_number: int,
	 *     completed_view: int,
	 *     result_type: string,
	 *     points: int,
	 *     completed_at: string,
	 *     status: string
	 * }|null
	 */
	private function normalize_result( array $raw, int $expect_num = 0 ): ?array {
		$game_number = isset( $raw['game_number'] ) ? absint( $raw['game_number'] ) : 0;
		$game_id     = isset( $raw['game_id'] ) ? absint( $raw['game_id'] ) : 0;
		$view        = isset( $raw['completed_view'] ) ? absint( $raw['completed_view'] ) : 0;
		$result_type = isset( $raw['result_type'] ) ? sanitize_key( (string) $raw['result_type'] ) : '';
		$points      = isset( $raw['points'] ) ? absint( $raw['points'] ) : 0;
		$status      = isset( $raw['status'] ) ? sanitize_key( (string) $raw['status'] ) : 'completed';
		$completed   = isset( $raw['completed_at'] ) ? sanitize_text_field( (string) $raw['completed_at'] ) : '';

		if ( $expect_num > 0 && $game_number !== $expect_num ) {
			return null;
		}

		if ( $game_number < 1 || $game_id < 1 ) {
			return null;
		}

		if ( $view < GameState::VIEW_MIN || $view > GameState::VIEW_COMPARISON ) {
			return null;
		}

		if ( ! in_array( $result_type, array( 'correct', 'idk' ), true ) ) {
			return null;
		}

		if ( $points > 4 ) {
			return null;
		}

		// Recompute points from view/result so stored points cannot be forged at write time.
		$calculator = new ScoreCalculator();
		$points     = $calculator->calculate( $view, $result_type );

		if ( 'completed' !== $status ) {
			$status = 'completed';
		}

		if ( '' === $completed ) {
			$completed = gmdate( 'Y-m-d H:i:s' );
		}

		return array(
			'game_id'        => $game_id,
			'game_number'    => $game_number,
			'completed_view' => $view,
			'result_type'    => $result_type,
			'points'         => $points,
			'completed_at'   => $completed,
			'status'         => $status,
		);
	}
}
