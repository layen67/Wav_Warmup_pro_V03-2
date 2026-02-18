<?php

namespace PostalWarmup\Core;

declare(strict_types=1);

/**
 * Define the internationalization functionality.
 */
class i18n {

	public function load_plugin_textdomain(): void {
		load_plugin_textdomain(
			'postal-warmup',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
