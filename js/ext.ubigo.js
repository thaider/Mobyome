$( document ).ready( function() {
	$( '.checknow').click( function(e) {
		var now = new Date();
		$( '.letztercheck input').val( now.getFullYear() + '/' + (now.getMonth() + 1) + '/' + now.getDate() );
	});
});
