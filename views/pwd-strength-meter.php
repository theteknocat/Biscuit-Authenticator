<span id="pwd-strength">
	<span id="pwd-meter-container"><span id="pwd-strength-meter">&nbsp;&nbsp;<span id="pwd-strength-text"><?php echo __('Strength'); ?></span></span></span>
</span>
<span class="instructions"><?php echo __('Must be at least 8 characters long. Use the strength meter as a guide to help you set a strong password.'); ?> <a href="http://www.microsoft.com/security/online-privacy/passwords-create.aspx" target="_blank"><?php echo __('Tips on creating a strong password'); ?></a></span>
<script type="text/javascript">
	$(document).ready(function() {
	    $('#attr_<?php echo $attribute_id; ?>').pwdstr('#pwd-strength');
	});
</script>