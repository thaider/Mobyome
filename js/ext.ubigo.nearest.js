/**
 * scripts to show nearest demand responsive transport offers
 */

jQuery( function( $ ) {

	nearestApiURL = wgServer + wgScriptPath + '/api.php?action=bvdistances&format=json&location=';
	
	var geoOptions = {
			enableHighAccuracy: true, // Super-Präzisions-Modus
			timeout: 20000, // Maximale Wartezeit vor Fehler
			maximumAge: 0 // Maximales akzeptiertes Cache-Alter
		};
	
/*
	$( '#nearestBV' ).append( '<button id="showNearestBV" class="btn btn-default"><span class="glyphicon glyphicon-map-marker"></span> nächstgelegene Bedarfsverkehre anzeigen</button>');
	$( '#showNearestBV' ).click( function() {
		$( this ).find( 'span' ).remove();
		$( this ).prepend( '<i class="fa fa-spinner fa-spin"></i>' );
		locateMe(); 
		});
*/

	$( '#nearestBV' ).append( '<i class="fa fa-spinner fa-spin"></i>' );
	locateMe();


	function locateMe() {
		if( navigator.geolocation ) {
			navigator.geolocation.getCurrentPosition(
						function( position ) {
							latlng = position.coords.latitude + "," + position.coords.longitude;
							loadNearest( latlng );
							}, 
						function( error ) {
							$( '#nearestBV' ).html( 'Bestimmung des Standorts fehlgeschlagen' );
							console.log( "Geolocation Fail: " + error.message + ", " + error.code );
							}, 
						geoOptions
						);
				}
			else {
				$( '#nearestBV' ).html( 'Bestimmung des Standorts nicht möglich.' );
				}
		}
		
	function loadNearest( location ) {
		$.getJSON( nearestApiURL + location )
		.done(function( data ) {
/*			$( '#nearestBV' ).html( '<h4>nächstgelegene Bedarfsverkehre</h4>' ); */
			$( '#nearestBV' ).html( '' );
			$.each( data.results, function( key, bv ) {
				distanz = '<div class="distance">' + Math.ceil( bv.Distanz / 5000 ) * 5 + '</div>';
				name = '<div class="name"><a href="' + mw.util.getUrl( bv.Name ) + '">' + bv.Name + '</a></div>';
				$( '#nearestBV' ).append( '<div>' + distanz + name + '</div>' );
				});
			});
		}


	
});
