<div id="login-form-container">
	<form action="" method="POST" accept-charset="utf-8" id="login-form" class="login-form">
		<?php echo RequestTokens::render_token_field(); ?>
		<input type="hidden" name="action" value="login" id="action">
		<?php
			if (Session::flash_isset('login_redirect')) {
		?>
		<input type="hidden" name="login_redirect" value="<?php echo Session::flash_get('login_redirect')?>">
		<?php
			}
		?>
		<p>
			<?php echo Form::text("username","login_info[username]","Username:",'',true,true,array('maxlength' => '255')) ?>
		</p>
		<p>
			<?php echo Form::password("password","login_info[password]","Password:",'',true,true,array('maxlength' => '255')) ?>
		</p>
		<p>
			<label>&nbsp;</label><a href="/reset-password">I forgot my password!</a>
		</p>
		<div class="controls"><input type="submit" class="SubmitButton" value="Login &rarr;"></div>
	</form>
</div>
<script type="text/javascript" charset="utf-8">
	$(document).observe('dom:loaded',function(el) {
		$('attr_username').focus();
		Biscuit.Ajax.LoginHandler('login-form','login-form-container');
	});
</script>