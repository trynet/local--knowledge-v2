<?php
/**
 * Registers the Local Knowledge Game custom post type.
 *
 * @package JoyOfCode\LocalKnowledge
 */

declare(strict_types=1);

namespace JoyOfCode\LocalKnowledge\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Game custom post type registration.
 */
final class GamePostType {

	/**
	 * Post type key.
	 */
	public const POST_TYPE = 'lk_game';

	/**
	 * Register the lk_game post type with WordPress.
	 */
	public function register(): void {
		register_post_type( self::POST_TYPE, $this->get_args() );
	}

	/**
	 * Build post type labels.
	 *
	 * @return array<string, string>
	 */
	private function get_labels(): array {
		return array(
			'name'               => __( 'Games', 'local-knowledge' ),
			'singular_name'      => __( 'Game', 'local-knowledge' ),
			'add_new'            => __( 'Add New Game', 'local-knowledge' ),
			'add_new_item'       => __( 'Add New Game', 'local-knowledge' ),
			'edit_item'          => __( 'Edit Game', 'local-knowledge' ),
			'new_item'           => __( 'New Game', 'local-knowledge' ),
			'view_item'          => __( 'View Game', 'local-knowledge' ),
			'search_items'       => __( 'Search Games', 'local-knowledge' ),
			'not_found'          => __( 'No games found.', 'local-knowledge' ),
			'not_found_in_trash' => __( 'No games found in Trash.', 'local-knowledge' ),
			'menu_name'          => __( 'Local Knowledge', 'local-knowledge' ),
			'all_items'          => __( 'Games', 'local-knowledge' ),
		);
	}

	/**
	 * Build register_post_type arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function get_args(): array {
		return array(
			'labels'              => $this->get_labels(),
			'description'         => __( 'Local Knowledge contest games.', 'local-knowledge' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'menu_icon'           => 'dashicons-location-alt',
			'supports'            => array( 'title' ),
		);
	}
}
