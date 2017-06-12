<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'DFF_Admin' ) ) {

	/**
	 * Class DFF_Admin to wire up the administrative components of this plugin. It configures the
	 * loading of the submenu and enqueuing scripts/styles, and initializes 
	 * `DFF_Admin_Controller` to handle the logic of requests.
	 *
	 * @see DFF_Admin_Controller for the logic on rendering the actual edit.php submenu page
	 */
	final class DFF_Admin {

		private $controller;

		function __construct() {
			$this->controller = new DFF_Admin_Controller();

			$this->admin_page_init();
		}

		/**
		 * This single action cascades through all functions in the class, setting up the plugin as
		 * required.
		 */
		function admin_page_init() {
			add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		}

		/**
		 * Registers the plugin submenu under the 'Posts' menu. Actions are then added to load the
		 * scripts and styles when the submenu page is loaded using namespaced-hooks.
		 */
		function add_admin_pages() {
			$title = __( 'Drafts for Friends', 'draftsforfriends' );

			$submenu = add_submenu_page( "edit.php",
				$title,
				$title,
				'manage_options',
				'drafts-for-friends',
				array( $this->controller, 'output_admin_submenu_page' ) );


			if (current_user_can('manage_options')) {
				add_action( 'load-' . $submenu, array( $this, 'load_admin_scripts' ) );
				add_action( 'load-' . $submenu, array( $this, 'load_admin_styles' ) );
			}
		}

		/**
		 * Called when our submenu page is loaded, which in turn will cause
		 * `$this->enqueue_admin_scripts()` to be called
		 */
		function load_admin_scripts() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		}

		/**
		 * Similar to `$this->load_admin_scripts()`, this is called when the submenu page loads and
		 * consequently enqueues the styles
		 */
		function load_admin_styles() {
			add_action( 'admin_head', array( $this, 'enqueue_admin_styles' ) );
		}

		/**
		 * Enqueue our scripts for the page. This enqueues jQuery and then the DFF javascript file
		 * (which has a dependency on jQuery)
		 */
		function enqueue_admin_scripts() {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script(
				'dff-admin-js',
				plugins_url( '/admin/js/admin.js', Drafts_For_Friends_Plugin::get_plugin_name() ),
				array( 'jquery' )
			);
		}

		/**
		 * Enqueue our styles for the page.
		 */
		function enqueue_admin_styles() {
			wp_enqueue_style(
				'dff-admin-css',
				plugins_url( '/admin/css/admin.css', Drafts_For_Friends_Plugin::get_plugin_name() )
			);
		}
	}
}