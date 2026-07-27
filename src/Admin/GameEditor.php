<?php
/**
 * Game Details editor for the lk_game post type.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers game metadata, the Game Details meta box, and secure save handling.
 */
final class GameEditor {

	/**
	 * Meta box identifier.
	 */
	private const META_BOX_ID = 'lk_game_details';

	/**
	 * Nonce action.
	 */
	private const NONCE_ACTION = 'lk_save_game_details';

	/**
	 * Nonce field name.
	 */
	private const NONCE_FIELD = 'lk_game_details_nonce';

	/**
	 * Registered meta keys keyed by logical field name.
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
	 * Wire WordPress hooks for the Game editor.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes_' . GamePostType::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . GamePostType::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register all Game post meta with types and sanitization.
	 */
	public function register_meta(): void {
		$integer_keys = array(
			self::META_KEYS['game_number'],
			self::META_KEYS['image_1_id'],
			self::META_KEYS['image_2_id'],
			self::META_KEYS['image_3_id'],
			self::META_KEYS['image_4_id'],
		);

		foreach ( $integer_keys as $meta_key ) {
			register_post_meta(
				GamePostType::POST_TYPE,
				$meta_key,
				array(
					'type'              => 'integer',
					'single'            => true,
					'default'           => 0,
					'sanitize_callback' => 'absint',
					'auth_callback'     => array( $this, 'can_edit_games' ),
					'show_in_rest'      => false,
				)
			);
		}

		$string_keys = array(
			self::META_KEYS['location_1'],
			self::META_KEYS['location_2'],
			self::META_KEYS['location_3'],
			self::META_KEYS['location_4'],
			self::META_KEYS['correct_location'],
		);

		foreach ( $string_keys as $meta_key ) {
			register_post_meta(
				GamePostType::POST_TYPE,
				$meta_key,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => array( $this, 'can_edit_games' ),
					'show_in_rest'      => false,
				)
			);
		}
	}

	/**
	 * Authorization callback for registered game meta.
	 */
	public function can_edit_games(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Add the Game Details meta box.
	 */
	public function add_meta_box(): void {
		add_meta_box(
			self::META_BOX_ID,
			__( 'Game Details', 'local-knowledge' ),
			array( $this, 'render_meta_box' ),
			GamePostType::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the Game Details meta box.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$game_number      = (int) get_post_meta( $post->ID, self::META_KEYS['game_number'], true );
		$correct_location = (string) get_post_meta( $post->ID, self::META_KEYS['correct_location'], true );

		$locations = array(
			'1' => (string) get_post_meta( $post->ID, self::META_KEYS['location_1'], true ),
			'2' => (string) get_post_meta( $post->ID, self::META_KEYS['location_2'], true ),
			'3' => (string) get_post_meta( $post->ID, self::META_KEYS['location_3'], true ),
			'4' => (string) get_post_meta( $post->ID, self::META_KEYS['location_4'], true ),
		);

		$image_ids = array(
			1 => (int) get_post_meta( $post->ID, self::META_KEYS['image_1_id'], true ),
			2 => (int) get_post_meta( $post->ID, self::META_KEYS['image_2_id'], true ),
			3 => (int) get_post_meta( $post->ID, self::META_KEYS['image_3_id'], true ),
			4 => (int) get_post_meta( $post->ID, self::META_KEYS['image_4_id'], true ),
		);
		?>
		<div class="lk-game-details">
			<p>
				<label for="lk_game_number">
					<strong><?php esc_html_e( 'Game Number', 'local-knowledge' ); ?></strong>
				</label><br />
				<input
					type="number"
					id="lk_game_number"
					name="lk_game_number"
					value="<?php echo esc_attr( (string) ( $game_number > 0 ? $game_number : '' ) ); ?>"
					min="1"
					max="10"
					step="1"
					class="small-text"
				/>
			</p>

			<fieldset class="lk-game-details__images">
				<legend><strong><?php esc_html_e( 'Images', 'local-knowledge' ); ?></strong></legend>
				<?php
				foreach ( $image_ids as $index => $attachment_id ) {
					$this->render_image_control( $index, $attachment_id );
				}
				?>
			</fieldset>

			<fieldset class="lk-game-details__locations">
				<legend><strong><?php esc_html_e( 'Location Choices', 'local-knowledge' ); ?></strong></legend>
				<?php foreach ( $locations as $index => $location ) : ?>
					<p>
						<label for="lk_location_<?php echo esc_attr( $index ); ?>">
							<?php
							printf(
								/* translators: %s: location choice number */
								esc_html__( 'Location Choice %s', 'local-knowledge' ),
								esc_html( $index )
							);
							?>
						</label><br />
						<input
							type="text"
							id="lk_location_<?php echo esc_attr( $index ); ?>"
							name="lk_location_<?php echo esc_attr( $index ); ?>"
							value="<?php echo esc_attr( $location ); ?>"
							class="widefat lk-location-input"
							data-location-index="<?php echo esc_attr( $index ); ?>"
						/>
					</p>
				<?php endforeach; ?>
			</fieldset>

			<p>
				<label for="lk_correct_location">
					<strong><?php esc_html_e( 'Correct Location', 'local-knowledge' ); ?></strong>
				</label><br />
				<select id="lk_correct_location" name="lk_correct_location">
					<option value=""><?php esc_html_e( 'Select correct location…', 'local-knowledge' ); ?></option>
					<?php foreach ( $locations as $index => $location ) : ?>
						<option value="<?php echo esc_attr( $index ); ?>" <?php selected( $correct_location, $index ); ?>>
							<?php
							$label = '' !== $location
								? $location
								: sprintf(
									/* translators: %s: location choice number */
									__( 'Location Choice %s', 'local-knowledge' ),
									$index
								);
							echo esc_html( $label );
							?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
		</div>
		<?php
	}

	/**
	 * Render a single Media Library image selector control.
	 *
	 * @param int $index         Image slot number (1–4).
	 * @param int $attachment_id Selected attachment ID.
	 */
	private function render_image_control( int $index, int $attachment_id ): void {
		$field_name = 'lk_image_' . $index . '_id';
		$preview    = '';

		if ( $attachment_id > 0 ) {
			$preview = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => 'lk-image-preview__img' ) );
		}

		$has_image = $attachment_id > 0 && '' !== $preview;
		?>
		<div class="lk-image-control" data-image-index="<?php echo esc_attr( (string) $index ); ?>">
			<p class="lk-image-control__label">
				<?php
				printf(
					/* translators: %d: image number */
					esc_html__( 'Image %d', 'local-knowledge' ),
					$index
				);
				?>
			</p>
			<div class="lk-image-preview<?php echo $has_image ? ' is-set' : ''; ?>">
				<?php
				if ( $has_image ) {
					echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() is escaped.
				}
				?>
			</div>
			<input
				type="hidden"
				class="lk-image-id"
				id="<?php echo esc_attr( $field_name ); ?>"
				name="<?php echo esc_attr( $field_name ); ?>"
				value="<?php echo esc_attr( (string) ( $attachment_id > 0 ? $attachment_id : '' ) ); ?>"
			/>
			<p class="lk-image-control__actions">
				<button type="button" class="button lk-select-image">
					<?php echo $has_image ? esc_html__( 'Replace Image', 'local-knowledge' ) : esc_html__( 'Select Image', 'local-knowledge' ); ?>
				</button>
				<button type="button" class="button lk-remove-image"<?php disabled( ! $has_image ); ?>>
					<?php esc_html_e( 'Remove Image', 'local-knowledge' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	/**
	 * Persist Game Details metadata securely.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
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

		$game_number = isset( $_POST['lk_game_number'] )
			? absint( wp_unslash( $_POST['lk_game_number'] ) )
			: 0;
		update_post_meta( $post_id, self::META_KEYS['game_number'], $game_number );

		for ( $i = 1; $i <= 4; $i++ ) {
			$field = 'lk_image_' . $i . '_id';
			$value = isset( $_POST[ $field ] ) ? absint( wp_unslash( $_POST[ $field ] ) ) : 0;
			update_post_meta( $post_id, self::META_KEYS[ 'image_' . $i . '_id' ], $value );
		}

		for ( $i = 1; $i <= 4; $i++ ) {
			$field = 'lk_location_' . $i;
			$value = isset( $_POST[ $field ] )
				? sanitize_text_field( wp_unslash( (string) $_POST[ $field ] ) )
				: '';
			update_post_meta( $post_id, self::META_KEYS[ 'location_' . $i ], $value );
		}

		$correct = isset( $_POST['lk_correct_location'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['lk_correct_location'] ) )
			: '';

		if ( ! in_array( $correct, array( '1', '2', '3', '4' ), true ) ) {
			$correct = '';
		}

		update_post_meta( $post_id, self::META_KEYS['correct_location'], $correct );
	}

	/**
	 * Enqueue Media Library and Game editor assets on lk_game screens only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || GamePostType::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'lk-admin-game-editor',
			LK_PLUGIN_URL . 'assets/css/admin-game-editor.css',
			array(),
			LK_VERSION
		);

		wp_enqueue_script(
			'lk-admin-game-editor',
			LK_PLUGIN_URL . 'assets/js/admin-game-editor.js',
			array( 'jquery' ),
			LK_VERSION,
			true
		);

		wp_localize_script(
			'lk-admin-game-editor',
			'lkGameEditor',
			array(
				'title'         => __( 'Select Image', 'local-knowledge' ),
				'button'        => __( 'Use this image', 'local-knowledge' ),
				'selectLabel'   => __( 'Select Image', 'local-knowledge' ),
				'replaceLabel'  => __( 'Replace Image', 'local-knowledge' ),
			)
		);
	}
}
