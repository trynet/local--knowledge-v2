<?php
/**
 * Reusable Game screen markup.
 *
 * Expected variables (provided by GameRenderer):
 * - int    $game_number
 * - string $image_url
 * - string $image_alt
 * - array  $locations
 * - bool   $is_preview
 * - bool   $playable
 * - int    $game_id
 * - string $nonce_action
 * - string $nonce_field
 * - string $form_action_value
 * - string $feedback
 * - string $selected_choice
 * - bool   $game_locked
 * - string $correct_location_label
 * - int    $current_view
 * - bool   $show_large_image
 * - bool   $show_comparison
 * - bool   $show_idk
 * - array  $comparison_images
 *
 * @package JoyOfCode\LocalKnowledge
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $game_number, $locations, $is_preview ) ) {
	return;
}

$playable               = ! empty( $playable );
$game_locked            = ! empty( $game_locked );
$feedback               = isset( $feedback ) ? (string) $feedback : '';
$selected_choice        = isset( $selected_choice ) ? (string) $selected_choice : '';
$correct_location_label = isset( $correct_location_label ) ? (string) $correct_location_label : '';
$game_id                = isset( $game_id ) ? (int) $game_id : 0;
$nonce_action           = isset( $nonce_action ) ? (string) $nonce_action : '';
$nonce_field            = isset( $nonce_field ) ? (string) $nonce_field : '';
$form_action_value      = isset( $form_action_value ) ? (string) $form_action_value : '';
$image_url              = isset( $image_url ) ? (string) $image_url : '';
$image_alt              = isset( $image_alt ) ? (string) $image_alt : '';
$show_large_image       = ! empty( $show_large_image );
$show_comparison        = ! empty( $show_comparison );
$show_idk               = ! empty( $show_idk );
$comparison_images      = isset( $comparison_images ) && is_array( $comparison_images ) ? $comparison_images : array();
?>
<main class="lk-game<?php echo $show_comparison ? ' lk-game--comparison' : ''; ?>">
	<?php if ( $is_preview ) : ?>
		<div class="lk-game__preview-notice" role="status">
			<?php esc_html_e( 'Preview Mode — No player progress or scores will be recorded.', 'local-knowledge' ); ?>
		</div>
	<?php endif; ?>

	<header class="lk-game__header">
		<h1 class="lk-game__title">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: game number */
					__( 'Game %d', 'local-knowledge' ),
					(int) $game_number
				)
			);
			?>
		</h1>
	</header>

	<?php if ( $show_large_image && '' !== $image_url ) : ?>
		<figure class="lk-game__image-frame">
			<img
				class="lk-game__image"
				src="<?php echo esc_url( $image_url ); ?>"
				alt="<?php echo esc_attr( $image_alt ); ?>"
			/>
		</figure>
	<?php endif; ?>

	<?php if ( $show_comparison && array() !== $comparison_images ) : ?>
		<section class="lk-game__comparison" aria-label="<?php esc_attr_e( 'Compare all game images', 'local-knowledge' ); ?>">
			<ul class="lk-game__comparison-grid">
				<?php foreach ( $comparison_images as $index => $item ) : ?>
					<?php
					$cell_url  = isset( $item['image_url'] ) ? (string) $item['image_url'] : '';
					$cell_full = isset( $item['full_url'] ) ? (string) $item['full_url'] : $cell_url;
					$cell_alt  = isset( $item['image_alt'] ) ? (string) $item['image_alt'] : '';
					$cell_n    = isset( $item['stage'] ) ? (int) $item['stage'] : ( (int) $index + 1 );

					if ( '' === $cell_url ) {
						continue;
					}

					$label = sprintf(
						/* translators: %d: image number */
						__( 'Enlarge game image %d', 'local-knowledge' ),
						$cell_n
					);
					?>
					<li class="lk-game__comparison-cell">
						<button
							type="button"
							class="lk-game__comparison-trigger"
							aria-haspopup="dialog"
							aria-controls="lk-comparison-dialog"
							data-lk-full-src="<?php echo esc_url( $cell_full ); ?>"
							data-lk-full-alt="<?php echo esc_attr( $cell_alt ); ?>"
						>
							<span class="lk-game__comparison-frame">
								<img
									class="lk-game__comparison-image"
									src="<?php echo esc_url( $cell_url ); ?>"
									alt="<?php echo esc_attr( $cell_alt ); ?>"
									draggable="false"
								/>
							</span>
							<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>

			<dialog class="lk-game__comparison-dialog" id="lk-comparison-dialog" aria-labelledby="lk-comparison-dialog-title">
				<div class="lk-game__comparison-dialog-inner">
					<h2 id="lk-comparison-dialog-title" class="lk-game__comparison-dialog-title">
						<?php esc_html_e( 'Enlarged game image', 'local-knowledge' ); ?>
					</h2>
					<button type="button" class="lk-game__comparison-dialog-close" data-lk-dialog-close>
						<?php esc_html_e( 'Close', 'local-knowledge' ); ?>
					</button>
					<figure class="lk-game__comparison-dialog-figure">
						<img class="lk-game__comparison-dialog-image" src="" alt="" />
					</figure>
				</div>
			</dialog>
		</section>
	<?php endif; ?>

	<?php if ( $playable && '' !== $feedback ) : ?>
		<div class="lk-game__feedback lk-game__feedback--<?php echo esc_attr( $feedback ); ?>" role="status" aria-live="polite">
			<?php if ( 'correct' === $feedback ) : ?>
				<p class="lk-game__feedback-message">
					<?php esc_html_e( 'Correct! You identified the location.', 'local-knowledge' ); ?>
				</p>
				<?php if ( '' !== $correct_location_label ) : ?>
					<p class="lk-game__correct-answer">
						<?php
						printf(
							/* translators: %s: correct location label */
							esc_html__( 'The correct location is: %s', 'local-knowledge' ),
							esc_html( $correct_location_label )
						);
						?>
					</p>
				<?php endif; ?>
			<?php elseif ( 'idk' === $feedback ) : ?>
				<p class="lk-game__feedback-message">
					<?php esc_html_e( 'Game complete. No correct location guess was submitted.', 'local-knowledge' ); ?>
				</p>
				<?php if ( '' !== $correct_location_label ) : ?>
					<p class="lk-game__correct-answer">
						<?php
						printf(
							/* translators: %s: correct location label */
							esc_html__( 'The correct location is: %s', 'local-knowledge' ),
							esc_html( $correct_location_label )
						);
						?>
					</p>
				<?php endif; ?>
			<?php elseif ( 'incorrect' === $feedback ) : ?>
				<p class="lk-game__feedback-message">
					<?php esc_html_e( 'That answer is incorrect. Please try again.', 'local-knowledge' ); ?>
				</p>
			<?php elseif ( 'missing' === $feedback ) : ?>
				<p class="lk-game__feedback-message">
					<?php esc_html_e( 'Please select a location before submitting.', 'local-knowledge' ); ?>
				</p>
			<?php elseif ( 'invalid_choice' === $feedback ) : ?>
				<p class="lk-game__feedback-message">
					<?php esc_html_e( 'The submitted answer is not valid. Please choose one of the listed locations.', 'local-knowledge' ); ?>
				</p>
			<?php else : ?>
				<p class="lk-game__feedback-message">
					<?php esc_html_e( 'Your submission could not be processed. Please try again.', 'local-knowledge' ); ?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $playable && ! $game_locked ) : ?>
		<form class="lk-game__form" method="post" action="">
			<input type="hidden" name="lk_game_action" value="<?php echo esc_attr( $form_action_value ); ?>" />
			<input type="hidden" name="lk_game_id" value="<?php echo esc_attr( (string) $game_id ); ?>" />
			<?php wp_nonce_field( $nonce_action, $nonce_field ); ?>

			<fieldset class="lk-game__choices">
				<legend class="lk-game__choices-legend">
					<?php esc_html_e( 'Choose a location', 'local-knowledge' ); ?>
				</legend>

				<?php foreach ( $locations as $index => $label ) : ?>
					<?php
					$input_id = 'lk-location-' . sanitize_key( (string) $index );
					?>
					<div class="lk-game__choice">
						<input
							type="radio"
							id="<?php echo esc_attr( $input_id ); ?>"
							name="lk_location"
							value="<?php echo esc_attr( (string) $index ); ?>"
							<?php checked( $selected_choice, (string) $index ); ?>
						/>
						<label for="<?php echo esc_attr( $input_id ); ?>">
							<?php echo esc_html( (string) $label ); ?>
						</label>
					</div>
				<?php endforeach; ?>

				<?php if ( $show_idk ) : ?>
					<div class="lk-game__choice lk-game__choice--idk">
						<input
							type="radio"
							id="lk-location-idk"
							name="lk_location"
							value="idk"
							<?php checked( $selected_choice, 'idk' ); ?>
						/>
						<label for="lk-location-idk">
							<?php esc_html_e( 'I Don\'t Know', 'local-knowledge' ); ?>
						</label>
					</div>
				<?php endif; ?>
			</fieldset>

			<button type="submit" class="lk-game__submit">
				<?php esc_html_e( 'Submit', 'local-knowledge' ); ?>
			</button>
		</form>
	<?php elseif ( ! $playable ) : ?>
		<form class="lk-game__form" action="#" method="post">
			<fieldset class="lk-game__choices">
				<legend class="lk-game__choices-legend">
					<?php esc_html_e( 'Choose a location', 'local-knowledge' ); ?>
				</legend>

				<?php foreach ( $locations as $index => $label ) : ?>
					<?php
					$input_id = 'lk-location-' . sanitize_key( (string) $index );
					?>
					<div class="lk-game__choice">
						<input
							type="radio"
							id="<?php echo esc_attr( $input_id ); ?>"
							name="lk_location"
							value="<?php echo esc_attr( (string) $index ); ?>"
						/>
						<label for="<?php echo esc_attr( $input_id ); ?>">
							<?php echo esc_html( (string) $label ); ?>
						</label>
					</div>
				<?php endforeach; ?>
			</fieldset>

			<button type="button" class="lk-game__submit">
				<?php esc_html_e( 'Submit', 'local-knowledge' ); ?>
			</button>
		</form>
	<?php endif; ?>
</main>
