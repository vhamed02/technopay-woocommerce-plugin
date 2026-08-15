<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TPFW_Refunds_Mock {

	public function __construct() {
		add_filter( 'pre_http_request', array( $this, 'intercept' ), 10, 3 );
	}

	public function intercept( $preempt, $args, $url ) {
		if ( false !== $preempt ) {
			return $preempt;
		}

		$path   = untrailingslashit( wp_parse_url( $url, PHP_URL_PATH ) );
		$method = strtoupper( isset( $args['method'] ) ? $args['method'] : 'GET' );

		if ( 'POST' === $method && '/payment/refund/cancel' === $path ) {
			return $this->cancel_refund( $args );
		}

		if ( 'POST' === $method && '/payment/refund' === $path ) {
			return $this->create_refund( $args );
		}

		if ( 'GET' !== $method || '/payment/refunds' !== $path ) {
			return $preempt;
		}

		$query = array();
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		$filters  = isset( $query['filters'] ) && is_array( $query['filters'] ) ? $query['filters'] : array();
		$per_page = isset( $query['per_page'] ) ? max( 1, min( 50, absint( $query['per_page'] ) ) ) : 15;
		$offset   = $this->get_offset( isset( $query['cursor'] ) ? $query['cursor'] : '' );
		$results  = array_values(
			array_filter(
				$this->get_results(),
				function ( $result ) use ( $filters ) {
					return $this->matches_result( $result, $filters );
				}
			)
		);
		$total       = count( $results );
		$page_results = array_slice( $results, $offset, $per_page );
		$next_offset = $offset + $per_page;
		$prev_offset = max( 0, $offset - $per_page );

		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode(
				array(
					'succeed' => true,
					'message' => 'لیست استردادها با موفقیت دریافت شد.',
					'results' => $page_results,
					'metas'   => array(
						'path'        => '/payment/refunds',
						'per_page'    => $per_page,
						'next_cursor' => $next_offset < $total ? 'mock-' . $next_offset : null,
						'prev_cursor' => $offset > 0 ? 'mock-' . $prev_offset : null,
					),
				)
			),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	private function cancel_refund( $args ) {
		$payload = isset( $args['body'] ) ? json_decode( (string) $args['body'], true ) : null;

		if ( ! is_array( $payload ) || empty( $payload['track_number'] ) ) {
			return $this->get_response(
				422,
				array(
					'message' => 'اطلاعات لغو درخواست استرداد معتبر نیست.',
					'errors'  => array(),
				)
			);
		}

		$track_number = (string) $payload['track_number'];
		$ticket       = null;

		foreach ( $this->get_results() as $result ) {
			if ( $result['track_number'] === $track_number ) {
				$ticket = $result;
				break;
			}
		}

		if ( null === $ticket || 'pending' !== $ticket['refund_status'] ) {
			return $this->get_response(
				422,
				array(
					'message' => 'لغو درخواست استرداد برای این پرداخت امکان‌پذیر نیست.',
					'errors'  => array(),
				)
			);
		}

		$requests                  = get_option( 'tpfw_refunds_mock_requests', array() );
		$requests[ $track_number ] = array(
			'requested_amount' => (int) $ticket['requested_amount'],
			'status'           => 'canceled',
		);

		update_option( 'tpfw_refunds_mock_requests', $requests, false );

		return $this->get_response(
			200,
			array(
				'succeed' => true,
				'message' => 'درخواست استرداد با موفقیت لغو شد.',
				'resCode' => '0',
				'results' => array(
					'track_number'     => $track_number,
					'status'           => 'canceled',
					'requested_amount' => (int) $ticket['requested_amount'],
				),
			)
		);
	}

	private function create_refund( $args ) {
		$payload = isset( $args['body'] ) ? json_decode( (string) $args['body'], true ) : null;

		if ( ! is_array( $payload ) || empty( $payload['track_number'] ) || empty( $payload['requested_amount'] ) ) {
			return $this->get_response(
				422,
				array(
					'message' => 'اطلاعات درخواست استرداد معتبر نیست.',
					'errors'  => array(),
				)
			);
		}

		$track_number = (string) $payload['track_number'];
		$amount       = (int) $payload['requested_amount'];
		$ticket       = null;

		foreach ( $this->get_results() as $result ) {
			if ( $result['track_number'] === $track_number ) {
				$ticket = $result;
				break;
			}
		}

		if ( null === $ticket || $amount < 1 || $amount > (int) $ticket['ticket_amount'] || in_array( $ticket['ticket_status'], array( 'settled', 'completed', 'finalized' ), true ) || ! in_array( $ticket['refund_status'], array( 'none', 'canceled', 'cancelled', 'rejected', 'failed' ), true ) ) {
			return $this->get_response(
				422,
				array(
					'message' => 'ثبت درخواست استرداد برای این پرداخت امکان‌پذیر نیست.',
					'errors'  => array(),
				)
			);
		}

		$requests                  = get_option( 'tpfw_refunds_mock_requests', array() );
		$requests[ $track_number ] = array(
			'requested_amount' => $amount,
			'status'           => 'pending',
		);

		update_option( 'tpfw_refunds_mock_requests', $requests, false );

		return $this->get_response(
			200,
			array(
				'succeed' => true,
				'message' => 'درخواست استرداد با موفقیت ثبت شد.',
				'resCode' => '0',
				'results' => array(
					'track_number'      => $track_number,
					'refund_request_id' => count( $requests ),
					'status'            => 'pending',
					'requested_amount'  => $amount,
				),
			)
		);
	}

	private function get_response( $status_code, $body ) {
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => $status_code,
				'message' => 200 === $status_code ? 'OK' : 'Unprocessable Content',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	private function get_offset( $cursor ) {
		return preg_match( '/^mock-(\d+)$/', (string) $cursor, $matches ) ? absint( $matches[1] ) : 0;
	}

	private function matches_result( $result, $filters ) {
		if ( isset( $filters['track_number'] ) && '' !== $filters['track_number'] && $result['track_number'] !== (string) $filters['track_number'] ) {
			return false;
		}

		if ( isset( $filters['customer_mobile'] ) && '' !== $filters['customer_mobile'] && false === strpos( $result['customer_mobile'], (string) $filters['customer_mobile'] ) ) {
			return false;
		}

		if ( isset( $filters['ticket_amount'] ) && '' !== (string) $filters['ticket_amount'] && (int) $result['ticket_amount'] !== (int) $filters['ticket_amount'] ) {
			return false;
		}

		if ( isset( $filters['status'] ) && '' !== $filters['status'] && $result['refund_status'] !== $filters['status'] ) {
			return false;
		}

		if ( isset( $filters['paid_at'] ) && ! $this->matches_date( $result['paid_at'], $filters['paid_at'] ) ) {
			return false;
		}

		return true;
	}

	private function matches_date( $paid_at, $filter ) {
		$date = substr( $paid_at, 0, 10 );

		if ( is_array( $filter ) ) {
			$from = isset( $filter[0] ) ? (string) $filter[0] : '';
			$to   = isset( $filter[1] ) ? (string) $filter[1] : '';

			return ( '' === $from || $date >= $from ) && ( '' === $to || $date <= $to );
		}

		return '' === (string) $filter || $date === (string) $filter;
	}

	private function get_results() {
		$names    = array( 'سپهر کیانی', 'نیلوفر رحیمی', 'آرش نادری', 'مهسا احمدی', 'میلاد صادقی' );
		$statuses = array( 'none', 'pending', 'approved', 'canceled', 'rejected' );
		$reasons  = array( 'returned-order', 'customer-cancellation', 'payment-error', 'other' );
		$results  = array();
		$now      = time();

		for ( $index = 0; $index < 24; $index++ ) {
			$status           = $statuses[ $index % count( $statuses ) ];
			$ticket_amount    = 1500000 + ( $index % 6 ) * 750000;
			$requested_amount = 'none' === $status ? 0 : ( 'approved' === $status && 0 === $index % 2 ? $ticket_amount : (int) floor( $ticket_amount / 2 ) );
			$paid_timestamp   = $now - $index * DAY_IN_SECONDS;
			$reason           = 'approved' === $status || 'pending' === $status || 'canceled' === $status || 'rejected' === $status
				? $reasons[ $index % count( $reasons ) ]
				: '';
			$custom_reason    = 'other' === $reason ? 'توضیحات تکمیلی برای دلیل استرداد این سفارش' : '';

			$results[] = array(
				'customer_full_name'  => $names[ $index % count( $names ) ],
				'customer_mobile'     => '0912' . str_pad( (string) ( 1000000 + $index ), 7, '0', STR_PAD_LEFT ),
				'track_number'        => '62194545' . str_pad( (string) ( 1000 + $index ), 4, '0', STR_PAD_LEFT ),
				'ticket_amount'       => (string) $ticket_amount,
				'requested_amount'    => (string) $requested_amount,
				'refund_status'       => $status,
				'ticket_status'       => 0 === $index % 7 ? 'settled' : 'paid',
				'paid_at'             => gmdate( 'c', $paid_timestamp ),
				'created_at'          => gmdate( 'c', $paid_timestamp + HOUR_IN_SECONDS ),
				'refund_reason'       => $reason,
				'custom_refund_reason' => $custom_reason,
			);
		}

		$requests = get_option( 'tpfw_refunds_mock_requests', array() );

		foreach ( $results as &$result ) {
			if ( ! isset( $requests[ $result['track_number'] ] ) ) {
				continue;
			}

			$req = $requests[ $result['track_number'] ];

			$result['requested_amount']    = (string) $req['requested_amount'];
			$result['refund_status']       = (string) $req['status'];
			$result['created_at']          = gmdate( 'c' );
			$result['refund_reason']       = isset( $req['refund_reason'] ) ? $req['refund_reason'] : $result['refund_reason'];
			$result['custom_refund_reason'] = isset( $req['custom_refund_reason'] ) ? $req['custom_refund_reason'] : $result['custom_refund_reason'];
		}

		unset( $result );

		return $results;
	}
}

new TPFW_Refunds_Mock();
