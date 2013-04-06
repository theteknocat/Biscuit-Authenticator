<?php
$include_file = 'authenticator/views/reset_password_step'.$step.'.php';
require($include_file);
?>
<script type="text/javascript" charset="utf-8">
	$(document).observe('dom:loaded',function() {
		$('reset-password-form').observe("submit",function(event) {
			Event.stop(event);
			new Biscuit.Ajax.FormValidator('reset-password-form');
		});
	});
</script>