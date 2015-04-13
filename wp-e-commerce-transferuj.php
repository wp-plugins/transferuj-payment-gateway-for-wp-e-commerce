<?php

/*
  Plugin Name: Transferuj Payment Gateway for WP e-Commerce
  Plugin URI: Transferuj.pl
  Description: Bramka płatnosci Transferuj.pl dla dodatku WP e-Commerce 
  Version: 1.0
  Author: Transferuj.pl
  Author URI: http://transferuj.pl
 */
$transferuj = new TransferujPaymentGateway();

class TransferujPaymentGateway  {

    function TransferujPaymentGateway() {
        $this->__construct();
    }

    function __construct() {
        add_action('init', array(&$this, 'callback'));

        // Set default values.
        if (!get_option('transferuj_merchantid') <> '')
            update_option('transferuj_merchantid', ' ');

        if (!get_option('transferuj_secretpass') <> '')
            update_option('transferuj_secretpass', ' ');

        if (!get_option('transferuj_view') <> '')
            update_option('transferuj_view', '0');
 
        
        global $nzshpcrt_gateways;

        $nzshpcrt_gateways[$num]['name'] = 'Transferuj';
        $nzshpcrt_gateways[$num]['internalname'] = 'Transferuj';
        $nzshpcrt_gateways[$num]['function'] = 'transferuj_gateway';
        $nzshpcrt_gateways[$num]['form'] = 'transferuj_form';
        $nzshpcrt_gateways[$num]['submit_function'] = 'transferuj_submit';
        $nzshpcrt_gateways[$num]['display_name'] = 'Transferuj.pl';	
    }

    // Process the callback and replies from QuickPay.
    function callback() {
        global $wpdb;

        $transaction_id = trim(stripslashes($_GET['transaction_id']));
        $sessionid = trim(stripslashes($_GET['sessionid']));

       

        $is_callback = false;

        if ((isset($_GET['transferuj_callback']) && $_GET['transferuj_callback'] == '1'))
            $is_callback = true;

        // Process the callback.
        if ($is_callback == true) {
            // Only enter this block if status code from QuickPay is 000 = Approved.
            if (($_SERVER['REMOTE_ADDR'] == '195.149.229.109') && (!empty($_POST))) {

                $md5 = sanitize_text_field($_POST['md5sum']);

                    $merchantid = get_option('transferuj_merchantid');
                    $secretpass = get_option('transferuj_secretpass');

                    $new_transaction = sanitize_text_field( $_POST['tr_id']);
                 
                    $ordernumber = "TRS" . $wpdb->get_var($wpdb->prepare("SELECT id FROM " . WPSC_TABLE_PURCHASE_LOGS . " WHERE sessionid = '%s' LIMIT 1;",$sessionid));
                    $id_sprzedawcy = sanitize_text_field($_POST['id']);
                    $status_transakcji = sanitize_text_field($_POST['tr_status']);
                    $id_transakcji = sanitize_text_field($_POST['tr_id']);
                    $kwota_transakcji = sanitize_text_field($_POST['tr_amount']);
                    $kwota_zaplacona = sanitize_text_field($_POST['tr_paid']);
                    $blad = sanitize_text_field($_POST['tr_error']);
                    $data_transakcji = sanitize_text_field($_POST['tr_date']);
                    $opis_transakcji = sanitize_text_field($_POST['tr_desc']);
                    $ciag_pomocniczy = sanitize_text_field(base64_decode($_POST['tr_crc']));
                    $email_klienta = sanitize_text_field($_POST['tr_email']);
                    $suma_kontrolna = sanitize_text_field($_POST['md5sum']);
                    $transakcja_testowa='';
                    
                    if ($_POST['test_mode'] == 1) {

                    $transakcja_testowa = '- TRANSAKCJA TESTOWA';
                }

                if ($status_transakcji == 'TRUE' && $blad == 'none') {


                    $md5tmp = md5($merchantid . $id_transakcji . $kwota_transakcji . $_POST['tr_crc'] . $secretpass);

                    $purchase_log = new WPSC_Purchase_Log($sessionid, 'sessionid');

                    if (!$purchase_log->exists() || $purchase_log->is_transaction_completed())
                        return;

                    if ($md5 == $md5tmp) {
                        // Order is accepted.
                        $notes = "Transakcja opłacona w systemie Transferuj.pl : " . $new_transaction . $transakcja_testowa;

                        // old way of doing it..
                        //$wpdb->query("UPDATE " . WPSC_TABLE_PURCHASE_LOGS . " SET processed = '3', transactid = '" . $new_transaction . "', date = '" . time() . "', notes = '" . $notes . "' WHERE sessionid = " . $sessionid . " LIMIT 1");
                        $notes=sanitize_text_field($notes);


                        $purchase_log->set('processed', WPSC_Purchase_Log::ACCEPTED_PAYMENT);
                        $purchase_log->set('transactid', $new_transaction);
                        $purchase_log->set('notes', $notes);
                        $purchase_log->save();
                    }
                } else {
                    $purchase_log = new WPSC_Purchase_Log($sessionid, 'sessionid');

                    if (!$purchase_log->exists() || $purchase_log->is_transaction_completed())
                        return;
                    $notes = "Transakcja  nie została opłacona poprawnie w Transferuj.pl : " . $new_transaction . $transakcja_testowa;


                    //   $purchase_log->set('processed', WPSC_Purchase_Log::ACCEPTED_PAYMENT);
                    $purchase_log->set('transactid', $new_transaction);
                    $purchase_log->set('notes', $notes);
                    $purchase_log->save();
                }


                echo 'TRUE';
                exit();
            }
        }
    }

}

function transferuj_form_hint($s) {
    return '<small style="line-height:14px;display:block;padding:2px 0 6px;">' . $s . '</small>';
}

// Displays the settings from within the WPEC control panel.
function transferuj_form() {
    // Get stored values.
    $merchantid = get_option('transferuj_merchantid');
    $secretpass = get_option('transferuj_secretpass');
    $view = get_option('transferuj_view');
    $img = plugins_url('images/logo.png', __FILE__);
    // Transferuj.
    $output = '<tr><td colspan="2" style="text-align:center;"><br/><a href="http://transferuj.pl" target="_new"><img src="'.$img.'"/></a>'
            . '</br><a href="https://secure.transferuj.pl/panel/rejestracja.htm" target="_new">Zarejestruj konto w systemie Transferuj.pl</a></td></tr>';
    $output .= '<tr><td colspan="2"><strong>Ustawienia</strong></td></tr>';

    // Merchant ID.
    $output .= '<tr><td><label for="transferuj_merchantid">ID SPrzedawcy</label></td>';
    $output .= '<td><input name="transferuj_merchantid" id="transferuj_merchantid" type="text" value="' . $merchantid . '"/><br/>';
    $output .= transferuj_form_hint('ID Sprzedawcy w systemie Transferuj.pl.');
    $output .= '</td></tr>';

    // Kod bezpieczenstwa.
    $output .= '<tr><td><label for="transferuj_secretpass">Kod bezpieczeństwa</label></td>';
    $output .= '<td><input name="transferuj_secretpass" id="transferuj_secretpass" type="text" value="' . $secretpass . '"/><br/>';
    $output .= transferuj_form_hint('Kod bezpieczeństwa dostępny w Panelu Odbiorcy Płatności.');
    $output .= '</td></tr>';

       //miejsce i sposob wyswietlania kanalow platnosci
    $views = array();
		$views['0'] = 'Ikony banków na stronie sklepu';
		$views['1'] = 'Lista banków na stronie sklepu';
		$views['2'] = 'Przekierowanie na Transferuj.pl';
		$output .= '<tr><td><label for="transferuj_view">Wybór kanału płatności</label></td><td>';
		$output .= "<select name='transferuj_view'>";
		
		foreach($views as $key => $value)
		{
			$output .= '<option value="' . $key . '"';
			
			if($view == $key)
				$output .= ' selected="selected"';
				
			$output .= '>' . $value . '</option>';
		}
		
		$output .= '</select><br/>';
		$output .= transferuj_form_hint('Wybierz w jakim miejscu Klient ma dokonać wyboru kanału płatności.');	
		$output .= '</td></tr>';


  

  return $output;

    
}

// Validates and saves the settings .
function transferuj_submit() {
    if ($_POST['transferuj_merchantid'] != null){
        
        $id=intval($_POST['transferuj_merchantid']);
        update_option('transferuj_merchantid', $id);
    
        
    }
    if ($_POST['transferuj_secretpass'] != null){
        $pass=sanitize_text_field($_POST['transferuj_secretpass']);
    update_option('transferuj_secretpass', $pass);}

    if($_POST['transferuj_view'] != null){
        $view=intval($_POST['transferuj_view']);
    update_option('transferuj_view', $view);}

    return true;
}

function transferuj_gateway($seperator, $sessionid) {
    global $wpdb, $wpsc_cart;

    $payurl = 'https://secure.transferuj.pl';

    $merchant = get_option('transferuj_merchantid');
    $view = get_option('transferuj_view');
    $secretpass = get_option('transferuj_secretpass');


    $number = $wpdb->get_var($wpdb->prepare("SELECT id FROM " . WPSC_TABLE_PURCHASE_LOGS . " WHERE sessionid = '%s' LIMIT 1;", $sessionid));
    $ordernumber = 'TRS' . $number;

    if (strlen($ordernumber) > 20)
        $ordernumber = time();
    
    $ordernumber=base64_encode($ordernumber);
    $amount = round($wpsc_cart->total_price, 2);
    $transaction_id = uniqid(md5(rand(1, 666)), true); // Set the transaction id to a unique value for reference in the system.
    $time=time();
    $wpdb->query($wpdb->prepare("UPDATE " . WPSC_TABLE_PURCHASE_LOGS . " SET processed = '1', transactid = %s, date = '%%' WHERE sessionid =%s  LIMIT 1", $transaction_id,$time,$sessionid));

    $callbackurl = transferuj_callbackurl($transaction_id, $sessionid);
    $continueurl = transferuj_accepturl($transaction_id, $sessionid);
    $cancelurl = transferuj_cancelurl($transaction_id, $sessionid);

    $md5sum = md5($merchant.$amount.$ordernumber.$secretpass);
    
    $purchase_log = $wpdb->get_row($wpdb->prepare("SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS . "` WHERE `sessionid`= %s LIMIT 1",  $sessionid), ARRAY_A);

    $usersql = $wpdb->prepare("SELECT `" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.value,
	`" . WPSC_TABLE_CHECKOUT_FORMS . "`.`name`,
	`" . WPSC_TABLE_CHECKOUT_FORMS . "`.`unique_name` FROM
	`" . WPSC_TABLE_CHECKOUT_FORMS . "` LEFT JOIN
	`" . WPSC_TABLE_SUBMITED_FORM_DATA . "` ON
	`" . WPSC_TABLE_CHECKOUT_FORMS . "`.id =
	`" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.`form_id` WHERE
	`" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.`log_id`=%s
	ORDER BY `" . WPSC_TABLE_CHECKOUT_FORMS . "`.`checkout_order`", $purchase_log['id']);

    $userinfo = $wpdb->get_results($usersql, ARRAY_A);

    foreach ($userinfo as $key => $value) {

        if (($value['unique_name'] == 'billingfirstname') && $value['value'] != '') {
            $name = $value['value'];
        }

        if (($value['unique_name'] == 'billinglastname') && $value['value'] != '') {
            $surname = $value['value'];
        }

        if (($value['unique_name'] == 'billingaddress') && $value['value'] != '') {
            $address = $value['value'];
        }

        if (($value['unique_name'] == 'billingcity') && $value['value'] != '') {
            $city = $value['value'];
        }

        if (($value['unique_name'] == 'billingemail') && $value['value'] != '') {
            $mail = $value['value'];
        }

        if (($value['unique_name'] == 'billingphone') && $value['value'] != '') {
            $phone = $value['value'];
        }

        if (($value['unique_name'] == 'billingcountry') && $value['value'] != '') {
            $country = $value['value'];
        }

        if (($value['unique_name'] == 'billingpostcode') && $value['value'] != '') {
            $postcode = $value['value'];
        }
    }
    $klient = $name . ' ' . $surname;
    $country = substr($country, 14, 2);
    $kanal= $_POST['kanal'];
    $akceptuje=$_POST['terms_t'];
    // Generate the form output.
    $output = "<div style=\"display:none;\">
		<form id=\"quickpay_form\" name=\"quickpay_form\" action=\"$payurl\" method=\"post\">
		<input type=\"hidden\" name=\"id\" value=\"$merchant\"/>
		<input type=\"hidden\" name=\"opis\" value=\"Zamowienie nr: $number\"/>
                <input type=\"hidden\" name=\"crc\" value=\"$ordernumber\"/>
		<input type=\"hidden\" name=\"kwota\" value=\"$amount\"/>
                <input type=\"hidden\" name=\"kanal\" value=\"$kanal\"/>
                <input type=\"hidden\" name=\"akceptuje_regulamin\" value=\"$akceptuje\"/>
		<input type=\"hidden\" name=\"pow_url\" value=\"$continueurl\"/>
		<input type=\"hidden\" name=\"pow_url_blad\" value=\"$cancelurl\"/>
		<input type=\"hidden\" name=\"wyn_url\" value=\"$callbackurl\"/>
                <input type=\"hidden\" name=\"nazwisko\" value=\"" . $klient . "\" />
                <input type=\"hidden\" name=\"adres\" value=\"" . $address . "\" />    
                <input type=\"hidden\" name=\"kod\" value=\"" . $postcode . "\" />
        	<input type=\"hidden\" name=\"telefon\" value=\"" . $phone . "\" />
                <input type=\"hidden\" name=\"miasto\" value=\"" . $city . "\" />
                <input type=\"hidden\" name=\"kraj\" value=\"" . $country . "\" />
                <input type=\"hidden\" name=\"email\" value=\"" . $mail . "\" />
		<input type=\"hidden\" name=\"md5sum\" value=\"$md5sum\"/>
		<input type=\"submit\" value=\"Pay\"/>
		</form>
		</div>";

    echo $output;
    echo "<script language=\"javascript\" type=\"text/javascript\">document.getElementById('quickpay_form').submit();</script>";
    echo "Please wait..";
    exit();
}

function transferuj_cancelurl($transaction_id, $session_id) {
    $cancelurl = get_option('shopping_cart_url');

    $params = array('transferuj_cancel' => '1', 'transaction_id' => $transaction_id, 'sessionid' => $session_id);
    return add_query_arg($params, $cancelurl);
}

function transferuj_accepturl($transaction_id, $session_id) {
    $accepturl = get_option('transact_url');

    $params = array('transferuj_accept' => '1', 'transaction_id' => $transaction_id, 'sessionid' => $session_id);
    return add_query_arg($params, $accepturl);
}

function transferuj_callbackurl($transaction_id, $session_id) {
    $callbackurl = get_option('siteurl');

    $string_end = substr($callbackurl, strlen($callbackurl) - 1);

    if ($string_end != '/')
        $callbackurl .= '/';

    $params = array('transferuj_callback' => '1', 'transaction_id' => $transaction_id, 'sessionid' => $session_id);
    return add_query_arg($params, $callbackurl);
}
if ( in_array( 'Transferuj', (array)get_option( 'custom_gateway_options' ) ) ) {
         $id = get_option('transferuj_merchantid');
    $view = get_option('transferuj_view');
    $jQuery = '$jQuery';
    if ($view == 0) {


        $str = <<<MY_MARKER
                <tr><td>
   <input type="hidden" id="channel"  name="kanal" value=" ">
            <style type="text/css">                 
            .checked_v {
                box-shadow: 0px 0px 10px 3px #15428F !important;;

            }
            .channel {
                display: inline-block; 
                width: 130px; 
                height:63px; 
               
                text-align:center;
            }
             </style>   
            
            <script type="text/javascript">
                function ShowChannelsCombo()
                {
                    var $jQuery = jQuery.noConflict();
                    var str = '<div  style="margin:20px 0 15px 0"  id="kanal"><label>Wybierz bank:</label></div>';

                    for (var i = 0; i < tr_channels.length; i++) {
                        str += '<div   class="channel" ><img id="' + tr_channels[i][0] + '" class="check" style="height: 80%" src="' + tr_channels[i][3] + '"></div>';
                    }

                    var container = jQuery("#kanaly_v");
                    container.append(str);
                    
                    
                      jQuery(document).ready(function () {
                        
                        jQuery(".check").click(function () {
                            
                            $jQuery(".check").removeClass("checked_v");
                            $jQuery(this).addClass("checked_v");
                            var n = $jQuery(document).height();
                            jQuery('html, body').animate({ scrollTop: n }, 500)
                            var kanal = 0;
                            kanal = jQuery(this).attr("id");
                             $jQuery('#channel').val(kanal);

                         });
                        });
                     


                }
                 jQuery.getScript("https://secure.transferuj.pl/channels-{$id}0.js", function () {
                    ShowChannelsCombo()
                });
            </script>
            <div style="background: white " id="kanaly_v"></div>
            <div id="descriptionBox"></div> <br/>
            <div id="termsCheckboxBox">
                <input type="checkbox" id="termsCheckbox" checked name="terms_t">
                    <a href="https://transferuj.pl/regulamin.pdf" target="blank">
                                Akceptuję warunki regulaminu korzystania z serwisu Transferuj.pl
                    </a>
                </input> <br/>
            </div>
            </td></tr>
MY_MARKER;
    }



    if ($view == 1) {
        if(is_numeric($id)){
        $channels_url = "https://secure.transferuj.pl/channels-" . $id . "0.js";
        $JSON = file_get_contents($channels_url);

        // parse the channel list
        $pattern = "!\['(?<id>\d{1,2})','(?<name>.+)','(.+)','(.+)','!";
        preg_match_all($pattern, $JSON, $matches);

        // create list of channels
        $channels = '<select class="channelSelect" id ="channelSelect" name="kanal">';
        for ($i = 0; $i < count($matches['id']); $i++) {
            $channels .= '<option value="' . $matches['id'][$i] . '">' .
                    $matches['name'][$i] . "</option>";
        }
        $channels .= '</select>';

        $str = <<<MY_MARKER
             <tr>        
   <td>  <div id="descriptionBox"></div> <br/>
            <div  style="margin:20px 0 15px 0"  id="kanal"><label>Wybierz bank:</label></div>
            <div id="channelSelectBox">{$channels}</div>
            <div style="margin:30px 0 15px 0" id="termsCheckboxBox">
                <input  type="checkbox" checked id="termsCheckbox" name="terms_t">
                    <a href="https://transferuj.pl/regulamin.pdf" target="blank">
                                Akceptuję warunki regulaminu korzystania z serwisu Transferuj.pl
                    </a>
                </input> <br/>
            </div>
            </td>
            </tr>
          
            
MY_MARKER;
    }
    }
    if ($view == 2) {
        $img = plugins_url('images/baner.png', __FILE__);

        $str = <<<MY_MARKER
             <tr>        
             <td>  
            <img style="margin: 15px 0 0 0 " src="$img" />
            </td>
            </tr>
          
            
MY_MARKER;
    }


    $gateway_checkout_form_fields[$nzshpcrt_gateways[$num]['internalname']] = $str;
}
?>