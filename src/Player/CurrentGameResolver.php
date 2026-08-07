<?php
/**
 * Determines the eligible current Game for a visitor.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Player;

use JoyOfCode\LocalKnowledge\Admin\GamePostType;
use JoyOfCode\LocalKnowledge\Frontend\GameDisplayData;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves Games by Game Number and permanent completion data.
 */
final class CurrentGameResolver {

	/**
	 * Permanent results.
	 */
	private PlayerResultRepository $results;

	/**
	 * Constructor.
	 */
	public function __construct( ?PlayerResultRepository $results = null ) {
		$this->results = $results ?? new PlayerResultRepository();
	}

	/**
	 * Resolve the Game the visitor should play.
	 *
	 * @param int $user_id 0 for guests.
	 * @return array{
	 *     status: string,
	 *     game_id?: int,
	 *     game_number?: int,
	 *     previous_points?: int,
	 *     message?: string
	 * }
	 */
	public function resolve( int $user_id = 0 ): array {
		if ( $user_id < 1 ) {
			return $this->resolve_guest();
		}

		return $this->resolve_player( $user_id );
	}

	/**
	 * Guest always starts at published Game Number 1.
	 *
	 * @return array<string, mixed>
	 */
	private function resolve_guest(): array {
		$game = $this->find_published_complete_game( 1 );

		if ( null === $game ) {
			return array(
				'status'  => 'unavailable',
				'message' => __( 'Game 1 is not available yet. Please check back later.', 'local-knowledge' ),
			);
		}

		return array(
			'status'      => 'play',
			'game_id'     => $game['game_id'],
			'game_number' => 1,
		);
	}

	/**
	 * Lowest-numbered published incomplete Game for a logged-in player.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	private function resolve_player( int $user_id ): array {
		$completed = $this->results->get_completed_game_numbers( $user_id );
		$next      = 1;

		if ( array() !== $completed ) {
			$next = max( $completed ) + 1;

			// Fill gaps: lowest missing number among 1..max+1.
			for ( $n = 1; $n <= max( $completed ) + 1; $n++ ) {
				if ( ! in_array( $n, $completed, true ) ) {
					$next = $n;
					break;
				}
			}
		}

		$game = $this->find_published_complete_game( $next );

		if ( null !== $game ) {
			$out = array(
				'status'      => 'play',
				'game_id'     => $game['game_id'],
				'game_number' => $next,
			);

			if ( $next > 1 ) {
				$prev = $this->results->get_result( $user_id, $next - 1 );

				if ( null !== $prev ) {
					$out['previous_points'] = absint( $prev['points'] );
					$out['previous_number'] = $next - 1;
				}
			}

			return $out;
		}

		// Next Game Number is due but not published.
		if ( in_array( 1, $completed, true ) && 2 === $next ) {
			$g1 = $this->results->get_result( $user_id, 1 );

			return array(
				'status'          => 'awaiting_next',
				'game_number'     => $next,
				'previous_points' => null !== $g1 ? absint( $g1['points'] ) : 0,
				'message'         => __( 'Game 2 is not available yet. Please check back later.', 'local-knowledge' ),
			);
		}

		if ( array() !== $completed ) {
			return array(
				'status'  => 'unavailable',
				'message' => __( 'No further Games are available to play right now. Please check back later.', 'local-knowledge' ),
			);
		}

		return array(
			'status'  => 'unavailable',
			'message' => __( 'No Games are available to play right now. Please check back later.', 'local-knowledge' ),
		);
	}

	/**
	 * Find a published, complete lk_game by Game Number.
	 *
	 * @param int $game_number Game Number.
	 * @return array{game_id: int}|null
	 */
	public function find_published_complete_game( int $game_number ): ?array {
		if ( $game_number < 1 ) {
			return null;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => GamePostType::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => GameDisplayData::META_KEYS['game_number'],
						'value'   => $game_number,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return null;
		}

		$game_id = (int) $query->posts[0];
		$display = new GameDisplayData();

		if ( array() !== $display->get_completeness_errors( $game_id ) ) {
			return null;
		}

		return array( 'game_id' => $game_id );
	}
}
