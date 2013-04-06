var UserEdit = {
	username_check_timeout: null,
	checking_username: false,
	initial_username_value: '',
	init_username_check: function() {
		UserEdit.initial_username_value = $('#user-edit-form #attr_username').val();
		$('#user-edit-form #attr_username').after('<span id="username-check"></span>');
		$('#user-edit-form #attr_username').keyup(function(event) {
			if (event.keyCode != 9 && event.keyCode != 27) { // Ignore tab and escape keys
				clearTimeout(UserEdit.username_check_timeout);
				var old_val = $(this).val();
				new_val = old_val.replace(/[^a-zA-Z0-9_\-\.\@]+/, '');
				$(this).val(new_val);
				UserEdit.username_check_timeout = setTimeout(UserEdit.check_username, 500);
			}
		});
	},
	check_username: function() {
		var val = $('#attr_username').val();
		if (val != '') {
			if (UserEdit.initial_username_value != '' && val == UserEdit.initial_username_value) {
				$('#username-check').html('').removeClass('indicator').removeClass('notice').removeClass('success').removeClass('error');
			} else if (!UserEdit.checking_username) {
				UserEdit.checking_username = true;
				$('#username-check').html(__('status_checking')).addClass('indicator').addClass('notice').removeClass('success').removeClass('error');
				var url = '/users/check_username/'+val;
				Biscuit.Ajax.Request(url, 'json', {
					success: function(data,text_status,xhr) {
						if (data.exists) {
							$('#username-check').html(__('status_unavailable')).addClass('indicator').addClass('error').removeClass('success').removeClass('notice');
						} else {
							$('#username-check').html(__('status_available')).addClass('indicator').addClass('success').removeClass('error').removeClass('notice');
						}
					},
					error: function() {
						$('#username-check').html(__('status_invalid')).addClass('indicator').addClass('error').removeClass('success').removeClass('notice');
					},
					complete: function() {
						UserEdit.checking_username = false;
					}
				});
			}
		} else {
			$('#username-check').html(__('status_invalid')).addClass('indicator').addClass('error').removeClass('success').removeClass('notice');
		}
	}
}

$(document).ready(function() {
	UserEdit.init_username_check();
});
