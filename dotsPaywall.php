<?php
/**
 * Plugin Name: Dots Paywall
 * Plugin URI: https://senddots.com/
 * Description: This plugin creates a paywall with Dots.
 * Version: 0.2.2
 * Author: Dots.
 * License: CC0
 */

function DOTS_paywall($atts = array() , $content) {

    //Filter get requests so hackers can't abuse the system
    filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
	
    //If there is a cookie with a payment hash, check if the invoice associated with that payment hash is paid. If it is, display the content concealed by the shortcode.
	$status = false;
	
	if (isset($_COOKIE["dotsauthhash"]) && isset($_COOKIE["username"])) {
        $url = 'https://' . $_SERVER["HTTP_HOST"] . strtok($_SERVER["REQUEST_URI"], '?');
        $dotsauthhash = sanitize_text_field($_COOKIE["dotsauthhash"]);
        $username = sanitize_text_field($_COOKIE["username"]);
        $price = sanitize_text_field($atts['price']);
        $status = DOTS_checkPayment($dotsauthhash, $url, $username, $price);
	}
	
	if ($status) {
		return $content;
    } else {
        //If no invoice is supplied by the client, this block of code runs.
        //Get the price attribute.
        $price = $atts["price"];
        $url = 'https://' . $_SERVER["HTTP_HOST"] . strtok($_SERVER["REQUEST_URI"], '?');
        
        global $post;
        $author_dots_username = get_the_author_meta( 'dots-username', $post->post_author );
        
        if ($author_dots_username) {
            
        } else {
            $author_dots_username = get_option('dots_username');
        }
        
        $dots_url = $author_dots_username . '?amount= '. $price .'&referrer=' . urlencode($url);
        
        wp_enqueue_script("json2");
        wp_enqueue_script('jquery'); 

        wp_register_script("cookiejs", plugin_dir_url('') . '/dotsPaywall/cookie.js');
        wp_enqueue_script("cookiejs");

        wp_register_script("dotspaywall", "https://senddots.com/js/dots_paywall/paywall.js");
        wp_enqueue_script("dotspaywall", "https://senddots.com/js/dots_paywall/paywall.js", array('jquery', 'cookiejs'));
        wp_add_inline_script('dotspaywall', '
            var $ = jQuery;
            var siteURL = "' . get_site_url() .'";
            var dotsURL = "' . $dots_url . '"; 
            var dotsUnlockPrice = "' . $price .'";
        ', 'before');


        return '
            <br />
            <br />

            <div style="display:flex; flex-direction: column; align-items: center; width: 100%;" id="dots-unlock-button">

            </div>

            <br/>
            <br/>
            <br/>
            <br/>
            <br/>
        ';
    }
}

add_shortcode("paywall", "DOTS_paywall");

//Connect to lnbits and find out if payment hash is paid
function DOTS_checkPayment($dots_auth, $url, $dots_username, $amount)
{

    $endpoint = 'https://internalapi.senddots.com/api/check_paywall_transaction';
    $body = [
        'auth_hash' => $dots_auth,
        'url' => $url, 
        'username' => $dots_username,
        'amount' => $amount
    ];
    $body = wp_json_encode($body);
    $options = [
        'body' => $body,
        'headers' => [
            'Content-Type' => 'application/json'
        ], 
        'timeout' => 60, 
        'redirection' => 5, 
        'blocking' => true, 
        'httpversion' => '1.0',
        'sslverify' => false,
        'data_format' => 'body'
    ];
    $response = wp_remote_post($endpoint, $options);
    $body = wp_remote_retrieve_body($response);
    $responseData = (!is_wp_error($response)) ? json_decode($body, true) : null;
    if ($responseData['success']) {
        return 1;
    } else {
        return 0;
    }
}

//This script checks invoices.
function DOTS_invoiceChecker() {

    //Filter get requests so hackers can't abuse the system
    filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);

    //If there is a post request with a payment hash, check if the invoice associated with that payment hash is paid. If it is, display the content concealed by the shortcode.
    if (isset($_POST["dots_auth"]) && isset($_POST["url"]) && isset($_POST["username"]) && isset($_POST["amount"])) {
        
        $authToken = sanitize_text_field($_POST["dots_auth"]);
        $url = esc_url_raw($_POST["url"]);
        $username = sanitize_text_field($_POST["username"]);
        $amount = sanitize_text_field($_POST["amount"]);
        
        $status = DOTS_checkPayment($authToken, $url, $username, $amount);

        if ($status == 1) {
            echo 1;
            die();
        } else {
            echo esc_html($status);
        }
    }
    echo 0;
    die();
}

add_action('wp_ajax_invoicechecker', 'DOTS_invoiceChecker');
add_action('wp_ajax_nopriv_invoicechecker', 'DOTS_invoiceChecker');


function dotsPaywall_register_settings() {
    add_option('dots_username', '');
    add_option('lightbox_showpaywalltext', 'Read more');
    register_setting('dotsPaywall_options_group', 'dots_username', 'dotsPaywall_callback');
    register_setting('dotsPaywall_options_group', 'lightbox_showpaywalltext', 'dotsPaywall_callback');

}
add_action('admin_init', 'dotsPaywall_register_settings');

function dotsPaywall_register_options_page() {
    add_options_page('Dots Paywall', 'Dots Paywall', 'manage_options', 'dotsPaywall', 'dotsPaywall_options_page');
}
add_action('admin_menu', 'dotsPaywall_register_options_page');

function dotsPaywall_options_page() {
?>
    <h2 style="text-decoration: underline;">Dots Paywall</h2>
    <form method="post" action="options.php">
        <?php settings_fields('dotsPaywall_options_group'); ?>
        <h3>
            Dots Settings
        </h3>
        <table>
            <tr valign="middle">
                <th scope="row">
                    <label for="dots username">
                        Dots Username
                    </label>
                </th>
                <td>
                    <input type="text" id="dots_username" name="dots_username" value="<?php echo esc_attr(get_option('dots_username')); ?>" placeholder="username" />
                </td>
            </tr>
        </table>
        <br/>
        <?php submit_button(); ?>
    </form>
<?php
} ?>
