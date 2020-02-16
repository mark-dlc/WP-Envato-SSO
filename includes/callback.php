<?php
/**
 * File callback.php
 *
 * @author Justin Greer <justin@justin-greer.com
 * @package WP Single Sign On Client
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Redirect the user back to the home page if logged in.
if ( is_user_logged_in() ) {
    wp_redirect( home_url() );
    exit;
}

// Get the redirect location
if(isset($_COOKIE['wp-sso-login-redirect'])) {
   $no_dashboard_redirect = $_COOKIE['wp-sso-login-redirect'];
} else {
   $no_dashboard_redirect = site_url();
}
 
// Grab a copy of the options and set the redirect location.
$options       = get_option( 'wposso_options' );
$user_redirect = $options['redirect_to_dashboard'] == '1' ? get_dashboard_url() : $no_dashboard_redirect;
$buyers_only = $options['buyers_only'] == '1';

// Authenticate Check and Redirect
if ( ! isset( $_GET['code'] ) && ! isset( $_GET['code_background'] ) ) {
        // Save current url
        global $wp;
        $current_url = home_url(add_query_arg(array(),$wp->request));
        setcookie('wp-sso-login-redirect', $current_url, time() + 1 * HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );

    $params = array(
        'oauth'         => 'authorize',
        'response_type' => 'code',
        'client_id'     => $options['client_id'],
        'redirect_uri'  => site_url( '?auth=sso')
    );
    $params = http_build_query( $params );
    wp_redirect( 'https://api.envato.com/authorization?' . $params );
    exit;
}

// Handle the callback from the server if there is one.
if ( isset( $_GET['code'] ) && ! empty( $_GET['code'] ) ) {
       ?>

<html>
<head>
<style>
.loader {
  border: 16px solid #f3f3f3;
  border-radius: 50%;
  border-top: 16px solid #3498db;
  width: 120px;
  height: 120px;
  margin: auto;
  position: absolute;
  top: 0; left: 0; bottom: 0; right: 0;
  -webkit-animation: spin 2s linear infinite;
  animation: spin 2s linear infinite;
}

@-webkit-keyframes spin {
  0% { -webkit-transform: rotate(0deg); }
  100% { -webkit-transform: rotate(360deg); }
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
<script>
       $(document).ready(function() {
          window.location.href = "?auth=sso&code_background=" + "<?php echo $_GET['code'] ?>";
       });
</script>
</head>
<body>

<noscript>Please enable JavaScript or <a href="?auth=sso&code_background=<?php echo $_GET['code'] ?>">click here</a> to continue manually.</noscript>
<div class="loader"></div>
<div id="content"></div>

</body>
</html>

       <?php
}

// Handle the callback from the server is there is one.
if ( isset( $_GET['code_background'] ) && ! empty( $_GET['code_background'] ) ) {
    $code       = sanitize_text_field( $_GET['code_background'] );
    $server_url = 'https://api.envato.com/token?';
    $response   = wp_remote_post( $server_url, array(
        'method'      => 'POST',
        'timeout'     => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => array(),
        'body'        => array(
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $options['client_id'],
            'client_secret' => $options['client_secret'],
            'redirect_uri'  => site_url( '?auth=sso' )
        ),
        'cookies'     => array(),
        'sslverify'   => false
    ) );

    $tokens = json_decode( $response['body'] );
    if ( isset( $tokens->error ) ) {
        wp_die( $tokens->error_description );
    }


    $server_url = 'https://api.envato.com/v3/market/buyer/purchases';
    $response   = wp_remote_get( $server_url, array(
        'timeout'     => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => array( 'Authorization' => 'Bearer ' . $tokens->access_token ),
        'sslverify'   => false
    ) );

    $json = json_decode( $response['body'] );
    $envato_user_name = $json->buyer->username;
    $envato_user_id = $json->buyer->id;
    
    if ( $buyers_only ) {
        $purchases = $json->purchases;

        if (count($json->purchases) < 1){
            wp_die( 'You do not appear to have any purchases with us. Please sign in using with same account you have made your purchase with.');
            exit;
        }
    }

    /* {
        $server_url = 'https://api.envato.com/v1/market/private/user/username.json';
        $response   = wp_remote_get( $server_url, array(
            'timeout'     => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array( 'Authorization' => 'Bearer ' . $tokens->access_token ),
            'sslverify'   => false
        ) );

        $envato_user_name = json_decode( $response['body'] )->username;
    } */

    $server_url = 'https://api.envato.com/v1/market/private/user/email.json';
    $response   = wp_remote_get( $server_url, array(
        'timeout'     => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => array( 'Authorization' => 'Bearer ' . $tokens->access_token ),
        'sslverify'   => false
    ) );

    $user_email = json_decode( $response['body'] );

    $user_id   = username_exists( $envato_user_id );
    if ( ! $user_id && email_exists( $user_email->email ) == false ) {
        
        /*
         //Solution to our issue, create users this way, and also retrieve them based on the envato_user_id. Not wise to let wp username be actual username, since this may change in Envato, but not in Wordpress.
         //Need to do, for the ticket creation, the username is prefilled based on the wp user login, but should now be the wp nicname.
            $userdata = array(
            'user_login' =>  $envato_user_id
            'nickname'   =>  $envato_user_name,
            'user_pass'  =>  $random_password
            );
             
            $user_id = wp_insert_user( $userdata ) ;
             
            // On success.
            if ( ! is_wp_error( $user_id ) ) {
                echo "User created : ". $user_id;
            }
         */

        // Does not have an account... Register and then log the user in
        $random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
        $user_id         = wp_create_user( $envato_user_id, $random_password, $user_email->email );
        $user_id         = wp_update_user( array( 'ID' => $user_id, 'nickname' => $envato_user_name ) );
        if ( is_wp_error( $user_id ) ) {
            wp_die( 'Problem registering' );
        }

        wp_clear_auth_cookie();
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );

        if ($purchases) {
            update_user_meta($user_id, 'user_purchases', $purchases);
        }

        if ( is_user_logged_in() ) {
            wp_redirect( $user_redirect );
            exit;
        }

    } else if (email_exists( $user_email->email ) == false ) {
        exit( 'User with email already exists.');
    } else {

        // Already Registered... Log the User In
        $random_password = __( 'User already exists.  Password inherited.' );
        $user            = get_user_by( 'login', $envato_user_id );

        // User ID 1 is not allowed
        //if ( '1' === $user->ID ) {
        //    wp_die( 'For security reasons, this user can not use Single Sign On' );
        //}

        if ($purchases) {
            update_user_meta($user_id, 'user_purchases', $purchases);
        }

        wp_clear_auth_cookie();
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID );

        if ( is_user_logged_in() ) {
            wp_redirect( $user_redirect );
            exit;
        }

    }

    wp_die( '<h1>Single Sign On with Envato Failed.</h1><p>To consult our documentation, use your purchase code instead. To submit a ticket, please use the <a href="https://sherdle.com/help/create-ticket/">pre-purchase question</a> option.' );
    exit;
}
