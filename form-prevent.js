if( $('.LV_invalid').length == 0 ) {
	setTimeout( 
		function () {
			event.preventDefault();
			form.submit();
			}, 
		800);
	}
});
