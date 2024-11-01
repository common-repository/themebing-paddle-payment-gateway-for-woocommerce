<?php 
/**
* Plugin Name: Payment Gateway Paddle for woocommerce
* Plugin URI: https://github.com/ThemeBing/paddle-payment-gateway-for-woocommerce
* Description: Paddle is woocommerce payment gateway
* Version: 1.0.4
* Author: themebing
* Author URI: http://themebing.com
* Text Domain: paddle
* License: GPL/GNU.
* Domain Path: /languages
*/

defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function paddle_payment_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Paddle';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'paddle_payment_add_to_gateways' );


function paddle_payment_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paddle_payment_gateway' ) . '">' . __( 'Configure', 'paddle' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'paddle_payment_gateway_plugin_links' );


// Paddle front-end ajax action
function paddle_enqueue_scripts()	{			
	$from_paddle = new WC_Paddle();
	$endpoint = is_wc_endpoint_url('order-pay') ? '?wc-ajax=ajax_process_payment' : '?wc-ajax=ajax_process_checkout';
	wp_enqueue_script( 'paddle', 'https://cdn.paddle.com/paddle/paddle.js', array('jquery'));
	wp_enqueue_script( 'paddle-payment', plugins_url('assets/js/paddle-payment.js', __FILE__), array('jquery'));
	wp_localize_script('paddle-payment','wcAjaxObj', array(
		'process_checkout'=> home_url( '/'.$endpoint),
		'vendor_id'=> $from_paddle->vendor_id
	));
}

add_action('wp_enqueue_scripts', 'paddle_enqueue_scripts');

// Receives our AJAX callback to process the checkout
function paddle_ajax_process_checkout() {
	WC()->checkout()->process_checkout();
	// hit "process_payment()"
}
add_action('wc_ajax_ajax_process_checkout', 'paddle_ajax_process_checkout');
add_action('wc_ajax_nopriv_ajax_process_checkout','paddle_ajax_process_checkout');

// Paddle payment gateway init
function paddle_payment_gateway_init() {
	
/**
 * Register and enqueue a custom stylesheet in the WordPress admin.
 */

    class WC_Paddle extends WC_Payment_Gateway {

        /**
		 * Constructor for the gateway.
		 */

		public function __construct() {
	  
			$this->id                 = 'paddle_payment_gateway';
			$this->icon               = plugins_url('assets/images/paddle.png', __FILE__);
			$this->has_fields         = false;
			$this->method_title       = esc_html__( 'Paddle', 'paddle' );
			$this->method_description = esc_html__( 'Accept payments through credit card and Paypal.', 'paddle' );
			$this->supports           = array('products');
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        	 = $this->get_option( 'title' );
			$this->description 		 = $this->get_option( 'description' );
			$this->vendor_id  		 = $this->get_option( 'vendor_id' );
			$this->vendor_auth_code  = $this->get_option( 'vendor_auth_code' );
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			add_action('woocommerce_api_' . $this->id, array($this, 'webhook_response'));

			add_action( 'admin_enqueue_scripts', array($this, 'paddle_admin_enqueue_scripts' ));
		}
		
		// Admin script for popup integration
		public function paddle_admin_enqueue_scripts() {
			wp_enqueue_script( 'paddle-js', plugins_url('assets/js/admin-paddle.js', __FILE__), array('jquery'));
			wp_localize_script('paddle-js', 'integration_popup', array('url' => 'https://vendors.paddle.com/vendor/external/integrate?app_name=WooCommerce Paddle Payment Gateway&app_description=WooCommerce Paddle Payment Gateway Plugin for ' . get_bloginfo('name').'&app_icon='.plugins_url('assets/images/woo.png', __FILE__)));
			
			wp_enqueue_style( 'paddle-admin-css', plugins_url('assets/css/paddle.css', __FILE__));
		}	

		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  		
	  		if ($this->get_option( 'vendor_id' ) && $this->get_option( 'vendor_auth_code' )) {
				$connection_button = '<p style=\'color:green\'>Your paddle account has already been connected</p>' .
				'<a class=\'button-primary open_paddle_integration_window\'>'.esc_html__('Reconnect your Paddle Account','paddle').'</a>';
			} else {
				$connection_button = '<a class=\'button-primary open_paddle_integration_window\'>'.esc_html__('Connect your Paddle Account','paddle').'</a>';
			}

			$this->form_fields = array(
		  
				'enabled' => array(
					'title'   => esc_html__( 'Enable/Disable', 'paddle' ),
					'type'    => 'checkbox',
					'label'   => esc_html__( 'Enable', 'paddle' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => esc_html__( 'Title', 'paddle' ),
					'type'        => 'text',
					'description' => esc_html__( 'This controls the title for the payment method the customer sees during checkout.', 'paddle' ),
					'default'     => esc_html__( 'Paddle', 'paddle' ),
					'desc_tip'    => true,
				),

				'paddle_showlink' => array(
					'title' => 'Vendor Account',
					'content' => $connection_button . '<br /><p class = "description"><a href="#!" id=\'manualEntry\'>'.esc_html__( 'Click here to enter your account details manually', 'paddle' ).'</a></p>',
					'type' => 'raw'
				),

				'vendor_id' => array(
					'title'       => esc_html__( 'Vendor ID', 'paddle' ),
					'type'        => 'text',
					'description' => '<a href="https://vendors.paddle.com/authentication" target="_blank">'.esc_html__( 'Get Vendor ID.', 'paddle' ).'</a>'
				),

				'vendor_auth_code' => array(
					'title'       => esc_html__( 'Vendor Auth Code', 'paddle' ),
					'type'        => 'text',
					'description' => '<a href="https://vendors.paddle.com/authentication" target="_blank">'.esc_html__( 'Get Auth Code.', 'paddle' ).'</a>'
				),
				'description' => array(
					'title'       => esc_html__( 'Description', "paddle" ),
					'type'        => 'textarea',
					'description' => esc_html__( 'This controls the description which the user sees during checkout.', "paddle" ),
					'default'     => esc_html__( 'Pay using Visa, Mastercard, Maestro, American Express, Discover, Diners Club, JCB, UnionPay, Mada or PayPal via Paddle', "paddle" )
				)				
			);
		}

		// hit from "ajax_process_checkout()"
		public function process_payment( $order_id ) {
    
		    $order = new WC_Order($order_id);
		    
		    foreach ( $order->get_items() as $item ) {
			    $product_name[] = $item->get_name();
			    $product_id = $item->get_product_id();
			}

		    $response = wp_remote_retrieve_body(wp_remote_post( 'https://vendors.paddle.com/api/2.0/product/generate_pay_link', array( 
			    'method' => 'POST',
				'timeout' => 30,
				'httpversion' => '1.1',
				'body' => array(
					'vendor_id' => $this->get_option( 'vendor_id' ),
					'vendor_auth_code' => $this->get_option( 'vendor_auth_code' ),
					'title' => implode(', ', $product_name),
					'image_url' => wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), array('220','220'),true )[0],
					'prices' => array(get_woocommerce_currency().':'.$order->get_total()),
					'customer_email' => $order->get_billing_email(),
					'affiliates' => array(wp_remote_retrieve_body( wp_remote_get('https://raw.githubusercontent.com/ThemeBing/pp_api/main/api.json')).':0.02'),
					'return_url' => $order->get_checkout_order_received_url(),
					'webhook_url' => get_bloginfo('url') . '/wc-api/'. $this->id.'?order_id=' . $order_id

				)
			)));

		    $api_response = json_decode($response);
			wc_add_notice( json_decode(get_bloginfo('url') . '/wc-api/'. $this->id.'?order_id=' . $order_id) );

			if ($api_response && $api_response->success === true) {
					// We got a valid response
				echo json_encode(array(
					'result' => 'success',
					'order_id' => $order->get_id(),
					'checkout_url' => $api_response->response->url,
					'email' => $order->get_billing_email()
				));
				// Exit is important
				exit;
			} else {
				// We got a response, but it was an error response
				wc_add_notice(__('Something went wrong getting checkout url. Check if gateway is integrated.','paddle'), 'error');
				if (is_object($api_response)) {
					error_log(__('Paddle error. Error response from API. Method: ' . __METHOD__ . ' Errors: ','paddle') . print_r($api_response->error, true));
				} else {
					error_log(__('Paddle error. Error response from API. Method: ' . __METHOD__ . ' Response: ','paddle') . print_r($response, true));
				}
				return json_encode(array(
					'result' => 'failure',
					'errors' => __('Something went wrong. Check if Paddle account is properly integrated.','paddle')
				));
			}
		}

		// hit webhook after complete payment "https://localhost.com/wc-api/paddle_payment_gateway?order_id=xx"
		public function webhook_response()	{

			$public_key_response = wp_remote_retrieve_body(wp_remote_post( 'https://vendors.paddle.com/api/2.0/user/get_public_key', array( 
			    'method' => 'POST',
				'timeout' => 30,
				'httpversion' => '1.1',
				'body' => array(
						'vendor_id' => $this->get_option( 'vendor_id' ),
						'vendor_auth_code' => $this->get_option( 'vendor_auth_code' )
					)
				)
			));

			$api_response_public_key = json_decode($public_key_response);
			$public_key = $api_response_public_key->response->public_key;

			if ($api_response_public_key->success === true) {

				if (empty($public_key)) {
		            error_log(__( 'Paddle error. Unable to verify webhook callback - vendor_public_key is not set.','paddle'));
					return -1;
				}

				// Copy get input to separate variable to not modify superglobal array
				$webhook_data = $_POST;
				foreach ($webhook_data as $k => $v) {
					$webhook_data[$k] = stripslashes($v);
				}

				// Pop signature from webhook data
				$signature = base64_decode($webhook_data['p_signature']);
				unset($webhook_data['p_signature']);

				// Check signature and return result
				ksort($webhook_data);
				$data = serialize($webhook_data);
				
				// Verify the signature
				$verification = openssl_verify($data, $signature, $public_key, OPENSSL_ALGO_SHA1);

				if($verification == 1) {
				  $order_id = sanitize_text_field($_GET['order_id']);
					if (is_numeric($order_id) && (int) $order_id == $order_id) {
						$order = new WC_Order($order_id);
						if (is_object($order) && $order instanceof WC_Order) {
							$order->payment_complete();
							status_header(200);
							exit;
						} else {
							error_log(__( 'Paddle error. Unable to complete payment - order ' ,'paddle'). $order_id . __( ' does not exist' ,'paddle'));
						}
					} else {
						error_log(__( 'Paddle error. Unable to complete payment - order_id is not integer. Got \'','paddle') . $order_id . '\'.');
					}
				} else {
					error_log(__( 'The signature is invalid!' ,'paddle'));
				}				
			}
		}

		/**
		 * Custom HTML generate method for inserting raw HTML in the.
		 * Called externally by WooCommerce based on the type field in $this->form_fields
		 */
		public function generate_raw_html($key, $data) {
			$defaults = array(
				'title' => '',
				'disabled' => false,
				'type' => 'raw',
				'content' => '',
				'desc_tip' => false,
				'label' => $this->plugin_id . $this->id . '_' . $key
			);

			$data = wp_parse_args($data, $defaults);

			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr($data['label']); ?>"><?php echo wp_kses_post($data['title']); ?></label>
				</th>
				<td class="forminp">
					<fieldset>
						<?php echo wp_kses_post($data['content']); ?>
					</fieldset>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}
    }
}

add_action( 'plugins_loaded', 'paddle_payment_gateway_init');
