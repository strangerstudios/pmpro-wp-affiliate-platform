<?php
/*
Plugin Name: PMPro WP Affiliate Platform Integration
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-dev/
Description: Process an affiliate via WP Affiliate Platform after a PMPro checkout.
Version: .3.1.1
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
		 
Both Paid Memberships Pro (http://wordpress.org/extend/plugins/paid-memberships-pro/) and WP Affiliate Platform (http://www.tipsandtricks-hq.com/wordpress-affiliate/) must be installed and activated.
*/
function wpa_pmpro_after_checkout($user_id)
{	
	$morder = new MemberOrder();	
	$morder->getLastMemberOrder($user_id);
	
	if(!empty($morder->total))
	{
		$sale_amt = $morder->total; //TODO - The commission will be calculated based on this amount
		$unique_transaction_id = $morder->code; //TODO - The unique transaction ID for reference
		$email = $morder->Email; //TODO - Customer email for record
		$referrer = $_COOKIE['ap_id'];
		do_action('wp_affiliate_process_cart_commission', array("referrer" => $referrer, "sale_amt" =>$sale_amt, "txn_id"=>$unique_transaction_id, "buyer_email"=>$email));
		
		//save affiliate id in order
		$morder->affiliate_id = $referrer;
		$morder->saveOrder();
	}
}
add_action("pmpro_after_checkout", "wpa_pmpro_after_checkout");
function wpa_pmpro_add_order($morder)
{	
	if(!empty($morder->total))
	{
		$sale_amt = $morder->total; //TODO - The commission will be calculated based on this amount
		$unique_transaction_id = $morder->code; //TODO - The unique transaction ID for reference
		$muser = get_userdata($morder->user_id);
		$email = $muser->user_email; //TODO - Customer email for record
		
		//need to get the last order before this
		$last_order = new MemberOrder();
		$last_order->getLastMemberOrder($morder->user_id);
				
		if(!empty($last_order->affiliate_id))
		{		
			//perform
			$referrer = $last_order->affiliate_id;			
						
			do_action('wp_affiliate_process_cart_commission', array("referrer" => $referrer, "sale_amt" =>$sale_amt, "txn_id"=>$unique_transaction_id, "buyer_email"=>$email));
			
			//update the affiliate id for this order
			global $wpa_pmpro_affiliate_id;
			$wpa_pmpro_affiliate_id = $referrer;
		}		
	}
}
add_action("pmpro_add_order", "wpa_pmpro_add_order");
function wpa_pmpro_added_order($morder)
{
	global $wpa_pmpro_affiliate_id;
		
	if(!empty($wpa_pmpro_affiliate_id))
	{
		$morder->affiliate_id = $wpa_pmpro_affiliate_id;
		$morder->saveOrder();				
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
