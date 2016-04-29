<?php
/**
 * Plugin Name: Multisite Widgets Context
 * Plugin URI: https://github.com/nicholasio/multisite-widgets-context
 * Description: A WordPress Multisite Plugin that runs a Widget in a context of another site that belongs to the network 
 * Author: nicholas_io
 * Author URI: http://nicholasandre.com.br
 * Version: 1.1.2
 * License: GPLv2 or later
 * Text Domain: wpmulwc
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly.

define( 'WPMULWC_VERSION', '1.1.2' );

/**
 * Multisite Widgets Context
 *
 * @author  Nícholas André <nicholas@iotecnologia.com.br>
 */
class Multisite_Widgets_Context {

	/**
	 * Plugin Slug
	 * @var string
	 */
	public $plugin_slug = 'wpmulwc';

	/**
	 * Instance of this class.
	 *
	 * @var Multisite_Widgets_Context
	 */
	protected static $instance = null;

	/**
	 * An array with BLOG_ID => Blog_Name
	 * @var Array
	 */
	protected $arrSites;

	/**
	 * All Blogs
	 * @var Array
	 */
	protected $blogsID;

	/**
	 * Initialize the plugin
	 */
	private function __construct() {
		$this->init();

		$this->arrSites = array();
		$this->blogsID  = array();
	}

	/**
	 * Set up Actions and Filters
	 *
	 * @since 1.1.0
	 */
	public function init() {
		// Load plugin text domain
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

		// Load scripts js and styles css
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 999 );

		//This filter fires before widget form displays
		add_action( 'widget_form_callback' ,  array( $this, 'before_widget_form'), 99, 2);
		//This action fires after widget form display (inside widget form)
		add_action( 'in_widget_form' , array( $this, 'after_widget_form'), 99, 3 );

		//to save our data
		add_action( 'widget_update_callback', array( $this, 'widget_update_callback'), 99, 4 );

		//This actions fires before display
		add_action( 'widget_display_callback', array( $this, 'before_render_widget'), 99, 3 );
		//It's a trick: this filter fires after a widget is displayed, but we use to restore_current_blog if needed
		add_filter( 'dynamic_sidebar_params', array( $this, 'after_render_previous_widget'), 99, 1 );

		//After render all widgets return back to the original blog
		add_action( 'dynamic_sidebar_after', array( $this, 'after_render_all_widgets' ), 99 );

		//Cache purging
		add_action( 'wpmu_new_blog', array( $this, 'purge_cache' ) );
		add_action( 'delete_blog', 	 array( $this, 'purge_cache' ) );

		if ( is_admin() ) {
			add_action( 'dynamic_sidebar_params', arraY( $this, 'append_current_site_context' ) );
		}
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Purges the plugin cache
	 *
	 * @since 1.1.0
	 * @param $blog_id
	 */
	public function purge_cache( $blog_id ) {
		delete_site_transient( 'wpmulwc_blog_ids' );
	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    1.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	public static function get_blog_ids() {
		$blog_ids = get_site_transient( 'wpmulwc_blog_ids' );

		if ( false === $blog_ids ) {
			global $wpdb;

			$blogs = esc_sql( $wpdb->blogs );
			// get an array of blog ids
			$sql = "SELECT blog_id FROM {$blogs}  WHERE archived = '0' AND spam = '0'AND deleted = '0'";

			$blog_ids =  $wpdb->get_col( $sql );

			set_site_transient( 'wpmulwc_blog_ids', $blog_ids, 6 * HOUR_IN_SECONDS );
		}

		return $blog_ids;

	}
	/**
	 * Load the plugin text domain for translation.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( $this->plugin_slug, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Callback for plugin activation
	 *
	 * @return void
	 */
	public static function activate() { 
		if ( ! is_multisite() ) {
			wp_die( __( "You aren't using multisite. You need multisite in order to use this plugin", 'wpmulwc' ) );
		}
	}

	/**
	 * Appends the current site context to the widget title
	 *
	 * @since 1.1.0
	 *
	 * @param $params Widget Params
	 *
	 * @return array
	 */
	public function append_current_site_context( $params ) {
		global $wp_registered_widgets;

		$widget_number = $params[1]['number'];

		$widget = $wp_registered_widgets[ $params[0]['widget_id'] ]['callback'][0];

		if ( method_exists( $widget, 'get_settings' ) ) {
			$widget_settings = $wp_registered_widgets[ $params[0]['widget_id'] ]['callback'][0]->get_settings();

			if ( isset( $widget_settings[ $widget_number ] ) ) {
				$instance = $widget_settings[ $widget_number ];

				if ( isset( $instance['wpmulwc-site_id'] ) ) {
					$site_context = esc_html( get_blog_option( (int) $instance['wpmulwc-site_id'], 'blogname' ) );
					$params[0]['widget_name'] .= " ({$site_context})";
				}

			}
		}


		return $params;
	}

	/**
	 * Checks if we need to switch to a specific blog before displaying the widget form
	 *
	 * @param $instance
	 * @param $_this
	 * @return mixed
	 */
	public function before_widget_form( $instance, $_this ) {
		if ( isset( $instance[ $this->plugin_slug . '-grab-data' ] ) && isset( $instance[ $this->plugin_slug . '-site_id' ] ) ) {

			$site_id    =  $instance[ $this->plugin_slug . '-site_id' ];
			$grabData   =  $instance[ $this->plugin_slug . '-grab-data'];

			if ( $grabData && $site_id != get_current_blog_id() ) {
				//I wish I could do this without globals
				$GLOBALS[ '_wpmulwc_switched_admin'  ] = true;
				switch_to_blog( $site_id );
			}
		}

		return $instance;
	}

	/**
	 * Display extra fields on every single Widget registered with WordPress
	 */
	public function after_widget_form( $_this, $return, $instance ) {
		if ( isset( $GLOBALS[ '_wpmulwc_switched_admin' ] ) && $GLOBALS[ '_wpmulwc_switched_admin' ] ) {
			$GLOBALS[ '_wpmulwc_switched_admin'  ] = false;
			restore_current_blog();
		}
		
		if ( ! current_user_can( 'manage_network ') ) return false;

		$selectName = $_this->get_field_name( $this->plugin_slug . '-site_id' );
		$site_id    = isset( $instance[ $this->plugin_slug . '-site_id' ] ) 	? $instance[ $this->plugin_slug . '-site_id' ] : false;
		$grabData   = isset( $instance[ $this->plugin_slug . '-grab-data' ] ) 	? $instance[ $this->plugin_slug . '-grab-data'] : false;

		if ( empty( $this->blogsID ) ) {
			$this->blogsID = self::get_blog_ids();	
		}
		
		$grabDataId   = $_this->get_field_id( $this->plugin_slug . '-grab-data' );
		$grabDataName = $_this->get_field_name( $this->plugin_slug . '-grab-data' );

		echo "<div class='multisite-widget-context-select' data-widget-name='" . esc_attr( $_this->name ) . "'>";
			echo "<span class='button-wrap'>";
				echo "<a href='#' class='button'>" . esc_html__( 'Widget Context', $this->plugin_slug ). "</a>";
				echo "<p>" . esc_html__( 'Click to change the widget context' ) . "</p>";
			echo "</span>";
			echo "<div class='wpmulwc-form'>";
				echo "<h4>" . esc_html__( 'Choose a site context to run this widget' , $this->plugin_slug ) . "</h4>";
				echo "<p>";
					echo "<input " . checked( $grabData, true, false)  .  " type='checkbox' value='1' id='" . esc_attr( $grabDataId ) . "' name='" . esc_attr( $grabDataName ) . "'>";
					echo "<label for='" . esc_attr( $grabDataId ) . "'>" .  esc_html__( 'Pulls widget data from target site.', $this->plugin_slug ) . "</label>";
				echo "</p>";
				echo "<p>";
					echo "<select name='" . esc_attr( $selectName ) . "' class='widefat'>";
					foreach($this->blogsID as $blog_id) {
						//save this for later
						if ( ! isset( $this->arrSites[ $blog_id ] ) ) {
							$this->arrSites[ $blog_id ] = get_blog_option( $blog_id, 'blogname' );
						}

						//XSS ok
						$selected = ( $site_id === false && $blog_id == get_current_blog_id() ) ? 'selected' : '';
						$selected = ( $site_id !== false && $blog_id == $site_id ) ? 'selected' : $selected;

						echo "<option value='" . esc_attr( $blog_id ) .  "' $selected>" . esc_html( $this->arrSites[ $blog_id ] ) . "</option>";
					}
					echo "</select>";
				echo "</p>";
			echo "</div>"; //.wpmulwc-form

		echo "</div>";


	}

	/**
	 * Save extra fields that we added
	 */
	public function widget_update_callback( $instance, $new_instance, $old_instance, $_this ) {

		if ( isset( $new_instance[ $this->plugin_slug . '-site_id' ] )  ) {
			$instance[ $this->plugin_slug . '-site_id' ] 	= (int) $new_instance[ $this->plugin_slug . '-site_id' ];;
			$instance[ $this->plugin_slug . '-grab-data' ] 	= (int) $new_instance[ $this->plugin_slug . '-grab-data' ];
		}

		return $instance;
	}

	/**
	 * Before render widget, check if we need to switch_to_blog
	 */
	public function before_render_widget( $instance, $_this, $args ) {
		if ( isset( $instance[ $this->plugin_slug . '-site_id' ] )  && is_int( $instance[ $this->plugin_slug . '-site_id' ] ) ) {
			if ( $instance[ $this->plugin_slug . '-site_id' ] != get_current_blog_id() ) {
				$GLOBALS[ '_wpmulwc_switched' ] = true;
				switch_to_blog( (int) $instance[ $this->plugin_slug . '-site_id' ]);	
			}
			
		}
		return $instance;
	}


	/**
	 * It's a trick: this filter fires after a widget is displayed, we need to restore_current_blog if it's
	 * in the switched state
	 */
	public function after_render_previous_widget( $params ) {
		//Before render, check if we need to restore to current blog
		if ( isset( $GLOBALS[ '_wpmulwc_switched' ] ) && $GLOBALS[ '_wpmulwc_switched' ] ) {
			$GLOBALS[ '_wpmulwc_switched' ] = false;
			restore_current_blog();	
		}

		return $params;
	}

	/**
	 * Ensures that we will be on the right blog if the last widget has been switched
	 */
	public function after_render_all_widgets( $index ) {
		$this->after_render_previous_widget( null );
	}

	/**
	 * Load assets
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		
		if ( 'widgets' === $screen->id ) {
			wp_enqueue_style(
				$this->plugin_slug . '-widgets-css' ,
				plugins_url( 'assets/css/widgets.css', __FILE__ ),
				array(),
				WPMULWC_VERSION,
				'all'
			);

			wp_enqueue_script(
				$this->plugin_slug . '-widgets-js' ,
				plugins_url( 'assets/js/widgets.js', __FILE__ ),
				array(),
				WPMULWC_VERSION,
				true
			);
		}
	}

}

register_activation_hook( __FILE__ , array( 'Multisite_Widgets_Context', 'activate') );
add_action( 'plugins_loaded', array( 'Multisite_Widgets_Context', 'get_instance' ), 0 );
