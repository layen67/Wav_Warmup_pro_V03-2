<?php
// src/API/Client.php

declare(strict_types=1);

namespace PostalWarmup\API;

use PostalWarmup\Models\Database;
use PostalWarmup\Services\Logger;
use PostalWarmup\Admin\Settings;

/**
 * Client API for external integrations (if any) or internal helper.
 * Manages specific Reply-To injection for tracking.
 */
class Client {

    /**
     * Generate a special Reply-To address for tracking conversations.
     * Format: reply-{hash}@domain
     *
     * @param string $domain The sending domain
     * @param int $conversation_id The conversation ID
     * @return string The email address
     */
    public static function get_tracking_reply_to( string $domain, int $conversation_id ): string {
        // Create a hash to prevent guessing, but keeping it simple enough
        // Ideally we store this hash in the conversation, or we just sign the ID.
        // For simplicity in this version, we use the ID directly or a simple hash if needed.
        // But to route it back, we need to parse it.

        // Let's use a simple format: reply-{id}-{random}@domain
        // And we store the random part in the conversation if we want security.
        // For now, let's stick to reply-{id}@domain as a basic implementation unless specified otherwise.

        // However, to avoid collisions with real accounts, use a specific prefix.
        $prefix = 'reply-' . $conversation_id;

        // Verify domain configuration for catch-all or specific alias if possible.
        // We assume the domain has a catch-all or we configured this specific address.

        return "{$prefix}@{$domain}";
    }

    /**
     * Parses a recipient email to extract the conversation ID.
     *
     * @param string $email
     * @return int|null Conversation ID or null
     */
    public static function parse_reply_to( string $email ): ?int {
        if ( ! is_email( $email ) ) {
            return null;
        }

        $local = explode( '@', $email )[0];

        if ( preg_match( '/^reply-(\d+)$/', $local, $matches ) ) {
            return (int) $matches[1];
        }

        return null;
    }
}
