<?php
/**
 * Nosara Tracks for Woo
 */

require_once dirname(__FILE__) . '/class-wc-tracks.php';
require_once dirname(__FILE__) . '/class-wc-tracks-event.php';
require_once dirname(__FILE__) . '/class-wc-tracks-client.php';

function woocommerce_tracks_product_updated( $product_id, $post ) {
	if ( $post->post_type !== 'product' ) {
		return;
	};
	$properties = array(
		'product_id' => $product_id,
	);

	WC_Tracks::record_event( 'update_product', $properties );
}

function track_woo_usage() {
	if ( ! class_exists( 'WC_Tracks' ) ) {
		return;
	}
	add_action( 'edit_post', 'woocommerce_tracks_product_updated', 10, 2 );
}

add_action( 'init',  'track_woo_usage' );
