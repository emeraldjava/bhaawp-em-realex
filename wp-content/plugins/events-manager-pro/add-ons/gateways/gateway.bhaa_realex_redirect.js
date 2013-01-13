//add realex_redirect redirection
$(document).bind('em_booking_gateway_add_realex_redirect', function(event, response){ 
	// called by EM if return JSON contains gateway key, notifications messages are shown by now.
	if(response.result){
		var ppForm = $('<form action="'+response.realex_redirect_url+'" method="post" id="em-realex_redirect-redirect-form"></form>');
		$.each( response.realex_redirect_vars, function(index,value){
			ppForm.append('<input type="hidden" name="'+index+'" value="'+value+'" />');
		});
		ppForm.append('<input id="em-realex_redirect-submit" type="submit" style="display:none" />');
		ppForm.appendTo('body').trigger('submit');
	}
});