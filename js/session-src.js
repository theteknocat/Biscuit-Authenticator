// Javascript session functions, for handling login validation, tracking session expiry etc.
Biscuit.Session = {
	remaining_time: null,
	check_timer: null,
	decrement_timer: null,
	display_remaining_timer: null,
	refresh_dialog_obj: null,
	login_dialog: null,
	KeepAlive: {
		ping_timer: null,
		ping: function() {
			Biscuit.Session.Extend(function() {
				Biscuit.Session.KeepAlive.ping_timer = setTimeout('Biscuit.Session.KeepAlive.ping();', 60000);
			});
		}
	},
	Extend: function(on_success) {
		if (Biscuit.Session.remaining_time != null) {
			if (on_success == undefined) {
				var on_success = function() {}
			}
			var currTime = new Date();
			var now = currTime.getTime();
			Biscuit.Ajax.Request('/ping/'+now,'ping',{
				success: function() {
					on_success();
				}
			});
		}
	},
	InitTracker: function() {
		if (this.remaining_time != null) {
			// Check session expiry every second
			this.check_timer = setTimeout('Biscuit.Session.CheckExpiry();',1000);
			this.decrement_timer = setTimeout('Biscuit.Session.DecrementTime();',1000);
			// Setup Ajax error and complete handlers that will look for biscuit session time header and, if present, use that to refresh the countdown
			$(document).ajaxError(function(e, xhr, settings, exception) {
				var session_timeout = parseInt(xhr.getResponseHeader('X-Biscuit-Session-Time'));
				if (!isNaN(session_timeout) && session_timeout > 0) {
					Biscuit.Session.remaining_time = session_timeout;
				}
			});
			$(document).ajaxComplete(function(e, xhr, settings) {
				var session_timeout = parseInt(xhr.getResponseHeader('X-Biscuit-Session-Time'));
				if (!isNaN(session_timeout) && session_timeout > 0) {
					Biscuit.Session.remaining_time = session_timeout;
				}
			});
		}
	},
	CheckExpiry: function() {
		if (this.remaining_time <= 120) {
			// 2 minute warning
			this.ExpiryRefreshHandler();
		} else {
			this.check_timer = setTimeout('Biscuit.Session.CheckExpiry();',1000);
		}
	},
	DecrementTime: function() {
		// Decrease by one second
		if (this.remaining_time >= 0) {
			this.remaining_time -= 1;
			this.decrement_timer = setTimeout('Biscuit.Session.DecrementTime();',1000);
		}
	},
	ExpiryRefreshHandler: function() {
		this.refresh_already_handled = true;
		clearTimeout(this.check_timer);
		clearTimeout(this.KeepAlive.ping_timer);
		var message = '<h4><strong>'+__('login_expiry')+'</strong></h4>';
		var remaining_time = '<strong>'+this.FormattedRemainingTime()+'</strong>';
		message += '<p id="session-time-remaining">'+__('login_time_remaining',[remaining_time])+'</p>';
		this.display_remaining_timer = setTimeout('Biscuit.Session.UpdateRemainingTimeDisplay();',1005);
		this.refresh_dialog_obj = Biscuit.Crumbs.Confirm(message,function() {
			clearTimeout(Biscuit.Session.display_remaining_timer);
			Biscuit.Session.Extend(function() {
				Biscuit.Session.check_timer = setTimeout('Biscuit.Session.CheckExpiry();',1000);
			});
		},__('confirm_session_extend'),function() {
			clearTimeout(Biscuit.Session.display_remaining_timer);
			clearTimeout(Biscuit.Session.decrement_timer);
			Biscuit.Session.Logout(false);
		},__('cancel_session_extend'));
	},
	UpdateRemainingTimeDisplay: function() {
		if (this.remaining_time < 0) {
			this.refresh_dialog_obj.dialog('option','hide','').dialog('close');
			this.refresh_dialog_obj = null;
			this.LoginDialog();
		} else {
			var remaining_time = '<strong>'+this.FormattedRemainingTime()+'</strong>';
			$('#session-time-remaining').html(__('login_time_remaining',[remaining_time]));
			this.display_remaining_timer = setTimeout('Biscuit.Session.UpdateRemainingTimeDisplay();',1000);
		}
	},
	LoginDialog: function() {
		// Open a new dialog with a login form:
		var loading_dialog = Biscuit.Crumbs.LoadingBox(__('login_form_retrieving'));
		Biscuit.Ajax.Request('/login','update',{
			data: {
				'login_dialog_request': 1
			},
			type: 'get',
			success: function(html) {
				loading_dialog.dialog('option','hide','').dialog('close');
				Biscuit.Session.login_dialog = Biscuit.Crumbs.Confirm('<h4 class="attention"><strong>'+__('login_dialog_title')+'</strong></h4>'+html,function() {
					Biscuit.Session.LoginDialogSubmit();
				},__('login'),function() {
					top.location.href = top.location.href;
				});
				setTimeout('$(\'#login-form\').submit(function() { Biscuit.Session.LoginDialogSubmit(); Biscuit.Session.login_dialog.dialog(\'option\',\'hide\',\'\').dialog(\'close\'); return false; });',250);
			},
			error: function() {
				loading_dialog.dialog('option','hide','').dialog('close');
				Biscuit.Crumbs.Alert(__('login_form_retrieval_fail'));
			}
		});
	},
	LoginDialogSubmit: function() {
		var pending_login_dialog = Biscuit.Crumbs.LoadingBox(__('checking_credentials'));
		var params = $('#login-form').serialize();
		params += '&login_dialog_request=1';
		Biscuit.Ajax.Request('/login','login',{
			data: params,
			type: 'post',
			success: function(data) {
				pending_login_dialog.dialog('option','hide','').dialog('close');
				Biscuit.Session.remaining_time = data.remaining_session_time;
				this.decrement_timer = setTimeout('Biscuit.Session.DecrementTime();',1000);
				Biscuit.Session.check_timer = setTimeout('Biscuit.Session.CheckExpiry();',1000);
			},
			error: function() {
				pending_login_dialog.dialog('option','hide','').dialog('close');
				Biscuit.Crumbs.Alert('<h4 class="attention"><strong>'+__('invalid_credentials')+'</strong></h4>',__('notice'),function() {
					Biscuit.Session.LoginDialog();
				},__('try_again'));
			}
		});
	},
	FormattedRemainingTime: function() {
		var minutes = 0;
		var seconds = 0;
		if (this.remaining_time >= 60) {
			minutes = Math.floor(this.remaining_time/60);
			seconds = this.remaining_time-(minutes*60);
		} else {
			minutes = 0;
			seconds = this.remaining_time;
		}
		if (minutes < 10) {
			minutes = '0'+minutes;
		}
		if (seconds < 10) {
			seconds = '0'+seconds;
		}
		return minutes+':'+seconds;
	},
	Logout: function(is_auto) {
		var pathname = top.location.pathname;
		if (pathname == '/') {
			pathname = '';
		}
		if (pathname.substr(0,1) != '/' && pathname.length > 0) {
			pathname = '/'+pathname;
		}
		top.location.href = pathname+'/logout?js_auto_logout='+(is_auto ? 1 : 0);
	}
}
