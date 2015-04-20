<?php
/**
 * Plugin Name: Multisite Widgets Context
 * Plugin URI: https://github.com/nicholasio/multisite-widgets-context
 * Description: A WordPress Multisite Plugin that runs a Widget in a context of another site that belongs to the network 
 * Author: nicholas_io
 * Author URI: http://nicholasandre.com.br
 * Version: 1.0.0
 * License: GPLv2 or later
 * Text Domain: wpmulwc
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly.

/**
 * Multisite Widgets Context
 *
 * @author  Nícholas André <nicholas@iotecnologia.com.br>
 */
class Multisite_Widgets_Context {

	/**
	 * Plugin Slug
	 * @var strng
	 */
	public $plugin_slug = 'wpmulwc';

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * A hash with BLOG_ID => Blog_Name
	 */
	protected $arrSites;

	/**
	 * All Blogs
	 */
	protected $blogsID;

	/**
	 * Initialize the plugin
	 */
	private function __construct() {
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

		$this->arrSites = array();
		$this->blogsID  = array();

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
		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );
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
	 */
	public static function activate() { 
		if ( ! is_multisite() ) {
			die(__("You aren't using multisite. You need multisite in order to use this plugin") );
		}
	}

	public function before_widget_form( $instance, $_this ) {
		if ( isset( $instance[ $this->plugin_slug . '-grab-data' ] ) && isset( $instance[ $this->plugin_slug . '-site_id' ]) ) {

			$site_id    =  $instance[ $this->plugin_slug . '-site_id' ];
			$grabData   =  $instance[ $this->plugin_slug . '-grab-data'];

			if ( $grabData && $site_id != get_current_blog_id() ) {
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
		if ( $GLOBALS[ '_wpmulwc_switched_admin'  ] ) {
			$GLOBALS[ '_wpmulwc_switched_admin'  ] = false;
			restore_current_blog();
		}
		
		if ( ! current_user_can( 'manage_network ') ) return false;

		$selectName = $_this->get_field_name( $this->plugin_slug . '-site_id' );
		$site_id    = isset($instance[ $this->plugin_slug . '-site_id' ]) 	? $instance[ $this->plugin_slug . '-site_id' ] : false;
		$grabData   = isset($instance[ $this->plugin_slug . '-grab-data' ] ) ? $instance[ $this->plugin_slug . '-grab-data'] : false;
		
		if ( empty( $this->blogsID ) ) {
			$this->blogsID = self::get_blog_ids();	
		}
		
		$grabDataId   = $_this->get_field_id( $this->plugin_slug . '-grab-data');
		$grabDataName = $_this->get_field_name( $this->plugin_slug . '-grab-data');

		echo "<div class='multisite-widget-context-select'>";
			echo "<p><h4>" . __('Choose the context of a site to run this widget', $this->plugin_slug) . "</h4></p>";
			echo "<p>";	
				echo "<input " . checked($grabData, true, false) .  " type='checkbox' value='1' id='" . $grabDataId . "' name='" . $grabDataName . "'>";
				echo "<label for='" . $grabDataId . "'>" .  __('Pulls widget data from target site.', $this->plugin_slug) . "</label>";
			echo "</p>";
			echo "<p>";
				echo "<select name='{$selectName}' class='widefat'>";
				foreach($this->blogsID as $blog_id) {
					if ( ! isset( $this->arrSites[ $blog_id ] ) ) {
						$this->arrSites[ $blog_id ] = get_blog_option( $blog_id, 'blogname' );
					}
					
					$selected = ( $blog_id == get_current_blog_id() ) ? 'selected' : '';
					$selected = ( $site_id !== false && $blog_id == $site_id ) ? 'selected' : '';

					echo "<option value='{$blog_id}' $selected>{$this->arrSites[ $blog_id ]}</option>";
				}
				echo "</select>";
			echo "</p>";

		echo "</div>";


	}

	/**
	 * Save extra fields that we added
	 */
	public function widget_update_callback( $instance, $new_instance, $old_instance, $_this ) {
		if ( isset( $new_instance[ $this->plugin_slug . '-site_id' ] )  ) {
			$wpmulwc_id = (int) $new_instance[ $this->plugin_slug . '-site_id' ];
			$instance[ $this->plugin_slug . '-site_id' ] = $wpmulwc_id;
			$instance[ $this->plugin_slug . '-grab-data' ] = $new_instance[ $this->plugin_slug . '-grab-data' ];
		}
		return $instance;
	}

	/**
	 * Before render widget, check if we need to switch_to_blog
	 */

	public function before_render_widget( $instance, $_this, $args ) {
		if ( isset( $instance[ $this->plugin_slug . '-site_id' ] )  && is_int($instance[ $this->plugin_slug . '-site_id' ]) ) {
			if ( $instance [ $this->plugin_slug . '-site_id' ] != get_current_blog_id() ) {
				$GLOBALS[ '_wpmulwc_switched' ] = true;
				switch_to_blog( (int) $instance[ $this->plugin_slug . '-site_id' ]);	
			}
			
		}
		return $instance;
	}


	/**
	 * It's a trick: this filter fires after a widget is displayed, but we use to restore_current_blog if needed
	 */
	public function after_render_previous_widget( $params ) {
		//Before render, check if we need to restore to current blog
		if ( $GLOBALS[ '_wpmulwc_switched' ] ) {
			$GLOBALS[ '_wpmulwc_switched' ] = false;
			restore_current_blog();	
		}

		return $params;
	}

	/**
	 * Ensures that we will be on right blog with the last widget is switched
	 */
	public function after_render_all_widgets( $index ) {
		$this->after_render_previous_widget( null );
	}

	/**
	 * Load scripts js and styles css
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		
		if ( 'widgets' === $screen->id ) {
			wp_enqueue_style(  $this->plugin_slug . '-widgets-css' , plugins_url( 'assets/css/widgets.css', __FILE__ ), array() , null , 'all' );
		}
	}

}

register_activation_hook( __FILE__ , array( 'Multisite_Widgets_Context', 'activate') );
add_action( 'plugins_loaded', array( 'Multisite_Widgets_Context', 'get_instance' ), 0 );



























