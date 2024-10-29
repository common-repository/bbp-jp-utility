<?php
/*
 * bbpress User Lost Password Form template 
 */
?>

<form method="post"  class="bbp-login-form bbp-lost-pass">
	<fieldset class="bbp-form">
		<legend><?php esc_html_e( 'Lost Password' ); ?></legend>

		<div class="bbp-username">
			<p>
				<label for="user_login" class="hide"><?php esc_html_e( 'Username or Email Address' ); ?>: </label>
				<input type="text" name="user_login" value="" size="20" id="user_login" tabindex="<?php bbp_tab_index(); ?>" />
			</p>
		</div>

		<?php 
        do_action( 'login_form', 'resetpass' );
		$ajax_nonce = wp_create_nonce( 'bbp-user-lost-pass' );
        ?>

		<div class="bbp-submit-wrapper">

            <?php echo '<p class="hide-if-no-js"><button id="bbp-lost-pass-submit" class="button submit bbp-ajax-submit" href="" onclick="WPCustomResetPass(\'' . $ajax_nonce . '\');return false;" >'. esc_html__('Reset Password') .'</button></p>'; ?>
			<?php //bbp_user_lost_pass_fields(); ?>

		</div>
        
	</fieldset>
    <div class="custom-resetpass-info">
    </div>
</form>
