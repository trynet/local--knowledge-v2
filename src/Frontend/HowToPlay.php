<?php
/**
 * Shared How To Play instructions and video modal.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Instruction copy, Watch How To Play button, and portrait video dialog.
 */
final class HowToPlay {

	/**
	 * Style handle.
	 */
	public const STYLE_HANDLE = 'lk-how-to-play';

	/**
	 * Script handle.
	 */
	public const SCRIPT_HANDLE = 'lk-how-to-play';

	/**
	 * Absolute How To Play video URL (staging and live).
	 */
	private const VIDEO_URL = 'https://budk33.sg-host.com/wp-content/uploads/2026/09/InstructionVideo.mp4';

	/**
	 * Fixed How To Play video URL.
	 */
	public static function video_url(): string {
		return self::VIDEO_URL;
	}

	/**
	 * Enqueue How To Play CSS and modal script.
	 */
	public static function enqueue(): void {
		wp_enqueue_style(
			self::STYLE_HANDLE,
			LK_PLUGIN_URL . 'assets/css/how-to-play.css',
			array(),
			LK_VERSION
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			LK_PLUGIN_URL . 'assets/js/how-to-play.js',
			array(),
			LK_VERSION,
			true
		);
	}

	/**
	 * Instruction paragraphs, Watch button, and video dialog markup.
	 *
	 * Shortcodes print styles/scripts with the fragment (same pattern as GameRenderer).
	 */
	public static function instructions_html(): string {
		self::enqueue();

		$video_url = self::video_url();

		ob_start();
		wp_print_styles( self::STYLE_HANDLE );
		?>
		<div class="lk-how-to-play-copy">
			<p><?php esc_html_e( 'You’ll see a photo of a location and four possible answers. Choose the location you think is correct and click Submit.', 'local-knowledge' ); ?></p>
			<p><?php esc_html_e( 'A correct answer on the first photo earns 4 points. If you’re wrong, another photo is revealed and the possible score drops by one point with each additional photo.', 'local-knowledge' ); ?></p>
			<p><?php esc_html_e( 'After all four photos have been revealed, you can continue guessing or choose I Don’t Know. An incorrect final answer or I Don’t Know earns 0 points.', 'local-knowledge' ); ?></p>
			<p><?php esc_html_e( 'There are 10 games. Your scores from all 10 games are added together for your final score.', 'local-knowledge' ); ?></p>
		</div>
		<p class="lk-how-to-play-actions">
			<button
				type="button"
				class="lk-how-to-play-watch"
				aria-haspopup="dialog"
				aria-controls="lk-how-to-play-dialog"
			>
				<?php esc_html_e( 'Watch How To Play', 'local-knowledge' ); ?>
			</button>
		</p>
		<dialog
			class="lk-how-to-play-dialog"
			id="lk-how-to-play-dialog"
			aria-labelledby="lk-how-to-play-dialog-title"
		>
			<div class="lk-how-to-play-dialog-panel">
				<h2 id="lk-how-to-play-dialog-title" class="lk-how-to-play-dialog-title">
					<?php esc_html_e( 'Watch How To Play', 'local-knowledge' ); ?>
				</h2>
				<button
					type="button"
					class="lk-how-to-play-dialog-close"
					data-lk-how-to-play-close
					aria-label="<?php esc_attr_e( 'Close', 'local-knowledge' ); ?>"
				>
					<span aria-hidden="true">&times;</span>
				</button>
				<?php if ( '' !== $video_url ) : ?>
					<video
						class="lk-how-to-play-video"
						controls
						playsinline
						preload="metadata"
						src="<?php echo esc_url( $video_url ); ?>"
					>
						<?php esc_html_e( 'Your browser does not support the video tag.', 'local-knowledge' ); ?>
					</video>
				<?php else : ?>
					<p class="lk-how-to-play-video-missing">
						<?php esc_html_e( 'The instruction video is not available right now.', 'local-knowledge' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</dialog>
		<?php
		wp_print_scripts( self::SCRIPT_HANDLE );
		$html = ob_get_clean();

		return is_string( $html ) ? $html : '';
	}
}
