<?php
namespace CRH;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {
	public static function init() : void {
		// Placeholder for admin hooks; provides options accessors used by Areas
	}

	/**
	 * Get plugin options. Prefer ACF options if available, otherwise return sensible defaults.
	 * Expected keys used in code:
	 * - enabled (bool)
	 * - accordion_default_open (bool)
	 */
	public static function get() : array {
		$defaults = [
			'enabled' => true,
			'accordion_default_open' => true,
		];

		// If ACF is present, you can map fields e.g. get_field('crh_areas_enabled', 'option')
		if ( function_exists( 'get_field' ) ) {
			$acf_enabled = get_field( 'crh_areas_enabled', 'option' );
			$acf_accordion = get_field( 'crh_areas_accordion_default_open', 'option' );
			if ( $acf_enabled !== null ) {
				$defaults['enabled'] = (bool) $acf_enabled;
			}
			if ( $acf_accordion !== null ) {
				$defaults['accordion_default_open'] = (bool) $acf_accordion;
			}
		}

		return $defaults;
	}
}
