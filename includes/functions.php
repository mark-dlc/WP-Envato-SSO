<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Main Functions
 *
 * @author Justin Greer <justin@justin-greer.com>
 * @package WP Single Sign On Client
 */

/**
 * Function wp_sso_login_form_button
 *
 * Add login button for SSO on the login form.
 * @link https://codex.wordpress.org/Plugin_API/Action_Reference/login_form
 */
function wp_sso_login_form_button() {
	?>
	<a style="color:#FFF; width:100%; text-align:center; margin-bottom:1em;" class="button button-primary button-large"
	   href="<?php echo site_url( '?auth=sso' ); ?>">Login with Envato</a>
	<div style="clear:both;"></div>
	<?php
}

add_action( 'login_form', 'wp_sso_login_form_button' );

/**
 * Login Button Shortcode
 *
 * @param  [type] $atts [description]
 *
 * @return [type]       [description]
 */
function single_sign_on_login_button_shortcode( $atts ) {
	$a = shortcode_atts( array(
		'type'   => 'primary',
		'title'  => 'Login using Envato',
		'class'  => 'sso-button',
		'target' => '_blank',
		'text'   => 'Single Sign On'
	), $atts );

	return '<a class="' . $a['class'] . '" href="' . site_url( '?auth=sso' ) . '" title="' . $a['title'] . '" target="' . $a['target'] . '">' . $a['text'] . '</a>';
}

add_shortcode( 'sso_button', 'single_sign_on_login_button_shortcode' );


/**
 * Get user purchases
 *
 * @param  [type] $atts [description]
 *
 * @return [type]       [description]
 */
function single_sign_on_purchases_shortcode( $atts ) {
	$a = shortcode_atts( array(
		'option_id'  => 'purchase_option',
		'option_class'  => 'purchase_option',
		'select_class'  => 'purchase_select',
		'select_id'   => 'purchase_select',
        'select_heading'   => '<h3>Select the template to contact us about</h3>'
	), $atts );

        $user_id = get_current_user_id();

        if ($user_id == 0) {
            return '<p name="template" error="user not logged in"></p>';
        }

        $options       = get_option( 'wposso_options' );
        $buyers_only = $options['buyers_only'] == '1';
        if (!$buyers_only) {
            return '<p name="template" error"required option disabled">The option that only allows users that purchased your items to sign in, needs to be enabled.</p>';
        }

        $purchases = get_user_meta( $user_id, 'user_purchases', true); 

        if (!is_array($purchases) || count($purchases) < 1){
            return '<p name="template" error"no purchases">You do not have any purchases</p>';
        }
        
        $select_options = '';
        foreach($purchases as $purchase) {
             $select_options .= '<option value="' . $purchase->code . '" 
                          id="'. $a['option_id'] . '" class="'. $a['option_class'] . '">' . 
                          $purchase->item->name . ' (' . $purchase->license . ')'. '</option>';
        }
        $select = $a['select_heading'] .
                  '<select name="template" id="'. $a['select_id'] . '" class="'. $a['select_class'] . '">' . 
                  $select_options . '</select>';

	return $select;
}

add_shortcode( 'sso_purchases', 'single_sign_on_purchases_shortcode' );