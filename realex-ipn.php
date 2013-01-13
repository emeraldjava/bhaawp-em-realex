<?php
function record_transaction($EM_Booking, $amount, $currency, $timestamp, $txn_id, $status, $note) {
	global $wpdb;
	$data = array();
	$data['booking_id'] = $EM_Booking->booking_id;
	$data['transaction_gateway_id'] = $txn_id;
	$data['transaction_timestamp'] = $timestamp;
	$data['transaction_currency'] = $currency;
	$data['transaction_status'] = $status;
	$data['transaction_total_amount'] = $amount;
	$data['transaction_note'] = $note;
	$data['transaction_gateway'] = "realex_redirect";

	if ( !empty($txn_id) ) {
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT transaction_id, transaction_status, transaction_gateway_id, transaction_total_amount FROM ".EM_TRANSACTIONS_TABLE." WHERE transaction_gateway = %s AND transaction_gateway_id = %s", "realex_redirect", $txn_id ) );
	}
	$table = EM_TRANSACTIONS_TABLE;
	if ( is_multisite() && !EM_MS_GLOBAL && !empty($EM_Event->blog_id) && !is_main_site($EM_Event->blog_id) ) {
		//we must get the prefix of the transaction table for this event's blog if it is not the root blog
		$table = $wpdb->get_blog_prefix($EM_Event->blog_id).'em_transactions';
	}
	if ( !empty($existing->transaction_gateway_id) && $amount == $existing->transaction_total_amount && $status != $existing->transaction_status ) {
		// Update only if txn id and amounts are the same (e.g. pending payments changing status)
		$wpdb->update( $table, $data, array('transaction_id' => $existing->transaction_id) );
	} else {
		// Insert
		$wpdb->insert( $table, $data );
	}
}
function bhaa_event_email($fail,$subscription,$membertype,$event_details){
	//in here we can clearly see where the emails are going.
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
	$headers .= 'From: BHAA Events <events@bhaa.ie>' . "\r\n";
	$to = $event_details["user_email"];
	$who = $event_details["user_name"];
	$where = $event_details["event_location"];
	$when = $event_details["event_time"];
	$amount=$event_details["amount"];
	$event_name = $event_details["event_name"];
	if($subscription==true){//! we have a subscription
		$subject="BHAA Annual Membership";
		switch($fail){
			case 00://sucessful payment
				switch($membertype){
					case "M":
					case "D":
					case "I":
					default:
					$heading='Annual Membership';
					$messageForType='
								<p>Because of your subscription, you can now avail of a reduced fee
								for all <a href="http://bhaa.ie/events/">BHAA
								events</a>.</p>';
					break;
				}
			break;
			case 101:
			case 102://payment has been denied
				$heading='Annual Membership';
				$messageForType='
								<p>Your subscription payment was denied, please go to <a href="http://wpdemo.bhaa.ie/events/">BHAA
								events</a> to try again.</p>';
				$amount="0.00";
			break;
			default;//payment not taken, for another reason 
				$heading='Annual Membership';
				$messageForType='
								<p>Your subscription payment was unsuccessful, please go to <a href="http://wpdemo.bhaa.ie/events/">BHAA
								events</a> to try again.</p>';
				$amount="0.00";
			break;
		}
	} else {//! Simple email for an event
		$subject="BHAA Event Booking - ".$event_name;
		switch($fail){
			case 00://sucessful payment
				switch($membertype){
					case "M":
					case "D":
					case "I":
					default:
					$heading='Event Booking';
					$messageForType='
								<p>Your booking for '.$event_name.', has been accepted and paid for. Thank you.</p>';
					break;
				}
			break;
			case 101:
			case 102://payment has been denied
				$heading='Event Booking';
				$messageForType='
								<p>Your booking payment for '.$event_name.', was denied. Please go to <a href="http://bhaa.ie/events/">BHAA
								events</a> to try again.</p>';
				$amount="0.00";
			break;
			default;//payment not taken, for another reason 
				$heading='Event Booking';
				$messageForType='
								<p>Your booking payment for '.$event_name.', was unsuccessful. Please go to <a href="http://bhaa.ie/events/">BHAA
								events</a> to try again.</p>';
				$amount="0.00";
			break;
		}
	}
	
// Set up the email template, then send.
	$message='<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <title></title>
</head>
<body>
    <div style="">
        <table cellspacing="0" cellpadding="8" border="0" summary="" style="width:100%;font-family:Arial,Sans-serif;border:1px Solid #ccc;border-width:1px 2px 2px 1px;background-color:#fff;">
            <tr> 
            	<td>
                    <div style="padding:2px">
            		<img class="bhaa-logo" src="http://wpdemo.bhaa.ie/wp-content/themes/BHAA/images/logo.png" width="194" height="200" alt="BHAA Logo" style="float:left;padding:0 20px">
                        <h3 style="padding:0 0 6px 0;margin:0;font-family:Arial,Sans-serif;font-size:16px;font-weight:bold;color:#222">'.$heading.'</h3>
                        <table cellpadding="0" cellspacing="0" border="0" summary="Event details">
                        ';
                        if($subscription==true){//don't show When/where details when not applicable
                        $message.='
                            <tr>
                                <td style="padding:0 1em 10px 0;font-family:Arial,Sans-serif;font-size:13px;color:#888;white-space:nowrap" valign="top">
                                    <div>
                                        <i style="font-style:normal">When</i>
                                    </div>
                                </td>
                                <td style="padding-bottom:10px;font-family:Arial,Sans-serif;font-size:13px;color:#222" valign="top">'.$when.'</td>
                            </tr>

                            <tr>
                                <td style="padding:0 1em 10px 0;font-family:Arial,Sans-serif;font-size:13px;color:#888;white-space:nowrap" valign="top">
                                    <div>
                                        <i style="font-style:normal">Where</i>
                                    </div>
                                </td>
                                <td style="padding-bottom:10px;font-family:Arial,Sans-serif;font-size:13px;color:#222" valign="top">'.$where.'</td>
                            </tr>';
                        }
                        $message.='
                            <tr>
                                <td style="padding:0 1em 10px 0;font-family:Arial,Sans-serif;font-size:13px;color:#888;white-space:nowrap" valign="top">
                                    <div>
                                        <i style="font-style:normal">Price</i>
                                    </div>
                                </td>
                                <td style="padding-bottom:10px;font-family:Arial,Sans-serif;font-size:13px;color:#222" valign="top"> &euro;'.$amount.'</td>
                            </tr>
                          
                            <tr>
                                <td style="padding:0 1em 10px 0;font-family:Arial,Sans-serif;font-size:13px;color:#888;white-space:nowrap" valign="top">
                                    <div>
                                        <i style="font-style:normal">Who</i>
                                    </div>
                                </td>
                                <td style="padding-bottom:10px;font-family:Arial,Sans-serif;font-size:13px;color:#222" valign="top">'.$who.'</td>
                            </tr>
                            
                            <tr>
                                <td style="padding:0 1em 10px 0;font-family:Arial,Sans-serif;font-size:13px;color:#888;white-space:nowrap" valign="top">
                                    <div>
                                        <i style="font-style:normal">Notes</i>
                                    </div>
                                </td>
                                <td style="padding-bottom:10px;font-family:Arial,Sans-serif;font-size:13px;color:#222" valign="top">'.$messageForType.'</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
            <tr>
                <td style="background-color:#f6f6f6;color:#888;border-top:1px Solid #ccc;font-family:Arial,Sans-serif;font-size:11px">
                    <p>Reminder from <a href="https://www.bhaa.ie/" target="_blank" style="">Business Houses Athletic Association</a></p>

                    <p>You are receiving this email at the account '.$to.' because you just used the BHAA payments system.</p>
                </td><td style="background-color:#f6f6f6;color:#888;border-top:1px Solid #ccc;font-family:Arial,Sans-serif;font-size:11px">
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

	';
	return mail($to, $subject, $message, $headers);
}
require_once ("wp-config.php");
$timestamp = $_POST['TIMESTAMP'];
$orderid = $_POST['ORDER_ID'];
$result = $_POST['RESULT'];
$message = $_POST['MESSAGE'];
$pasref = $_POST['PASREF'];
$authcode = $_POST['AUTHCODE'];
$realexmd5 = $_POST['MD5HASH'];
$user_id = $_POST['uid'];
$merchantid = get_option( 'em_realex_redirect_merchant_id', "FALSE" );
$secret = get_option( 'em_realex_redirect_merchant_secret', "FALSE" );
$account = get_option( 'em_realex_redirect_merchant_account', "internet" );
//---------------------------------------------------------------
$tmp = "$timestamp.$merchantid.$orderid.$result.$message.$pasref.$authcode";
$md5hash = md5($tmp);
$tmp = "$md5hash.$secret";
$md5hash = md5($tmp);
$drt = explode(":", $_POST['drt']);//days,role,type
//Check to see if hashes match or not
$ip=$_POST['REMOTE_ADDR'];
$out='<!DOCTYPE html>
<html>
	<head>
		<title>BHAA Realex IPN</title>
		<meta charset="utf-8">
	</head>
	<body>';
$msg="";
if (!file_exists('../logs')) {
	mkdir('../logs');
	$msg='$status,$user_id,$orderid,$timestamp,$ip'.PHP_EOL;
}
if(!file_exists('../logs/ipn.csv')){
	$msg='$status,$user_id,$orderid,$timestamp,$ip'.PHP_EOL;
}
if ($timestamp) {
	$timestamp = date('Y-m-d', strtotime($timestamp));
} else {
	$timestamp = date('Y-m-d');
}
if ($md5hash != $realexmd5) {
	$out.="<p>hashes don't match - response not authenticated!<br />This is not an authenticated Realex Request.</p>";
	file_put_contents('../logs/ipn.csv', $msg."-100000,$user_id,$orderid,$timestamp,$ip".PHP_EOL, FILE_APPEND);
} else {
	// md5 is correct. authorised response from RealEx Servers
	file_put_contents('../logs/ipn.csv', $msg."$result,$user_id,$orderid,$timestamp,$ip".PHP_EOL, FILE_APPEND);
	//switch the result
	$new_status = false;
	//Common variables
	$amount = ($_POST['AMOUNT']/100);
	$currency = $_POST['CURRENCY'];
	$custom_values = explode(':', $_POST['booking_id']);
	$booking_id = !empty($custom_values[0]) ? $custom_values[0]:$mem;
	$event_id = !empty($custom_values[1]) ? $custom_values[1]:0;
	$EM_Booking = new EM_Booking($booking_id);
	$SUB = strtolower($custom_values[2])=="true" ? true:false;
	if ( !empty($EM_Booking->booking_id) && count($custom_values) == 3 ) {
		//booking exists
		$EM_Booking->manage_override = true; //since we're overriding the booking ourselves.
		$user_id = $EM_Booking->person_id;
		// process realex_redirect response
		
		$membertype = get_user_meta($user_id,"bhaa_runner_status",true);
		if(trim($membertype)==""){
			$membertype="D";
		}
		
		
		switch ($result) {
		case '00':// case: successful payment
			$note="Successful Payment";
			record_transaction($EM_Booking, $amount, $currency, $timestamp, $_POST['order_id'], $result, $note);
			$user_data = array();
			if ( !empty($EM_Booking->booking_meta['registration']) && is_array($EM_Booking->booking_meta['registration']) ) {
				foreach ($EM_Booking->booking_meta['registration'] as $fieldid => $field) {
					if ( trim($field) !== '' ) {
						$user_data[$fieldid] = $field;
					}
				}
			}
			if ( $amount >= $EM_Booking->get_price(false, false, true) && (!get_option('em_realex_redirect_manual_approval', false) || !get_option('dbem_bookings_approval')) ) {
				$EM_Booking->approve(true, true); //approve and ignore spaces
			} else {
				$EM_Booking->set_status(0); //Set back to normal "pending"
			}
			do_action('em_payment_processed', $EM_Booking, $this);
			
			
			
			
			//! START CUSTOM TICKET HANDLING AREA
			
			switch($membertype){
			case "D":
			case "M":
			case "I":
			default:
				foreach($EM_Booking->tickets as $ticket){
					switch(strtolower($ticket->ticket_name)){
						case "annual membership"://process membership
							update_user_meta( $user_id, "bhaa_runner_status", "M");//! update to an annual role
							update_user_meta( $user_id, "bhaa_runner_dateofrenewal", $timestamp);//! date of new membership
							break;
						case "annual member":
						case "day member":
						case "inactive member":
						default:
						//process ticket specific actions here
							break;
					}
				}
			
				break;
			}
			
			//! END CUSTOM TICKET HANDLING AREA
			
			
			
			
			
			$out.='<p>Thank you, your payment has been successful.<br /><br />To continue browsing please <a href="http://bhaa.ie"><b>return to the bhaa site</b></a><br /><br /></p>';
			break;
		case '101':
		case '102':// case: denied
			$note = 'Last transaction has been reversed. Reason: Payment Denied';
			record_transaction($EM_Booking, $amount, $currency, $timestamp, $_POST['order_id'], $result, $note);
			$EM_Booking->cancel();
			do_action('em_payment_denied', $EM_Booking, $this);
			$out .= '<p>There was an error processing your payment.<br />To try again please <a href="http://bhaa.ie/events">go back to the bhaa event page.</a></p>';
			break;
		default:
			$out .= '<p>There was an error processing your payment.<br />To try again please <a href="http://bhaa.ie/events">go back to the bhaa event page.</a></p>';
		}
		
		//! start email handler
		$EM_Event = new EM_Event($event_id);
		$user_data= get_userdata($user_id);
		$EM_Location = em_get_location($EM_Event->location_id);
		$event_details = array(
			"user_id" => $user_id,
			"user_email" => $user_data->user_email,
			"user_name" => $user_data->user_firstname." ".$user_data->user_lastname,
			"amount" => $amount,
			"event_name" => $EM_Event->event_name,
			"event_location" => $EM_Location->location_name,
			"event_time" => $EM_Event->event_start_date.": ".$EM_Event->event_start_time." - ".$EM_Event->event_end_time,
			"event_date" => $EM_Event->time,
			"event_id" => $event_id
		);

//! EMAIL WILL NOT BE SENT BECAUSE THE NEXT LINES ARE COMMENTED
//! EMAIL WILL NOT BE SENT BECAUSE THE NEXT LINES ARE COMMENTED
//! EMAIL WILL NOT BE SENT BECAUSE THE NEXT LINES ARE COMMENTED
//! EMAIL WILL NOT BE SENT BECAUSE THE NEXT LINES ARE COMMENTED
//! EMAIL WILL NOT BE SENT BECAUSE THE NEXT LINES ARE COMMENTED
//! EMAIL WILL NOT BE SENT BECAUSE THE NEXT LINES ARE COMMENTED
//! EMAIL WILL NOT BE SENT BECAUSE THE NEXT LINES ARE COMMENTED
//! EMAIL WILL NOT BE SENT BECAUSE THE NEXT LINES ARE COMMENTED


		// if(bhaa_event_email($result,$SUB,$membertype,$event_details)){
		// 	$out.='<p><small>You have been sent an email to the account: '.$event_details["user_email"].' to confirm these details.</small></p>';
		// }
	//! end email handler

	}else {
		// ! has been accepted before
		if ( $result == "00" ) {
			$message = apply_filters('em_gateway_realex_redirect_bad_booking_email', "
A Payment has been received by realex for a non-existent booking.

Event Details : %event%

It may be that this user's booking has timed out yet they proceeded with payment at a later stage.

To refund this transaction, you must go to your realex account and search for this transaction:

Transaction ID : %transaction_id%
Email : %payer_email%

When viewing the transaction details, you should see an option to issue a refund.

If there is still space available, the user must book again.

Sincerely,
BHAA Events Manager
			", $booking_id, $event_id);
			if ( !empty($event_id) ) {
				$EM_Event = new EM_Event($event_id);
				$event_details = $EM_Event->name . " - " . date_i18n(get_option('date_format'), $EM_Event->start);
			}else { $event_details = __('Unknown', 'em-pro'); }
			$message  = str_replace(array('%transaction_id%', '%payer_email%', '%event%'), array($_POST['order_id'], $_POST['payer_email'], $event_details), $message);
			wp_mail(get_option('em_realex_redirect_email' ), __('Unprocessed payment needs refund'), $message);
			$out .='Error: This payment has been received, but we do not know what booking it is for. Management has been notified. The booking number is :'.$EM_Booking->booking_id;
		}else {
			$out .='Error: Bad IPN request, custom ID does not correspond with any pending booking. Payment was not taken.';
		}
	}
}
$out.="
	</body>
</html>";
echo $out;

?>