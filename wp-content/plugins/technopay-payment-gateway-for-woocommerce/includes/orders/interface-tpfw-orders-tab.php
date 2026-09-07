<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TPFW_Orders_Tab {

	public function get_slug();

	public function get_label();

	public function get_date_column_label();

	public function get_refund_column_label();

	public function get_empty_message();

	public function get_status_filter_key();

	public function fetch( TPFW_Technopay_Api_Client $api_client, array $query );

	public function get_rows( array $results, $row_offset );
}
