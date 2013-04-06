<p>In order to reset your password, your user account must have:</p>
<ul>
	<li>A valid email address</li>
	<li>A security question and anwser</li>
</ul>
<p>If your account does not contain this information, you will need to contact a system administrator to update your account.</p>
<p>The password reset process is as follows:</p>
<ol>
	<li>Enter your account username</li>
	<li>Check your email. A message will be sent to you containing a temporary access code. Select and copy the access code from your email</li>
	<li>Paste the access code into the form in step 2, answer your security question and enter a new password twice</li>
</ol>
<p>Type your username and hit enter to begin.</p>
<form name="reset-password-form" id="reset-password-form" action="" accept-charset="utf-8" method="post">
	<?php echo RequestTokens::render_token_field(); ?>
	<input type="hidden" name="step" value="1">
	<p>
		<?php print Form::text('username','username','Username',$username,true,$Authenticator->field_is_valid('username'),array('maxlength' => '255')) ?>
	</p>
	<div class="controls"><a href="/login">Cancel</a><input type="submit" class="SubmitButton" name="SubmitButton" value="Next"></div>
</form>