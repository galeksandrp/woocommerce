<?php
/**
 * PHP Tracks Client
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
require_once( dirname(__FILE__) . '/class.tracks-event.php' );
require_once( dirname(__FILE__) . '/class.tracks-client.php' );

/**
 * Procedurally build a Tracks Event Object.
 * @param $identity WP_user object
 * @param string $event_name The name of the event
 * @param array $properties Custom properties to send with the event
 * @return \Woo_Tracks_Event|\WP_Error
 */
function woo_tracks_build_event_obj( $user, $event_name, $properties = array() ) {

	$identity = woo_tracks_get_identity( $user->ID );

	$properties['user_lang'] = $user->get( 'WPLANG' );

	$blog_details = array(
		'blog_lang' => isset( $properties['blog_lang'] ) ? $properties['blog_lang'] : get_bloginfo( 'language' )
	);

	$timestamp = round( microtime( true ) * 1000 );
	$timestamp_string = is_string( $timestamp ) ? $timestamp : number_format( $timestamp, 0, '', '' );

	return new Woo_Tracks_Event( array_merge( $blog_details, (array) $properties, $identity, array(
		'_en' => $event_name,
		'_ts' => $timestamp_string
	) ) );
}

/*
 * Get the identity to send to tracks.
 *
 * @param int $user_id The user id of the local user
 * @return array $identity
 */
function woo_tracks_get_identity( $user_id ) {

	// Meta is set, and user is still connected.  Use WPCOM ID
	$wpcom_id = get_user_meta( $user_id, 'jetpack_tracks_wpcom_id', true );
	if ( $wpcom_id && Jetpack::is_user_connected( $user_id ) ) {
		return array(
			'_ut' => 'wpcom:user_id',
			'_ui' => $wpcom_id
		);
	}

	// User is connected, but no meta is set yet.  Use WPCOM ID and set meta.
	if ( Jetpack::is_user_connected( $user_id ) ) {
		$wpcom_user_data = Jetpack::get_connected_user_data( $user_id );
		add_user_meta( $user_id, 'jetpack_tracks_wpcom_id', $wpcom_user_data['ID'], true );

		return array(
			'_ut' => 'wpcom:user_id',
			'_ui' => $wpcom_user_data['ID']
		);
	}

	// User isn't linked at all.  Fall back to anonymous ID.
	$anon_id = get_user_meta( $user_id, 'jetpack_tracks_anon_id', true );
	if ( ! $anon_id ) {
		$anon_id = Woo_Tracks_Client::get_anon_id();
		add_user_meta( $user_id, 'jetpack_tracks_anon_id', $anon_id, false );
	}

	if ( ! isset( $_COOKIE[ 'tk_ai' ] ) && ! headers_sent() ) {
		setcookie( 'tk_ai', $anon_id );
	}

	return array(
		'_ut' => 'anon',
		'_ui' => $anon_id
	);

}

/**
 * Record an event in Tracks - this is the preferred way to record events from PHP.
 *
 * @param string $event_name The name of the event
 * @param array $properties Custom properties to send with the event
 * @return bool true for success | \WP_Error if the event pixel could not be fired
 */
function woo_tracks_record_event( $event_name, $properties = array() ) {

	$user = wp_get_current_user();

	$data['_via_ua']  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
	$data['_via_ip']  = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
	$data['_lg']      = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
	$data['blog_url'] = get_option( 'siteurl' );
	$data['blog_id']  = Jetpack_Options::get_option( 'id' );

	// We don't want to track user events during unit tests/CI runs.
	if ( $user instanceof WP_User && 'wptests_capabilities' === $user->cap_key ) {
		return false;
	}

	$event_obj = woo_tracks_build_event_obj( $user, $event_name, array_merge( $properties, $data ) );

	if ( is_wp_error( $event_obj->error ) ) {
		return $event_obj->error;
	}

	return $event_obj->record();
}
