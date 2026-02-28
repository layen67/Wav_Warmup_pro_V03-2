<?php

declare(strict_types=1);

namespace PostalWarmup\Services;

use PostalWarmup\Services\TemplateLoader;
use PostalWarmup\Admin\Settings;
use PostalWarmup\Models\Database;
use PostalWarmup\Core\TemplateEngine;

/**
 * Service de génération de shortcodes [warmup_mailto]
 * Permet d'insérer des liens mailto dynamiques pour le warmup.
 */
class Mailto {

	/**
	 * Initialise les shortcodes.
	 */
	public function init(): void {
		// Shortcode principal
		add_shortcode( 'warmup_mailto', array( $this, 'render_shortcode' ) );
		// Alias legacy
		add_shortcode( 'email_warmup', array( $this, 'render_shortcode' ) );
		add_shortcode( 'wav_warmup', array( $this, 'render_shortcode' ) ); // Alias demandé
		// Shortcode pour lien automatique
		add_shortcode( 'warmup_auto_link', array( $this, 'render_auto_link' ) );
	}

	/**
	 * Rendu du shortcode [warmup_mailto]
	 *
	 * @param array|string $atts Attributs du shortcode
	 * @param string|null $content Contenu entre les balises (label)
	 * @return string HTML du lien
	 */
	public function render_shortcode( $atts, ?string $content = null ): string {
		// Normalisation des attributs
		if ( ! is_array( $atts ) ) {
			$atts = [];
		}

		$atts = shortcode_atts( array(
			'template' => '', // Slug ou nom du template
			'name'     => '', // Alias pour template
			'label'    => '', // Texte du bouton/lien
			'prefix'   => '', // Override: préfixe email cible
			'emails'   => '', // Override: liste d'emails cibles (séparés par virgule)
			'subjects' => '', // Override: liste de sujets (séparés par |)
			'bodies'   => '', // Override: liste de corps (séparés par |)
			'rotate'   => 'true', // Rotation aléatoire (toujours active en réalité)
			'spintax'  => 'true', // Activer le spintax processing
			'class'    => 'pw-mailto-link', // Classes CSS
			'style'    => '', // 'link' (défaut) ou 'button'
		), $atts, 'warmup_mailto' );

		// Gestion alias 'name' -> 'template'
		if ( ! empty( $atts['name'] ) && empty( $atts['template'] ) ) {
			$atts['template'] = $atts['name'];
		}

		$template_name = sanitize_text_field( $atts['template'] );
		$template_data = null;

		// 1. Chargement du Template depuis la DB ou Fichier JSON
		if ( ! empty( $template_name ) ) {
			$template_data = TemplateLoader::load( $template_name );
		}

		// 2. Préparation des Données Cibles (Pool d'adresses)
		$pool = [];
		if ( ! empty( $atts['emails'] ) ) {
			// Priorité 1: Liste explicite dans le shortcode
			$pool = array_map( 'trim', explode( ',', $atts['emails'] ) );
		} elseif ( ! empty( $atts['prefix'] ) ) {
			// Priorité 2: Préfixe explicite + Domaines serveurs actifs
			$prefixes = array_map( 'trim', explode( ',', $atts['prefix'] ) );
			$servers = Database::get_servers( true );
			foreach ( $servers as $server ) {
				foreach ( $prefixes as $p ) {
					$pool[] = $p . '@' . $server['domain'];
				}
			}
		} elseif ( $template_data && ! empty( $template_data['mailto_email_prefix'] ) ) {
			// Priorité 3: Configuration du Template
			$prefixes = explode( ',', $template_data['mailto_email_prefix'] );
			$servers = Database::get_servers( true );
			foreach ( $servers as $server ) {
				foreach ( $prefixes as $p ) {
					$pool[] = trim( $p ) . '@' . $server['domain'];
				}
			}
		} else {
			// Fallback: Adresses par défaut (contact@...)
			$pool = $this->get_inbound_addresses();
		}

		if ( empty( $pool ) ) {
			return '<!-- Postal Warmup: Aucune adresse de destination disponible -->';
		}

		// Sélection aléatoire d'une adresse cible
		$target_email = (string) $pool[ array_rand( $pool ) ];

		// 3. Préparation du Sujet
		$subject = '';
		if ( ! empty( $atts['subjects'] ) ) {
			$subjects_list = explode( '|', $atts['subjects'] );
			if ( ! empty( $subjects_list ) ) {
				$subject = $subjects_list[ array_rand( $subjects_list ) ];
			}
		} elseif ( $template_data && ! empty( $template_data['mailto_subject'] ) ) {
			$subject = TemplateLoader::pick_random( $template_data['mailto_subject'] );
		} elseif ( $template_data && ! empty( $template_data['subject'] ) ) {
			// Fallback sur le sujet d'envoi classique si mailto_subject est vide
			$subject = TemplateLoader::pick_random( $template_data['subject'] );
		} else {
			$subject = 'Question';
		}

		// 4. Préparation du Corps
		$body = '';
		if ( ! empty( $atts['bodies'] ) ) {
			$bodies_list = explode( '|', $atts['bodies'] );
			if ( ! empty( $bodies_list ) ) {
				$body = $bodies_list[ array_rand( $bodies_list ) ];
			}
		} elseif ( $template_data && ! empty( $template_data['mailto_body'] ) ) {
			$body = TemplateLoader::pick_random( $template_data['mailto_body'] );
		} elseif ( $template_data && ! empty( $template_data['text'] ) ) {
			// Fallback sur le texte d'envoi classique
			$body = TemplateLoader::pick_random( $template_data['text'] );
		}

		// 5. Traitement du Spintax (Variations de texte)
		if ( $atts['spintax'] === 'true' ) {
			if ( class_exists( 'PostalWarmup\Core\TemplateEngine' ) ) {
				$subject = TemplateEngine::process_spintax( $subject );
				$body = TemplateEngine::process_spintax( $body );
			}
		}

		// 6. Construction de l'URL mailto:
		$href = 'mailto:' . sanitize_email( $target_email );
		$params = [];
		if ( $subject ) $params['subject'] = rawurlencode( html_entity_decode( $subject ) );
		if ( $body ) $params['body'] = rawurlencode( html_entity_decode( $body ) );
		
		if ( ! empty( $params ) ) {
			$href .= '?' . build_query( $params );
		}

		// 7. Détermination du Libellé (Label)
		// Ordre de priorité : Contenu > Attribut label > Config Template > Défaut
		$label = '';
		if ( ! empty( $content ) ) {
			$label = strip_tags( $content );
		}

		if ( empty( $label ) && ! empty( $atts['label'] ) ) {
			$label = $atts['label'];
		}

		if ( empty( $label ) && $template_data && ! empty( $template_data['default_label'] ) ) {
			$label = $template_data['default_label'];
		}

		if ( empty( $label ) ) {
			$label = __( 'Envoyer un email', 'postal-warmup' );
		}

		// 8. Classes CSS et Style
		$classes = esc_attr( $atts['class'] );
		if ( $atts['style'] === 'button' ) {
			$classes .= ' pw-btn'; // Classe bouton standard du plugin
		}

		// Rendu HTML sécurisé
		return sprintf(
			'<a href="%s" class="%s" rel="nofollow">%s</a>',
			esc_url( $href ),
			$classes,
			esc_html( $label )
		);
	}

	/**
	 * Rendu du shortcode [warmup_auto_link]
	 * Alias simplifié pour un lien par défaut.
	 */
	public function render_auto_link( $atts ): string {
		return $this->render_shortcode( shortcode_atts( [ 'style' => 'link' ], $atts, 'warmup_auto_link' ) );
	}

	/**
	 * Récupère une liste d'adresses par défaut sur les serveurs actifs.
	 */
	private function get_inbound_addresses(): array {
		$servers = Database::get_servers( true );
		$addresses = [];
		foreach ( $servers as $server ) {
			// On suppose des alias standard ou catch-all
			$addresses[] = 'contact@' . $server['domain'];
			$addresses[] = 'support@' . $server['domain'];
		}
		return $addresses;
	}
}
