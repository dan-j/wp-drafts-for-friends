<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'DFF_Public_Filterer' ) ) {

	/**
	 * Filter class to determine if a user can see a draft and filter results accordingly.
	 */
	final class DFF_Public_Filterer {

		private $shared_post = null;

		function __construct() {
			add_filter( 'the_posts', array( $this, 'the_posts_intercept' ) );
			add_filter( 'posts_results', array( $this, 'posts_results_intercept' ) );
		}

		/**
		 * Use the Transient API to check a user can view the draft by checking that they have
		 * provided the correct 'secret' request parameter. If the transient is present then we can
		 * assume it hasn't expired.
		 *
		 * @param string $post_id The ID of the post to check access-rights.
		 *
		 * @return bool True if the user has a valid URL to access the shared post and that it
		 * hasn't expired. False otherwise
		 */
		function can_view_draft( $post_id ) {
			if ( isset( $post_id ) && $_GET['secret'] ) {

				/*
				 * Shape of this variable is array('secret' => secret, 'expiry' => expiry).
				 * Although the transient is deleted after expiry, we need the value in the admin
				 * pages to calculate the time remaining.
				 */
				$share_transient = get_transient(
					Drafts_For_Friends_Plugin::TRANSIENT_PREFIX . $post_id
				);

				if ( $share_transient
				     && isset( $share_transient['secret'] )
				     && $share_transient['secret'] === $_GET['secret']
				) {
					return true;
				}
			}

			return false;
		}

		/**
		 * This method is called with all posts matching the query for this request.
		 *
		 * This plugin only cares if the query matches only one post and it's status isn't
		 * 'publish'. In all other cases this interceptor does nothing and just returns the $posts
		 * parameter.
		 *
		 * @param array $posts all posts for this request
		 *
		 * @return mixed
		 */
		function posts_results_intercept( $posts ) {
			if ( 1 != count( $posts ) ) {
				return $posts;
			}
			$post   = $posts[0];
			$status = get_post_status( $post );

			// if the post being requested is 'publish' then we don't care and just return the
			if ( 'publish' != $status && $this->can_view_draft( $post->ID ) ) {
				$this->shared_post = $post;
			}

			return $posts;
		}

		/**
		 * Interceptor on 'the_posts' hook, if the posts are empty and $this->shared_post isn't
		 * null, then the query is for a draft which the user can view. Otherwise we nullify
		 * `$this->shared_post` and return the original array.
		 *
		 * @param array $posts An array of posts after they've been internally processed by WP
		 *
		 * @return array The posts to render
		 */
		function the_posts_intercept( $posts ) {
			if ( empty( $posts ) && ! is_null( $this->shared_post ) ) {
				return array( $this->shared_post );
			} else {
				$this->shared_post = null;

				return $posts;
			}
		}
	}
}