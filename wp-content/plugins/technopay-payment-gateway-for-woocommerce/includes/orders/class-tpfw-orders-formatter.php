<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TPFW_Orders_Formatter {

	public function get_scalar_value( $result, $key ) {
		return isset( $result[ $key ] ) && is_scalar( $result[ $key ] ) ? sanitize_text_field( (string) $result[ $key ] ) : '';
	}

	public function get_first_scalar_value( $result, array $keys ) {
		foreach ( $keys as $key ) {
			$value = $this->get_scalar_value( $result, $key );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	public function get_display_value( $value ) {
		return '' !== trim( $value ) ? $value : '—';
	}

	public function parse_amount( $amount ) {
		if ( null === $amount || ! is_scalar( $amount ) ) {
			return null;
		}

		$amount = str_replace( array( ',', '٬', ' ' ), '', $this->normalize_digits( (string) $amount ) );

		return is_numeric( $amount ) ? (float) $amount : null;
	}

	public function format_amount( $amount ) {
		return null === $amount ? '—' : number_format( $amount, 0, '.', ',' ) . ' ' . __( 'تومان', 'technopay-payment-gateway-for-woocommerce' );
	}

	public function format_optional_amount( $amount ) {
		return null !== $amount && $amount > 0 ? $this->format_amount( $amount ) : '—';
	}

	public function format_raw_amount( $amount ) {
		return null === $amount ? '' : (string) (int) $amount;
	}

	public function format_date( $value ) {
		if ( '' === $value ) {
			return '—';
		}

		try {
			$date = new DateTimeImmutable( $value );
		} catch ( Throwable $exception ) {
			return $this->normalize_digits( $value );
		}

		if ( class_exists( 'IntlDateFormatter' ) ) {
			try {
				$timezone  = $this->get_timezone()->getName();
				$timezone  = preg_match( '/^[+-]/', $timezone ) ? 'GMT' . $timezone : $timezone;
				$formatter = new IntlDateFormatter(
					'fa_IR@calendar=persian',
					IntlDateFormatter::NONE,
					IntlDateFormatter::NONE,
					$timezone,
					IntlDateFormatter::TRADITIONAL,
					'yyyy/MM/dd'
				);
				$formatted = $formatter->format( $date->getTimestamp() );

				if ( false !== $formatted ) {
					return $this->normalize_digits( $formatted );
				}
			} catch ( Throwable $exception ) {
				return $this->format_gregorian_date( $date );
			}
		}

		return $this->format_gregorian_date( $date );
	}

	public function parse_reasons( $reasons ) {
		if ( ! is_array( $reasons ) ) {
			return array();
		}

		$parsed = array();

		foreach ( $reasons as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$parsed[] = array(
				'code'        => $this->get_scalar_value( $item, 'code' ),
				'reason'      => $this->get_scalar_value( $item, 'reason' ),
				'description' => $this->get_scalar_value( $item, 'description' ),
			);
		}

		return $parsed;
	}

	public function normalize_status( $status ) {
		return sanitize_key( strtolower( str_replace( array( ' ', '-' ), '_', $status ) ) );
	}

	public function normalize_digits( $value ) {
		return strtr(
			(string) $value,
			array(
				'۰' => '0',
				'۱' => '1',
				'۲' => '2',
				'۳' => '3',
				'۴' => '4',
				'۵' => '5',
				'۶' => '6',
				'۷' => '7',
				'۸' => '8',
				'۹' => '9',
				'٠' => '0',
				'١' => '1',
				'٢' => '2',
				'٣' => '3',
				'٤' => '4',
				'٥' => '5',
				'٦' => '6',
				'٧' => '7',
				'٨' => '8',
				'٩' => '9',
			)
		);
	}

	public function get_timezone() {
		$timezone_string = get_option( 'timezone_string' );

		if ( is_string( $timezone_string ) && '' !== $timezone_string ) {
			try {
				return new DateTimeZone( $timezone_string );
			} catch ( Throwable $exception ) {
				$timezone_string = '';
			}
		}

		$offset  = (float) get_option( 'gmt_offset', 0 );
		$hours   = (int) $offset;
		$minutes = (int) round( abs( $offset - $hours ) * 60 );
		$sign    = $offset < 0 ? '-' : '+';

		return new DateTimeZone( sprintf( '%s%02d:%02d', $sign, abs( $hours ), $minutes ) );
	}

	private function format_gregorian_date( $date ) {
		return $this->normalize_digits( $date->setTimezone( $this->get_timezone() )->format( 'Y/m/d' ) );
	}
}
