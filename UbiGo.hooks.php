<?php
/**
 * Hooks for UbiGo
 *
 * @file
 * @ingroup Extensions
 */
class UbiGoHooks
{

	static function nearestSetup( Parser $parser ) {
		$parser->setHook( 'nearest', 'UbiGoHooks::nearestRender' );
				 return true;
	}
 
	static function nearestRender( $input, array $args, Parser $parser, PPFrame $frame ) {
		$parser->getOutput()->addModules( 'ext.nearest' );
		return '<div id="nearestBV"></div>';
	}
	
}