<?php

declare(strict_types=1);

namespace PostalWarmup\Services;

use PostalWarmup\Services\TemplateLoader;
use PostalWarmup\Admin\Settings;



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

	public function render_shortcode( $atts, ?string $content = null ): string {
		// Normalize $atts if null (no attributes)
		if ( ! is_array( $atts ) ) {
			$atts = [];
		}

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

		// 2. Prepare Data
		// Prefixes
		if ( ! empty( $atts['prefix'] ) ) {
			$prefixes = array_map( 'trim', explode( ',', $atts['prefix'] ) );
		} elseif ( $template_data && ! empty( $template_data['from_name'] ) ) {
			// from_name usually contains display names, not prefixes.
			// But for consistency with legacy shortcode which used prefix to build email address:
			// If we are generating mailto link, we need a TARGET address.
			// The plugin sends FROM server TO contact.
			// BUT [warmup_mailto] generates a link for CONTACT TO SEND TO SERVER.
			// So target address must be on server.
			$prefixes = [ 'contact', 'info', 'support', 'hello' ];
		} else {
			$prefixes = [ 'contact', 'info', 'support', 'hello' ];
		}

		// Target Emails (Where user sends email to)
		if ( ! empty( $atts['emails'] ) ) {
			$pool = array_map( 'trim', explode( ',', $atts['emails'] ) );
		} else {
			$pool = $this->get_inbound_addresses();
		}

		if ( empty( $pool ) ) return '<!-- No warmup addresses available -->';

		// Select random target
		$target_email = (string) $pool[ array_rand( $pool ) ];

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

		// Label Resolution Priority
		// 1. Inner content
		$label = '';
		if ( ! empty( $content ) ) {
			$label = strip_tags( $content );
		}

		// 2. Attribute label=
		if ( empty( $label ) && ! empty( $atts['label'] ) ) {
			$label = $atts['label'];
		}

		// 3. Template default_label
		if ( empty( $label ) && $template_data && ! empty( $template_data['default_label'] ) ) {
			$label = $template_data['default_label'];
		}

		// 4. Fallback
		if ( empty( $label ) ) {
			$label = __( 'Envoyer un email', 'postal-warmup' );
		}

		// CSS Classes
		$classes = esc_attr( $atts['class'] );
		if ( $atts['style'] === 'button' ) {
			$classes .= ' pw-btn';
		}

		// Tracking (Optional)
		// We use an admin-ajax endpoint for tracking if enabled?
		// Usually shortcode clicks are tracked via JS.
		// For now, simple link.
		$onclick = ""; // JS handler can attach to class

		return sprintf(
			'<a href="%s" class="%s" rel="nofollow">%s</a>',
			esc_url( $href ),
			$classes,
			esc_html( $label )
		);
	}

	public function render_auto_link( $atts ): string {
		return $this->render_shortcode( shortcode_atts( [ 'style' => 'link', 'track' => 'false' ], $atts, 'warmup_auto_link' ) );
	}

	private function get_inbound_addresses(): array {
		$servers = \PostalWarmup\Models\Database::get_servers( true );
		$addresses = [];
		foreach ( $servers as $server ) {
			// Assume catch-all or standard prefixes on server domain
			$addresses[] = 'contact@' . $server['domain'];
			$addresses[] = 'support@' . $server['domain'];
		}
		return $addresses;
	}
}
