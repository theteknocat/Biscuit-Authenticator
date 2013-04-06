<span id="pwd-strength">
	<span id="pwd-meter-container"><span id="pwd-strength-meter" class="small">&nbsp;&nbsp;Strength</span></span>
</span>
<span class="instructions">We enforce strong passwords to help you protect your information. Your password must be at least 8 characters and include both upper and lower case letters with at least one number and one symbol.</span>
<script type="text/javascript" charset="utf-8">
	$(document).observe('dom:loaded',function(event) {
		PasswordStrength.startMeter('attr_<?php echo $attribute_id ?>');
	});
</script>