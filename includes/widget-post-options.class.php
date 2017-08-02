<?php

class GetYourGuide_Widget_Post_Options {
	const OPTION_NAME_QUERY = 'getyourguide_query';
	const OPTION_FIELD_ID_QUERY = 'getyourguide_widget_query';

	const OPTION_NAME_SIDEBARS = 'getyourguide_sidebars';
	const OPTION_FIELD_ID_SIDEBARS = 'getyourguide_widget_sidebars';

	const METABOX_ID = 'getyourguide_widget_box';

	/**
	 * Get the query for a given post. If no query is set for this post it will return null.
	 *
	 * @param int $postId The post for which you want to get the query.
	 *
	 * @return string|null The query associated with the given post.
	 */
	static public function getQuery( $postId ) {
		$query = get_post_meta( $postId, self::OPTION_NAME_QUERY, true );
		if ( ! $query ) {
			$query = null;
		}

		return $query;
	}

	/**
	 * Set the query for a specific post. Pass null as query to remove the query from the post.
	 *
	 * @param $query
	 * @param $postId
	 */
	static public function setQuery( $postId, $query ) {
		if ( $query != null ) {
			// Update the value
			$query = trim( $query );
			update_post_meta( $postId, self::OPTION_NAME_QUERY, $query );
		} else {
			delete_post_meta( $postId, self::OPTION_NAME_QUERY );
		}
	}

	static protected function getDefaultAffectedSidebars() {
		global $wp_registered_sidebars;
		$sidebars = [];
		foreach ( $wp_registered_sidebars as $sidebar ) {
			$sidebars[] = $sidebar['id'];
		}

		return $sidebars;
	}

	/**
	 * Get all IDs of sidebars that are affected by this post's query.
	 * This method always returns an array.
	 *
	 * @param $postId
	 *
	 * @return array
	 */
	static public function getAffectedSidebars( $postId ) {
		global $wp_registered_sidebars;

		$sidebars = get_post_meta( $postId, self::OPTION_NAME_SIDEBARS, true );
		// By default all sidebars are affected.
		if ( ! $sidebars || ! is_array( $sidebars ) ) {
			$sidebars = self::getDefaultAffectedSidebars();
		}

		return $sidebars;
	}

	/**
	 * Set the array of sidebars that are affected by this post's query.
	 *
	 * @param $postId
	 * @param $sidebars
	 */
	static public function setAffectedSidebars( $postId, $sidebars ) {

		if ( ! is_array( $sidebars ) || count( $sidebars ) == 0 || ! $sidebars ) {
			// Set the default
			$sidebars = self::getDefaultAffectedSidebars();
		}

		// Save the ideas
		update_post_meta( $postId, self::OPTION_NAME_SIDEBARS, $sidebars );
	}

	function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_post' ] );
	}

	function add_meta_boxes() {
		add_meta_box(
			self::METABOX_ID,
			__( 'GetYourGuide Widget', 'getyourguide-widget' ),
			[ $this, 'display_widget_box' ],
			[ 'page', 'post', 'custom_post_type' ],
			'side',
			'default'
		);
	}

	function display_widget_box( $post ) {
		global $wp_registered_sidebars;
		wp_nonce_field( basename( __FILE__ ), self::METABOX_ID );

		// Prepare the sidebars
		$sidebars = [];
		foreach ( $wp_registered_sidebars as $sidebar ) {
			$sidebars[ $sidebar['id'] ] = $sidebar['name'];
		}
		$sidebar_field_id  = self::OPTION_FIELD_ID_SIDEBARS;
		$selected_sidebars = get_post_meta( $post->ID, self::OPTION_NAME_SIDEBARS, true );
		if ( ! is_array( $selected_sidebars ) ) {
			$selected_sidebars = [];
		}

		$query_field_id = self::OPTION_FIELD_ID_QUERY;
		$query          = self::getQuery( $post->ID );

		include dirname( __FILE__ ) . '/../views/metabox.php';
	}

	/**
	 * When the user submits a post we need to store the custom meta data (i.e. query)
	 *
	 * @param $postId
	 */
	function save_post( $postId ) {
		global $wp_registered_sidebars;

		$query = isset( $_POST[ self::OPTION_FIELD_ID_QUERY ] ) ? $_POST[ self::OPTION_FIELD_ID_QUERY ] : null;
		self::setQuery( $postId, $query );

		$sidebar_ids = isset( $_POST[ self::OPTION_FIELD_ID_SIDEBARS ] ) ? $_POST[ self::OPTION_FIELD_ID_SIDEBARS ] : null;
		self::setAffectedSidebars( $postId, $sidebar_ids );
	}
}
