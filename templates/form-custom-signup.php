<?php
/*
 * bbpress User Registration Form template 
 */
?>

<form method="post"  class="bbp-login-form bbp-register">
	<fieldset class="bbp-form">
		<legend><?php esc_html_e( 'Create an Account', 'bbpress' ); ?></legend>

		<div class="bbp-template-notice">
			<p><?php esc_html_e( 'Your username must be unique, and cannot be changed later.', 'bbpress' ) ?></p>
			<p><?php esc_html_e( 'We use your email address to email you a secure password and verify your account.', 'bbpress' ) ?></p>

		</div>

		<div class="bbp-username">
			<label for="user_login"><?php esc_html_e( 'Username' ); ?>: </label>
			<input type="text" name="user_login" value="<?php bbp_sanitize_val( 'user_login' ); ?>" size="20" id="user_login" tabindex="<?php bbp_tab_index(); ?>" />
		</div>

		<div class="bbp-email">
			<label for="user_email"><?php esc_html_e( 'Email' ); ?>: </label>
			<input type="text" name="user_email" value="<?php bbp_sanitize_val( 'user_email' ); ?>" size="20" id="user_email" tabindex="<?php bbp_tab_index(); ?>" />
		</div>

		<?php 
        do_action( 'register_form' );
		$ajax_nonce = wp_create_nonce( 'bbp-user-register' );
        ?>

		<div class="bbp-submit-wrapper">
            <?php echo '<p class="hide-if-no-js"><button id="bbp-signup-submit" class="button submit bbp-ajax-submit" href="" onclick="WPCustomRegister(\'' . $ajax_nonce . '\');return false;" >'. esc_html__('Register') .'</button></p>'; ?>
			<?php //bbp_user_register_fields(); ?>

		</div>
	</fieldset>
    <div class="custom-register-info">
    </div>
    
</form>
