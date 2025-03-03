<?php
abstract class HbPaymentGateway {

	public $id = '';
	public $name = '';
	public $version = '1.0';
	public $has_redirection = 'no';
	public $hbdb;
	public $utils;

	public function get_strings_section() {
		return array();
	}

	public function get_strings_value() {
		return array();
	}

	public function insert_plugin_strings() {
		global $wpdb;
		$strings = $this->get_strings_value();
		foreach	( $strings as $string_id => $string_value ) {
			if ( ! $this->utils->get_string( $string_id ) ) {
				$query = "INSERT INTO {$this->hbdb->strings_table} ( id, locale, value ) VALUES ( '$string_id', 'en_US', '$string_value' )";
				$wpdb->query( $query );
			}
		}
	}

	public function add_plugin_strings( $hb_strings ) {
		$plugin_strings = $this->get_strings_section();
		if ( $plugin_strings ) {
			$hb_strings[ $this->id . '_strings' ] = $plugin_strings;
		}
		return $hb_strings;
	}

	public function get_required_form_fields() {
		return array();
	}

	public function insert_plugin_required_form_fields() {
		global $wpdb;
		$fields = $this->hbdb->get_details_form_fields();
		$result = $wpdb->get_results(
			"
			SELECT MAX( order_num )
			FROM {$this->hbdb->fields_table}
			"
		, ARRAY_A );
		if ( ! $result ) {
			$order_num = 1;
		} else {
			$order_num = $result[0]['MAX( order_num )'] + 1;
		}
		$required_fields = $this->get_required_form_fields();
		foreach	( $required_fields as $field_id => $field_info ) {
			$key = array_search( $field_id, array_column( $fields, 'id' ) );
			if ( false === $key ) {
				$field_data = array(
					'id' => $field_id ,
					'name' => $field_info['name'],
					'standard' => 1,
					'displayed' => 1,
					'required' => 1,
					'admin_only' => 0,
					'type' => $field_info['type'],
					'has_choices' => 0,
					'order_num' => $order_num,
					'data_about' => 'customer',
					'column_width' => 'full',
				);
				$wpdb->insert( $this->hbdb->fields_table, $field_data );
				$order_num++;
			}
		}
	}

	public function get_payment_method_label() {
		return '';
	}

	public function admin_fields() {
		return array();
	}

	public function admin_js_scripts() {
		return array();
	}

	public function js_scripts() {
		return array();
	}

	public function js_data() {
		return array();
	}

	public function payment_form() {
		return '';
	}

	public function bottom_area() {
		return '';
	}

	public function css_styles() {
		return array();
	}

	public function hb_http_api_curl_ssl_version( $handle ) {
		curl_setopt( $handle, CURLOPT_SSLVERSION, 6 );
	}

	public function hb_http_api_curl_timeout( $handle ) {
		curl_setopt( $handle, CURLOPT_TIMEOUT, 0 );
	}

	public function hb_remote_post( $url, $post_args ) {
		$ssl_verify = true;
		if ( get_option( 'hb_ssl_verify' ) == 'no' ) {
			$ssl_verify = false;
		}
		$post_args = array_merge( $post_args, array( 'sslverify' => $ssl_verify, 'httpversion' => '1.1') );
		if ( get_option( 'hb_curl_set_ssl_version' ) == 'yes' ) {
			add_action( 'http_api_curl', array( $this, 'hb_http_api_curl_ssl_version' ) );
		}
		if ( get_option( 'hb_curl_set_timeout' ) == 'yes' ) {
			add_action( 'http_api_curl', array( $this, 'hb_http_api_curl_timeout' ) );
		}
		$response = wp_remote_post( $url, $post_args );
		if ( get_option( 'hb_curl_set_ssl_version' ) == 'yes' ) {
			remove_action( 'http_api_curl', array( $this, 'hb_http_api_curl_ssl_version' ) );
		}
		if ( get_option( 'hb_curl_set_timeout' ) == 'yes' ) {
			remove_action( 'http_api_curl', array( $this, 'hb_http_api_curl_timeout' ) );
		}
		return $response;
	}

	public function get_payment_token() {
		return false;
	}

	public function get_external_payment_desc( $resa_info, $customer_info, $token = '' ) {
		$hb_strings = $this->utils->get_strings();
		$payment_desc = $hb_strings['external_payment_txt_desc'];
		$wptexturize = remove_filter( 'the_title', 'wptexturize' );
		if ( isset( $resa_info['accom_ids'] ) ) {
			$accom_name = array();
			foreach ( $resa_info['accom_ids'] as $accom_id ) {
				if ( $accom_id != 'parent_resa' ) {
					$accom_name[] = get_the_title( $this->utils->get_translated_post_id( $accom_id ) );
				}
			}
			$accom_name = implode( ', ', $accom_name );
		} else {
			$accom_name = get_the_title( $this->utils->get_translated_post_id( $resa_info['accom_id'] ) );
		}
		if ( $wptexturize ) {
			add_filter( 'the_title', 'wptexturize' );
		}
		$payment_desc = str_replace( '%accom_name', $accom_name, $payment_desc );
		if ( $resa_info['payment_type'] == 'deposit' && ( get_option( 'hb_resa_payment_deposit' ) == 'yes' || get_option( 'hb_resa_payment' ) == 'deposit' ) ) {
			$payment_desc = str_replace( '%deposit_txt', $hb_strings['external_payment_txt_deposit'], $payment_desc );
		} else {
			$payment_desc = str_replace( '%deposit_txt', '', $payment_desc );
		}
		$number_of_nights = $this->utils->get_number_of_nights( $resa_info['check_in'], $resa_info['check_out'] );
		if ( get_option( 'hb_charge_per_day' ) == 'yes' ) {
			$number_of_nights++;
		}
		if ( $number_of_nights > 1 ) {
			$payment_desc = str_replace( '%nights_txt', $hb_strings['external_payment_txt_several_nights'], $payment_desc );
			$payment_desc = str_replace( '%nb_nights', $number_of_nights, $payment_desc );
		} else {
			$payment_desc = str_replace( '%nights_txt', $hb_strings['external_payment_txt_one_night'], $payment_desc );
		}
		$payment_desc = str_replace( '%check_in_date', $this->utils->format_date( $resa_info['check_in'] ), $payment_desc );
		$payment_desc = str_replace( '%check_out_date', $this->utils->format_date( $resa_info['check_out'] ), $payment_desc );
		if ( $resa_info['adults'] > 1 ) {
			$payment_desc = str_replace( '%adults_txt', $hb_strings['external_payment_txt_several_adults'], $payment_desc );
			$payment_desc = str_replace( '%nb_adults', $resa_info['adults'], $payment_desc );
		} else if ( get_option( 'hb_display_adults_field' ) != 'no' ) {
			$payment_desc = str_replace( '%adults_txt', $hb_strings['external_payment_txt_one_adult'], $payment_desc );
		} else {
			$payment_desc = str_replace( '%adults_txt', '', $payment_desc );
		}
		if ( $resa_info['children'] > 0 ) {
			if ( $resa_info['children'] > 1 ) {
				$payment_desc = str_replace( '%children_txt', $hb_strings['external_payment_txt_several_children'], $payment_desc );
				$payment_desc = str_replace( '%nb_children', $resa_info['children'], $payment_desc );
			} else {
				$payment_desc = str_replace( '%children_txt', $hb_strings['external_payment_txt_one_child'], $payment_desc );
			}
		} else {
			$payment_desc = str_replace( '%children_txt', '', $payment_desc );
		}
		if ( $resa_info['alphanum_id'] ) {
			$payment_desc = str_replace( '%alphanum_id', $resa_info['alphanum_id'], $payment_desc );
		} else {
			$payment_desc = str_replace( '%alphanum_id', '', $payment_desc );
		}
		$payment_desc = str_replace( '%payment_token', $token, $payment_desc );
		if ( $resa_info['customer_id'] ) {
			$payment_desc = str_replace( '%customer_id', $resa_info['customer_id'], $payment_desc );
			$customer_info = $this->hbdb->get_customer_info(  $resa_info['customer_id'] );
			if ( isset( $customer_info['first_name'] ) && isset( $customer_info['last_name'] ) ) {
				$customer_name = $customer_info['first_name'] . ' ' . $customer_info['last_name'];
			}
			$payment_desc = str_replace( '%customer_name', $customer_name, $payment_desc );
		} else {
			$payment_desc = str_replace( '%customer_id', '', $payment_desc );
		}
		return $payment_desc;
	}

	public function get_return_urls( $parameters_to_remove = array() ) {
		$parameters_to_remove = array_merge( $parameters_to_remove, array( 'payment_gateway', 'payment_confirm', 'payment_cancel' ) );
		$current_url = $_POST['hb-current-url'];
		foreach ( $parameters_to_remove as $param ) {
			$pattern = '/&' . $param . '(\=[^&]*)?(?=&|$)|' . $param . '(\=[^&]*)?(&|$)/';
			$current_url = preg_replace( $pattern, '', $current_url );
		}
		if ( strpos( $current_url, '#' ) ) {
			$current_url = substr( $current_url, 0, strpos( $current_url, '#' ) );
		}
		if ( substr( $current_url, -1 ) != '?' ) {
			if ( strpos( $current_url, '?' ) > 0 ) {
				$current_url .= '&';
			} else {
				$current_url .= '?';
			}
		}
		$return_urls = array();
		$current_url .= 'payment_gateway=' . $this->id . '&';
		$return_urls['payment_confirm'] = $current_url . 'payment_confirm=1';
		$return_urls['payment_cancel'] = $current_url . 'payment_cancel=1';
		return $return_urls;
	}

	public function confirm_payment() {
		$reponse = array( 'success' => true );
		return $response;
	}

	public function check_resa_payment_delayed_status( $resa ) {
	}

	abstract public function process_payment( $resa_info, $customer_info, $amount_to_pay );

}