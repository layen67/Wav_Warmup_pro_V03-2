<?php

namespace PostalWarmup\Services;

use PostalWarmup\Models\Database;
use PostalWarmup\Services\Logger;

declare(strict_types=1);

/**
 * Service de mise à jour des templates depuis une source externe (si nécessaire)
 */
class TemplateSync {

	public static function sync_remote(): void {
		// Placeholder for remote sync logic (e.g. from GitHub or API)
		// For now, it's a stub to satisfy class existence
		Logger::info( 'TemplateSync: Sync initiated (Stub).' );
	}
}
