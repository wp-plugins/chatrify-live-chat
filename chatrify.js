(function($) {

var Chatrify = {

	server_url: 'https://app.chatrify.com',

	init: function () {
		if ($('#chatrify').length <= 0) {
			return;
		}
		this.resetData();
		this.toggleFrm();
		this.userAlreadyExistsForm();
		this.newCustomerForm();
		this.cpResize();
		this.fadeFrms();
		this.showSettings();

		if ($('#license_number').val() != 0
			&& $('#license_number').val().length > 0) {

			$.ajax({
				url: Chatrify.server_url + '/api/groupdetail/?company_uid='+$('#license_number').val()+'&jsoncallback=?',
				type: "GET",
				dataType: 'jsonp',
				cache: false,
				success: function (data, status, error) {

					if (data.status == 'false') {
						return;
					}

					var currSkill = $('#skill').val();

					var htm = '<select name="skill" id="skill">';

					for (var grp_id in data.groups) {
						var grp = data.groups[grp_id];
						htm += '<option value="' + grp_id + '">' + grp + '</option>';
					}

					htm += '</select>';

					$('#skill').replaceWith(htm);

					$('#skill').val(currSkill);

				},
				error: function (data, status, error) {
					
				}
			});
		}
	},

	resetData: function() {
		$('#mc_reset_settings a').click(function()
		{
			return confirm('This will reset your Chatrify plugin settings. Continue?');
		})
	},

	toggleFrm: function()
	{
		var toggleFrm = function()
		{
			// display account details page if license number is already known
			if ($('#mc_choice_account').length == 0 || $('#choice_account_1').is(':checked'))
			{
				$('#chatrify_new_account').hide();
				$('#chatrify_already_have').show();
				$('#chatrify_login').focus();
			}
			else if ($('#choice_account_0').is(':checked'))
			{
				$('#chatrify_already_have').hide();
				$('#chatrify_new_account').show();

				if ($.trim($('#name').val()).length == 0)
				{
					$('#name').focus();
				}
				else
				{			
					$('#password').focus();
				}
			}
		};

		toggleFrm();
		$('#mc_choice_account input').click(toggleFrm);
	},

	userAlreadyExistsForm: function()
	{
		$('#chatrify_already_have form').submit(function()
		{
			if ($('#license_number').val().toString() !== "0") {
				return;
			}

			var login = $.trim($('#chatrify_login').val());
			if (!login.length)
			{
				$('#chatrify_login').focus();
				return false;
			}

			$('#chatrify_already_have .ajax_message').removeClass('message').addClass('wait').html('Please wait&hellip;');
			
			$.ajax({
				url: Chatrify.server_url + '/api/logindetail/?email='+login+'&jsoncallback=?',
				type: "GET",
				dataType: 'jsonp',
				cache: false,
				success: function (data, status, error) {

					if (data.status == 'false') {
						$('#chatrify_already_have .ajax_message').removeClass('wait').addClass('message').html('Incorrect Chatrify login.');
						$('#chatrify_login').focus();
						return false;
					} else {
						$('#license_number').val(data.company_uid);
						$('#chatrify_already_have form').submit();
					}

				},
				error: function (data, status, error) {
					$('#chatrify_already_have .ajax_message').removeClass('wait').addClass('message').html('Try again.');
					$('#chatrify_login').focus();
				}
			});
			return false;

		});		
	},

	newCustomerForm: function()
	{
		$('#chatrify_new_account form').submit(function()
		{
			if ($('#new_license_number').val() != 0
				&& $('#new_license_number').val().length > 0) {
				return true;
			}

			if (Chatrify.ValidateNewCustForm())
			{
				$('#chatrify_new_account .ajax_message').removeClass('message').addClass('wait').html('Please wait&hellip;');

				// Check if email address is available
				$.getJSON(Chatrify.server_url + '/api/checkemail?email='+$('#email').val()+'&jsoncallback=?',
				function(response)
				{
					if (response.status == 'true')
					{
						Chatrify.createCustomer();
					}
					else if (response.status == 'false')
					{
						$('#chatrify_new_account .ajax_message').removeClass('wait').addClass('message').html('This email address is already in use. Please choose another e-mail address.');
					}
					else
					{
						$('#chatrify_new_account .ajax_message').removeClass('wait').addClass('message').html('Could not create account. Please try again later.');
					}
				});
			}

			return false;
		});
	},

	createCustomer: function()
	{
		var url;

		$('#chatrify_new_account .ajax_message').removeClass('message').addClass('wait').html('Creating new account&hellip;');

		url = Chatrify.server_url + '/api/signup/';
		url += '?name='+encodeURIComponent($('#name').val());
		url += '&email='+encodeURIComponent($('#email').val());
		url += '&password='+encodeURIComponent($('#password').val());
		url += '&action=wordpress_signup';
		url += '&jsoncallback=?';

		$.getJSON(url, function(data)
		{

			if (data.status == 'ERROR')
			{
				$('#chatrify_new_account .ajax_message').html('Could not create account. Please try again later.').addClass('message').removeClass('wait');
				return false;
			}

			// save new licence number
			$('#new_license_number').val(data.company_uid);
			$('#save_new_license').submit();
		});
	},

	ValidateNewCustForm: function()
	{
		if ($('#name').val().length < 1)
		{
			alert ('Please enter your name.');
			$('#name').focus();
			return false;
		}

		if (/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,6}$/i.test($('#email').val()) == false)
		{
			alert ('Please enter a valid email address.');
			$('#email').focus();
			return false;
		}

		if ($.trim($('#password').val()).length < 6)
		{
			alert('Password must be at least 6 characters long');
			$('#password').focus();
			return false;
		}

		if ($('#password').val() !== $('#password_retype').val())
		{
			alert('Both passwords do not match.');
			$('#password').val('');
			$('#password_retype').val('');
			$('#password').focus();
			return false;
		}

		return true;
	},

	calculateGMT: function()
	{
		var date, dateGMTString, date2, gmt;

		date = new Date((new Date()).getFullYear(), 0, 1, 0, 0, 0, 0);
		dateGMTString = date.toGMTString();
		date2 = new Date(dateGMTString.substring(0, dateGMTString.lastIndexOf(" ")-1));
		gmt = ((date - date2) / (1000 * 60 * 60)).toString();

		return gmt;
	},

	cpResize: function()
	{
		var cp = $('#control_panel');
		if (cp.length)
		{
			var cp_resize = function()
			{
				var cp_height = window.innerHeight ? window.innerHeight : $(window).height();
				cp_height -= $('#wphead').height();
				cp_height -= $('#updated-nag').height();
				cp_height -= $('#control_panel + p').height();
				cp_height -= $('#footer').height();
				cp_height -= 70;

				cp.attr('height', cp_height);
			}
			cp_resize();
			$(window).resize(cp_resize);
		}
	},

	fadeFrms: function()
	{
		$cs = $('#changes_saved_info');

		if ($cs.length)
		{
			setTimeout(function()
			{
				$cs.slideUp();
			}, 1000);
		}
	},

	showSettings: function()
	{
		$('#mc_advanced-link a').click(function()
		{
			if ($('#advanced').is(':visible'))
			{
				$(this).html('Show advanced settings&hellip;');
				$('#advanced').slideUp();
			}
			else
			{
				$(this).html('Hide advanced settings&hellip;');
				$('#advanced').slideDown();
			}

			return false;
		})
	}


}

	$(document).ready(function () {
		Chatrify.init();
	});

})(jQuery);