<?php
$password = '';
$password_confirmation = '';
if ($user->is_new() && Request::is_post() && $user->errors()) {
	$password = $user->password();
	$password_confirmation = Request::form('password_confirmation');
}
$cancel_url = $Authenticator->return_url();
if (!$Authenticator->user_is_logged_in() || $user->is_new()) {
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
	if (!empty($access_level_list)) {
		print ModelForm::select($access_level_list,$user,'user_level');
	} else {
		?>
		<input type="hidden" name="user[user_level]" value="<?php echo $user_level ?>">
		<?php
	}
	if (!empty($account_status_list)) {
		print ModelForm::select($account_status_list,$user,'status');
	}

	print ModelForm::text($user,'first_name');
	
	print ModelForm::text($user,'last_name');
	
	print ModelForm::text($user,'username','May contain only letters, numbers, hyphens, underscores, @ symbols and periods');
	
	print ModelForm::password($user,'password',null,array('show_strength_meter' => true, 'autocomplete' => 'off'));

	?>
	<p class="<?php echo $Navigation->tiger_stripe('striped_User_form') ?>">
		<?php print Form::password('password_confirmation','password_confirmation','Confirm Password',$password_confirmation,$user->password_is_required(),$user->password_confirmation_is_valid(),array('autocomplete' => 'off')) ?>
	</p>
	<?php

	print ModelForm::text($user,'email_address');

	// The following two fields are not required by default. Add them in if you want to use them as an extra level of security for users to reset their password. Be sure to
	// set the db columns to not allow NULL so they will be validated

	// print ModelForm::text($user,'security_question');

	// print ModelForm::text($user,'security_answer','You can leave these blank, but they will be required if you want to be able to reset your password.');

	print Form::footer($Authenticator,$user,(!$user->is_new() && $Authenticator->user_can_delete($user)),__('Save'),$cancel_url) ?>
<script type="text/javascript">
	$(document).ready(function() {
		Biscuit.Crumbs.Forms.AddValidation('user-edit-form');
	});
</script>