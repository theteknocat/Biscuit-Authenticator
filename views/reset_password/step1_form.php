<p>Type your username and hit enter:</p>
<form name="reset-password-form" id="reset-password-form" action="" accept-charset="UTF-8" method="post">
	<?php echo RequestTokens::render_token_field(); ?>
	<p>
		<?php print Form::text('username','username','Username',$username,true,$Authenticator->field_is_valid('username'),array('maxlength' => '255')) ?>
	</p>
	<div class="controls"><a href="/login">Cancel</a><input type="submit" class="SubmitButton" name="SubmitButton" value="Next"></div>
</form>
<script type="text/javascript" charset="utf-8">
	$(document).ready(function() {
		$('#attr_username').focus();
		$('#reset-password-form').submit(function() {
			Biscuit.Crumbs.Forms.DisableSubmit('reset-password-form');
		});
	});
</script>
