<?php
/**
 * Hooks for UbiGo
 *
 * @file
 * @ingroup Extensions
 */
class UbiGoHooks
{

	static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'nearest', 'UbiGoHooks::nearestRender' );
		$parser->setFunctionHook( 'konto', 'UbiGoHooks::konto' );
		$parser->setFunctionHook( 'money', 'UbiGoHooks::money' );
		$parser->setFunctionHook( 'quellenlink', 'UbiGoHooks::quellenlink' );
		return true;
	}

	static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$out->addModules( 'ext.ubigo' );
	}
		
 
	static function nearestRender( $input, array $args, Parser $parser, PPFrame $frame ) {
		$parser->getOutput()->addModules( 'ext.nearest' );
		return '<div id="nearestBV"></div>';
	}


	/**
	 * Zahl als Währung ausgeben
	 *
	 * @param Float $betrag
	 */
	static function money( $parser, $betrag ) {
		setlocale(LC_MONETARY, 'de_DE');
		$betrag = '€ ' . money_format( '%!n', $betrag );
		return $betrag;
	}

	/**
	 * Kontoinformationen für UbiGo-Mitglieder ausgeben
	 *
	 * @param Parser $parser
	 * @param String $mitglied
	 * @param String $konto
	 */
	static function konto( $parser, $mitglied = NULL, $konto = NULL, $datum = NULL ) {
		if( !is_null( $datum ) ) {
			$datum = new DateTime( $datum );
		} else {
			$datum = new DateTime( 'now' );
		}
		$conditions = '[[Kategorie:Arbeitszeit || Verrechnung]]';
		if( ! is_null( $konto ) ) {
			$conditions = '[[Kategorie:' . $konto . ']]';
		}
		if( ! is_null( $mitglied ) && $mitglied != '') {
			$conditions .= '[[Teammitglied::Benutzer:' . $mitglied . ']]';
		}
		$query = '{{#ask:' . $conditions . '|?Betrag#|?Datum#ISO|mainlabel=-|limit=10000|format=array|link=none|headers=plain|headersep==|sep=<Buchung>}}'; 
		$buchungen = $parser->recursiveTagParse( $query );

	    $buchungen = explode( '&lt;Buchung&gt;', $buchungen );
		$betrag = 0;

	    foreach( $buchungen as $key => $props ) {
	    	$props = explode( '&lt;PROP&gt;', $props );
	    	$buchungen[$key] = Array();
	    	foreach( $props as $prop ) {
	    		$prop = explode( '=', $prop );
	    		$buchungen[$key][$prop[0]] = $prop[1];
	   		}
		}
		foreach( $buchungen as &$buchung ) {
			$buchungsdatum = new DateTime( $buchung['Datum'] );
			$zeitdifferenz = $buchungsdatum->diff( $datum );
			$buchung['Zeitdifferenz'] = $zeitdifferenz->y;
			$buchung['Zinsbetrag'] = $buchung['Betrag'] * pow( 1.04, $zeitdifferenz->days/365 );
			$betrag += $buchung['Zinsbetrag'];
		}
		$betrag = round( $betrag, 2 );
//		die( var_dump( $buchungen ) );
		
		return $betrag;
	}
	
	static function siteBodyClasses( $skinTweeki, &$additionalBodyClasses ) {
		$additionalBodyClasses[] = 'site-' . $GLOBALS['wgSitename'];
		return true;
	}
	

	/**
	 * Quelle als Link zurückgeben (falls es sich um Link handelt)
	 *
	 * @param String $quelle
	 */
	static function quellenlink( $parser, $quelle ) {
		if( strpos( $quelle, 'http' ) === 0 ) {
			$quelle = '[' . $quelle . ']';
		}
		return $quelle;
	}

}
