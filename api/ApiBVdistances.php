<?php

/**
 * API module for geocoding.
 *
 * @since 1.0.3
 *
 * @file ApiGeocode.php
 * @ingroup Maps
 * @ingroup API
 *
 * @licence GNU GPL v2++
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ApiBVdistances extends ApiBase {
	
	public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}
	
	public function execute() {
		global $wgUser, $egMapsDefaultGeoService, $egMapsDistanceDecimals, $egMapsDistanceUnit;
		
		if ( !$wgUser->isAllowed( 'bvdistances' ) || $wgUser->isBlocked() ) {
			$this->dieUsageMsg( array( 'badaccess-groups' ) );
		}			
		
		$params = $this->extractRequestParams();
		
		$results = array();
				
		if ( MapsGeocoders::canGeocode() ) {
			$location = MapsGeocoders::attemptToGeocode( $params['location'], $egMapsDefaultGeoService );
		} else {
			$location = MapsCoordinateParser::parseCoordinates( $params['location'] );
		}

    $query = "{{#ask:[[Bundesland::+]][[aktiv::wahr]][[Lage::+]]|?Lage|?=Name|mainlabel=-|format=array|link=none|headers=plain|headersep==|sep=<BV>}}";

		$mainpage = Title::newMainPage();
		$options = new ParserOptions();
		$localparser = new Parser();
		$localparser->Title ( $mainpage );
		$localparser->Options( $options );
		$localparser->clearState();

    $bedarfsverkehre = $localparser->RecursiveTagParse( $query );
    $bedarfsverkehre = explode( '&lt;BV&gt;', $bedarfsverkehre );
    foreach( $bedarfsverkehre as $key => $props ) {
    	$props = explode( '&lt;PROP&gt;', $props );
    	$bedarfsverkehre[$key] = Array();
    	foreach( $props as $prop ) {
    		$prop = explode( '=', $prop );
    		$bedarfsverkehre[$key][$prop[0]] = $prop[1];
    		}
			$bvlocation = MapsCoordinateParser::parseCoordinates( $bedarfsverkehre[$key]['Lage'] );
			if ( $location && $bvlocation ) {
				$bedarfsverkehre[$key]['Distanz'] = MapsGeoFunctions::calculateDistance( $location, $bvlocation );
				} else {
				// The locations should be valid when this method gets called.
				throw new MWException( 'Attempt to find the distance between locations of at least one is invalid' . $bedarfsverkehre[$key]['Name'] );
				}
    	}
		
		usort( $bedarfsverkehre, array( "ApiBVdistances", "distanceSort" ) );
		$results = array_slice( $bedarfsverkehre, 0, 10 );
		
		$this->getResult()->addValue(
			null,
			'results',
			$results
		);
	}

	static function distanceSort( $a, $b ) {
		if( $a['Distanz'] == $b['Distanz'] ) {
			return 0;
			}
		return ( $a['Distanz'] < $b['Distanz'] ) ? -1 : 1;
		}

	public function getAllowedParams() {
		return array(
			'location' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_ISMULTI => false,
			),
		);
	}
	
	public function getParamDescription() {
		return array(
			'location' => 'The location to calculate the distances from'
		);
	}
	
	public function getDescription() {
		return array(
			'API module for calculation of distances from location to available demand responsive public transport systems.'
		);
	}
	
	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'missingparam', 'location' ),
		) );
	}

	protected function getExamples() {
		return array(
			'api.php?action=bvdistances&location=42.5,16.8',
			'api.php?action=bvdistances&location=Wien'
		);
	}	
	
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}		
	
}
