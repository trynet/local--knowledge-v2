<?php
/**
 * Validates lk_game data before allowing published status.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces publication requirements for Games.
 */
final class GameValidator {

	/**
	 * Transient key prefix for per-user validation errors.
	 */
	private const TRANSIENT_PREFIX = 'lk_game_validation_errors_';

	/**
	 * Query flag indicating validation failed on the last save.
	 */
	private const QUERY_FLAG = 'lk_game_invalid';

	/**
	 * Meta keys used for validation.
	 *
	 * @var array<string, string>
	 */
	private const META_KEYS = array(
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
	 * Whether a draft reversion is currently in progress.
	 */
	private static bool $is_reverting = false;

	/**
	 * Wire WordPress hooks.
	 */
	public function register(): void {
		add_action( 'save_post_' . GamePostType::POST_TYPE, array( $this, 'validate_on_save' ), 20, 2 );
		add_filter( 'redirect_post_location', array( $this, 'filter_redirect' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	/**
	 * Validate published Games after metadata has been saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function validate_on_save( int $post_id, \WP_Post $post ): void {
		if ( self::$is_reverting ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( GamePostType::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$errors = $this->get_validation_errors( $post_id );

		if ( array() === $errors ) {
			return;
		}

		$this->store_errors( $errors );
		$this->revert_to_draft( $post_id );
	}

	/**
	 * Collect human-readable validation errors for a Game.
	 *
	 * @param int $post_id Post ID.
	 * @return list<string>
	 */
	public function get_validation_errors( int $post_id ): array {
		$errors = array();

		$game_number = absint( get_post_meta( $post_id, self::META_KEYS['game_number'], true ) );

		if ( $game_number < 1 ) {
			$errors[] = __( 'Game Number is required and must be a positive integer.', 'local-knowledge' );
		} elseif ( $this->game_number_exists( $game_number, $post_id ) ) {
			$errors[] = sprintf(
				/* translators: %d: game number */
				__( 'Game Number %d is already assigned to another Game.', 'local-knowledge' ),
				$game_number
			);
		}

		for ( $i = 1; $i <= 4; $i++ ) {
			$image_id = absint( get_post_meta( $post_id, self::META_KEYS[ 'image_' . $i . '_id' ], true ) );
			$image_error = $this->validate_image( $image_id, $i );

			if ( null !== $image_error ) {
				$errors[] = $image_error;
			}
		}

		$locations = array();

		for ( $i = 1; $i <= 4; $i++ ) {
			$location = sanitize_text_field(
				(string) get_post_meta( $post_id, self::META_KEYS[ 'location_' . $i ], true )
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
			(string) get_post_meta( $post_id, self::META_KEYS['correct_location'], true )
		);

		if ( ! in_array( $correct, array( '1', '2', '3', '4' ), true ) ) {
			$errors[] = __( 'Correct Location is required and must be one of the four location choices.', 'local-knowledge' );
		} elseif ( '' === ( $locations[ $correct ] ?? '' ) ) {
			$errors[] = sprintf(
				/* translators: %s: correct location choice number */
				__( 'Correct Location refers to Location Choice %s, which is empty.', 'local-knowledge' ),
				$correct
			);
		}

		return $errors;
	}

	/**
	 * Validate a single image attachment ID.
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

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return sprintf(
				/* translators: %d: image number */
				__( 'Image %d must reference a valid Media Library attachment.', 'local-knowledge' ),
				$index
			);
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return sprintf(
				/* translators: %d: image number */
				__( 'Image %d must be an image attachment.', 'local-knowledge' ),
				$index
			);
		}

		return null;
	}

	/**
	 * Whether another Game already uses the given game number.
	 *
	 * @param int $game_number Game number to check.
	 * @param int $post_id     Current post ID to exclude.
	 */
	private function game_number_exists( int $game_number, int $post_id ): bool {
		$query = new \WP_Query(
			array(
				'post_type'              => GamePostType::POST_TYPE,
				'post_status'            => array( 'draft', 'publish', 'pending', 'private' ),
				'posts_per_page'         => 1,
				'post__not_in'           => array( $post_id ),
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => self::META_KEYS['game_number'],
						'value'   => $game_number,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		return $query->have_posts();
	}

	/**
	 * Revert a Game to Draft without re-entering validation.
	 *
	 * @param int $post_id Post ID.
	 */
	private function revert_to_draft( int $post_id ): void {
		self::$is_reverting = true;

		remove_action( 'save_post_' . GamePostType::POST_TYPE, array( $this, 'validate_on_save' ), 20 );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		add_action( 'save_post_' . GamePostType::POST_TYPE, array( $this, 'validate_on_save' ), 20, 2 );

		self::$is_reverting = false;
	}

	/**
	 * Store validation errors for the current user through the save redirect.
	 *
	 * @param list<string> $errors Validation messages.
	 */
	private function store_errors( array $errors ): void {
		set_transient(
			$this->transient_key(),
			$errors,
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Build the per-user transient key.
	 */
	private function transient_key(): string {
		return self::TRANSIENT_PREFIX . get_current_user_id();
	}

	/**
	 * Adjust the post-save redirect when validation failed.
	 *
	 * @param string $location Redirect URL.
	 * @param int    $post_id  Post ID.
	 */
	public function filter_redirect( string $location, int $post_id ): string {
		$post = get_post( $post_id );

		if ( ! $post || GamePostType::POST_TYPE !== $post->post_type ) {
			return $location;
		}

		$errors = get_transient( $this->transient_key() );

		if ( ! is_array( $errors ) || array() === $errors ) {
			return $location;
		}

		$location = remove_query_arg( 'message', $location );

		return add_query_arg( self::QUERY_FLAG, '1', $location );
	}

	/**
	 * Display validation error notices in wp-admin.
	 */
	public function render_admin_notices(): void {
		$screen = get_current_screen();

		if ( ! $screen || GamePostType::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$errors = get_transient( $this->transient_key() );

		if ( ! is_array( $errors ) || array() === $errors ) {
			return;
		}

		delete_transient( $this->transient_key() );
		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<strong><?php esc_html_e( 'This Game could not be published. It has been saved as a Draft.', 'local-knowledge' ); ?></strong>
			</p>
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<?php if ( is_string( $error ) && '' !== $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}
