<?php

/**
 * The GetYourGuide Wordpress widget that enables partners to easily display the widget on their Wordpress site.
 */
class GetYourGuide_Widget extends WP_Widget {
	/**
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * widget file.
	 *
	 * @since    1.0.0*
	 * @var      string
	 */
	const WIDGET_SLUG = 'getyourguide-widget';

	/**
	 * @inheritdoc
	 */
	public function __construct() {
		// Load plugin text domain
		add_action( 'init', [ $this, 'widget_textdomain' ] );

		parent::__construct(
			self::WIDGET_SLUG,
			__( 'GetYourGuide Widget', 'getyourguide-widget' ),
			[
				'classname'   => self::WIDGET_SLUG . '-class',
				'description' => __( 'Displays GetYourGuide tours and activities.', 'getyourguide-widget' ),
			]
		);

		// Register site styles and scripts
		add_action( 'wp_enqueue_scripts', [ $this, 'register_widget_styles' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_widget_scripts' ] );

		// Refreshing the widget's cached output with each new post
		add_action( 'save_post', [ $this, 'flush_widget_cache' ] );
		add_action( 'deleted_post', [ $this, 'flush_widget_cache' ] );
		add_action( 'switch_theme', [ $this, 'flush_widget_cache' ] );
	}

	/*--------------------------------------------------*/
	/* Widget API Functions
	/*--------------------------------------------------*/

	/**
	 * @inheritdoc
	 */
	public function widget( $args, $instance ) {
		// Check if there is a cached output
		$cache = wp_cache_get( self::WIDGET_SLUG, 'widget' );

		if ( ! is_array( $cache ) ) {
			$cache = [];
		}

		if ( ! isset ( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		if ( isset ( $cache[ $args['widget_id'] ] ) ) {
			return print $cache[ $args['widget_id'] ];
		}

		$widget_parameters = $this->getWidgetParameters( $args['id'], $instance );


		extract( $args, EXTR_SKIP );

		$widget_string = isset( $before_widget ) ? $before_widget : '';

		// The content is loaded dynamically into this HTML element
		$widget_string .= '<div id="' . $args['widget_id'] . '"></div>';
		$widget_string .= '<script type="text/javascript">GYG.Widget(document.getElementById("' . $args['widget_id'] . '"), ' . json_encode( $widget_parameters ) . ', {"loadCss": false});</script>';

		$widget_string .= isset( $after_widget ) ? $after_widget : '';

		$cache[ $args['widget_id'] ] = $widget_string;

		wp_cache_set( self::WIDGET_SLUG, $cache, 'widget' );

		return print $widget_string;
	}

	public function flush_widget_cache() {
		wp_cache_delete( self::WIDGET_SLUG, 'widget' );
	}

	/**
	 * @inheritdoc
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$default_values = GetYourGuide_Widget_Options::get_default_options();
		$new_instance   = wp_parse_args( $new_instance, $default_values );

		// Make sure we got valid values!
		$number_regex    = '/\d+/';
		$number_of_items = $new_instance[ GetYourGuide_Widget_Options::OPTION_NUMBER_OF_ITEMS ];
		if ( preg_match( $number_regex, $number_of_items ) ) {
			$instance[ GetYourGuide_Widget_Options::OPTION_NUMBER_OF_ITEMS ] = (int) $number_of_items;
		}

        $campaign_param = $new_instance[ GetYourGuide_Widget_Options::OPTION_CAMPAIGN_PARAM ];
        if ( $campaign_param == '' || preg_match( '![0-9 A-Za-zäöüß]+!i', $campaign_param ) ) {
            $instance[ GetYourGuide_Widget_Options::OPTION_CAMPAIGN_PARAM ] = (string) $campaign_param;
        }

		$query = sanitize_text_field( $new_instance[ GetYourGuide_Widget_Options::OPTION_QUERY ] );
		if ( $query != '' ) {
			$instance[ GetYourGuide_Widget_Options::OPTION_QUERY ] = $query;
		}

		return $instance;
	}

	/**
	 * @inheritdoc
	 */
	public function form( $instance ) {

		$instance = wp_parse_args(
			(array) $instance,
			GetYourGuide_Widget_Options::get_default_options()
		);

		$number_of_items = $instance[ GetYourGuide_Widget_Options::OPTION_NUMBER_OF_ITEMS ];
		$query           = $instance[ GetYourGuide_Widget_Options::OPTION_QUERY ];
        $campaign_param  = $instance[ GetYourGuide_Widget_Options::OPTION_CAMPAIGN_PARAM ];

		// Display the admin form
		include( dirname( __FILE__ ) . '/../views/admin.php' );
	}

	/**
	 * Loads the Widget's text domain for localization and translation.
	 */
	public function widget_textdomain() {
		$domainPath = dirname( getyourguide_widget_plugin_self() ) . '/languages';
		load_plugin_textdomain( self::WIDGET_SLUG, false, $domainPath );
	}

	/**
	 * Registers and enqueues widget-specific styles.
	 */
	public function register_widget_styles() {
		wp_enqueue_style(
			self::WIDGET_SLUG . '-styles',
			'https://widget.getyourguide.com/v2/core.css',
			[], // no dependencies
			null // don't add any version number
		);
	}

	/**
	 * Registers and enqueues widget-specific scripts.
	 */
	public function register_widget_scripts() {
		wp_enqueue_script(
			self::WIDGET_SLUG . '-script',
			'https://widget.getyourguide.com/v2/core.js',
			[], // no dependencies
			null // don't add any version number
		);
	}

	/**
	 * Get Widget parameters for a given instance.
	 *
	 * @param array $instance The widget instance.
	 *
	 * @return array
	 */
	protected function getWidgetParameters( $id, array $instance ) {
		// Retrieve the options set on the Widget itself
		$optionsSetOnWidget = wp_parse_args( $instance, GetYourGuide_Widget_Options::get_default_options() );

		// Retrieve the options set in the Post
		$postQuery = null;
		$postId    = get_queried_object_id();

		// If the page contains a post we need to check the post's widget settings.
		if ( $postId ) {
			// Check if this sidebar should be affected by the query associated with this post.
			$affected_sidebars = GetYourGuide_Widget_Post_Options::getAffectedSidebars( $postId );
			if ( in_array( $id, $affected_sidebars ) ) {
				$postQuery = GetYourGuide_Widget_Post_Options::getQuery( $postId );
			}
		}

		// Prepare the widget parameters
		$widget_parameters = [];

		$query = $postQuery ? $postQuery : $optionsSetOnWidget[ GetYourGuide_Widget_Options::OPTION_QUERY ];
		if ( $query != null ) {
			$widget_parameters['q'] = $query;
		}

		$partnerId = get_option( GetYourGuide_Widget_Settings::OPTION_NAME_PARTNER_ID );
		if ( $partnerId != null ) {
			$widget_parameters['partnerId'] = $partnerId;
		}

		$numberOfItems = $optionsSetOnWidget[ GetYourGuide_Widget_Options::OPTION_NUMBER_OF_ITEMS ];
		if ( $numberOfItems != null && $numberOfItems != GetYourGuide_Widget_Settings::NUMBER_OF_ITEMS_DEFAULT ) {
			$widget_parameters['numberOfItems'] = $numberOfItems;
		}

        $campaignParam = $optionsSetOnWidget[ GetYourGuide_Widget_Options::OPTION_CAMPAIGN_PARAM ];
        if ( $campaignParam != null ) {
            $widget_parameters['cmp'] = $campaignParam;
        }

		$currency = get_option( GetYourGuide_Widget_Settings::OPTION_NAME_CURRENCY );
		if ( $currency != null && $currency != GetYourGuide_Widget_Settings::CURRENCY_DEFAULT ) {
			$widget_parameters['currency'] = $currency;
		}

		$locale                          = get_option( GetYourGuide_Widget_Settings::OPTION_NAME_LOCALE,
			GetYourGuide_Widget_Settings::LOCALE_DEFAULT );
		$widget_parameters['localeCode'] = $locale;

		return $widget_parameters;
	}
}
