<p>An email has been sent containing your temporary access code.  Please check your email now and select and copy the access code to paste into the form below.</p>
<form name="reset-password-form" id="reset-password-form" action="" accept-charset="utf-8" method="POST">
	<?php echo RequestTokens::render_token_field(); ?>
	<input type="hidden" name="step" value="2">
	<input type="hidden" name="username" value="<?php echo $username ?>">
	<p>
		<?php print Form::text('access_code','access_code','Access Code',$access_code,true,$Authenticator->field_is_valid('access_code'),array('maxlength' => '255', 'autocomplete' => 'off')) ?>
	</p>
	<p>
		<label for="attr_security_answer" class="label-wide<?php if (!$Authenticator->field_is_valid('security_answer')) { ?>error<?php } ?>">*Security Question:</label>
		<span style="display: block;float:left"><?php echo $security_question ?></span><br>
		<input type="text" class="text<?php if (!$Authenticator->field_is_valid('security_answer')) { ?> error<?php } ?>" name="security_answer" id="attr_security_answer" value="<?php echo $security_answer ?>" maxlength="255" autocomplete="off">
	</p>
	<p>
		<?php print Form::password('new_password1','new_password1','New Password',$new_password1,true,$Authenticator->field_is_valid('new_password1'),array('maxlength' => '255', 'autocomplete' => 'off')) ?>
	</p>
	<p>
		<?php print Form::password('new_password2','new_password2','Confirm Password',$new_password2,true,$Authenticator->field_is_valid('new_password2'),array('maxlength' => '255', 'autocomplete' => 'off')) ?>
	</p>
	<div class="controls"><a href="/reset-password">&laquo; Back</a><input type="submit" class="SubmitButton" name="SubmitButton" value="Finish"></div>
</form>