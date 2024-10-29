<?php
/*
 * bbpress User Login Form template 
 */

if (!function_exists('bbp_utility_login_link')) {
    function bbp_utility_login_link() {
        global $bbp_register_link_url;
        global $bbp_lostpass_link_url;

        if ( !empty( $bbp_register_link_url ) ) { ?>
            <a href="<?php echo esc_url( $bbp_register_link_url ); ?>" title="<?php esc_attr_e( 'Register' ); ?>" class="bbp-register-link"><?php esc_html_e( 'Register' ); ?></a>
        <?php } 
        if ( !empty( $bbp_lostpass_link_url ) ) { ?>
            <a href="<?php echo esc_url( $bbp_lostpass_link_url ); ?>" title="<?php esc_attr_e( 'Lost Password' ); ?>" class="bbp-lostpass-link"><?php esc_html_e( 'Lost Password' ); ?></a>
        <?php }
    }
}
?>
<form method="post"  class="bbp-login-form">
    <fieldset>
        <legend><?php esc_html_e( 'Log In' ); ?></legend>

        <div class="bbp-username">
            <label for="user_login"><?php esc_html_e( 'Username or Email Address' ); ?>: </label>
            <input type="text" name="log" value="<?php bbp_sanitize_val( 'user_login', 'text' ); ?>" size="20" id="user_login" tabindex="<?php bbp_tab_index(); ?>" />
        </div>

        <div class="bbp-password">
            <label for="user_pass"><?php esc_html_e( 'Password' ); ?>: </label>
            <input type="password" name="pwd" value="<?php bbp_sanitize_val( 'user_pass', 'password' ); ?>" size="20" id="user_pass" tabindex="<?php bbp_tab_index(); ?>" />
        </div>

        <div class="bbp-remember-me">
            <input type="checkbox" name="rememberme" value="forever" <?php checked( bbp_get_sanitize_val( 'rememberme', 'checkbox' ), true, true ); ?> id="rememberme" tabindex="<?php bbp_tab_index(); ?>" />
            <label for="rememberme"><?php esc_html_e( 'Remember Me' ); ?></label>
        </div>

		<?php
        do_action( 'login_form' );
		$ajax_nonce = wp_create_nonce( 'bbp-user-login' );

        global $bbp_redirect_to;
        global $bbp_lostpass_link_url;
        $bbp_redirect_to = apply_filters( 'bbp_user_login_redirect_to', '' );
        if ( empty( $bbp_redirect_to ) ) {
            if ( isset( $_SERVER['REQUEST_URI'] ) ) {
                $bbp_redirect_to = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            } else {
                $bbp_redirect_to = wp_get_referer();
            }
        }
        // Remove loggedout query arg if it's there
        $bbp_redirect_to = esc_url( remove_query_arg( 'loggedout', $bbp_redirect_to ));
        $bbp_lostpass_link_url = (!empty($bbp_lostpass_link_url))? esc_url($bbp_lostpass_link_url) : '';
        
        $is_widget = Celtis_bbp_utility::in_dynamic_sidebar();
        ?>        
        <div class="bbp-submit-wrapper">
            <?php echo '<p class="hide-if-no-js"><button id="bbp-login-submit" class="button submit bbp-ajax-submit" href="" onclick="WPCustomLogin(\'' . $ajax_nonce . '\' , \'' . $is_widget . '\' , \'' . $bbp_redirect_to . '\' , \'' . $bbp_lostpass_link_url . '\' );return false;" >'. esc_html__( 'Log In' ) .'</button></p>'; ?>
            <?php //bbp_user_login_fields(); ?>
        </div>
        <div class="bbp-login-links">
            <?php bbp_utility_login_link(); ?>
        </div>
        <div class="custom-login-info">
        </div>
    </fieldset>
    
</form>
