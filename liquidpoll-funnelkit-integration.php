<?php
/**
 * Plugin Name: LiquidPoll - Funnelkit Integration
 * Plugin URI: https://liquidpoll.com/plugin/liquidpoll-funnelkit
 * Description: Integration with Funnelkit
 * Version: 1.0.3
 * Author: LiquidPoll
 * Text Domain: liquidpoll-funnelkit
 * Domain Path: /languages/
 * Author URI: https://liquidpoll.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

use WPDK\Utils;

defined( 'ABSPATH' ) || exit;

defined( 'LIQUIDPOLL_FUNNELKIT_PLUGIN_URL' ) || define( 'LIQUIDPOLL_FUNNELKIT_PLUGIN_URL', WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/' );
defined( 'LIQUIDPOLL_FUNNELKIT_PLUGIN_DIR' ) || define( 'LIQUIDPOLL_FUNNELKIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'LIQUIDPOLL_FUNNELKIT_PLUGIN_FILE' ) || define( 'LIQUIDPOLL_FUNNELKIT_PLUGIN_FILE', plugin_basename( __FILE__ ) );


if ( ! class_exists( 'LIQUIDPOLL_Integration_funnelkit' ) ) {
	/**
	 * Class LIQUIDPOLL_Integration_funnelkit
	 */
	class LIQUIDPOLL_Integration_funnelkit {

		protected static $_instance = null;

		/**
		 * LIQUIDPOLL_Integration_funnelkit constructor.
		 */
		function __construct() {

			load_plugin_textdomain( 'liquidpoll-funnelkit', false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );

			add_filter( 'LiquidPoll/Filters/poll_meta_field_sections', array( $this, 'add_field_sections' ) );

			add_action( 'liquidpoll_email_added_local', array( $this, 'add_emails_to_funnelkit' ) );
		}


		/**
		 * Add emails to Fluent CRM
		 *
		 * @param $args
		 */
		function add_emails_to_funnelkit( $args ) {

			global $wpdb;

			$poll_id         = Utils::get_args_option( 'poll_id', $args );
			$poller_id_ip    = Utils::get_args_option( 'poller_id_ip', $args );
			$email_address   = Utils::get_args_option( 'email_address', $args );
			$first_name      = Utils::get_args_option( 'first_name', $args );
			$last_name       = Utils::get_args_option( 'last_name', $args );
			$funnelkit_lists = Utils::get_meta( 'poll_form_int_funnelkit_lists', $poll_id, array() );
			$funnelkit_tags  = Utils::get_meta( 'poll_form_int_funnelkit_tags', $poll_id, array() );
			$polled_value    = $wpdb->get_var( $wpdb->prepare( "SELECT polled_value FROM " . LIQUIDPOLL_RESULTS_TABLE . " WHERE poll_id = %d AND poller_id_ip = %s ORDER BY datetime DESC LIMIT 1", $poll_id, $poller_id_ip ) );

			if ( ! empty( $polled_value ) ) {
				$poll         = liquidpoll_get_poll( $poll_id );
				$poll_options = $poll->get_poll_options();
				$poll_type    = $poll->get_type();

				foreach ( $poll_options as $option_id => $option ) {
					if ( $polled_value == $option_id ) {

						if ( 'poll' == $poll_type ) {
							$funnelkit_tags = array_merge( $funnelkit_tags, Utils::get_args_option( 'funnelkit_tags', $option, array() ) );
						}

						if ( 'nps' == $poll_type ) {
							$funnelkit_tags = array_merge( $funnelkit_tags, Utils::get_args_option( 'funnelkit_nps_tags', $option, array() ) );

						}

						break;
					}
				}
			}

			if ( class_exists( 'BWFAN_Pro' ) ) {
				new BWFCRM_Contact( $email_address, true,
					array(
						'f_name' => $first_name,
						'l_name' => $last_name,
						'status' => 1,
						'lists'  => $funnelkit_lists,
						'tags'   => $funnelkit_tags,
					)
				);
			}
		}


		/**
		 * Add section in form field
		 *
		 * @param $field_sections
		 *
		 * @return array
		 */
		function add_field_sections( $field_sections ) {

			if ( class_exists( 'BWFAN_Pro' ) ) {

				$field_sections['poll_form']['fields'][] = array(
					'type'       => 'subheading',
					'content'    => esc_html__( 'Integration - Funnelkit', 'wp-poll' ),
					'dependency' => array( '_type', 'any', 'poll,nps,reaction', 'all' ),
				);

				$field_sections['poll_form']['fields'][] = array(
					'id'         => 'poll_form_int_funnelkit_enable',
					'title'      => esc_html__( 'Enable Integration', 'wp-poll' ),
					'label'      => esc_html__( 'This will store the submissions in Funnelkit.', 'wp-poll' ),
					'type'       => 'switcher',
					'default'    => false,
					'dependency' => array( '_type', 'any', 'poll,nps,reaction', 'all' ),
				);

				$field_sections['poll_form']['fields'][] = array(
					'id'         => 'poll_form_int_funnelkit_lists',
					'title'      => esc_html__( 'Select Lists', 'wp-poll' ),
					'subtitle'   => esc_html__( 'Select Funnelkit lists', 'wp-poll' ),
					'type'       => 'select',
					'multiple'   => true,
					'chosen'     => true,
					'options'    => $this->get_funnelkit_lists(),
					'dependency' => array( '_type|poll_form_int_funnelkit_enable', 'any|==', 'poll,nps,reaction|true', 'all' ),
				);

				$field_sections['poll_form']['fields'][] = array(
					'id'         => 'poll_form_int_funnelkit_tags',
					'title'      => esc_html__( 'Select Tags', 'wp-poll' ),
					'subtitle'   => esc_html__( 'Select Funnelkit tags', 'wp-poll' ),
					'type'       => 'select',
					'multiple'   => true,
					'chosen'     => true,
					'options'    => $this->get_funnelkit_tags(),
					'dependency' => array( '_type|poll_form_int_funnelkit_enable', 'any|==', 'poll,nps,reaction|true', 'all' ),
				);

				foreach ( Utils::get_args_option( 'fields', $field_sections['poll_options'], array() ) as $index => $arr_field ) {
					if ( isset( $arr_field['id'] ) && 'poll_meta_options' == $arr_field['id'] ) {
						$field_sections['poll_options']['fields'][ $index ]['fields'][] = array(
							'id'         => 'funnelkit_tags',
							'title'      => esc_html__( 'Select Tags', 'wp-poll' ),
							'subtitle'   => esc_html__( 'Select Funnelkit tags', 'wp-poll' ),
							'type'       => 'select',
							'multiple'   => true,
							'chosen'     => true,
							'options'    => $this->get_funnelkit_tags(),
							'dependency' => array( '_type', '==', 'poll', 'all' ),
						);
						break;
					}
				}

				foreach ( Utils::get_args_option( 'fields', $field_sections['poll_options'], array() ) as $index => $arr_field ) {
					if ( isset( $arr_field['id'] ) && 'poll_meta_options_nps' == $arr_field['id'] ) {
						$field_sections['poll_options']['fields'][ $index ]['fields'][] = array(
							'id'         => 'funnelkit_nps_tags',
							'title'      => esc_html__( 'Select Tags', 'wp-poll' ),
							'subtitle'   => esc_html__( 'Select Funnelkit tags', 'wp-poll' ),
							'type'       => 'select',
							'multiple'   => true,
							'chosen'     => true,
							'options'    => $this->get_funnelkit_tags(),
							'dependency' => array( '_type', '==', 'nps', 'all' ),
						);
						break;
					}
				}
			}

			return $field_sections;
		}


		/**
		 * Return Funnelkit tags
		 *
		 * @return array
		 */
		function get_funnelkit_tags() {

			if ( ! class_exists( 'BWFAN_Pro' ) ) {
				return array();
			}

			$tags          = BWFCRM_Tag::get_tags();
			$formattedTags = [];

			foreach ( $tags as $tag ) {
				$formattedTags[ $tag['ID'] ] = $tag['name'];
			}

			return $formattedTags;
		}


		/**
		 * Return Funnelkit lists
		 *
		 * @return array
		 */
		function get_funnelkit_lists() {

			if ( ! class_exists( 'BWFAN_Pro' ) ) {
				return array();
			}

			$lists          = BWFCRM_Lists::get_lists();
			$formattedLists = [];

			foreach ( $lists as $list ) {
				$formattedLists[ $list['ID'] ] = $list['name'];
			}

			return $formattedLists;
		}


		/**
		 * @return \LIQUIDPOLL_Integration_funnelkit|null
		 */
		public static function instance() {

			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}
	}
}

add_action( 'wpdk_init_wp_poll', array( 'LIQUIDPOLL_Integration_funnelkit', 'instance' ) );
