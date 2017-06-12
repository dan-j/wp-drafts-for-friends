<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'DFF_Admin_View' ) ) {

	/**
	 * Class DFF_Admin_View Render the Drafts For Friends Admin page.
	 */
	class DFF_Admin_View {

		private $all_drafts;

		private $message;

		private $url_format;

		/**
		 * Constants used by `calculate_expires_after()`
		 */
		const UNIT_LIMITS = array(
			array( 'second', 60 ),
			array( 'minute', 60 ),
			array( 'hour', 24 ),
			array( 'day', 7 )
		);

		/**
		 * @param WP_Post[] $all_drafts an array of `WP_Post` objects which should be rendered
		 * @param string|null $message an optional message to print at the top of the page
		 */
		function __construct( $all_drafts = array(), string $message = null ) {
			$this->all_drafts = $all_drafts;
			$this->message    = $message;

			$this->url_format = get_bloginfo( 'url' ) . '/?p=%s&secret=%s';
		}

		/**
		 * Given the expiry of a shared draft, generate a human-readable string for the time
		 * remaining.
		 *
		 * @param array $share_details the array stored in the transient API denoting the share
		 *                             details
		 *
		 * @return string
		 */
		private function calculate_expires_after( array $share_details ) {

			if ( ! isset( $share_details['expiry'] ) ) {
				return __( 'Unknown expiry', 'draftsforfriends' );
			}

			$expiry = $share_details['expiry'];
			$now    = current_time( 'timestamp' );

			if ( $now > $expiry ) {
				return __( 'Expired', 'draftsforfriends' );
			}

			$seconds_remaining = $expiry - $now;

			$i     = 0;
			$value = $seconds_remaining;

			// whilst $value is less then the unit limit (secs => 60, mins => 60 etc), divide by
			// that unit limit..
			// once $value is less than the unit limit, we have the unit (using $i), and the
			// numerical value to display.
			while ( $i < count( self::UNIT_LIMITS ) ) {

				if ( $value < self::UNIT_LIMITS[ $i ][1] ) {
					break;
				}

				$value = floor( $value / self::UNIT_LIMITS[ $i ][1] );
				$i ++;
			}

			// this is a singular unit
			$unit = self::UNIT_LIMITS[ $i ][0];

			// handle internationalization for plurals
			// this can be any singular/plural of 'second', 'minute', 'hour', 'day'.
			// these words are listed in the POT file for plural translation support
			return sprintf(
				_n(
					'%s ' . $unit . ' remaining',
					'%s ' . $unit . 's remaining',
					$value,
					'draftsforfriends'
				),
				number_format_i18n( $value )
			);
		}

		/**
		 * Print the header HTML and if present, the optional message provided in `__construct()`.
		 */
		private function output_header() {
			?>
            <h2>
				<?php
				esc_html_e( 'Drafts for Friends', 'draftsforfriends' );
				?>
            </h2>
			<?php if ( $this->message ) : ?>
                <div id="message" class="updated fade">
                    <!-- These messages have already been localized -->
					<?php echo esc_html( $this->message ); ?>
                </div>
			<?php endif;
		}

		/**
		 * Output HTML with the shared draft's URL, or a string saying 'Not Shared'.
		 *
		 * @param string $draft_id the ID of the draft being outputted
		 * @param array|null $share_details the array stored in the transient API denoting the share
		 *                                  details
		 */
		private function output_draft_status_or_url(
			string $draft_id,
			array $share_details = null
		) {
			if ( $share_details ) {
				$url = sprintf( $this->url_format, $draft_id, $share_details['secret'] );
				?>
                <a href="<?php echo esc_attr( $url ); ?>"><?php echo esc_html( $url ); ?></a>
				<?php
			} else {
				?>
                <i><?php esc_html_e( 'Not Shared', 'draftsforfriends' ) ?></i>
				<?php
			}
		}

		/**
		 * Output HTML stating how much time a draft is shared for (if at all).
		 *
		 * @param array|null $share_details the array stored in the transient API denoting the share
		 *                                  details
		 */
		private function output_expires_after( array $share_details = null ) {
			if ( $share_details ) {
				echo $this->calculate_expires_after( $share_details );
			}
		}

		/**
		 * Output HTML under the actions column.
		 *
		 * If the draft isn't shared then a single button to share the draft is rendered. If a
		 * draft is shared then two buttons will be rendered, one to extend the expiry and
		 * another to stop sharing the draft.
		 *
		 * @param string $draft_id the ID of the draft being rendered
		 * @param array|null $share_details the array stored in the transient API denoting the share
		 *                                  details
		 */
		private function output_share_extend_buttons(
			string $draft_id,
			array $share_details = null
		) {
			// we always use $draft_id in HTML attributes to escape it here
			$esc_attr_id = esc_attr( $draft_id );

			if ( $share_details ) {
				?>
                <form id="dff-extend-share-<?php echo $esc_attr_id; ?>" method="post">
                    <input type="submit" class="button" name="dff-extend-share"
                           value="<?php
					       esc_attr_e( 'Extend', 'draftsforfriends' );
					       ?>"
                    />
                    <input type="hidden" name="post_id" value="<?php echo $esc_attr_id ?>"/>
                    <input class="hidden hideable" title="number" name="expires" type="text"
                           value="2" size="4"/>
                    <select title="units" class="hidden hideable" name="measure">
                        <option value="s">
							<?php esc_html_e( 'seconds', 'draftsforfriends' ); ?>
                        </option>
                        <option value="m">
							<?php esc_html_e( 'minutes', 'draftsforfriends' ); ?>
                        </option>
                        <option value="h" selected="selected">
							<?php esc_html_e( 'hours', 'draftsforfriends' ); ?>
                        </option>
                        <option value="d">
							<?php esc_html_e( 'days', 'draftsforfriends' ); ?>
                        </option>
                    </select>
                    <input type="hidden" name="_nonce" value="<?php
					echo wp_create_nonce( Drafts_For_Friends_Plugin::ACTION_EXTEND_SHARE )
					?>"/>
                    <a class="hidden hideable" id="cancel" href="#">Cancel</a>
                </form>

                <form id="dff-delete-share-<?php echo $esc_attr_id; ?>" method="post">
                    <input type="submit" class="button button-alert hideable"
                           name="dff-delete-share"
                           value="<?php
					       esc_attr_e( 'Stop Sharing', 'draftsforfriends' );
					       ?>"
                    />
                    <input type="hidden" name="post_id" value="<?php echo $esc_attr_id ?>"/>
                    <input type="hidden" name="_nonce" value="<?php
					echo wp_create_nonce( Drafts_For_Friends_Plugin::ACTION_DELETE_SHARE )
					?>"/>
                </form>
				<?php
			} else {
				?>
                <form id="dff-share-draft-<?php echo $esc_attr_id ?>" method="post">
                    <input type="submit" class="button-primary" name="dff-new-share"
                           value="<?php
					       esc_attr_e( 'Share Draft', 'draftsforfriends' );
					       ?>"
                    />
                    <input type="hidden" name="post_id" value="<?php echo $esc_attr_id ?>"/>
                    <input class="hidden hideable" title="number" name="expires" type="text"
                           value="2" size="4"/>
                    <select class="hidden hideable" title="units" name="measure">
                        <option value="s">
							<?php esc_html_e( 'seconds', 'draftsforfriends' ); ?>
                        </option>
                        <option value="m">
							<?php esc_html_e( 'minutes', 'draftsforfriends' ); ?>
                        </option>
                        <option value="h" selected="selected">
							<?php esc_html_e( 'hours', 'draftsforfriends' ); ?>
                        </option>
                        <option value="d">
							<?php esc_html_e( 'days', 'draftsforfriends' ); ?>
                        </option>
                    </select>
                    <input type="hidden" name="_nonce" value="<?php
					echo wp_create_nonce( Drafts_For_Friends_Plugin::ACTION_CREATE_SHARE )
					?>"/>
                    <a class="hidden hideable" id="cancel" href="#">
						<?php esc_html_e( 'Cancel', 'draftsforfriends' ); ?>
                    </a>
                </form>
				<?php
			}
		}

		/**
		 * Output HTML for the <tr> of a draft. This doesn't do much other than delegate concerns
		 * to more specific functions.
		 *
		 * @param WP_Post $draft the draft this row will represent
		 * @param array|null $share_details the array stored in the transient API denoting the share
		 *                                  details
		 */
		private function output_table_row( WP_Post $draft, array $share_details = null ) {
			?>
            <tr>
                <td><?php echo esc_html( $draft->ID ); ?></td>
                <td><?php echo esc_html( $draft->post_title ); ?></td>
                <td><?php $this->output_draft_status_or_url( $draft->ID, $share_details ); ?></td>
                <td><?php $this->output_expires_after( $share_details ); ?></td>
                <td class="share-controls">
					<?php $this->output_share_extend_buttons( $draft->ID, $share_details ); ?>
                </td>
            </tr>
			<?php
		}

		/**
		 * Output HTML for the <table> element.
		 */
		private function output_table() {
			?>
            <table class="widefat dff-admin">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'ID', 'draftsforfriends' ); ?></th>
                    <th><?php esc_html_e( 'Title', 'draftsforfriends' ); ?></th>
                    <th><?php esc_html_e( 'Link', 'draftsforfriends' ); ?></th>
                    <th>
						<?php esc_html_e( 'Expires After', 'draftsforfriends' ); ?>
                    </th>
                    <th class="actions">
						<?php esc_html_e( 'Actions', 'draftsforfriends' ); ?>
                    </th>
                </tr>
                </thead>
                <tbody>
				<?php
				foreach ( $this->all_drafts as $draft ) {
					// default to null if not set
					$share_details = get_transient(
						Drafts_For_Friends_Plugin::TRANSIENT_PREFIX . $draft->ID
					) ?: null;
					$this->output_table_row( $draft, $share_details );
				}
				?>
                </tbody>
            </table>
			<?php
		}


		/**
		 * Output the HTML to render the Drafts for Friends administration page
		 */
		public function output() {
			?>
            <div class="wrap">
				<?php $this->output_header(); ?>
				<?php $this->output_table(); ?>
            </div>
			<?php
		}

	}

}