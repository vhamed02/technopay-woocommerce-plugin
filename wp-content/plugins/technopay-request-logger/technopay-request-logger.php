<?php
/**
 * Plugin Name: TechnoPay Request Logger
 * Description: Logs every outgoing HTTP request made by the TechnoPay plugin as a cURL command to wp-content/technopay-logs/.
 * Version: 1.0.0
 * Author: TechnoPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TPFW_Request_Logger {

	const LOG_DIR = WP_CONTENT_DIR . '/technopay-logs';

	public function __construct() {
		add_action( 'http_api_debug', array( $this, 'log_request' ), 10, 5 );
	}

	public function log_request( $response, $context, $transport, $args, $url ) {
		// Only log requests to TechnoPay API endpoints.
		if ( false === strpos( $url, 'technopay.ir' ) && false === strpos( $url, 'tgms.ir' ) ) {
			return;
		}

		$this->ensure_log_dir();

		$method  = strtoupper( isset( $args['method'] ) ? $args['method'] : 'GET' );
		$headers = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
		$body    = isset( $args['body'] ) ? $args['body'] : '';

		// Build cURL command.
		$curl = "curl --location --request {$method} \\\n";
		$curl .= "  '" . $url . "'";

		foreach ( $headers as $key => $value ) {
			$curl .= " \\\n  --header '" . addslashes( $key ) . ": " . addslashes( $value ) . "'";
		}

		if ( '' !== $body ) {
			$curl .= " \\\n  --data '" . addslashes( $body ) . "'";
		}

		// Build response summary.
		$status_code    = '';
		$response_body  = '';

		if ( is_wp_error( $response ) ) {
			$response_body = 'WP_Error: ' . $response->get_error_message();
		} else {
			$status_code   = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );
		}

		$timestamp = current_time( 'Y-m-d H:i:s' );
		$separator = str_repeat( '-', 80 );

		$log  = $separator . "\n";
		$log .= "# [{$timestamp}]  {$method}  {$url}\n";
		$log .= $separator . "\n\n";
		$log .= "## Request (cURL)\n\n";
		$log .= $curl . "\n\n";
		$log .= "## Response" . ( '' !== $status_code ? " (HTTP {$status_code})" : '' ) . "\n\n";
		$log .= $response_body . "\n\n";

		$filename = self::LOG_DIR . '/' . current_time( 'Y-m-d' ) . '.log';

		file_put_contents( $filename, $log, FILE_APPEND | LOCK_EX );
	}

	private function ensure_log_dir() {
		if ( is_dir( self::LOG_DIR ) ) {
			return;
		}

		wp_mkdir_p( self::LOG_DIR );

		// Prevent direct browser access.
		file_put_contents( self::LOG_DIR . '/.htaccess', "Deny from all\n" );
		file_put_contents( self::LOG_DIR . '/index.php', "<?php // Silence is golden.\n" );
	}
}

new TPFW_Request_Logger();
