<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TPFW_Refund_Status {

	const PENDING   = array( 'pending', 'requested', 'waiting', 'in_progress', 'processing' );
	const COMPLETED = array( 'approved', 'completed', 'done', 'refunded', 'success', 'successful', 'partial_refunded' );
	const CANCELED  = array( 'canceled', 'cancelled' );
	const REJECTED  = array( 'failed', 'rejected' );

	public static function is_pending( $status_key ) {
		return in_array( $status_key, self::PENDING, true );
	}

	public static function is_completed( $status_key ) {
		return in_array( $status_key, self::COMPLETED, true );
	}

	public static function is_canceled( $status_key ) {
		return in_array( $status_key, self::CANCELED, true );
	}

	public static function is_rejected( $status_key ) {
		return in_array( $status_key, self::REJECTED, true );
	}
}
