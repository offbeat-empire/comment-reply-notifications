<?php
/*
Plugin Name: Unified Comment Notifications
Description: Allow site visitors to follow comments and manage their follows. 
Version: 1.0
License: GPL
Author: Jennifer M. Dodd
Author URI: http://uncommoncontent.com/
Text Domain: unified-comment-notifications 
Domain Path: /languages/

================================================================================

Copyright 2013 Jennifer M. Dodd

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.
	
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


if ( !defined( 'ABSPATH' ) ) exit;


if ( !class_exists( 'UCC_Unified_Comment_Notifications' ) ) {
class UCC_Unified_Comment_Notifications {
	private static $instance;
	public static $version;
	public static $plugin_dir;
	public static $plugin_url;
	public static $options;

	public function __construct() {
		self::$instance = $this;
		$this->version = '2013010703';
		$this->version = time();
		
		// Useful pathinfo
		$this->plugin_dir = plugin_dir_path( __FILE__ );
		$this->plugin_url = plugins_url( __FILE__ );

		// Languages
		load_plugin_textdomain( 'unified-comment-notifications', false, basename( dirname( __FILE__ ) ) . '/languages' );

		// Default settings
		/* @todo
		$options = get_option( '_ucc_ucn_options' );
		*/
		$options = false;
		if ( !$options ) {
			$options = array(
				'allow_all'      => false,
				'auto_add'       => false, 
				'add_before'	 => false,
				'comment_form'   => true,
				'always_checked' => true,
				'post_types'     => array( 'post' )
			);
			/* @todo
			update_option( '_ucc_ucn_options', $options );
			*/
		}
		$options = apply_filters( 'ucc_ucn_options', $options );
		$this->options = $options;

		$this->plugin_dir = plugin_dir_path( __FILE__ );
		$this->plugin_url = plugin_dir_url( __FILE__ );

		/* @todo
		// Admin-side plugin settings
		if ( is_admin() )
			add_action( 'admin_init', array( $this, 'register_admin_settings' ), 15 );
		*/

		// User-side form
		if ( $options['auto_add'] )
			add_action( 'the_content', array( $this, 'auto_add' ) );

		// Comment checkbox form
		if ( $options['comment_form'] )
			add_action( 'comment_form', array( $this, 'generate_checkbox' ) );

		// Regular form callback
		add_action( 'wp', array( $this, 'action_handler' ) );
		add_action( 'comment_post', array( $this, 'checkbox_handler' ) );

		// Send notifications
		add_action( 'comment_post', array( $this, 'notify_subscribers' ), 10, 2 );
	} // __construct

	static function this() {
		return self::$instance;
	}

	public function action_handler() {

		// Check for our form
		if ( !isset( $_REQUEST['ucc_ucn_acn'] ) )
			return;

		// Define local variables
		$user_id = $post_id = 0;
		$action  = '';
		$errors  = array();

		/** User Details **********************************************/
		// Is logged in
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$user_id      = get_current_user_id(); 

		// Not allowed 
		} else {
			$errors[] = __( 'You do not have permission to do that.', 'unified-comment-notifications' );
		}

		/** Post ID ***************************************************/

		if ( isset( $_REQUEST['ucc_ucn_pid'] ) ) {
			$post_id = (int) $_REQUEST['ucc_ucn_pid'];
		}

		/** Action ****************************************************/

		if ( isset( $_REQUEST['ucc_ucn_acn'] ) ) {

			if ( !in_array( $_REQUEST['ucc_ucn_acn'], array(
				'subscribe',      // AJAX/link subscribe
				'unsubscribe',    // AJAX/link unsubscribe
				'unsubscribe_all' // AJAX/link unsubscribe all
			) ) )
				$errors[] = __( 'Invalid action specified.', 'unified-comment-notifications' ); 
			else
				$action = $_REQUEST['ucc_ucn_acn'];

		// Required
		} else {
			$errors[] = __( 'No action specified.', 'unified-comment-notifications' );
		}

		/** Key *******************************************************/
/*
		if ( isset( $_REQUEST['ucc_ucn_key'] ) ) {
			$key = $_REQUEST['ucc_ucn_key'];

			if ( !$this->check_key( $user_id, $key ) )
				$errors[] = __( 'Invalid key.', 'unified-comment-notifications' );
		} else {
			$errors[] = __( 'No key specified.', 'unified-comment-notifications' );
		}
*/
		/** Required **************************************************/

		if ( 'unsubscribe_all' != $action && empty( $post_id ) )
			$errors[] = __( 'No post id specified.', 'unified-comment-notifications' );

		/** No Errors *************************************************/

		if ( empty( $errors ) ) {

			// Process action
			switch( $action ) {
				case 'subscribe':
					$this->subscribe( $user_id, $post_id );
					break;

				case 'unsubscribe':
					$this->unsubscribe( $user_id, $post_id );
					break;

				case 'unsubscribe_all':
					$this->unsubscribe_all( $user_id );
					break;
			}
		}
	}

	public function checkbox_handler() {

		// Check for our form
		if ( !isset( $_REQUEST['ucc_ucn_checkbox'] ) )
			return;

		// Define local variables
		$user_id = $post_id = 0;
		$action  = '';
		$errors  = array();

		/** User Details **********************************************/
		// Is logged in
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$user_id      = get_current_user_id();

		// Not allowed
		} else {
			$errors[] = __( 'You do not have permission to do that.', 'unified-comment-notifications' );
		}

		/** Post ID ***************************************************/

		if ( isset( $_REQUEST['ucc_ucn_pid'] ) ) {
			$post_id = (int) $_REQUEST['ucc_ucn_pid'];
		} else {
			$errors[] = __( 'No post id specified.', 'unified-comment-notifications' );
		}

		/** Action ****************************************************/

		if ( isset( $_REQUEST['ucc_ucn_acn'] ) && 'subscribe' == $_REQUEST['ucc_ucn_acn'] ) {
			$action = 'subscribe';
		} else {
			$action = 'unsubscribe';
		}

		/** No Errors *************************************************/

		if ( empty( $errors ) ) {

			// Process action
			switch( $action ) {
				case 'subscribe':
					$this->subscribe( $user_id, $post_id );
					break;

				case 'unsubscribe':
					$this->unsubscribe( $user_id, $post_id );
					break;
			}
		}
	}

	public function notify_subscribers( $comment_id, $comment_approved ) {

		// Get comment
		$comment = get_comment( $comment_id );
		if ( !$comment )
			return;

		// Check comment type
		if ( '' != $comment->comment_type )
			return;

		// Check comment approved
		if ( 1 != $comment_approved )
			return;

		// Get post
		$post = get_post( $comment->comment_post_ID );
		if ( !$post )
			return;

		// Check post status and privacy
		if ( !$this->is_public( $post->ID ) )
			return;

		// Get subscribers
		$subscribers = $this->get_subscribers( $post->ID ); 

		foreach ( $subscribers as $subscriber_id ) {

			// Don't email post authors
			if ( $post->post_author == $subscriber_id ) {
				// Do nothing.
			} elseif ( $comment->user_id == $subscriber_id ) {
				// Do nothing.
			} else {
				// Get userdata
				$user = get_userdata( $subscriber_id );

				// Email variables
				$title		= html_entity_decode(get_the_title( $post->ID ));
				$permalink	    = get_permalink( $post->ID );
				$comment_link	 = get_comment_link( $comment->comment_ID );
				$unsubscribe_link     = $this->unsubscribe_link( $user->ID, $post->ID );
				$unsubscribe_all_link = $this->unsubscribe_all_link( $user->ID, $post->ID );
	
				// Mail parameters
				$to      = $user->user_email;
				$subject = sprintf( __( "New comment posted on '%s'", 'unified-comment-notifications' ),
					$title
				);
				$message = sprintf( __( "<p>A new comment has been posted on <a href='%s'>%s</a>.</p>", 'unified-comment-notifications' ),
					$permalink,
					$title
				);
				$message .= sprintf( __( "<p>Author: %s<br />Comment:</p>%s", 'unified-comment-notifications' ),
					$comment->comment_author,
					apply_filters( 'comment_text', get_comment_text( $comment->comment_ID ) )
				);
				$message .= sprintf( __( "<p>See all comments on this post:<br />%s</p>", 'unified-comment-notifications' ),
					$permalink . '#comments'
				);
				$message .= sprintf( __( "<p><a href='%s'>Unsubscribe</a> from comment notifications on <a href='%s'>%s</a>.<br />", 'unified-comment-notifications' ),
					$unsubscribe_link,
					$permalink,
					$title
				);
				$message .= sprintf( __( "<a href='%s'>Unsubscribe</a> from <strong>all</strong> comment notifications.</p>", 'unified-comment-notifications' ),
					$unsubscribe_all_link
				);
	
				/* @todo
				// Message headers
				*/
	
				add_filter( 'wp_mail_content_type', array( $this, 'wp_mail_content_type' ) );
				wp_mail( $to, $subject, $message );
				remove_filter( 'wp_mail_content_type', array( $this, 'wp_mail_content_type' ) );
			}
		}
	}

	public function is_public( $post_id ) {
		$post = get_post( $post_id );
		return (bool) ( 'publish' == get_post_status( $post_id ) && '' == $post->post_password );
	}

	public function is_subscribed( $user_id, $post_id ) {
		$subscriptions = $this->get_subscriptions( $user_id );
		return (bool) in_array( $post_id, $subscriptions );
	}

	public function get_subscriptions( $user_id ) {
		$subscriptions = get_user_meta( $user_id, '_ucc_ucn_subscriptions', true );
		if ( !$subscriptions )
			$subscriptions = array();
		return $subscriptions;
	}

	public function get_subscribers( $post_id ) {
		$subscribers = get_post_meta( $post_id, '_ucc_ucn_subscribers', true );
		if ( !$subscribers )
			$subscribers = array();
		return $subscribers;
	}

	public function subscribe( $user_id, $post_id ) {

		// Update subscribers
		$subscribers = $this->get_subscribers( $post_id );

		if ( !in_array( $user_id, $subscribers ) ) {
			$subscribers[] = $user_id;
			update_post_meta( $post_id, '_ucc_ucn_subscribers', $subscribers );
		}

		// Update subscriptions
		$subscriptions = $this->get_subscriptions( $user_id );
		if ( !in_array( $post_id, $subscriptions ) ) {
			$subscriptions[] = $post_id;
			update_user_meta( $user_id, '_ucc_ucn_subscriptions', $subscriptions );	
		}
	}

	public function unsubscribe( $user_id, $post_id ) {

		// Update subscribers
		$subscribers = $this->get_subscribers( $post_id );
		if ( ( $key = array_search( $user_id, $subscribers ) ) !== false ) {
			unset( $subscribers[$key] );
			update_post_meta( $post_id, '_ucc_ucn_subscribers', $subscribers );
		}

		// Update subscriptions
		$subscriptions = $this->get_subscriptions( $user_id );
		if ( ( $key = array_search( $post_id, $subscriptions ) ) !== false ) {
			unset( $subscriptions[$key] );
			update_user_meta( $user_id, '_ucc_ucn_subscriptions', $subscriptions );
		}
	}

	public function unsubscribe_all( $user_id ) {

		// Get subscriptions
		$subscriptions = $this->get_subscriptions( $user_id );
		foreach ( $subscriptions as $post_id ) {
			$subscribers = $this->get_subscribers( $post_id );
			if ( ( $key = array_search( $user_id, $subscribers ) ) !== false ) {
				unset( $subscribers[$key] );
				update_post_meta( $post_id, '_ucc_ucn_subscribers', $subscribers );
			}
		}

		// Remove user subscriptions
		update_user_meta( $user_id, '_ucc_ucn_subscriptions', array() );
	}

	public function get_key( $user_id ) {
		$key = get_user_meta( $user_id, '_ucc_ucn_key', true );

		// Generate if it doesn't exist
		if ( empty( $key ) ) {
			$key = uniqid( '', true );
			update_user_meta( $user_id, '_ucc_ucn_key', $key );
		}

		return $key;
	}

	public function check_key( $user_id, $_key ) {
		return (bool) ( $_key == $this->get_key( $user_id ) );
	}

	public function subscribe_link( $user_id, $post_id ) {
		$key  = $this->get_key( $user_id );
		$link = add_query_arg( array(
			'ucc_ucn_uid' => $user_id,
			'ucc_ucn_pid' => $post_id,
			'ucc_ucn_acn' => 'subscribe',
			'ucc_ucn_key' => $key
		), get_permalink( $post_id ) );
		return $link;
	}

	public function unsubscribe_link( $user_id, $post_id ) {
		$key  = $this->get_key( $user_id ); 
		$link = add_query_arg( array(
			'ucc_ucn_uid' => $user_id,
			'ucc_ucn_pid' => $post_id,
			'ucc_ucn_acn' => 'unsubscribe',
			'ucc_ucn_key' => $key
		), get_permalink( $post_id ) );
		return $link;
	}

	public function unsubscribe_all_link( $user_id, $post_id ) {
		$key  = $this->get_key( $user_id );
		$link = add_query_arg( array(
			'ucc_ucn_uid' => $user_id,
			'ucc_ucn_acn' => 'unsubscribe_all',
			'ucc_ucn_key' => $key
		), get_permalink( $post_id ) );
		return $link;
	}

	public function wp_mail_content_type( $content_type ) {
		return 'text/html';
	}

	public function generate_form( $post_id = 0 ) {
		global $post;

		if ( !$post_id )
			$post_id = $post->ID;

		if ( !$post_id )
			return;

		if ( !$this->is_public( $post_id ) )
			return;

		$user_id = get_current_user_id();
		$_post    = get_post( $post_id );

		// Author is viewing
//		if ( $_post->post_author == $user_id )
//			return;

		// Unsubscribe link
		if ( $this->is_subscribed( $user_id, $post_id ) ) {
			$form = sprintf( __( "You are subscribed to comments on this post | <a href='%s'>Unsubscribe</a>", 'unified-comment-notifications' ),
				$this->unsubscribe_link( $user_id, $post_id )
			);

		// Subscribe link
		} else {
			$form = sprintf( __( "<a href='%s'>Subscribe</a>", 'unified-comment-notifications' ),
				$this->subscribe_link( get_current_user_id(), $post_id )
			);
		}

		return $form;
	}

	public function generate_checkbox( $post_id = 0, $echo = true ) {
		global $post;
		$options = $this->options;

		if ( !$post_id )
			$post_id = $post->ID;

		if ( !$post_id )
			return;

		if ( !$this->is_public( $post_id ) )
			return;

		$user_id = get_current_user_id();
		$_post   = get_post( $post_id );

		// Author is viewing
//		if ( $_post->post_author == $user_id )
//			return;

		$checked = apply_filters( 'ucc_ucn_checkbox_is_checked', $this->is_subscribed( $user_id, $post_id ) );
		if ( $options['always_checked'] )
			$checked = true;

		// Checkbox form
		$form  = "<input type='hidden' name='ucc_ucn_pid' value='{$post_id}' />";
		$form .= "<input type='hidden' name='ucc_ucn_checkbox' value='ucc_ucn_checkbox' />";
		$form .= "<input type='checkbox' name='ucc_ucn_acn' value='subscribe' " . checked( $checked, true, false ) . " />";
		$form .= __( "Subscribe to comments on this post", 'unified-comment-notifications' );

		if ( $echo )
			echo $form;
		else
			return $form;
	}

	// Prepend or append copyedit submission form to the_content
	public function auto_add( $content ) {
		global $post;
		$options = $this->options;

		// Post exists
		if ( $post && is_singular( $options['post_types'] ) ) {
			$post_id = $post->ID;
			$post_type = $post->post_type;

			// Only deal with some post types
			if ( in_array( $post_type, $options['post_types'] ) ) {
				$form = $this->generate_form( $post_id );

				// Return the content and form
				if ( $this->options['add_before'] )
					return $form . $content;
				else
					return $content . $form;
			}
		}

		return $content;
	}
} }


if ( !function_exists( 'ucc_ucn_subscribe_form' ) ) {
function ucc_ucn_subscribe_form( $post_id = 0, $echo = true ) {
	global $post;

	// Validate
	if ( empty( $post_id ) )
		$post_id = $post->ID;

	$instance = new UCC_Unified_Comment_Notifications;
	$form = $instance->generate_form( $post_id );

	// Output
	if ( $echo )
		echo $form;
	else
		return $form;
} }

if ( !function_exists( 'ucc_ucn_subscribe_checkbox' ) ) {
function ucc_ucn_subscribe_checkbox( $post_id = 0, $echo = true ) {
	global $post;

	// Validate
	if ( empty( $post_id ) )
		$post_id = $post->ID;

	$instance = new UCC_Unified_Comment_Notifications;
	$checkbox = $instance->generate_checkbox( $post_id );

	// Output
	if ( $echo )
		echo $checkbox;
	else
		return $checkbox;
} }


// Load plugin
if ( !function_exists( 'ucc_ucn_loader' ) ) {
function ucc_ucn_loader() {
	new UCC_Unified_Comment_Notifications;
} }
add_action( 'init', 'ucc_ucn_loader' );
