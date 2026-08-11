<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TPFW_TechnoPay_Order_Query {

	private $filters;

	public function __construct( $filters ) {
		$this->filters = $filters;
	}

	public function get_results( $page, $per_page ) {
		$args = array(
			'type'           => 'shop_order',
			'payment_method' => 'technopay',
			'status'         => $this->get_statuses(),
			'limit'          => $per_page,
			'page'           => $page,
			'paginate'       => true,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'return'         => 'objects',
		);

		if ( $this->filters['customer_mobile'] !== '' ) {
			$args['billing_phone'] = $this->filters['customer_mobile'];
		}

		if ( $this->filters['amount'] !== '' ) {
			$args['total'] = $this->filters['amount'];
		}

		$date_created = $this->get_date_created_query();
		if ( $date_created !== '' ) {
			$args['date_created'] = $date_created;
		}

		return wc_get_orders( $args );
	}

	private function get_statuses() {
		if ( $this->filters['status'] !== '' ) {
			return array( 'wc-' . $this->filters['status'] );
		}

		$statuses = array_merge( wc_get_is_paid_statuses(), array( 'refunded' ) );
		return array_map(
			function ( $status ) {
				return 'wc-' . $status;
			},
			array_unique( $statuses )
		);
	}

	private function get_date_created_query() {
		$from = $this->create_timestamp( $this->filters['date_from'], '00:00:00' );
		$to   = $this->create_timestamp( $this->filters['date_to'], '23:59:59' );

		if ( $from && $to ) {
			return $from . '...' . $to;
		}

		if ( $from ) {
			return '>' . $from;
		}

		if ( $to ) {
			return '<' . $to;
		}

		return '';
	}

	private function create_timestamp( $date, $time ) {
		if ( $date === '' ) {
			return 0;
		}

		$date_time = DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i:s',
			$date . ' ' . $time,
			wp_timezone()
		);

		return $date_time ? $date_time->getTimestamp() : 0;
	}
}
