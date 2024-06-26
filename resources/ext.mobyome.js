$( document ).ready( function() {
	$( '.checknow').click( function(e) {
		var now = new Date();
		$( '.letztercheck input').val( now.getFullYear() + '/' + (now.getMonth() + 1) + '/' + now.getDate() );
	});

	$('.stunden').trigger('keyup');
});

$( document ).on('keyup', '.stunden', function(e) {
	var stunden = parseFloat( $(this).val().replace(',','.') );
	var stundensatz = mw.message('Auszahlungsstundensatz').text().replace(',','.');
	var text = '(von maximal € ' + ( Math.round( stunden * stundensatz ) ) + ',-)';
	if( ! stunden > 0 ) {
		text = '';
	}
	$( '.auszahlungsbetrag' ).text( text );
});
