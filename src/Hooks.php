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
		$parser->setFunctionHook( 'liquidity', [ self::class, 'liquidity' ] );
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

	/**
	 * Liquiditätsplanung üfr die kommenden 12 Monate
	 */
	static function liquidity( $parser ) {
		$liquidity = "
,worst,best
Jan,500,1000
Feb,100,1500";
		$liquidity = "<pLines ymin=0 ymax=10000 axiscolor=888888 cubic filled angle=90 plots legend>" . $liquidity . "
</pLines>";

		$kontostand = $parser->recursiveTagParse( '{{formatnum:{{#ask:[[Semorg-role-metric-measurement-metric::Kennzahlen/9]]|mainlabel=-|?semorg-role-metric-measurement-value#=|sort=semorg-role-metric-measurement-date|order=desc|limit=1|searchlabel=}}|R}}' );

		$heute = new \DateTime();
		$kontostand_datum = new \DateTime( $parser->recursiveTagParse( '{{#ask:[[Semorg-role-metric-measurement-metric::Kennzahlen/9]]|mainlabel=-|?semorg-role-metric-measurement-date#ISO=|sort=semorg-role-metric-measurement-date|order=desc|limit=1|searchlabel=}}' ) );

		$auszahlungen_seither = $parser->recursiveTagParse( '{{#ask:[[Kategorie:Verrechnung]][[Datum::>>' . $kontostand_datum->format('Y-m-d') . ']]|?Betrag#|format=sum|default=0}}');
		$auszahlungen_offen = $parser->recursiveTagParse( '{{#expr:{{#ask:[[Konto::Arbeitszeit]][[Teammitglied::+]][[Auszahlungsbetrag::>>0]]
        |?Auszahlungsbetrag#
        |format=sum
        |default=0
        |limit=1000
      }} + {{#ask:[[Referenz.Teammitglied::+]]
        |?Betrag#
        |format=sum
        |default=0
        |limit=1000
      }}}}' );

		$liquidity = [];
		for( $i = 0; $i < 12; $i++ ) {
			$date = new \DateTime( $kontostand_datum->format('Y-m-1') );
			$date->add(new \DateInterval('P' . $i . 'M'));
			$firstday = ( $i == 0 ? $kontostand_datum : new \DateTime( $date->format('Y-m-1') ) );
			$next_month = clone $firstday;
			$next_month->add( new \DateInterval( 'P1M' ) );
		       	$lastday = new \DateTime( $next_month->format('Y-m-1') );
			$lastday->sub(new \DateInterval('PT1S'));

			// Auszahlungen
			$auszahlungen = $parser->recursiveTagParse( '{{#expr:{{#ask:[[Roadmap::Roadmap Q{{semorg-quarter|' . $date->format('Y-m-1') . '}}/' . $date->format('Y') . ']][[Wochenstunden::+]]|?Wochenstunden#|format=sum|default=0}}*13*15/3}}');

			$liquidity[] = [ 
				'datestring' => $date->format('Y-m-1'), 
				'date' => $date,
			    	'firstday' => $firstday,
				'lastday' => $lastday,
				'auszahlungen' => (float) $auszahlungen,
				'transaktionen' => 0,
				'text' => [],
			];
		}

		$transaktionen = explode( '&lt;TRANS&gt;', $parser->recursiveTagParse( '{{#ask:[[semorg-liquidity-planning-end-date::>{{#time:Y-m-d}}]][[semorg-liquidity-planning-status::100]]|mainlabel=-|?semorg-liquidity-planning-date#ISO=|?semorg-liquidity-planning-end-date#ISO=|?semorg-liquidity-planning-amount#=|?semorg-liquidity-planning-frequency=|?semorg-liquidity-planning-title=|format=array|sep=<TRANS>}}' ));
		foreach( $transaktionen as $transaktion ) {
			$transaktion = explode( '&lt;PROP&gt;', $transaktion );
			$start = new \DateTime( $transaktion[0] );
			$ende = new \DateTime( $transaktion[1] );
			$amount = $transaktion[2];
			$frequenz = $transaktion[3];
			$text = $transaktion[4];
			if( $frequenz == 'once' ) {
				foreach( $liquidity as $key => $month ) {
					if( $start >= $month['firstday'] && $ende <= $month['lastday'] ) {
						$liquidity[$key]['transaktionen'] += $amount;
						$liquidity[$key]['text'][] = $text;
					}
				}
			}
			if( $frequenz == 'year' ) {
				while( $start <= $ende ) {
					foreach( $liquidity as $key => $month ) {
						if( $start >= $month['firstday'] && $start <= $month['lastday'] ) {
							$liquidity[$key]['transaktionen'] += $amount;
							$liquidity[$key]['text'][] = $text;
						}
					}

					$start->add( new \DateInterval( 'P1Y' ) );
				}
			}
			if( $frequenz == 'quarter' ) {
				while( $start <= $ende ) {
					$day = $start->format('d');

					foreach( $liquidity as $key => $month ) {
						if( $start >= $month['firstday'] && $start <= $month['lastday'] ) {
							$liquidity[$key]['transaktionen'] += $amount;
							$liquidity[$key]['text'][] = $text;
						}
					}

					$start = new \DateTime( $start->format('Y-m-1') );
					$start->add( new \DateInterval( 'P3M' ) );
					$start = new \DateTime( $start->format('Y-m-') . $day );
				}
			}
			if( $frequenz == 'month' ) {
				foreach( $liquidity as $key => $month ) {
					if( $start <= $month['lastday'] && $ende >= $month['firstday'] ) {
						$liquidity[$key]['transaktionen'] += $amount;
					}
				}
			}
		}

		$kumuliert = $kontostand;
		$kumuliert += $auszahlungen_seither;
		$kumuliert -= $auszahlungen_offen;

		$html = '<ul class="mb-4">';
		$html .= '<li>aktueller Kontostand: € {{formatnum:' . $kontostand . '}} <small>(per ' . $kontostand_datum->format('j.n.Y') . ')</small></li>';
		$html .= '<li>Auszahlungen seither: € {{formatnum:' . -$auszahlungen_seither . '}}</li>';
		$html .= '<li>offene Auszahlungen: € {{formatnum:' . $auszahlungen_offen . '}}</li>';
		$html .= '</ul>';

		$html .= '<div><table class="table table-sm table-bordered"><tr><th>Monat</th><th>Auszahlungen</th><th>Transaktionen</th><th>kumuliert</th></tr>';
		foreach( $liquidity as $month ) {
			$kumuliert = $kumuliert - $month['auszahlungen'] + $month['transaktionen'];
			$html .= '<tr>';
			$html .= '<td>' . $month['date']->format('Y-m') . '<div class="semorg-list-row-details">' . join(', ', $month['text'] ) . '</div></td>';
			$html .= '<td class="text-right">{{semorg-currency|{{formatnum:' . round( $month['auszahlungen'] ) . '}}}}</td>';
			$html .= '<td class="text-right">{{semorg-currency|{{formatnum:' . round( $month['transaktionen'] ) . '}}}}</td>';
			$html .= '<td class="text-right">{{semorg-currency|{{formatnum:' . round( $kumuliert ) . '}}}}</td>';
			$html .= '</tr>';
		}
		$html .= '</table></div>';

		return [ $html, 'noparse' => false ];
	}

}
