<?php
$password = '';
$password_confirmation = '';
if ($user->is_new() && Request::is_post() && $user->errors()) {
	$password = $user->password();
	$password_confirmation = Request::form('password_confirmation');
}
$cancel_url = $Authenticator->return_url();
if (!$Authenticator->user_is_logged_in() || !$Authenticator->user_is_super() || $user->is_new()) {
	$user_level = 1;
} else {
	$user_level = $user->user_level();
}
?>
<?php print Form::header($user,'user-edit-form'); ?>
	<?php
	if (!$user->is_new()) {
		?>
	<input type="hidden" name="current_password" value="<?php echo $user->password() ?>">
		<?php
	}
	if ($Authenticator->user_is_logged_in() && !empty($access_level_list)) {
		?>
	<p class="<?php echo $Navigation->tiger_stripe('striped_User_form') ?>">
		<?php print Form::select($access_level_list,'user_level','user[user_level]','Access Level',$user_level,$user->user_level_is_required(),$user->user_level_is_valid()) ?>
	</p>
		<?php
	} else {
		?>
	<input type="hidden" name="user[user_level]" value="<?php echo $user_level ?>">
		<?php
	}

	print ModelForm::text($user,'first_name');
	
	print ModelForm::text($user,'last_name');
	
	print ModelForm::text($user,'username');
	
	print ModelForm::password($user,'password',null,array('show_strength_meter' => true));

	?>
	<p class="<?php echo $Navigation->tiger_stripe('striped_User_form') ?>">
		<?php print Form::password('password_confirmation','password_confirmation','Confirm Password',$password_confirmation,$user->password_is_required(),$user->password_confirmation_is_valid(),array('autocomplete' => 'off')) ?>
	</p>
	<?php

	print ModelForm::text($user,'email_address');

	print ModelForm::text($user,'security_question');

	print ModelForm::text($user,'security_answer','You can leave these blank, but they will be required if you want to be able to reset your password.');

	if ($user->is_new() && !$Authenticator->user_is_logged_in()) {
		?>
	<p class="<?php echo $Navigation->tiger_stripe('striped_User_form') ?>">
		<?php echo Form::text('security_code','security_code','Security Code','',true,$Authenticator->field_is_valid('security_code'),array('maxlength' => '15')); ?>
	</p>
	<?php $Navigation->tiger_stripe('striped_User_form') ?>
	<p class="<?php echo $Navigation->tiger_stripe('striped_User_form') ?>">
		<label for="captcha-widget">&nbsp;</label><span id="captcha-widget" class="leftfloat"><?php Captcha::render_widget(); ?></span>
		<span style="clear:both;height:0;display:block"></span>
	</p>
		<?php
	}
	?>
	<?php print Form::footer($Authenticator,$user,(!$user->is_new() && $Authenticator->user_can_delete($user)),'Submit',$cancel_url) ?>
<script type="text/javascript" charset="utf-8">
	$(document).observe('dom:loaded',function() {
		$('user-edit-form').observe('submit',function(event) {
			Event.stop(event);
			new Biscuit.Ajax.FormValidator('user-edit-form');
		});
	});
</script>