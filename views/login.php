<div id="login-form-container">
	<form action="" method="POST" accept-charset="UTF-8" id="login-form" class="login-form">
		<input type="hidden" name="action" value="login">
		<?php
			if (Session::flash_isset('login_redirect')) {
		?>
		<input type="hidden" name="login_redirect" value="<?php echo Session::flash_get('login_redirect')?>">
		<?php
			}
		?>
		<p>
			<?php echo Form::text("username","login_info[username]",__("Username"),'',true,true,array('maxlength' => '255')) ?>
		</p>
		<p>
			<?php echo Form::password("password","login_info[password]",__("Password"),'',true,true,array('maxlength' => '255')) ?>
		</p>
		<?php
		if (!$is_login_dialog_request) {
		?>
		<p>
			<label>&nbsp;</label><a href="/reset-password"><?php echo __('I forgot my password!') ?></a>
		</p>
		<div class="controls"><input type="submit" class="SubmitButton" value="<?php echo __('Login') ?> &rarr;"></div>
		<?php
		}
		?>
	</form>
</div>
<?php
if (!$is_login_dialog_request) {
?>
<script type="text/javascript" charset="utf-8">
	$(document).ready(function() {
		$('#attr_username').focus();
		Biscuit.Ajax.LoginHandler('login-form','login-form-container');
	});
</script>
<?php
}
?>