<?php
/**
 * Plugin Name: WPBot Commands
 * Plugin URI: http://www.wp-bot.net
 * Description: Manage the commands of WPBot in #WordPress
 * Version: 1.0.0
 * Author: Clorith
 * Author URI: http://www.clorith.net
 * Text Domain: wpbot-commands
 * License: GPLv2
 *
 * Copyright 2016 Marius Jensen (email : marius@jits.no)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

class WPBot_Commands {
	public function __construct() {
		add_action( 'init', array( $this, 'add_post_type' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_boxes' ) );
	}

	function add_post_type() {
		register_post_type(
			'wpbot_commands',
			array(
				'labels'      => array(
					'name'          => __( 'Commands', 'wpbot-commands' ),
					'singular_name' => __( 'Command', 'wpbot-commands' )
				),
				'public'      => true,
				'has_archive' => true,
				'menu_icon'   => 'dashicons-leftright',

			)
		);
	}

	function add_meta_boxes() {
		add_meta_box(
			'wpbot_command_meta',
			__( 'WPBot Command', 'wpbot-commands' ),
			array( $this, 'display_meta_boxes' ),
			'wpbot_commands',
			'advanced',
			'high'
		);
	}

	function display_meta_boxes( $post ) {
		wp_nonce_field( 'wpbot_command', '_wpbot_command' );

		$meta = get_post_meta( $post->ID, '_wpbot_command', true );

		printf(
			'<p><label for="wpbot_command_trigger"><strong>%s</strong></label></p><input type="text" name="wpbot_command_trigger" id="wpbot_command_trigger" value="%s" style="width: 100%%;">',
			esc_html__( 'Command', 'wpbot-commands' ),
			esc_attr( ( isset( $meta['trigger'] ) ? $meta['trigger'] : '' ) )
		);

		printf(
			'<p><label for="wpbot_command_response"><strong>%s</strong></label></p><input type="text" name="wpbot_command_response" id="wpbot_command_response" value="%s" style="width: 100%%;">',
			esc_html__( 'Response', 'wpbot-commands' ),
			esc_attr( ( isset( $meta['response'] ) ? $meta['response'] : '' ) )
		);
	}

	function save_meta_boxes( $post_id ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $post_id;
		}
		if ( ! wp_verify_nonce( $_POST['_wpbot_command'], 'wpbot_command' ) ) {
			return $post_id;
		}

		$meta = array(
			'trigger'  => $_POST['wpbot_command_trigger'],
			'response' => $_POST['wpbot_command_response'],
			'uri'      => get_the_permalink( $post_id )
		);

		update_post_meta( $post_id, '_wpbot_command', $meta );
	}
}
new WPBot_Commands();