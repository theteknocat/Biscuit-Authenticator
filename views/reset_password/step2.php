<p>Welcome, <?php echo $user->full_name() ?>, you may now enter a new password.</p>
<?php print Form::header($user,'password-reset-form'); ?>
	<input type="hidden" name="id" value="<?php echo $user->id() ?>">
	<input type="hidden" name="password-reset" value="1">

	<?php
	print ModelForm::password($user,'password',null,array('show_strength_meter' => true, 'autocomplete' => 'off'));

	?>
	<p class="<?php echo $Navigation->tiger_stripe('striped_User_form') ?>">
		<?php print Form::password('password_confirmation','password_confirmation','Confirm Password',$password_confirmation,$user->password_is_required(),$user->password_confirmation_is_valid(),array('autocomplete' => 'off')) ?>
	</p>
	<?php
	if ($user->security_question_is_required() && $user->security_answer_is_required()) {
		?>
	<p class="<?php echo $Navigation->tiger_stripe('striped_User_form') ?>">
		<?php echo $user->security_question(); ?>
	</p>
	<?php $Navigation->tiger_stripe('striped_User_form') ?>
	<p class="<?php echo $Navigation->tiger_stripe('striped_User_form') ?>">
		<?php echo Form::text('security_answer','security_answer','Answer','',true,$Authenticator->field_is_valid('security_answer'),array('maxlength' => '255')); ?>
	</p>
		<?php
	}
	?>
	<p class="<?php echo $Navigation->tiger_stripe('striped_User_form') ?>">
		<?php echo Form::text('security_code','security_code','Security Code','',true,$Authenticator->field_is_valid('security_code'),array('maxlength' => '15')); ?>
	</p>
	<?php $Navigation->tiger_stripe('striped_User_form') ?>
	<p class="<?php echo $Navigation->tiger_stripe('striped_User_form') ?>">
		<label for="captcha-widget">&nbsp;</label><span id="captcha-widget" class="leftfloat"><?php Captcha::render_widget(); ?></span>
		<span style="clear:both;height:0;display:block"></span>
	</p>
	<?php print Form::footer($Authenticator,$user,false,'Save','no-cancel-button') ?>
<script type="text/javascript" charset="utf-8">
	$(document).ready(function() {
		$('#attr_password').focus();
		$('#password-reset-form').submit(function() {
			new Biscuit.Ajax.FormValidator('password-reset-form');
			return false;
		});
	});
</script>
