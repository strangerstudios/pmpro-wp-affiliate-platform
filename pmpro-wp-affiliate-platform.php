<?php
/*
Plugin Name: Paid Memberships Pro - WP Affiliate Platform Integration Add On
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-dev/
Description: Process an affiliate via WP Affiliate Platform after a PMPro checkout.
Version: 1.7.1
Author: Stranger Studios, Tips and Tricks HQ
Author URI: http://www.strangerstudios.com
		 
Both Paid Memberships Pro (http://wordpress.org/extend/plugins/paid-memberships-pro/) and WP Affiliate Platform (http://www.tipsandtricks-hq.com/wordpress-affiliate/) must be installed and activated.
*/

/**
 * Temporary function to deal with missing affilaite_log_debug functionality
 * @param string $text - The debug message
 * @param boolean $show - Whether to actually log something (or not)
 */
if (!function_exists('wp_affiliate_log_debug')) {
	function wp_affiliate_log_debug( $text, $show ) {
		
		if (true === $show) {
			error_log($text);
		}
	}
}
/*
	Track affiliates after checkout.
*/
function wpa_pmpro_after_checkout($user_id)
{	
	//get order
	$morder = new MemberOrder();	
	$morder->getLastMemberOrder($user_id);
	
	//find referrer
	$referrer = $_COOKIE['ap_id'];
    wp_affiliate_log_debug("wpa_pmpro_after_checkout() - user id: " . $user_id . ". affiliate id: " . $referrer, true);

	//make sure we have a referrer
    if (empty($referrer)) {
        wp_affiliate_log_debug("wpa_pmpro_after_checkout() - affiliate id is empty. Nothing to do here", true);
        return;
    }
	
	//make sure the order is non-zero
	if(!empty($morder->total))
	{
		$sale_amt = $morder->total; //TODO - The commission will be calculated based on this amount
		$unique_transaction_id = $morder->code; //TODO - The unique transaction ID for reference
		$email = $morder->Email; //TODO - Customer email for record
		
		do_action('wp_affiliate_process_cart_commission', array("referrer" => $referrer, "sale_amt" =>$sale_amt, "txn_id"=>$unique_transaction_id, "buyer_email"=>$email));
		
		wp_affiliate_log_debug("wpa_pmpro_after_checkout() - saving affiliate id (" . $referrer . ") with order id: " . $morder->code, true);
		
		//save affiliate id in order
		$morder->affiliate_id = $referrer;
		$morder->saveOrder();
	}
}
add_action("pmpro_after_checkout", "wpa_pmpro_after_checkout");

/*
	For new orders (e.g. recurring orders via web hooks) check if a previous affiliate id was used and process
*/
function wpa_pmpro_add_order($morder)
{
	if(!empty($morder->total) || !empty($morder->subtotal))
	{
		if(!empty($morder->total))
			$sale_amt = $morder->total; //TODO - The commission will be calculated based on this amount
		else
			$sale_amt = $morder->subtotal;
		$unique_transaction_id = $morder->code; //TODO - The unique transaction ID for reference
		$muser = get_userdata($morder->user_id);
		$email = $muser->user_email; //TODO - Customer email for record
		
		//need to get the last order before this
		$last_order = new MemberOrder();
		$last_order->getLastMemberOrder($morder->user_id);
		
		if(!empty($last_order->affiliate_id))
		{					
			wp_affiliate_log_debug("wpa_pmpro_add_order() - affiliate id: " . $last_order->affiliate_id . ". Order id: " . $unique_transaction_id, true);
			
			$referrer = $last_order->affiliate_id;
			
			//perform commission if status is success
			if($morder->status == "success")
			{									
				do_action('wp_affiliate_process_cart_commission', array("referrer" => $referrer, "sale_amt" =>$sale_amt, "txn_id"=>$unique_transaction_id, "buyer_email"=>$email));
			}
			
			//update the affiliate id for this order
			global $wpa_pmpro_affiliate_id;
			$wpa_pmpro_affiliate_id = $referrer;
		}
		else
		{
			wp_affiliate_log_debug("wpa_pmpro_add_order() - No affiliate id. Order id: " . $unique_transaction_id, true);
		}
	}
}
add_action("pmpro_add_order", "wpa_pmpro_add_order");

/*
	Set affiliate ID from global variable when orders are added.
*/
function wpa_pmpro_added_order($morder)
{
	global $wpa_pmpro_affiliate_id;
	
	if(!empty($wpa_pmpro_affiliate_id))
	{
		$morder->affiliate_id = $wpa_pmpro_affiliate_id;
		$morder->saveOrder();
		wp_affiliate_log_debug("wpa_pmpro_added_order() - saving affiliate id (" . $wpa_pmpro_affiliate_id . ") with order id: " . $morder->code, true);		
	}
}
add_action("pmpro_added_order", "wpa_pmpro_added_order");

//show affiliate id on orders dashboard page
add_action("pmpro_orders_show_affiliate_ids", "__return_true");

/*
	Show affiliate ID in confirmation emails to admins.
*/
function wpa_pmpro_email_body($body, $email)
{
	//is this a checkout email to admins?
	if(strpos($email->template, "checkout") !== false && strpos($email->template, "admin") !== false)
	{
		//get the order
		$order = new MemberOrder($email->data['invoice_id']);
		
		if($order->affiliate_id)
		{
			//add ids to email body
			$body = str_replace("Total Billed:", "Affiliate ID:" . $order->affiliate_id . "<br />Affiliate SubID:" . $order->affiliate_subid . "<br />Total Billed:", $body);
		}
	}

	return $body;
}
add_action('pmpro_email_body', 'wpa_pmpro_email_body', 10, 2);

/* 
	For handlings gateways like PayPal Standard and 2Checkout that update the order status when payment has gone through.
*/
function wpa_pmpro_update_order($order) {
    global $wpdb;
	wp_affiliate_log_debug("PMPRO Integration - handling pmpro_update_order hook", true);
    
	//we're only concerned when status is switching from review/token/pending to success
	if($order->status != "success"){
		wp_affiliate_log_debug("PMPRO Integration - the order status is not set to success. So not an update we are concerned with.", true);   
		return;
	}
	
	//check that the old status was review, token, or pending
	$old_status_in_review = $wpdb->get_var("SELECT status FROM $wpdb->pmpro_membership_orders WHERE id = '" . $order->id . "' AND status IN('review','token','pending') LIMIT 1");
	if(empty($old_status_in_review))
	{
		wp_affiliate_log_debug("PMPRO Integration - the old order status is not review, token, or pending. So not an update we are concerned with.", true);   
		return;
	}
		
    //look for referrer
	$referrer = $order->affiliate_id;
    if(empty($referrer) && !empty($_COOKIE['ap_id '])){//Try to get it from the cookie if possible
        $referrer = $_COOKIE['ap_id'];        
    }

	//get some info from the order
	$payment_type = $order->payment_type;
    $status = $order->status;
    $sale_amt = $order->total;
    $email = $order->Email;
    $first_name = $order->FirstName;
    $last_name = $order->LastName;
    $name = $first_name . " " . $last_name;    	
    $txn_id = $order->code; //This was used to log as txn_id when commission was recorded.
    $txn_id = $txn_id . "_" . date("Y-m-d"); //Add the subscription charge date to make this unique

    wp_affiliate_log_debug("PMPRO Integration - Debug data: " . $payment_type . "|" . $status . "|" . $txn_id . "|" . $sale_amt . "|" . $referrer, true);

	if(empty($sale_amt)){        
        //must have a positive sale amount
		wp_affiliate_log_debug("PMPRO Integration - the order amount is 0. No commission will be generated for this transaction.", true);       
    }    
    elseif(!empty($referrer)) {
        wp_aff_award_commission_unique($referrer, $sale_amt, $txn_id, '', $email, '', '', $name);
        //do_action('wp_affiliate_process_cart_commission', array("referrer" => $referrer, "sale_amt" => $sale_amt, "txn_id" => $txn_id, "buyer_email" => $email));
        wp_affiliate_log_debug("PMPRO Integration - process commission function executed", true);
    } 
	else {
        wp_affiliate_log_debug("PMPRO Integration - This transaction has no referrer attached to it. Commission processing is not required.", true);
    }
	
	//if order didn't have affiliate ID and we found it in cookie, update the order
	if(empty($order->affiliate_id) && !empty($referrer)){          
	   wp_affiliate_log_debug("PMPRO Integration - updated affiliate_id during pmpro_update_order hook.", true);
	   $order->affiliate_id = $referrer;
	   $order->saveOrder();
	}
}
add_action("pmpro_update_order", "wpa_pmpro_update_order");

/*
	Save affiliate id before checkout
*/
function wpa_pmpro_save_id_before_checkout($user_id, $morder) {
    wp_affiliate_log_debug("wpa_pmpro_save_id_before_checkout() - user id: " . $user_id . ". Order ID: " . $morder->code, true);
    $referrer = $_COOKIE['ap_id'];

    //save affiliate id with the order
    wp_affiliate_log_debug("wpa_pmpro_save_id_before_checkout(). Saving affiliate id (" . $referrer . ") with order id: " . $morder->code, true);
    $morder->affiliate_id = $referrer;
    $morder->saveOrder();
}
add_action('pmpro_before_send_to_paypal_standard', 'wpa_pmpro_save_id_before_checkout', 10, 2);
add_action('pmpro_before_send_to_twocheckout', 'wpa_pmpro_save_id_before_checkout', 10, 2);

/*
	Function to add links to the plugin row meta
*/
function wpa_pmpro_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-wp-affiliate-platform.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('http://www.paidmembershipspro.com/add-ons/third-party-integration/pmpro-wp-affiliate-platform-integration/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url('http://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'wpa_pmpro_plugin_row_meta', 10, 2);
