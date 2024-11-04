<?php
	/**
	 * Plugin Name: Order Tracking
	 * Description: Get Current Loation of the courier and plot it in the map
	 * Version: 1.0
	 * Author: Errol Mark Tudio
	 */

	if (!defined('ABSPATH')) {
	    exit; // Exit if accessed directly.
	}

	function eti_enqueue_styles_scripts_pb() {
		wp_enqueue_script('custom-script-tracking', plugins_url('/assets/custom.js',__FILE__ ),array(), false, true );
		wp_register_style ('custom-css-tracking' ,  plugins_url('/assets/custom.css',__FILE__ ));
		wp_register_script('bootstrap-tracking', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js');
		wp_register_style('bootstrap-css-tracking', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css');

		$arr = array(
			'ajaxurl' => admin_url('admin-ajax.php'),
		);
		wp_localize_script( 'custom-script-tracking','ajax_object',$arr );

		wp_enqueue_style('custom-css-tracking');
		// wp_enqueue_style('bootstrap-css-tracking');
		// wp_enqueue_script('bootstrap-tracking');

	}
	function eti_enqueue_styles_firebase() {
		wp_enqueue_script('firebase-app', 'https://www.gstatic.com/firebasejs/8.6.3/firebase-app.js');
		wp_enqueue_script('firebase-analytics', 'https://www.gstatic.com/firebasejs/8.6.3/firebase-analytics.js');
		wp_enqueue_script('firebase-auth', 'https://www.gstatic.com/firebasejs/8.6.3/firebase-auth.js');
		wp_enqueue_script('firebase-firestore', 'https://www.gstatic.com/firebasejs/8.6.3/firebase-firestore.js');
		wp_enqueue_script('firebase-messaging', 'https://www.gstatic.com/firebasejs/8.6.3/firebase-messaging.js');

		wp_enqueue_script( 'firebase-app' );
		wp_enqueue_script( 'firebase-analytics');
		wp_enqueue_script( 'firebase-auth');
		wp_enqueue_script( 'firebase-firestore');
		wp_enqueue_script( 'firebase-messaging');
		wp_enqueue_script( 'eti-firebase-js' );

	}

	//Check page and load the scripts
	function plugin_is_page() {
		if ( is_page( 'track-order' )  || is_page( 'my-account' ) ) {
			add_action('wp_enqueue_scripts','eti_enqueue_styles_scripts_pb');
			add_action('wp_enqueue_scripts','eti_enqueue_styles_firebase');
		}
		else{

			add_action('wp_enqueue_scripts','eti_enqueue_styles_scripts_pb');
		}
	}
	add_action( 'template_redirect', 'plugin_is_page' );


	/**Includes Files*/
	include( plugin_dir_path( __FILE__ ) . 'includes/eti-maps.php');

	// Your additional action button
	function add_my_account_my_orders_custom_action( $actions, $order ) {
		$action_slug = 'eti-track-order';
		$actions[$action_slug] = array(
				'url'  => home_url('/track-order/'.$order->ID),
				'name' => 'Track Order'
			);
		return $actions;
	}
	// add_filter( 'woocommerce_my_account_my_orders_actions', 'add_my_account_my_orders_custom_action', 10, 2 );

	// Jquery script
	function action_after_account_orders_js() {
		$action_slug = 'eti-track-order';
		?>
		<script>
		jQuery(function($){
			$('a.<?php echo $action_slug; ?>').each( function(){
				$(this).attr('target','_blank');
			})
		});
		</script>
		<?php
	}
	// add_action( 'woocommerce_after_account_orders', 'action_after_account_orders_js');

	//Add Track order on email
	function custom_content_to_processing_customer_email( $order, $sent_to_admin, $plain_text, $email ) {

		if( 'customer_processing_order' == $email->id ){
			echo '<p><a style="text-transform: uppercase;background-color: #f0bc35!important;border-color: #f0bc35!important;color: #fff!important;padding: 8px 15px;font-weight: 700;text-align: center;border-radius: 5px;text-decoration: none;" href="'.home_url('/track-order/'.$order->ID).'" target="_blank">Track  Order</a></p>';
		}

	}
	add_action( 'woocommerce_email_before_order_table', 'custom_content_to_processing_customer_email', 10, 4 );

	function trackorder_access(){
		if(is_page('track-order')){
			$uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
			$uri_segments = explode('/', $uri_path);
			$order = new WC_Order($uri_segments[2]);
			$order_user_id = $order->get_user_id();
			if(is_user_logged_in()){
				$current_user_id = get_current_user_id();
				if($order_user_id != $current_user_id){
					wp_redirect(home_url('/my-account/orders/'));
					exit();
				}
			}
			else{
				wp_redirect(home_url('/my-account?redirect=track-order&orderid='.$order->ID) );
				exit();
			}
		}
	}

	// add_action( 'template_redirect', 'trackorder_access' );

	function woo_redirect() {

		if(isset($_GET['redirect'])){
			$redirect_page = $_GET['redirect'];
			$orderID = $_GET['orderid'];
			$redirect_to = home_url('/'.$redirect_page.'/'.$orderID);
			return $redirect_to;
		}
		else{

			$redirect_to = home_url('/my-account/');
			return $redirect_to;
		}
	}
	add_filter( 'woocommerce_login_redirect', 'woo_redirect' );

	function eti_trackorder_link($orderid){
		$order = new WC_Order( $orderid );
		$order_meta = get_post_meta($order->ID);

		echo '<a class="trackorder-link btn btn-default" href="/track-order/'.$order->ID.'">Track Order <i class="fa fa-truck" aria-hidden="true"></i></a>';

	}
	add_action( 'woocommerce_thankyou', 'eti_trackorder_link' );


	//Progress bar status
	function get_status_progress($method,$order_id){


		$order = new WC_Order($order_id);
		$order_meta = get_post_meta($order->ID);

		foreach( $order->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
			$shipping_method_title = $shipping_item_obj->get_method_title();
		}

		$active = '';
		//array of statuses
		$payment_stage = array('pending', 'processing', 'paymentrequest','on-hold');
		$preparation_stage = array('preparing');
		$delivery_stage = array('toship','shipping','readyforpickup');
		$last_stage = array('completed','failed', 'deliveryfailed');

		//Set labels
		$payment_label = !empty($order_meta['_eclectus_tracking_status_payment'][0]) ? $order_meta['_eclectus_tracking_status_payment'][0]: '';
		$preparation_label = !empty($order_meta['_eclectus_tracking_status_preparation'][0]) ? $order_meta['_eclectus_tracking_status_preparation'][0]: '';
		$delivery_label = !empty($order_meta['_eclectus_tracking_status_delivery'][0]) ? $order_meta['_eclectus_tracking_status_delivery'][0]: '';
		$last_label = !empty($order_meta['_eclectus_tracking_status_last'][0]) ? $order_meta['_eclectus_tracking_status_last'][0]: '';

		// print_pre($order_meta);

		//Active Statuses
		$payment_active = '';
		$preparation_active = '';
		$delivery_active = '';
		$last_active = '';

		//Check payment stage
		if(in_array($order->get_status(),$payment_stage)){

			$label = $order->get_status() == 'pending' || $order->get_status() == 'on-hold' ? 'PENDING PAYMENT' : 'PROCESSING';


			$pending = $label == 'Pending Payment' ? ' pending': '';

			$payment_label = $label;
			$preparation_label = '';
			$delivery_label = '';
			$last_active = '';
			$payment_active = ' active'.$pending;

		}

		//Check preparation stage
		if(in_array($order->get_status(),$preparation_stage)){

			$payment_label = 'Paid';
			$preparation_label = 'Packing';
			$delivery_label = '';
			$last_active = '';

			$payment_active = ' done';
			$preparation_active = ' active';
			// if($method != 'Delivery'):
			// 	// $delivery_active = ' active';
			// 	$delivery_label = 'PREPARING';
			// endif;

		}

		//Check delivery stage
		if(in_array($order->get_status(),$delivery_stage)){

			if($order->get_status() == 'toship'){$label = 'FOR DISPATCH'; }
			if($order->get_status() == 'shipping'){$label = 'IN-TRANSIT'; }
			if($order->get_status() == 'readyforpickup'){$label = 'FOR PICKUP'; }

			$payment_label = 'Paid';
			$preparation_label = 'Packed';
			$delivery_label = $label;
			$last_active = '';

			$payment_active = ' done';
			$preparation_active = ' done';
			$delivery_active = ' active';
		}

		//Check last stage
		if(in_array($order->get_status(),$last_stage)){

			if($order->get_status() == 'completed'){

				if($shipping_method_title == 'Delivery' || $shipping_method_title == 'Delivery (Free)' || $shipping_method_title == 'Delivery(Free)'){
					$delivery_label = 'Shipped';

				}
				else{
					$delivery_label = 'Picked Up';
				}

				$label = 'Received';

				$class = ' done';
				$delivery_class = $class;

			}

			if($order->get_status() == 'failed'){
				$label = 'Failed';
				$class =' failed';

				if($shipping_method_title != 'Delivery' || $shipping_method_title != 'Delivery (Free)'){
					$delivery_class = $class;
					$delivery_label = 'Failed';

				}
				else{
					$delivery_label = 'Shipped';
					$delivery_class = ' done';
				}
			}

			if($order->get_status() == 'deliveryfailed'){
				$label = 'Delivery Failed';
				$class = ' failed';

				if($shipping_method_title != 'Delivery' || $shipping_method_title != 'Delivery (Free)'){
					$delivery_class = $class;
					$delivery_label = 'Failed';

				}
				else{
					$delivery_label = 'Shipped';
					$delivery_class = ' done';
				}
			}


			$payment_label = 'Paid';
			$preparation_label = 'Prepared';
			$payment_active = ' done';
			$preparation_active = ' done';
			$delivery_active = $delivery_class;
			$last_label = $label;
			$last_active = $class;

			// if($method != 'Delivery'):
			// 	// $delivery_active = ' active';
			// 	$last_active = ' active';
			// 	$last_label = 'PICKED sUP';
			// endif;

		}
		if($order->get_status() == 'cancelled'){

			$preparation_label = '';
			$delivery_label = '';
			$last_active = '';
			$payment_label = 'Cancelled';
			$payment_active = ' active cancelled';
			$preparation_active = ' cancelled';

		}
		if($order->get_status() == 'refunded'){

			$payment_label = 'Refunded';
			$preparation_label = 'Prepared';

			if($shipping_method_title != 'Delivery' || $shipping_method_title != 'Delivery (Free)'){
				$delivery_label = 'Picked Up';

			}
			else{
				$delivery_label = 'Shipped';
			}

			$last_label = 'Received';

			$payment_active = ' done refunded';
			$preparation_active = ' done refunded';
			$delivery_active = ' done refunded';
			$last_active = ' done refunded';

		}

		$progressbar = '';

		$progressbar_title = is_user_logged_in() && is_account_page() ? '<h4 class="progressbar_title">Order Status</h4>':'<h6 class="progressbar_title">Order Status</h6>';

		$progressbar .= '<div class="progressbar-container">'.$progressbar_title.'<div class="progressbar-wrapper">';
		if($method == 'Delivery' || $shipping_method_title == 'Delivery (Free)'):
			$progressbar .= '<ul class="progressbar">
				<li class="pb-deliver pb-payment'.$payment_active.'"><span class="pb-text">'.$payment_label.'</span></li>
				<li class="pb-deliver pb-preparation'.$preparation_active.'"><span class="pb-text">'.$preparation_label.'</span></li>
				<li class="pb-deliver pb-delivery'.$delivery_active.'"><span class="pb-text">'.$delivery_label.'</span></li>
				<li class="pb-deliver pb-complete'.$last_active.'"><span id="complete-progressbar" class="complete-progressbar"></span><span class="pb-text">'.$last_label.'</span></li>
			</ul>';
		else:
			$progressbar .= '<ul class="progressbar">
				<li class="pb-pickup pb-payment'.$payment_active.'"><span class="pb-text">'.$payment_label.'</span></li>
				<li class="pb-pickup pb-preparation'.$preparation_active.'"><span class="pb-text">'.$preparation_label.'</span></li>
				<li class="pb-pickup pb-delivery'.$delivery_active.'"><span class="pb-text">'.$delivery_label.'</span></li>
			</ul>';
		endif;
		$progressbar .= '</div></div>';

		echo $progressbar;
	}


//**Track your order */
add_shortcode('track_order', function ($atts) {
    ob_start();
    $order_id = 0;
    if(is_user_logged_in()){

        $order_id = isset($_GET['order_id'])?$_GET['order_id']:0;

        if($order_id != 0){
            echo do_shortcode('[track_order_map order-id='.$order_id.']');
        }
		else{
			trackOrderForm();
		}
    }
    else{
        trackOrderForm();
    }


    return ob_get_clean();
});

/**Track Order Form */
function trackOrderForm(){
    ?>
        <div id="track-order-form-container" class="form-box">
            <form id="trackorder_form">
                <div id="track-order-form-fields">
					<label for = "track_order_num" class="field-label">Order ID<span class="required">*</span></label>
                    <div class="track-order-form-control field-container">
                        <input type="text" class="form-control" id="track_order_num" name="track_order_num" placeholder="">
						<i class="field-icon field-icon-invalid fa-solid fa-circle-exclamation"></i>
						<i class="field-icon field-icon-valid fa-solid fa-circle-check"></i>
                    </div>
						<?php echo wp_nonce_field('ajax-trackorder-nonce', 'trackorder_security') ?>
                    <div class="track-order-form-control">
                        <button id="track_order_submit" class="btn btn-green">Proceed</button>
                    </div>
                </div>
            </form>
        </div>
    <?php
}

add_action('wp_ajax_nopriv_trackOrderFormAjax', 'trackOrderFormAjax');
add_action('wp_ajax_trackOrderFormAjax', 'trackOrderFormAjax');
function trackOrderFormAjax(){
	$data = $_POST;
    $order = wc_get_order($data['order_id']);
	if (!wp_verify_nonce($data['nonce_security'], 'ajax-trackorder-nonce')) {
		return;
	}

	if($order){

        echo json_encode(array('notice' => 'success', 'message' => 'Order #'.$data['order_id'] . ' found'));

	}
	else{

        echo json_encode(array('notice' => 'failed', 'message' => 'Order #'.$data['order_id'] . ' not found'));
	}
    wp_die();
}

add_action('wp_ajax_nopriv_renderTracking', 'renderTracking');
add_action('wp_ajax_renderTracking', 'renderTracking');
function renderTracking(){
	$data = $_POST;

    $order = wc_get_order($data['order_id']);
	?>
	<div id="tracking-page-container">
		<div id="tracking-page-details">
			<h5 id="tracking-page-details-header" class="no-margin-b">Order ID: #<?php echo $order->get_id();?></h5>
			<p id="tracking-page-details-subheader" class="no-margin-b"><strong>Date Ordered:</strong> <?php echo date_format($order->get_date_created(),"F d, Y g:i A");?></p>
		</div>
		<div id="tracking-page-trackorder">
			<?php echo  do_shortcode('[track_order_map order-id='.$order->get_id().']');?>
		</div>
	</div>
	<a href="/track-order/" class="btn-green-reverse">Track Another Order</a>
	<?php
    wp_die();
}