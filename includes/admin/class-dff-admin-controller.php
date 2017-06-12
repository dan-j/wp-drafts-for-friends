<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'DFF_Admin_Controller' ) ) {

	/**
	 * This class concerns itself with the backend logic of storing the details of shared drafts,
	 * and handling requests to manipulate those details.
	 */
	final class DFF_Admin_Controller {

		const UNITS = array( 's' => 1, 'm' => 60, 'h' => 60 * 60, 'd' => 24 * 3600 );

		function __construct() {
		}

		/**
		 * @param string $measure The textual unit to share the post for ('s', 'm', 'h', 'd')
		 * @param int $value The number of units to share the post for
		 *
		 * @return int the number of seconds the post should be shared for
		 * @throws Exception if $measure can't be converted to seconds
		 */
		function convert_period_to_seconds( string $measure, int $value ) {

			// this should rarely (if ever) happen, throwing an exception keeps the logic simpler
			if ( ! isset( self::UNITS[ $measure ] ) ) {
				// measure is a character from the POST, so doesn't need localizing
				throw new Exception( "'$measure' " .
				                     __( 'is not a valid unit' ) );
			}

			return self::UNITS[ $measure ] * $value;
		}

		/**
		 * Get all 'draft', 'future' and 'pending' posts.
		 *
		 * @return array an array of `WP_Post` objects
		 */
		function get_drafts() {
			return get_posts( array(
				'posts_per_page' => - 1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => array( 'draft', 'future', 'pending' ),
			) );
		}

		/**
		 * Validate that the provided value is an integer and throw an exception if not.
		 *
		 * @param int $value the value to validate
		 *
		 * @return int the parsed integer
		 * @throws Exception
		 */
		function validate_int( int $value ) {
			if ( $e = intval( $value ) ) {
				return $e;
			} else {
				throw new Exception( 'Unable to validate integer' );
			}
		}

		/**
		 * Remove a draft from being shared.
		 *
		 * `$params` must be of the shape:
		 *
		 *     $params = array(
		 *         'post_id' => int,
		 *         'expires' => int,
		 *         'measure' => string, // (one of s, m, h, d)
		 *         '_nonce'  => string
		 *     );
		 *
		 * @param array $params POST parameters for a new shared draft
		 *
		 * @return string a localized message to be printed on the page
		 */
		function process_post_new_share( $params ) {

			if ( ! ( isset( $params['post_id'] )
			         && isset( $params['expires'] )
			         && isset( $params['measure'] )
			         && isset( $params['_nonce'] ) )
			) {
				return __( 'Unable to share post, invalid parameters', 'draftsforfriends' );
			}

			if ( ! wp_verify_nonce(
				$params['_nonce'],
				Drafts_For_Friends_Plugin::ACTION_CREATE_SHARE )
			) {
				return __(
					'Invalid security token, please refresh the page and try again',
					'draftsforfriends'
				);
			}

			$post = get_post( $params['post_id'] );

			if ( ! $post ) {
				return __( 'There is no such post!', 'draftsforfriends' );
			}
			if ( 'publish' == get_post_status( $post ) ) {
				return __( 'The post is already published!', 'draftsforfriends' );
			}

			$expiry_value = null;
			try {
				$expiry_value = $this->validate_int( $params['expires'] );
			} catch ( Exception $e ) {
				return __(
					'Unable to share post, invalid parameters',
					'draftsforfriends'
				);
			}

			try {

				$expiry_seconds = $this->convert_period_to_seconds(
					$params['measure'],
					$expiry_value
				);

			} catch ( Exception $e ) {
				// this should already be localized
				return $e->getMessage();
			}

			$expiry_timestamp = current_time( 'timestamp' ) + $expiry_seconds;

			if ( set_transient(
				Drafts_For_Friends_Plugin::TRANSIENT_PREFIX . $post->ID,
				array(
					'secret' => wp_generate_password( 16, false ),
					'expiry' => $expiry_timestamp,
				),
				$expiry_seconds
			) ) {

				return __( "The post is being shared", 'draftsforfriends' );
			} else {
				return __(
					"Unable to share post, couldn't persist options to database",
					'draftsforfriends'
				);
			}
		}

		/**
		 * Remove a draft from being shared.
		 *
		 * `$params` must be of the shape:
		 *
		 *     $params = array(
		 *         'post_id' => int,
		 *         '_nonce'  => string,
		 *     );
		 *
		 * @param array $params POST parameters to stop sharing a draft
		 *
		 * @return string a localized message to be printed on the page
		 */
		function process_post_delete( $params ) {

			if ( ! ( isset( $params['post_id'] )
			         && isset( $params['_nonce'] ) )
			) {
				return __(
					'Unable to share post, invalid parameters',
					'draftsforfriends'
				);
			}

			if ( ! wp_verify_nonce(
				$params['_nonce'],
				Drafts_For_Friends_Plugin::ACTION_DELETE_SHARE )
			) {
				return __(
					'Invalid security token, please refresh the page and try again',
					'draftsforfriends'
				);
			}

			$transient_key = Drafts_For_Friends_Plugin::TRANSIENT_PREFIX . $params['post_id'];

			if ( ! get_transient( $transient_key ) ) {
				return __( "Post isn't shared", 'draftsforfriends' );
			}

			if ( delete_transient( $transient_key ) ) {
				return __( 'Post has been unshared', 'draftsforfriends' );
			} else {
				return __( 'Unknown error occurred', 'draftsforfriends' );
			}

		}

		/**
		 * Extend the expiry time that a draft is shared for.
		 *
		 * `$params` must be of the shape:
		 *
		 *     $params = array(
		 *         'post_id' => int,
		 *         'expires' => int,
		 *         'measure' => string, // (one of s, m, h, d)
		 *         '_nonce'  => string
		 *     );
		 *
		 * @param array $params POST parameters to extend the duration a draft is shared for.
		 *
		 * @return string a localized message to be printed on the page
		 */
		function process_post_extend( array $params ) {

			if ( ! ( isset( $params['post_id'] )
			         && isset( $params['expires'] )
			         && isset( $params['measure'] )
			         && isset( $params['_nonce'] ) )
			) {
				return __(
					'Unable to share post, invalid parameters',
					'draftsforfriends'
				);
			}

			if ( ! wp_verify_nonce(
				$params['_nonce'],
				Drafts_For_Friends_Plugin::ACTION_EXTEND_SHARE )
			) {
				return __(
					'Invalid security token, please refresh the page and try again',
					'draftsforfriends'
				);
			}

			$expiry_value = null;
			try {
				$expiry_value = $this->validate_int( $params['expires'] );
			} catch ( Exception $e ) {
				return __(
					'Unable to share post, invalid parameters',
					'draftsforfriends'
				);
			}

			try {

				$expiry_seconds = $this->convert_period_to_seconds(
					$params['measure'],
					$expiry_value
				);

			} catch ( Exception $e ) {
				return __( $e->getMessage(), 'draftsforfriends' );
			}

			$transient_key = Drafts_For_Friends_Plugin::TRANSIENT_PREFIX . $params['post_id'];

			$transient = get_transient( $transient_key );

			$expiry_timestamp = $transient['expiry'] + $expiry_seconds;

			if ( set_transient(
				$transient_key,
				array(
					'secret' => $transient['secret'],
					'expiry' => $expiry_timestamp,
				),
				$expiry_seconds
			) ) {

				return __( "The share has been extended", 'draftsforfriends' );
			} else {
				return __(
					"Unable to extend share, couldn't persist options to database",
					'draftsforfriends'
				);
			}
		}

		/**
		 * Method called when the submenu page needs to be rendered. First we handle any POSTs that
		 * were made, and then use `class DFF_Admin_View` to render the page.
		 */
		function output_admin_submenu_page() {
			$response_msg = null;
			if ( isset( $_POST['dff-new-share'] ) ) {
				$response_msg = $this->process_post_new_share( $_POST );
			} elseif ( isset( $_POST['dff-delete-share'] ) ) {
				$response_msg = $this->process_post_delete( $_POST );
			} elseif ( isset( $_POST['dff-extend-share'] ) ) {
				$response_msg = $this->process_post_extend( $_POST );
			}

			$admin_view = new DFF_Admin_View( $this->get_drafts(), $response_msg );
			$admin_view->output();
		}
	}

}