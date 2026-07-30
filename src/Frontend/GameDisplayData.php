<?php
/**
 * Shared Game display data and completeness checks.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Builds renderer view data and validates required Game fields.
 *
 * Correct Location is validated for completeness only and is never included
 * in the returned view array.
 */
final class GameDisplayData {

	/**
	 * Meta keys for completeness and display.
	 *
	 * @var array<string, string>
	 */
	public const META_KEYS = array(
		'game_number'      => '_lk_game_number',
		'image_1_id'       => '_lk_image_1_id',
		'image_2_id'       => '_lk_image_2_id',
		'image_3_id'       => '_lk_image_3_id',
		'image_4_id'       => '_lk_image_4_id',
		'location_1'       => '_lk_location_1',
		'location_2'       => '_lk_location_2',
		'location_3'       => '_lk_location_3',
		'location_4'       => '_lk_location_4',
		'correct_location' => '_lk_correct_location',
	);

	/**
	 * Collect every missing or invalid required Game field.
	 *
	 * @param int $game_id Game post ID.
	 * @return list<string>
	 */
	public function get_completeness_errors( int $game_id ): array {
		$errors = array();

		$game_number = absint( get_post_meta( $game_id, self::META_KEYS['game_number'], true ) );

		if ( $game_number < 1 ) {
			$errors[] = __( 'Game Number is required.', 'local-knowledge' );
		}

		for ( $i = 1; $i <= 4; $i++ ) {
			$image_id    = absint( get_post_meta( $game_id, self::META_KEYS[ 'image_' . $i . '_id' ], true ) );
			$image_error = $this->validate_image( $image_id, $i );

			if ( null !== $image_error ) {
				$errors[] = $image_error;
			}
		}

		$locations = array();

		for ( $i = 1; $i <= 4; $i++ ) {
			$location = sanitize_text_field(
				(string) get_post_meta( $game_id, self::META_KEYS[ 'location_' . $i ], true )
			);
			$locations[ (string) $i ] = $location;

			if ( '' === $location ) {
				$errors[] = sprintf(
					/* translators: %d: location choice number */
					__( 'Location Choice %d is required.', 'local-knowledge' ),
					$i
				);
			}
		}

		$correct = sanitize_text_field(
			(string) get_post_meta( $game_id, self::META_KEYS['correct_location'], true )
		);

		if ( ! in_array( $correct, array( '1', '2', '3', '4' ), true ) ) {
			$errors[] = __( 'Correct Location is required.', 'local-knowledge' );
		} elseif ( '' === ( $locations[ $correct ] ?? '' ) ) {
			$errors[] = __( 'Correct Location must refer to a completed location choice.', 'local-knowledge' );
		}

		return $errors;
	}

	/**
	 * Build renderer view data without correct-answer metadata.
	 *
	 * @param int  $game_id     Game post ID.
	 * @param bool $is_preview  Whether Preview Mode should be shown.
	 * @param int  $image_stage Image stage to display (1–4). Preview always uses 1.
	 * @return array<string, mixed>
	 */
	public function build_view( int $game_id, bool $is_preview, int $image_stage = 1 ): array {
		$game_number = absint( get_post_meta( $game_id, self::META_KEYS['game_number'], true ) );

		if ( $is_preview ) {
			$image_stage = 1;
		}

		$image_stage = max( 1, min( 4, $image_stage ) );
		$image_id    = $this->get_image_id_for_slot( $game_id, $image_stage );

		$image_url = '';
		$image_alt = '';

		if ( $image_id > 0 && wp_attachment_is_image( $image_id ) ) {
			$src = wp_get_attachment_image_src( $image_id, 'full' );

			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$image_url = (string) $src[0];
			}

			$image_alt = (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true );

			if ( '' === $image_alt ) {
				$attachment = get_post( $image_id );
				$image_alt  = $attachment instanceof \WP_Post ? $attachment->post_title : '';
			}
		}

		$locations = array();

		for ( $i = 1; $i <= 4; $i++ ) {
			$locations[ (string) $i ] = sanitize_text_field(
				(string) get_post_meta( $game_id, self::META_KEYS[ 'location_' . $i ], true )
			);
		}

		return array(
			'game_number' => $game_number,
			'image_id'    => $image_id,
			'image_url'   => $image_url,
			'image_alt'   => sanitize_text_field( $image_alt ),
			'locations'   => $locations,
			'is_preview'  => $is_preview,
			'image_stage' => $image_stage,
		);
	}

	/**
	 * Build static thumbnail data for Images 1–4 (Image 4 end-game layout).
	 *
	 * @param int $game_id Game post ID.
	 * @return list<array{stage: int, image_url: string, image_alt: string}>
	 */
	public function get_thumbnails( int $game_id ): array {
		$thumbnails = array();

		for ( $stage = 1; $stage <= 4; $stage++ ) {
			$image_id  = $this->get_image_id_for_slot( $game_id, $stage );
			$image_url = '';
			$image_alt = '';

			if ( $image_id > 0 && wp_attachment_is_image( $image_id ) ) {
				$src = wp_get_attachment_image_src( $image_id, 'medium' );

				if ( ! is_array( $src ) || empty( $src[0] ) ) {
					$src = wp_get_attachment_image_src( $image_id, 'full' );
				}

				if ( is_array( $src ) && ! empty( $src[0] ) ) {
					$image_url = (string) $src[0];
				}

				$image_alt = (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true );

				if ( '' === $image_alt ) {
					$attachment = get_post( $image_id );
					$image_alt  = $attachment instanceof \WP_Post ? $attachment->post_title : '';
				}

				if ( '' === $image_alt ) {
					$image_alt = sprintf(
						/* translators: %d: image stage number */
						__( 'Game image %d', 'local-knowledge' ),
						$stage
					);
				}
			}

			$thumbnails[] = array(
				'stage'     => $stage,
				'image_url' => $image_url,
				'image_alt' => sanitize_text_field( $image_alt ),
			);
		}

		return $thumbnails;
	}

	/**
	 * Map an image stage (1–4) to its post meta key.
	 *
	 * @param int $stage Image stage.
	 */
	public function get_image_meta_key_for_stage( int $stage ): string {
		$map = array(
			1 => self::META_KEYS['image_1_id'],
			2 => self::META_KEYS['image_2_id'],
			3 => self::META_KEYS['image_3_id'],
			4 => self::META_KEYS['image_4_id'],
		);

		return $map[ $stage ] ?? '';
	}

	/**
	 * Get the attachment ID for an image stage/slot (1–4).
	 *
	 * Stage 1 → `_lk_image_1_id`, stage 2 → `_lk_image_2_id`, etc.
	 *
	 * @param int $game_id Game post ID.
	 * @param int $slot    Image stage number.
	 */
	public function get_image_id_for_slot( int $game_id, int $slot ): int {
		$meta_key = $this->get_image_meta_key_for_stage( $slot );

		if ( '' === $meta_key ) {
			return 0;
		}

		return absint( get_post_meta( $game_id, $meta_key, true ) );
	}

	/**
	 * Get the correct location key for server-side answer checking only.
	 *
	 * @param int $game_id Game post ID.
	 */
	public function get_correct_location_key( int $game_id ): string {
		$correct = sanitize_text_field(
			(string) get_post_meta( $game_id, self::META_KEYS['correct_location'], true )
		);

		return in_array( $correct, array( '1', '2', '3', '4' ), true ) ? $correct : '';
	}

	/**
	 * Get a location choice label by key.
	 *
	 * @param int    $game_id Game post ID.
	 * @param string $key     Choice key 1–4.
	 */
	public function get_location_label( int $game_id, string $key ): string {
		if ( ! in_array( $key, array( '1', '2', '3', '4' ), true ) ) {
			return '';
		}

		return sanitize_text_field(
			(string) get_post_meta( $game_id, self::META_KEYS[ 'location_' . $key ], true )
		);
	}

	/**
	 * Validate one image attachment field.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $index         Image slot (1–4).
	 */
	private function validate_image( int $attachment_id, int $index ): ?string {
		if ( $attachment_id < 1 ) {
			return sprintf(
				/* translators: %d: image number */
				__( 'Image %d is required.', 'local-knowledge' ),
				$index
			);
		}

		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type || ! wp_attachment_is_image( $attachment_id ) ) {
			return sprintf(
				/* translators: %d: image number */
				__( 'Image %d is unavailable or invalid.', 'local-knowledge' ),
				$index
			);
		}

		return null;
	}
}
