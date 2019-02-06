<?php

/**
 * Send Tracks events on behalf of a user.
 *
 * @class   WC_Tracks_Client
 * @package WooCommerce/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Tracks_Client.
 */
class WC_Tracks_Client {
	const PIXEL           = 'https://pixel.wp.com/t.gif';
	const BROWSER_TYPE    = 'php-agent';
	const USER_AGENT_SLUG = 'tracks-client';

	/**
	 * Record a Tracks event
	 *
	 * @param  array $event Array of event properties
	 * @return bool|WP_Error         True on success, WP_Error on failure.
	 */
	public static function record_event( $event ) {
		if ( ! $event instanceof WC_Tracks_Event ) {
			$event = new WC_Tracks_Event( $event );
		}
		if ( is_wp_error( $event ) ) {
			return $event;
		}

		$pixel = $event->build_pixel_url( $event );

		if ( ! $pixel ) {
			return new WP_Error( 'invalid_pixel', 'cannot generate tracks pixel for given input', 400 );
		}

		return self::record_pixel( $pixel );
	}

	/**
	 * Synchronously request the pixel.
	 *
	 * @param string $pixel pixel url and query string.
	 * @return bool|WP_Error         True on success, WP_Error on failure.
	 */
	public static function record_pixel( $pixel ) {
		// Add the Request Timestamp and URL terminator just before the HTTP request.
		$pixel .= '&_rt=' . self::build_timestamp() . '&_=_';

		$response = wp_remote_get(
			$pixel,
			array(
				'blocking'    => true, // The default, but being explicit here.
				'timeout'     => 1,
				'redirection' => 2,
				'httpversion' => '1.1',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = isset( $response['response']['code'] ) ? $response['response']['code'] : 0;

		if ( 200 !== $code ) {
			return new WP_Error( 'request_failed', 'Tracks pixel request failed', $code );
		}

		return true;
	}

	/**
	 * Create a timestap representing milliseconds since 1970-01-01
	 *
	 * @return string A string representing a timestamp.
	 */
	public static function build_timestamp() {
		$ts = round( microtime( true ) * 1000 );
		return number_format( $ts, 0, '', '' );
	}

	/**
	 * Grabs the user's anon id from cookies, or generates and sets a new one
	 *
	 * @return string An anon id for the user
	 */
	public static function get_anon_id() {
		static $anon_id = null;

		if ( ! isset( $anon_id ) ) {

			// Did the browser send us a cookie?
			if ( isset( $_COOKIE['tk_ai'] ) && preg_match( '#^[A-Za-z0-9+/=]{24}$#', $_COOKIE['tk_ai'] ) ) {
				$anon_id = $_COOKIE['tk_ai'];
			} else {

				$binary = '';

				// Generate a new anonId and try to save it in the browser's cookies
				// Note that base64-encoding an 18 character string generates a 24-character anon id.
				for ( $i = 0; $i < 18; ++$i ) {
					$binary .= chr( wp_rand( 0, 255 ) );
				}

				$anon_id = 'jetpack:' . base64_encode( $binary );

				if ( ! headers_sent()
					&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
					&& ! ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
				) {
					setcookie( 'tk_ai', $anon_id );
				}
			}
		}

		return $anon_id;
	}
}
