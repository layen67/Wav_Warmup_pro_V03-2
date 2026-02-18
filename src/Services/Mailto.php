<?php

namespace PostalWarmup\Services;

use PostalWarmup\Services\TemplateLoader;
use PostalWarmup\Admin\Settings;

declare(strict_types=1);

/**
 * Service de génération de shortcodes [warmup_mailto]
 */
class Mailto {

	public function init(): void {
		add_shortcode( 'warmup_mailto', array( $this, 'render_shortcode' ) );
		// Legacy support
		add_shortcode( 'email_warmup', array( $this, 'render_shortcode' ) );
		// Auto Link shortcode
		add_shortcode( 'warmup_auto_link', array( $this, 'render_auto_link' ) );
	}

	public function render_shortcode( $atts ): string {
		$atts = shortcode_atts( array(
			'template' => '', // Legacy slug or ID
			'name'     => '', // Alias for template
			'label'    => '', // Button text
			'prefix'   => '', // Legacy override
			'emails'   => '', // Legacy override
			'subjects' => '', // Legacy override
			'bodies'   => '', // Legacy override
			'rotate'   => 'true',
			'spintax'  => 'true',
			'class'    => 'pw-mailto-link',
			'style'    => '', // link | button
		), $atts, 'warmup_mailto' );

		// Alias handling
		if ( ! empty( $atts['name'] ) && empty( $atts['template'] ) ) {
			$atts['template'] = $atts['name'];
		}

		$template_name = sanitize_text_field( $atts['template'] );
		$template_data = null;

		// 1. Load Template from DB/File
		if ( ! empty( $template_name ) ) {
			$template_data = TemplateLoader::load( $template_name );
		}

		// 2. Prepare Data (Prioritize attributes, fallback to template, then global)
		// Prefixes
		if ( ! empty( $atts['prefix'] ) ) {
			$prefixes = array_map( 'trim', explode( ',', $atts['prefix'] ) );
		} elseif ( $template_data && ! empty( $template_data['from_name'] ) ) {
			// Actually prefix is usually 'contact', 'support'.
			// If template defines 'from_name', it's display name.
			// Let's assume prefix logic is handled via TemplateEngine if using Sender,
			// but here we are generating a mailto link.
			// Users usually put prefixes in shortcode or use default.
			$prefixes = [ 'contact', 'info', 'support', 'hello' ];
		} else {
			$prefixes = [ 'contact', 'info', 'support', 'hello' ];
		}

		// Emails (Destinations)
		// For a mailto link, 'to' is usually the user clicking, but mailto: opens draft TO someone.
		// Wait, warmup mailto links usually point to the WARMUP INBOXES (so users send TO the warmup network).
		// So 'emails' attribute or template 'to' list?
		// Usually a pool of warmup addresses provided by the platform.
		// Let's assume we have a pool setting or shortcode attribute.
		// If explicit 'emails' attribute is set:
		if ( ! empty( $atts['emails'] ) ) {
			$pool = array_map( 'trim', explode( ',', $atts['emails'] ) );
		} else {
			// Fallback: This plugin sends FROM server TO external list.
			// BUT [warmup_mailto] generates a link for VISITORS to send TO server?
			// OR is it for internal use?
			// Context: "Postal Warmup". Usually outbound.
			// If this shortcode generates a mailto link, it means we want visitors to email US (inbound warmup).
			// So we need a target email address on our domain.
			// We can generate one based on active servers.
			$pool = $this->get_inbound_addresses();
		}

		if ( empty( $pool ) ) return '<!-- No warmup addresses available -->';

		$target_email = $pool[ array_rand( $pool ) ]; // Simple random for target is fine

		// Subject
		$subject = '';
		if ( ! empty( $atts['subjects'] ) ) {
			$subjects_list = explode( '|', $atts['subjects'] );
			$subject = $subjects_list[ array_rand( $subjects_list ) ];
		} elseif ( $template_data && ! empty( $template_data['mailto_subject'] ) ) {
			$subject = TemplateLoader::pick_random( $template_data['mailto_subject'] );
		} elseif ( $template_data && ! empty( $template_data['subject'] ) ) {
			$subject = TemplateLoader::pick_random( $template_data['subject'] );
		} else {
			$subject = 'Question';
		}

		// Body
		$body = '';
		if ( ! empty( $atts['bodies'] ) ) {
			$bodies_list = explode( '|', $atts['bodies'] );
			$body = $bodies_list[ array_rand( $bodies_list ) ];
		} elseif ( $template_data && ! empty( $template_data['mailto_body'] ) ) {
			$body = TemplateLoader::pick_random( $template_data['mailto_body'] );
		} elseif ( $template_data && ! empty( $template_data['text'] ) ) {
			$body = TemplateLoader::pick_random( $template_data['text'] );
		}

		// Spintax Processing
		if ( $atts['spintax'] === 'true' ) {
			if ( class_exists( 'PostalWarmup\Core\TemplateEngine' ) ) {
				$subject = \PostalWarmup\Core\TemplateEngine::process_spintax( $subject );
				$body = \PostalWarmup\Core\TemplateEngine::process_spintax( $body );
			}
		}

		// URL Encoding
		$href = 'mailto:' . sanitize_email( $target_email );
		$params = [];
		if ( $subject ) $params['subject'] = rawurlencode( html_entity_decode( $subject ) );
		if ( $body ) $params['body'] = rawurlencode( html_entity_decode( $body ) );
		
		if ( ! empty( $params ) ) {
			$href .= '?' . build_query( $params );
		}

		// Label
		$label = $atts['label'];
		if ( empty( $label ) ) {
			if ( $template_data && ! empty( $template_data['default_label'] ) ) {
				$label = $template_data['default_label'];
			} else {
				$label = __( 'Envoyer un email', 'postal-warmup' );
			}
		}

		// CSS Classes
		$classes = esc_attr( $atts['class'] );
		if ( $atts['style'] === 'button' ) {
			$classes .= ' pw-btn';
		}

		// Tracking (Optional)
		$onclick = "fetch('" . admin_url( 'admin-ajax.php?action=pw_track_click' ) . "', {method:'POST', body: new URLSearchParams({nonce:'" . wp_create_nonce( 'pw_track' ) . "', template:'" . esc_js( $template_name ) . "'})});";

		return sprintf(
			'<a href="%s" class="%s" onclick="%s" rel="nofollow">%s</a>',
			esc_url( $href ), // mailto is safe protocol
			$classes,
			$onclick,
			esc_html( $label )
		);
	}

	public function render_auto_link( $atts ): string {
		return $this->render_shortcode( shortcode_atts( [ 'style' => 'link', 'track' => 'false' ], $atts, 'warmup_auto_link' ) );
	}

	private function get_inbound_addresses(): array {
		// Retrieve active server domains and construct catch-all addresses
		// Or use specific configured list
		// Here we fetch active servers
		$servers = \PostalWarmup\Models\Database::get_servers( true );
		$addresses = [];
		foreach ( $servers as $server ) {
			$addresses[] = 'contact@' . $server['domain'];
			$addresses[] = 'support@' . $server['domain'];
		}
		return $addresses;
	}
}
