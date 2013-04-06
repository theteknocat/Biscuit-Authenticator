var PasswordStrength = {
	startMeter: function(element_id) {
		$(element_id).observe('keyup',function(event) {
			var el = Event.element(event);
			PasswordStrength.updateStrength($F(el));
		});
	},
	updateStrength: function(pw) {
		var strength = this.getStrength(pw);
		var width = (200/32)*strength;
		if (width > 50) {
			$('pwd-strength-meter').addClassName('light');
		} else if ($('pwd-strength-meter').hasClassName('light')) {
			$('pwd-strength-meter').removeClassName('light');
		}
		new Effect.Morph('pwd-strength-meter', {style:'width:'+width+'px', duration:'0.4'}); 
	},
	getStrength: function(passwd) {
		intScore = 0;
		if (passwd.match(/[a-z]/)) // [verified] at least one lower case letter
		{
		intScore = (intScore+1)
		} if (passwd.match(/[A-Z]/)) // [verified] at least one upper case letter
		{
		intScore = (intScore+5)
		} // NUMBERS
		if (passwd.match(/\d+/)) // [verified] at least one number
		{
		intScore = (intScore+5)
		} if (passwd.match(/(\d.*\d.*\d)/)) // [verified] at least three numbers
		{
		intScore = (intScore+5)
		} // SPECIAL CHAR
		if (passwd.match(/[!,@#$%^&*?_~]/)) // [verified] at least one special character
		{
		intScore = (intScore+5)
		} if (passwd.match(/([!,@#$%^&*?_~].*[!,@#$%^&*?_~])/)) // [verified] at least two special characters
		{
		intScore = (intScore+5)
		} // COMBOS
		if (passwd.match(/[a-z]/) && passwd.match(/[A-Z]/)) // [verified] both upper and lower case
		{
		intScore = (intScore+2)
		} if (passwd.match(/\d/) && passwd.match(/\D/)) // [verified] both letters and numbers
		{
		intScore = (intScore+2)
		} // [Verified] Upper Letters, Lower Letters, numbers and special characters
		if (passwd.match(/[a-z]/) && passwd.match(/[A-Z]/) && passwd.match(/\d/) && passwd.match(/[!,@#$%^&*?_~]/))
		{
		intScore = (intScore+2)
		}
		return intScore;
	}
}