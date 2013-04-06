/*
 * Password strength jQuery plugin.
 * Author: Peter Epp
 * 
 */
(function($){ 
	$.fn.extend({  
		pwdstr: function(el) {
			return this.each(function() {
				var alpha = "abcdefghijklmnopqrstuvwxyz";
				var upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
				var upper_punct = "~`!@#$%^&*()-_+=";
				var digits = "1234567890";

				var totalChars = 0x7f - 0x20;
				var alphaChars = alpha.length;
				var upperChars = upper.length;
				var upper_punctChars = upper_punct.length;
				var digitChars = digits.length;
				var otherChars = totalChars - (alphaChars + upperChars + upper_punctChars + digitChars);

				function calculateBits(passWord) {
					if (passWord.length <= 0) {
						return 0;
					}

					var fAlpha = false;
					var fUpper = false;
					var fUpperPunct = false;
					var fDigit = false;
					var fOther = false;
					var charset = 0;

					for (var i = 0; i < passWord.length; i++) {
						var char = passWord.charAt(i);

						if (alpha.indexOf(char) != -1)
							fAlpha = true;
						else if (upper.indexOf(char) != -1)
							fUpper = true;
						else if (digits.indexOf(char) != -1)
							fDigit = true;
						else if (upper_punct.indexOf(char) != -1)
							fUpperPunct = true;
						else
							fOther = true;
					}

					if (fAlpha)
						charset += alphaChars;
					if (fUpper)
						charset += upperChars;
					if (fDigit)
						charset += digitChars;
					if (fUpperPunct)
						charset += upper_punctChars;
					if (fOther)
						charset += otherChars;

					var bits = Math.log(charset) * (passWord.length / Math.log(2));

					return Math.floor(bits);
				}

				$(this).keyup(function() {
					var bits = calculateBits($(this).val());
					var width = 0;
					var str_text = __('pwd_strength_strength');
					var bg_color = '#FF4545';
					if (bits >= 128) {
						width = 100;
						bg_color = '#a4ff39';
						str_text = __('pwd_strength_best');
					} else {
						width = Math.floor((bits/128)*100);
						if (bits > 84) {
							bg_color = '#a4ff39';
							str_text = __('pwd_strength_strong');
						} else if (bits > 42) {
							bg_color = '#ffc63c';
							str_text = __('pwd_strength_medium');
						} else if (bits > 0) {
							bg_color = '#FF4545';
							str_text = __('pwd_strength_weak');
						}
					}
					var curr_width = $(el+'-meter').css('width');
					if (curr_width != width+'%') {
						$(el+'-meter').css({
							'background': bg_color,
							'width': width+'%'
						});
						$(el+'-text').text(str_text);
					}
				});

			});
		} 
	}); 
})(jQuery);