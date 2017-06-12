<?php

/*
Plugin Name: Drafts for Friends
Plugin URI: http://automattic.com/
Description: Now you don't need to add friends as users to the blog in order to let them preview your drafts
Author: Dan Jones
Author URI: github.com/dan-j
Version: 3.0
Text Domain: draftsforfriends
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Drafts_For_Friends_Plugin' ) ) {

	class Drafts_For_Friends_Plugin {

		/**
		 * The prefix that the plugin uses to store share-data to the Transient API
		 */
		const TRANSIENT_PREFIX = 'dff_shared_post_';

		const ACTION_CREATE_SHARE = 'dff_create_share';
		const ACTION_EXTEND_SHARE = 'dff_extend_share';
		const ACTION_DELETE_SHARE = 'dff_delete_share';

		public static function get_plugin_name() {
			return plugin_basename( __FILE__ );
		}

		function __construct() {
			add_action( 'init', array( &$this, 'init' ) );
		}

		function init() {

			$plugin_dir = plugin_dir_path( __FILE__ );

			if ( is_admin() ) {
				include_once( $plugin_dir . '/includes/admin/class-dff-admin-controller.php' );
				include_once( $plugin_dir . '/includes/admin/class-dff-admin-view.php' );
				include_once( $plugin_dir . '/includes/admin/class-dff-admin.php' );

				new DFF_Admin();

			} else {
				include_once( $plugin_dir . '/includes/class-public-filterer.php' );

				new DFF_Public_Filterer();
			}
		}
	}
}

new Drafts_For_Friends_Plugin();
