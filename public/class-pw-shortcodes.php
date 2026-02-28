<?php

/**
 * Public Shortcodes Handler
 */
class PW_Shortcodes {

	public function __construct() {
		// Nothing to init here, hooks in Plugin.php via Mailto service
	}

	// Legacy file kept for architecture, but logic moved to Services\Mailto
	// If themes include this file directly, we ensure it doesn't crash.
}
