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
 * - int    $image_stage
 * - bool   $show_idk
 * - bool   $show_thumbnails
 * - array  $thumbnails
 *
 * @package JoyOfCode\LocalKnowledge
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $game_number, $image_url, $image_alt, $locations, $is_preview ) ) {
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
$image_stage            = isset( $image_stage ) ? max( 1, min( 4, (int) $image_stage ) ) : 1;
$show_idk               = ! empty( $show_idk );
$show_thumbnails        = ! empty( $show_thumbnails );
$thumbnails             = isset( $thumbnails ) && is_array( $thumbnails ) ? $thumbnails : array();
?>
<main class="lk-game">
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

	<figure class="lk-game__image-frame">
		<img
			class="lk-game__image"
			src="<?php echo esc_url( (string) $image_url ); ?>"
			alt="<?php echo esc_attr( (string) $image_alt ); ?>"
		/>
	</figure>

	<?php if ( $show_thumbnails && array() !== $thumbnails ) : ?>
		<ul class="lk-game__thumbnails" aria-label="<?php esc_attr_e( 'All game images', 'local-knowledge' ); ?>">
			<?php foreach ( $thumbnails as $thumbnail ) : ?>
				<?php
				$thumb_url = isset( $thumbnail['image_url'] ) ? (string) $thumbnail['image_url'] : '';
				$thumb_alt = isset( $thumbnail['image_alt'] ) ? (string) $thumbnail['image_alt'] : '';

				if ( '' === $thumb_url ) {
					continue;
				}
				?>
				<li class="lk-game__thumbnail">
					<span class="lk-game__thumbnail-frame">
						<img
							class="lk-game__thumbnail-image"
							src="<?php echo esc_url( $thumb_url ); ?>"
							alt="<?php echo esc_attr( $thumb_alt ); ?>"
							draggable="false"
						/>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
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
					<?php esc_html_e( 'Game complete. You selected I Don\'t Know.', 'local-knowledge' ); ?>
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
