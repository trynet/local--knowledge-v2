<?php
/**
 * Resolves the public Play page URL for redirects and shortcodes.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Discovers the WordPress page that hosts [lk_current_game].
 *
 * Preference order:
 * 1. Option `lk_play_page_id` when it points to a published page.
 * 2. First published page whose content contains the [lk_current_game] shortcode.
 */
final class PlayPage {

	/**
	 * Option key for an explicit Play page ID.
	 */
	public const OPTION_PAGE_ID = 'lk_play_page_id';

	/**
	 * Transient cache for discovered page ID.
	 */
	private const CACHE_KEY = 'lk_play_page_id_cache';

	/**
	 * Absolute URL of the Play page, or empty when none is available.
	 */
	public static function get_url(): string {
		$page_id = self::get_page_id();

		if ( $page_id < 1 ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Published Play page ID, or 0.
	 */
	public static function get_page_id(): int {
		$configured = absint( get_option( self::OPTION_PAGE_ID, 0 ) );

		if ( $configured > 0 && 'publish' === get_post_status( $configured ) && self::page_has_shortcode( $configured ) ) {
			return $configured;
		}

		$cached = absint( get_transient( self::CACHE_KEY ) );

		if ( $cached > 0 && 'publish' === get_post_status( $cached ) && self::page_has_shortcode( $cached ) ) {
			return $cached;
		}

		$found = self::discover_page_id();

		if ( $found > 0 ) {
			set_transient( self::CACHE_KEY, $found, HOUR_IN_SECONDS );
		} else {
			delete_transient( self::CACHE_KEY );
		}

		return $found;
	}

	/**
	 * Clear the discovery cache (e.g. after page saves).
	 */
	public static function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * @param int $page_id Page ID.
	 */
	private static function page_has_shortcode( int $page_id ): bool {
		$post = get_post( $page_id );

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		return has_shortcode( (string) $post->post_content, 'lk_current_game' );
	}

	/**
	 * Scan published pages for the current-game shortcode.
	 */
	private static function discover_page_id(): int {
		$query = new \WP_Query(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'posts_per_page'         => 50,
				'orderby'                => 'menu_order title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			if ( has_shortcode( (string) $post->post_content, 'lk_current_game' ) ) {
				return (int) $post->ID;
			}
		}

		return 0;
	}
}
