<?php

declare(strict_types=1);

namespace PostalWarmup\Admin;



/**
 * Gestionnaire des réglages spécifiques au Warmup
 */
class WarmupSettings {

	public function register_settings(): void {
		// Currently handled by the main Settings class which consolidates everything.
		// This class is kept for potential future expansion or specific sub-page logic if needed.
		// For now, it's a placeholder to maintain architectural separation if we decide to split Settings.
	}
}
