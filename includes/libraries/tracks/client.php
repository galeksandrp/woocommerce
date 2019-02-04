<?php
/**
 * PHP Tracks Client
 *
 * @autounit nosara tracks-client
 * Example Usage:
 *
```php
	include( plugin_dir_path( __FILE__ ) . 'lib/tracks/client.php');
	$result = woo_tracks_record_event( $user, $event_name, $properties );

	if ( is_wp_error( $result ) ) {
		// Handle the error in your app
	}
```
 */

// Load the client classes
require_once dirname( __FILE__ ) . '/class-tracks-event.php';
require_once dirname( __FILE__ ) . '/class-tracks-client.php';

class Tracks {
	/*
	 * Get the identity to send to tracks.
	 *
	 * @param int $user_id The user id of the local user
	 * @return array $identity
	 */
	static function get_identity( $user_id ) {

		$has_jetpack = class_exists( 'Jetpack' );

		// Meta is set, and user is still connected.  Use WPCOM ID
		$wpcom_id = $has_jetpack && get_user_meta( $user_id, 'jetpack_tracks_wpcom_id', true );
		if ( $wpcom_id && Jetpack::is_user_connected( $user_id ) ) {
			return array(
				'_ut' => 'wpcom:user_id',
				'_ui' => $wpcom_id,
			);
		}

		// User is connected, but no meta is set yet.  Use WPCOM ID and set meta.
		if ( $has_jetpack && Jetpack::is_user_connected( $user_id ) ) {
			$wpcom_user_data = Jetpack::get_connected_user_data( $user_id );
			add_user_meta( $user_id, 'jetpack_tracks_wpcom_id', $wpcom_user_data['ID'], true );

			return array(
				'_ut' => 'wpcom:user_id',
				'_ui' => $wpcom_user_data['ID'],
			);
		}

		// User isn't linked at all.  Fall back to anonymous ID.
		$anon_id = get_user_meta( $user_id, 'jetpack_tracks_anon_id', true );
		if ( ! $anon_id ) {
			$anon_id = Tracks_Client::get_anon_id();
			add_user_meta( $user_id, 'jetpack_tracks_anon_id', $anon_id, false );
		}

		if ( ! isset( $_COOKIE['tk_ai'] ) && ! headers_sent() ) {
			setcookie( 'tk_ai', $anon_id );
		}

		return array(
			'_ut' => 'anon',
			'_ui' => $anon_id,
		);

	}

	static function get_blog_details() {
		return array(
			// @TODO: Add revenue/product info and url similar to wc-tracker
			'url'       => get_option( 'siteurl' ),
			'blog_lang' => get_bloginfo( 'language' ),
			'blog_id'   => ( class_exists( 'Jetpack' ) && Jetpack_Options::get_option( 'id' ) ) || null,
		);
	}

	static function get_server_details() {
		$data = array();

		$data['_via_ua'] = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$data['_via_ip'] = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		$data['_lg']     = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
		$data['_dr']     = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';

		$uri         = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$host        = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
		$data['_dl'] = $_SERVER['REQUEST_SCHEME'] . '://' . $host . $uri;

		return $data;
	}

	/**
	 * Record an event in Tracks - this is the preferred way to record events from PHP.
	 *
	 * @param string $event_name The name of the event
	 * @param array  $properties Custom properties to send with the event
	 * @return bool true for success | \WP_Error if the event pixel could not be fired
	 */
	static function record_event( $event_name, $properties = array() ) {
		$user = wp_get_current_user();

		// We don't want to track user events during unit tests/CI runs.
		if ( $user instanceof WP_User && 'wptests_capabilities' === $user->cap_key ) {
			return false;
		}

		$data = array(
			'_en' => $event_name,
			'_ts' => Tracks_Client::build_timestamp(),
		);

		$server_details = self::get_server_details();
		$identity       = self::get_identity( $user->ID );
		$blog_details   = self::get_blog_details();

		$event_obj = new Tracks_Event( array_merge( $data, $server_details, $identity, $blog_details, $properties ) );

		if ( is_wp_error( $event_obj->error ) ) {
			return $event_obj->error;
		}

		return $event_obj->record();
	}
}


