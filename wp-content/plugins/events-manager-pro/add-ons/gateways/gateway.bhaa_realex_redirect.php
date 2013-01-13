<?php
class EM_Gateway_realex_redirect extends EM_Gateway {
	//change these properties below if creating a new gateway, not advised to change this for realex_redirect
	var $gateway = 'realex_redirect';
	var $title = 'Realex Redirect';
	var $status = 4;
	var $status_txt = 'Awaiting realex Payment';
	var $button_enabled = true;
	var $payment_return = true;

	/**
	 * Sets up gateaway and adds relevant actions/filters
	 */
	function __construct() {
		parent::__construct();
		$this->status_txt = __('Awaiting realex Payment', 'em-pro');
		if ($this->is_active()) {
			//Booking Interception
			if ( absint(get_option('em_'.$this->gateway.'_booking_timeout')) > 0 ) {
				//Modify spaces calculations only if bookings are set to time out, in case pending spaces are set to be reserved.
				add_filter('em_bookings_get_pending_spaces', array(&$this, 'em_bookings_get_pending_spaces'), 1, 2);
			}
			add_action('em_gateway_js', array(&$this, 'em_gateway_js'));
			//Gateway-Specific
			add_action('em_template_my_bookings_header', array(&$this, 'say_thanks')); //say thanks on my_bookings page
			add_filter('em_bookings_table_booking_actions_4', array(&$this, 'bookings_table_actions'), 1, 2);
			add_filter('em_my_bookings_booking_actions', array(&$this, 'em_my_bookings_booking_actions'), 1, 2);
			//set up cron
			$timestamp = wp_next_scheduled('emp_cron_hook');
			if ( absint(get_option('em_realex_redirect_booking_timeout')) > 0 && !$timestamp ) {
				$result = wp_schedule_event(time(), 'em_minute', 'emp_cron_hook');
			}elseif ( !$timestamp ) {
				wp_unschedule_event($timestamp, 'emp_cron_hook');
			}
		}else {
			//unschedule the cron
			$timestamp = wp_next_scheduled('emp_cron_hook');
			wp_unschedule_event($timestamp, 'emp_cron_hook');
		}
	}

	/*
	 * --------------------------------------------------
	 * Booking Interception - functions that modify booking object behaviour
	 * --------------------------------------------------
	 */

	/**
	 * Modifies pending spaces calculations to include realex_redirect bookings, but only if realex_redirect bookings are set to time-out (i.e. they'll get deleted after x minutes), therefore can be considered as 'pending' and can be reserved temporarily.
	 * @param integer $count
	 * @param EM_Bookings $EM_Bookings
	 * @return integer
	 */
	function em_bookings_get_pending_spaces($count, $EM_Bookings) {
		foreach ($EM_Bookings->bookings as $EM_Booking) {
			if ($EM_Booking->booking_status == $this->status && $this->uses_gateway($EM_Booking)) {
				$count += $EM_Booking->get_spaces();
			}
		}
		return $count;
	}

	/**
	 * Intercepts return data after a booking has been made and adds realex_redirect vars, modifies feedback message.
	 * @param array $return
	 * @param EM_Booking $EM_Booking
	 * @return array
	 */
	function booking_form_feedback( $return, $EM_Booking = false ) {
		//Double check $EM_Booking is an EM_Booking object and that we have a booking awaiting payment.
		if ( is_object($EM_Booking) && $this->uses_gateway($EM_Booking) ) {
			if ( !empty($return['result']) && $EM_Booking->get_price() > 0 && $EM_Booking->booking_status == $this->status ) {
				$return['message'] = get_option('em_realex_redirect_booking_feedback');
				$realex_redirect_url = $this->get_realex_redirect_url();
				$realex_redirect_vars = $this->get_realex_redirect_vars($EM_Booking);
				$realex_redirect_return = array('realex_redirect_url'=>$realex_redirect_url, 'realex_redirect_vars'=>$realex_redirect_vars);
				$return = array_merge($return, $realex_redirect_return);
			}else {
				//returning a free message
				$return['message'] = get_option('em_realex_redirect_booking_feedback_free');
			}
		}
		return $return;
	}

	/**
	 * Called if AJAX isn't being used, i.e. a javascript script failed and forms are being reloaded instead.
	 * @param string $feedback
	 * @return string
	 */
	function booking_form_feedback_fallback( $feedback ) {
		global $EM_Booking;
		if ( is_object($EM_Booking) ) {
			$feedback = "<br />" . __('Javascript is required for event bookings on this site. Please reload with Javascript enabled.', 'dbem'). $this->em_my_bookings_booking_actions('', $EM_Booking);
		}
		return $feedback;
	}

	/**
	 * Triggered by the em_booking_add_yourgateway action, hooked in EM_Gateway. Overrides EM_Gateway to account for non-ajax bookings (i.e. broken JS on site).
	 * @param EM_Event $EM_Event
	 * @param EM_Booking $EM_Booking
	 * @param boolean $post_validation
	 */
	function booking_add($EM_Event, $EM_Booking, $post_validation = false) {
		parent::booking_add($EM_Event, $EM_Booking, $post_validation);
		if ( !defined('DOING_AJAX') ) { //we aren't doing ajax here, so we should provide a way to edit the $EM_Notices ojbect.
			add_action('option_dbem_booking_feedback', array(&$this, 'booking_form_feedback_fallback'));
		}
	}
	/*
	 * --------------------------------------------------
	 * Booking UI - modifications to booking pages and tables containing realex_redirect bookings
	 * --------------------------------------------------
	 */
	/**
	 * Instead of a simple status string, a resume payment button is added to the status message so user can resume booking from their my-bookings page.
	 * @param string $message
	 * @param EM_Booking $EM_Booking
	 * @return string
	 */
	function em_my_bookings_booking_actions( $message, $EM_Booking) {
		global $wpdb;
		if ($this->uses_gateway($EM_Booking) && $EM_Booking->booking_status == $this->status) {
			//first make sure there's no pending payments
			$pending_payments = $wpdb->get_var('SELECT COUNT(*) FROM '.EM_TRANSACTIONS_TABLE. " WHERE booking_id='{$EM_Booking->booking_id}' AND transaction_gateway='{$this->gateway}' AND transaction_status='Pending'");
			if ( count($pending_payments) == 0 ) {
				//user owes money!
				$realex_redirect_vars = $this->get_realex_redirect_vars($EM_Booking);
				$form = '<form action="'.$this->get_realex_redirect_url().'" method="post">';
				foreach ($realex_redirect_vars as $key=>$value) {
					$form .= '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
				}
				$form .= '<input type="submit" value="'.__('Resume Payment', 'em-pro').'">';
				$form .= '</form>';
				$message = $form;
			}
		}
		return $message;
	}
	/**
	 * Outputs extra custom content e.g. the realex_redirect logo by default.
	 */
	function booking_form() {
		echo get_option('em_'.$this->gateway.'_form');
	}
	/**
	 * Outputs some JavaScript during the em_gateway_js action, which is run inside a script html tag, located in gateways/gateway.realex.js
	 */
	function em_gateway_js() {
		include dirname(__FILE__).'/gateway.bhaa_realex_redirect.js';
	}
	/**
	 * Adds relevant actions to booking shown in the bookings table
	 * @param EM_Booking $EM_Booking
	 */
	function bookings_table_actions( $actions, $EM_Booking ) {
		return array(
			'approve' => '<a class="em-bookings-approve em-bookings-approve-offline" href="'.em_add_get_params($_SERVER['REQUEST_URI'], array('action'=>'bookings_approve', 'booking_id'=>$EM_Booking->booking_id)).'">'.__('Approve', 'dbem').'</a>',
			'delete' => '<span class="trash"><a class="em-bookings-delete" href="'.em_add_get_params($_SERVER['REQUEST_URI'], array('action'=>'bookings_delete', 'booking_id'=>$EM_Booking->booking_id)).'">'.__('Delete', 'dbem').'</a></span>',
			'edit' => '<a class="em-bookings-edit" href="'.em_add_get_params($EM_Booking->get_event()->get_bookings_url(), array('booking_id'=>$EM_Booking->booking_id, 'em_ajax'=>null, 'em_obj'=>null)).'">'.__('Edit/View', 'dbem').'</a>',
		);
	}
	/*
	 * --------------------------------------------------
	 * realex_redirect Functions - functions specific to realex_redirect payments
	 * --------------------------------------------------
	 */
	/**
	 * Retreive the realex_redirect vars needed to send to the gatway to proceed with payment
	 * @param EM_Booking $EM_Booking
	 */
	function get_realex_redirect_vars($EM_Booking) {//! get realex_redirect vars
		global $wp_rewrite, $EM_Notices;
		$price = 0;
		$sub="false";
		foreach ( $EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Booking ) {
			$price += $EM_Ticket_Booking->get_ticket()->get_price();
			if (strtolower($EM_Ticket_Booking->get_ticket()->ticket_name)=="annual membership") {
				$sub='true';//if any are memberships, we've got a subscription on our hands!
			}
		}
		$price = $price*100;
		$price = floor($price);//rounds then converts to cents.
		//price is full now, and in lowest unit

		//The code below is used to create the timestamp format required by realex_redirect Payments
		$timestamp = strftime("%Y%m%d%H%M%S");
		$merchantid = get_option('em_'.$this->gateway."_merchant_id" );
		$merchantaccount = get_option('em_'.$this->gateway."_merchant_account" );
		$uid=get_current_user_id();
		$orderid = "$uid-$timestamp";
		$currency=get_option('dbem_bookings_currency', 'EUR');

		$secret = get_option('em_'.$this->gateway.'_merchant_secret');
		$tmp = "$timestamp.$merchantid.$orderid.$price.$currency";
		$md5hash = md5($tmp);
		$tmp = "$md5hash.$secret";
		$md5hash = md5($tmp);
		//set $_POST vars to be sent
		$realex_redirect_vars = array(
			'TIMESTAMP'=> $timestamp,
			'MERCHANT_ID' => $merchantid ,
			'ORDER_ID' => $orderid,
			'ACCOUNT' => $merchantaccount,
			'CURRENCY' => $currency,
			'CUST_NUM' => $uid,
			'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'],
			'COMMENT1' => "Booking ".$EM_Booking->booking_id." for event ".$EM_Booking->event_id,
			'COMMENT2' => "You're booking for event ".$EM_Booking->event->event_name,
			'booking_id' => $EM_Booking->booking_id.':'.$EM_Booking->event_id.':'.$sub,
			'uid' => $uid,
			'AUTO_SETTLE_FLAG'=> 1
		);
		$realex_redirect_vars['MD5HASH']=$md5hash;
		$realex_redirect_vars['AMOUNT']= $price;
		$realex_redirect_vars['price']= $price;
		return apply_filters('em_gateway_realex_redirect_get_realex_redirect_vars', $realex_redirect_vars, $EM_Booking, $this);
	}
	/**
	 * gets realex_redirect gateway url (sandbox or live mode)
	 * @returns string
	 */
	function get_realex_redirect_url() {
		return 'https://epage.payandshop.com/epage.cgi';
	}
	function say_thanks() {
		if ( $_REQUEST['thanks'] == 1 ) {
			echo "<div class='em-booking-message em-booking-message-success'>".get_option('em_'.$this->gateway.'_booking_feedback_thanks').'</div>';
		}
	}
	/**
	 * Runs when realex_redirect sends IPNs to the return URL provided during bookings and EM setup. Bookings are updated and transactions are recorded accordingly.
	 */
	function handle_payment_return() {
		// !realex_redirect IPN handling code
		$new_status = false;
		//Common variables
		$amount = $_POST['AMOUNT'];
		$currency = $_POST['CURRENCY'];
		$timestamp = date('Y-m-d H:i:s', strtotime($_POST['payment_date']));
		$custom_values = explode(':', $_POST['booking_id']);
		$booking_id = $custom_values[0];
		$event_id = !empty($custom_values[1]) ? $custom_values[1]:0;
		$EM_Booking = new EM_Booking($booking_id);
		if ( !empty($EM_Booking->booking_id) && count($custom_values) == 2 ) {
			//booking exists
			$EM_Booking->manage_override = true; //since we're overriding the booking ourselves.
			$user_id = $EM_Booking->person_id;

			// process realex_redirect response
			switch ($result) {
			case '00':
				// case: successful payment
				$this->record_transaction($EM_Booking, $amount, $currency, $timestamp, $_POST['order_id'], $result, '');
				//get booking metadata
				$user_data = array();
				if ( !empty($EM_Booking->booking_meta['registration']) && is_array($EM_Booking->booking_meta['registration']) ) {
					foreach ($EM_Booking->booking_meta['registration'] as $fieldid => $field) {
						if ( trim($field) !== '' ) {
							$user_data[$fieldid] = $field;
						}
					}
				}
				if ( $_POST['AMOUNT'] >= $EM_Booking->get_price(false, false, true) && (!get_option('em_realex_redirect_manual_approval', false) || !get_option('dbem_bookings_approval')) ) {
					$EM_Booking->approve(true, true); //approve and ignore spaces
				}else {
					//TODO do something if pp payment not enough
					$EM_Booking->set_status(0); //Set back to normal "pending"
				}
				do_action('em_payment_processed', $EM_Booking, $this);
				break;
			case '101':
			case '102':
			default:
				// case: denied
				$note = 'Last transaction has been reversed. Reason: Payment Denied';
				$this->record_transaction($EM_Booking, $amount, $currency, $timestamp, $_POST['order_id'], $result, $note);

				$EM_Booking->cancel();
				do_action('em_payment_denied', $EM_Booking, $this);
				break;
			}
		}else {
			// ! everything worked!! process payment
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
				wp_mail(get_option('em_'. $this->gateway . "_email" ), __('Unprocessed payment needs refund'), $message);
			}else {
				//header('Status: 404 Not Found');
				echo 'Error: Bad IPN request, custom ID does not correspond with any pending booking.';
				//echo "<pre>"; print_r($_POST); echo "</pre>";
				exit;
			}
		}
		//fclose($log);
	}

	/*
	 * --------------------------------------------------
	 * Gateway Settings Functions
	 * --------------------------------------------------
	 */

	/**
	 * Outputs custom realex_redirect setting fields in the settings page
	 */
	function mysettings() {//! settings page
		global $EM_options;
?>
		<table class="form-table">
		<tbody>
		  <tr valign="top">
			  <th scope="row"><?php _e('Success Message', 'em-pro') ?></th>
			  <td>
			  	<input type="text" name="<?php echo $this->gateway; ?>_booking_feedback" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback" )); ?>" style='width: 40em;' /><br />
			  	<em><?php _e('The message that is shown to a user when a booking is successful whilst being redirected to realex for payment.', 'em-pro'); ?></em>
			  </td>
		  </tr>
		  <tr valign="top">
			  <th scope="row"><?php _e('Success Free Message', 'em-pro') ?></th>
			  <td>
			  	<input type="text" name="<?php echo $this->gateway; ?>_booking_feedback_free" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback_free" )); ?>" style='width: 40em;' /><br />
			  	<em><?php _e('If some cases if you allow a free ticket (e.g. pay at gate) as well as paid tickets, this message will be shown and the user will not be redirected to realex.', 'em-pro'); ?></em>
			  </td>
		  </tr>
		  <tr valign="top">
			  <th scope="row"><?php _e('Thank You Message', 'em-pro') ?></th>
			  <td>
			  	<input type="text" name="<?php echo $this->gateway; ?>_booking_feedback_thanks" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_feedback_thanks" )); ?>" style='width: 40em;' /><br />
			  	<em><?php _e('If you choose to return users to the default Events Manager thank you page after a user has paid on realex, you can customize the thank you message here.', 'em-pro'); ?></em>
			  </td>
		  </tr>
		</tbody>
		</table>
		<h3><?php echo sprintf(__('%s Options', 'em-pro'), 'realex_redirect'); ?></h3>
		<p><?php echo __('<strong>Important:</strong>In order to connect realex with your site, you need to enable IPN on your account.'); echo " ". sprintf(__('Your return url is %s', 'em-pro'), '<code>'.$this->get_payment_return_url().'</code>'); ?></p>
		<p><?php echo sprintf(__('Please visit the <a href="%s">documentation</a> for further instructions.', 'em-pro'), 'http://wp-events-plugin.com/documentation/'); ?></p>
		<table class="form-table">
		<tbody>
		  <tr valign="top">
			  <th scope="row"><?php _e('realex_redirect Merchant ID', 'em-pro') ?></th>
				  <td><input type="text" name="<?php echo $this->gateway; ?>_merchant_id" value="<?php esc_attr_e( get_option('em_'. $this->gateway . "_merchant_id" )); ?>" />
				  <br />
			  </td>
		  </tr>
		  <tr valign="top">
			  <th scope="row"><?php _e('realex_redirect Merchant Secret', 'em-pro') ?></th>
				  <td><input type="text" name="<?php echo $this->gateway; ?>_merchant_secret" value="<?php esc_attr_e( get_option('em_'. $this->gateway . "_merchant_secret" )); ?>" />
				  <br />
			  </td>
		  </tr>
		  <tr valign="top">
			  <th scope="row"><?php _e('realex_redirect Merchant Account', 'em-pro') ?></th>
				  <td><input type="text" name="<?php echo $this->gateway; ?>_merchant_account" value="<?php esc_attr_e( get_option('em_'. $this->gateway . "_merchant_account" )); ?>" />
				  <br />
			  </td>
		  </tr>
		  <tr valign="top">
			  <th scope="row"><?php _e('realex_redirect Currency', 'em-pro') ?></th>
			  <td><?php echo esc_html(get_option('dbem_bookings_currency', 'EUR')); ?><br /><i><?php echo sprintf(__('Set your currency in the <a href="%s">settings</a> page.', 'dbem'), EM_ADMIN_URL.'&amp;page=events-manager-options'); ?></i></td>
		  </tr>
		  <tr valign="top">
			  <th scope="row"><?php _e('Delete Bookings Pending Payment', 'em-pro') ?></th>
			  <td>
			  	<input type="text" name="<?php echo $this->gateway; ?>_booking_timeout" style="width:50px;" value="<?php esc_attr_e(get_option('em_'. $this->gateway . "_booking_timeout" )); ?>" style='width: 40em;' /> <?php _e('minutes', 'em-pro'); ?><br />
			  	<em><?php _e('Once a booking is started and the user is taken to realex_redirect, Events Manager stores a booking record in the database to identify the incoming payment. These spaces may be considered reserved if you enable <em>Reserved unconfirmed spaces?</em> in your Events &gt; Settings page. If you would like these bookings to expire after x minutes, please enter a value above (note that bookings will be deleted, and any late payments will need to be refunded manually via realex_redirect).', 'em-pro'); ?></em>
			  </td>
		  </tr>
		  <tr valign="top">
			  <th scope="row"><?php _e('Manually approve completed transactions?', 'em-pro') ?></th>
			  <td>
			  	<input type="checkbox" name="<?php echo $this->gateway; ?>_manual_approval" value="1" <?php echo (get_option('em_'. $this->gateway . "_manual_approval" )) ? 'checked="checked"':''; ?> /><br />
			  	<em><?php _e('By default, when someone pays for a booking, it gets automatically approved once the payment is confirmed. If you would like to manually verify and approve bookings, tick this box.', 'em-pro'); ?></em><br />
			  	<em><?php echo sprintf(__('Approvals must also be required for all bookings in your <a href="%s">settings</a> for this to work properly.', 'em-pro'), EM_ADMIN_URL.'&amp;page=events-manager-options'); ?></em>
			  </td>
		  </tr>
		</tbody>
		</table>
		<?php
	}

	/*
	 * Run when saving realex_redirect settings, saves the settings available in EM_Gateway_realex_redirect::mysettings()
	 */
	function update() {
		parent::update();
		$gateway_options = array(
			$this->gateway . "_email" => $_REQUEST[ $this->gateway.'_email' ],
			$this->gateway . "_site" => $_REQUEST[ $this->gateway.'_site' ],
			$this->gateway . "_merchant_id" => $_REQUEST[ $this->gateway.'_merchant_id' ],
			$this->gateway . "_merchant_secret" => $_REQUEST[ $this->gateway.'_merchant_secret' ],
			$this->gateway . "_merchant_account" => $_REQUEST[ $this->gateway.'_merchant_account' ],
			$this->gateway . "_currency" => $_REQUEST[ 'currency' ],
			$this->gateway . "_tax" => $_REQUEST[ $this->gateway.'_button' ],
			$this->gateway . "_format_logo" => $_REQUEST[ $this->gateway.'_format_logo' ],
			$this->gateway . "_format_border" => $_REQUEST[ $this->gateway.'_format_border' ],
			$this->gateway . "_manual_approval" => $_REQUEST[ $this->gateway.'_manual_approval' ],
			$this->gateway . "_booking_feedback" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback' ]),
			$this->gateway . "_booking_feedback_free" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback_free' ]),
			$this->gateway . "_booking_feedback_thanks" => wp_kses_data($_REQUEST[ $this->gateway.'_booking_feedback_thanks' ]),
			$this->gateway . "_booking_timeout" => $_REQUEST[ $this->gateway.'_booking_timeout' ],
			$this->gateway . "_return" => $_REQUEST[ $this->gateway.'_return' ],
			$this->gateway . "_cancel_return" => $_REQUEST[ $this->gateway.'_cancel_return' ],
			$this->gateway . "_form" => $_REQUEST[ $this->gateway.'_form' ]
		);
		foreach ($gateway_options as $key=>$option) {
			update_option('em_'.$key, stripslashes($option));
		}
		//default action is to return true
		return true;

	}
}
EM_Gateways::register_gateway('realex_redirect', 'EM_Gateway_realex_redirect');

/**
 * Deletes bookings pending payment that are more than x minutes old, defined by realex options.
 */
function em_gateway_realex_redirect_booking_timeout() {
	global $wpdb;
	//Get a time from when to delete
	$minutes_to_subtract = absint(get_option('em_realex_redirect_booking_timeout'));
	if ( $minutes_to_subtract > 0 ) {
		//get booking IDs without pending transactions
		$booking_ids = $wpdb->get_col('SELECT b.booking_id FROM '.EM_BOOKINGS_TABLE.' b LEFT JOIN '.EM_TRANSACTIONS_TABLE." t ON t.booking_id=b.booking_id  WHERE booking_date < TIMESTAMPADD(MINUTE, -{$minutes_to_subtract}, NOW()) AND booking_status=4 AND transaction_id IS NULL" );
		if ( count($booking_ids) > 0 ) {
			//first delete ticket_bookings with expired bookings
			$sql = "DELETE FROM ".EM_TICKETS_BOOKINGS_TABLE." WHERE booking_id IN (".implode(',', $booking_ids).");";
			$wpdb->query($sql);
			//then delete the bookings themselves
			$sql = "DELETE FROM ".EM_BOOKINGS_TABLE." WHERE booking_id IN (".implode(',', $booking_ids).");";
			$wpdb->query($sql);
		}
	}
}
add_action('emp_cron_hook', 'em_gateway_realex_redirect_booking_timeout');
?>