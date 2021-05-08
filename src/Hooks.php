<?php

namespace MediaWiki\Extension\Mobyome;

/**
 * Hooks for mobyome
 *
 * @file
 * @ingroup Extensions
 */
class Hooks {

	static private $cache;

	static function onParserFirstCallInit( \Parser $parser ) {
		$parser->setFunctionHook( 'konto', [ self::class, 'konto' ] );
		$parser->setFunctionHook( 'zinsen', [ self::class, 'zinsen' ] );
		$parser->setFunctionHook( 'money', [ self::class, 'money' ] );
		return true;
	}


	/**
	 * Module laden
	 */
	static function onBeforePageDisplay( \OutputPage &$out, \Skin &$skin ) {
		$out->addModules( 'ext.mobyome' );
	}


	/**
	 * Kontoinformationen für Mobyome-Mitglieder ausgeben
	 *
	 * @param Parser $parser
	 * @param String $mitglied Benutzername
	 * @param String $konto Konto (Arbeitszeit oder Verrechnung)
	 * @param String $datum Datum des Stichtags für die Berechnung
	 *
	 * @return Float Kontostand
	 */
	static function konto( $parser, $mitglied = NULL, $konto = NULL, $datum = NULL, $verzinst = false ) {
		if( !is_null( $datum ) && $datum != '') {
			$datum = new \DateTime( $datum );
		} else {
			$datum = new \DateTime( 'now' );
		}
		$conditions = '[[Kategorie:Arbeitszeit || Verrechnung]]';
		if( ! is_null( $konto ) && $konto != '' ) {
			$conditions = '[[Kategorie:' . $konto . ']]';
		}
		if( isset( self::$cache[$conditions] ) ) {
			$buchungen = self::$cache[$conditions];
		} else {	
			$query = '{{#ask:' . $conditions . '
			|?Betrag#
				|?Datum#ISO
				|?Teammitglied
				|mainlabel=-
				|limit=10000
				|format=array
				|link=none
				|headers=plain
				|headersep==
				|sep=<Buchung>
		}}'; 
			$buchungen = $parser->recursiveTagParse( $query );

			if( $buchungen == "" ) {
				return 0;
			}

			$buchungen = explode( '&lt;Buchung&gt;', $buchungen );

			foreach( $buchungen as $key => $props ) {
				$props = explode( '&lt;PROP&gt;', $props );
				$buchungen[$key] = Array();
				foreach( $props as $prop ) {
					$prop = explode( '=', $prop );
					$buchungen[$key][$prop[0]] = $prop[1];
				}
			}
			self::$cache[$conditions] = $buchungen;
		}

		$betrag = 0;
		foreach( $buchungen as &$buchung ) {
			// nach Mitglied filtern?
			if( ! is_null( $mitglied ) && $mitglied != '' && $buchung['Teammitglied'] != 'Benutzer:' . $mitglied ) {
				continue;
			}
			if( $verzinst !== false ) {
				$buchungsdatum = new \DateTime( $buchung['Datum'] );
				$zeitdifferenz = $buchungsdatum->diff( $datum );
				$buchung['Zeitdifferenz'] = $zeitdifferenz->y;
				$buchung['Zinsbetrag'] = $buchung['Betrag'] * pow( 1.04, $zeitdifferenz->days/365 );
				$betrag += $buchung['Zinsbetrag'];
			} else {
				$betrag += $buchung['Betrag'];
			}
		}
		$betrag = round( $betrag, 2 );

		return $betrag;
	}


	/**
	 * Zinsen zurückgeben
	 *
	 * @param Parser $parser
	 * @param String $mitglied Benutzername
	 * @param String $datum Datum des Stichtags für die Berechnung
	 *
	 * @return Float Zinsbetrag
	 */
	static function zinsen( $parser, $mitglied = NULL, $datum = NULL ) {
		if( !is_null( $datum ) ) {
			$datum = new \DateTime( $datum );
		} else {
			$datum = new \DateTime( 'now' );
		}
		$conditions = '[[Kategorie:Arbeitszeit || Verrechnung]]';
		if( isset( self::$cache[$conditions] ) ) {
			$buchungen = self::$cache[$conditions];
		} else {	
			$query = '{{#ask:' . $conditions . '
				|?Betrag#
				|?Datum#ISO
				|?Teammitglied
				|mainlabel=-
				|limit=10000
				|format=array
				|link=none
				|headers=plain
				|headersep==
				|sep=<Buchung>
			}}'; 
			$buchungen = $parser->recursiveTagParse( $query );

			if( $buchungen == "" ) {
				return 0;
			}

			$buchungen = explode( '&lt;Buchung&gt;', $buchungen );

			foreach( $buchungen as $key => $props ) {
				$props = explode( '&lt;PROP&gt;', $props );
				$buchungen[$key] = Array();
				foreach( $props as $prop ) {
					$prop = explode( '=', $prop );
					$buchungen[$key][$prop[0]] = $prop[1];
				}
			}
			self::$cache[$conditions] = $buchungen;
		}

		$betrag = 0;
		foreach( $buchungen as &$buchung ) {
			// nach Mitglied filtern?
			if( ! is_null( $mitglied ) && $mitglied != '' && $buchung['Teammitglied'] != 'Benutzer:' . $mitglied ) {
				continue;
			}
			$buchungsdatum = new \DateTime( $buchung['Datum'] );
			$zeitdifferenz = $buchungsdatum->diff( $datum );
			$buchung['Zeitdifferenz'] = $zeitdifferenz->y;
			$buchung['Zinsbetrag'] = $buchung['Betrag'] * pow( 1.04, $zeitdifferenz->days/365 );
			$betrag += $buchung['Zinsbetrag'] - $buchung['Betrag'];
		}
		$betrag = round( $betrag, 2 );

		return $betrag;
	}


	/**
	 * Klasse zur Unterscheidung der Subseiten hinzufügen
	 *
	 * @param Skin $skinTweeki
	 * @param Array $additionalBodyClasses
	 */
	static function onSkinTweekiAdditionalBodyClasses( $skinTweeki, &$additionalBodyClasses ) {
		$additionalBodyClasses[] = 'site-' . $GLOBALS['wgSitename'];
		return true;
	}

	/**
	 * Zahl als Währung ausgeben
	 *
	 * @param Parser $parser
	 * @param Float $betrag
	 *
	 * @return String formatierter Währungsbetrag
	 */
	static function money( $parser, $betrag ) {
		setlocale(LC_MONETARY, 'de_DE');
		$betrag = '€&nbsp;' . money_format( '%!n', $betrag );
		return $betrag;
	}

}
