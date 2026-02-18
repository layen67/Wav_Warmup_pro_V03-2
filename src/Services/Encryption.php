<?php

namespace PostalWarmup\Services;

use PostalWarmup\Admin\Settings;

declare(strict_types=1);

/**
 * Service de chiffrement des données sensibles (API Keys)
 */
class Encryption {

	private const METHOD = 'aes-256-cbc';

	public static function encrypt( string $data ): string {
		if ( empty( $data ) ) return '';

		$key = self::get_key();
		$iv_length = openssl_cipher_iv_length( self::METHOD );
		$iv = openssl_random_pseudo_bytes( $iv_length );

		$encrypted = openssl_encrypt( $data, self::METHOD, $key, 0, $iv );

		// Use HMAC for integrity check
		$hmac = hash_hmac( 'sha256', $encrypted, $key );

		return base64_encode( $iv . $hmac . $encrypted );
	}

	public static function decrypt( string $data ): string {
		if ( empty( $data ) ) return '';

		$key = self::get_key();
		$raw = base64_decode( $data );
		$iv_length = openssl_cipher_iv_length( self::METHOD );

		// Check minimal length (IV + HMAC + Data)
		if ( strlen( $raw ) < $iv_length + 32 ) {
			// Try legacy decryption (no HMAC) for backward compatibility
			return self::decrypt_legacy( $data );
		}

		$iv = substr( $raw, 0, $iv_length );
		$hmac = substr( $raw, $iv_length, 32 );
		$encrypted = substr( $raw, $iv_length + 32 );

		$calcmac = hash_hmac( 'sha256', $encrypted, $key );
		if ( ! hash_equals( $hmac, $calcmac ) ) {
			return ''; // Integrity failed
		}

		return openssl_decrypt( $encrypted, self::METHOD, $key, 0, $iv ) ?: '';
	}

	private static function decrypt_legacy( string $data ): string {
		$key = self::get_key();
		$raw = base64_decode( $data );
		$iv_length = openssl_cipher_iv_length( self::METHOD );

		if ( strlen( $raw ) <= $iv_length ) return '';

		$iv = substr( $raw, 0, $iv_length );
		$encrypted = substr( $raw, $iv_length );

		return openssl_decrypt( $encrypted, self::METHOD, $key, 0, $iv ) ?: '';
	}

	private static function get_key(): string {
		// Use SECURE_AUTH_KEY if available, else fallback to a stored random key
		if ( defined( 'SECURE_AUTH_KEY' ) ) {
			return substr( SECURE_AUTH_KEY, 0, 32 );
		}

		// Fallback (Not recommended for prod but prevents crash)
		$key = get_option( 'pw_encryption_key' );
		if ( ! $key ) {
			$key = wp_generate_password( 64, true, true );
			update_option( 'pw_encryption_key', $key );
		}
		return substr( $key, 0, 32 );
	}
}
