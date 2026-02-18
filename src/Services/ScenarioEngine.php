<?php

namespace PostalWarmup\Services;

use PostalWarmup\Models\Database;
use PostalWarmup\Models\Scenario;
use PostalWarmup\Models\ReplyTemplateRule;
use PostalWarmup\Services\Logger;
use PostalWarmup\Admin\Settings;

declare(strict_types=1);

/**
 * Moteur de scénarios (Engagement Engine)
 */
class ScenarioEngine {

	public static function on_new_incoming( string $contact_email, int $server_id, array $message_data ): void {
		$plain_body = $message_data['body'] ?? '';
		$subject = $message_data['subject'] ?? '';

		// 1. Unsubscribe detection
		if ( self::handle_unsubscribe_keywords( $plain_body, $subject, $contact_email ) ) {
			Logger::info( 'Scenario: Unsubscribe detected', [ 'email' => $contact_email ] );
			return;
		}

		// 2. Find active conversation
		$conversation = ConversationManager::get_active_for_contact( $contact_email, $server_id );

		if ( ! $conversation ) {
			// New Conversation
			$scenario = self::find_matching_scenario( [
				'trigger_event' => 'reply', // Initial incoming is treated as a reply-trigger for scenario start
				'subject'       => $subject,
				'body'          => $plain_body,
				'server_id'     => $server_id
			] );

			if ( $scenario ) {
				$conv_id = ConversationManager::record_reply( [
					'contact_email' => $contact_email,
					'server_id'     => $server_id,
					'scenario_id'   => $scenario['id'],
					'from_prefix'   => $message_data['prefix'] ?? 'contact',
					'status'        => 'active',
					'current_stage' => 0,
					'loop_cycle'    => 0,
					'waiting_for_reply' => 0 // Will process immediately
				] );

				Logger::info( "Scenario: Started '{$scenario['name']}' for $contact_email" );
				self::advance_conversation( (int)$conv_id, 'first_contact' );
			} else {
				Logger::info( "Scenario: No matching scenario for $contact_email" );
			}
			return;
		}

		// Existing Conversation
		$conv_id = (int) $conversation['id'];

		// Update last contact time
		ConversationManager::update_last_contact( $conv_id );

		if ( (int)$conversation['waiting_for_reply'] === 1 ) {
			// Expected reply received -> Advance
			ConversationManager::set_waiting( $conv_id, false );
			self::advance_conversation( $conv_id, 'reply' );
		} else {
			// Eager contact (sent message while we were processing or paused)
			Logger::info( "Scenario: Eager contact from $contact_email (already processing)" );
			// Do nothing else, avoid double send
		}

		// Cancel any pending reactivation actions
		if ( function_exists( 'as_unschedule_action' ) ) {
			as_unschedule_action( 'pw_check_contact_silence', [ 'conversation_id' => $conv_id ] );
		}
	}

	public static function advance_conversation( int $conversation_id, string $trigger = 'incoming' ): void {
		$conversation = ConversationManager::get( $conversation_id );
		if ( ! $conversation || $conversation['status'] !== 'active' ) return;

		$scenario = Scenario::get( (int)$conversation['scenario_id'] );
		if ( ! $scenario ) return;

		$steps = $scenario['steps'] ?? []; // JSON decoded array
		$current_stage = (int) $conversation['current_stage'];

		// Safety check for stage bounds
		if ( ! isset( $steps[ $current_stage ] ) ) {
			// Should ideally loop or end if out of bounds?
			// Logic handled at end of previous step usually.
			// If we are here, something is wrong or loop config is needed.
			Logger::warning( "Scenario: Stage $current_stage not found for conv $conversation_id" );
			return;
		}

		$step = $steps[ $current_stage ];

		// Resolve template (Rotation logic)
		$template_name = self::resolve_template_for_step( $step, $conversation );

		// Calculate Delay
		$delay_seconds = 0;
		$delay_hours = (int) ($step['delay_hours'] ?? 0);
		if ( $step['delay_type'] === 'after_incoming' ) {
			// Delay from NOW (since we just got the reply)
			$delay_seconds = $delay_hours * 3600;
		} else {
			// after_previous: we don't track previous send time easily here without querying logs.
			// Simplify: assume after_incoming logic for robustness, or add logic if needed.
			// Let's treat it as delay from now for simplicity in MVP.
			$delay_seconds = $delay_hours * 3600;
		}

		// Schedule Send
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time() + $delay_seconds,
				'pw_send_engagement_step',
				[
					'conversation_id' => $conversation_id,
					'template_name'   => $template_name,
					'server_id'       => (int)$conversation['server_id'],
					'contact_email'   => $conversation['contact_email'],
					'step_index'      => $current_stage,
					'loop_cycle'      => (int)$conversation['loop_cycle'],
					'from_prefix'     => $conversation['from_prefix']
				],
				'postal-warmup'
			);
		}

		// Determine Next Stage
		$action = $step['action'] ?? 'send';
		$next_stage = $current_stage + 1;
		$next_loop_cycle = (int)$conversation['loop_cycle'];
		$waiting = 0;
		$new_status = 'active';

		if ( $action === 'send_and_loop' || ! isset( $steps[ $next_stage ] ) ) {
			// Loop logic
			$next_stage = (int) ($scenario['loop_back_to_stage'] ?? 0);
			$next_loop_cycle++;

			$max_loops = (int) ($scenario['loop_max_cycles'] ?? 0);
			if ( $max_loops > 0 && $next_loop_cycle >= $max_loops ) {
				$new_status = 'completed';
			}
		} elseif ( $action === 'send_and_complete' ) {
			$new_status = 'completed';
		} elseif ( $action === 'send_and_pause' ) {
			$waiting = 1;
		}

		// Update Conversation
		ConversationManager::update( $conversation_id, [
			'current_stage' => $next_stage,
			'loop_cycle'    => $next_loop_cycle,
			'waiting_for_reply' => $waiting, // Logic: we wait for reply AFTER sending?
			// Actually, if we schedule a send, we are "processing".
			// If step action is "send", we expect a reply after that send?
			// The Requirement says: "send" -> advance. "send_and_pause" -> wait.
			// Usually in conversation, we ALWAYS wait for reply after sending, unless it's a drip sequence (require_reply_to_advance=0).
			// If require_reply_to_advance=1 (default), we automatically wait after sending.
			// Let's force waiting=1 if require_reply=1.
			'status' => $new_status
		] );

		if ( ($scenario['require_reply_to_advance'] ?? 1) && $new_status === 'active' ) {
			ConversationManager::set_waiting( $conversation_id, true );
		}
	}

	public static function send_engagement_step( array $args ): void {
		$conv_id = (int) $args['conversation_id'];
		$conversation = ConversationManager::get( $conv_id );

		if ( ! $conversation || $conversation['status'] !== 'active' ) return;

		// Check suppression
		// TODO: Suppression check via Database/Client

		// Send via QueueManager with FORCE SERVER ID
		// This respects the "Conversation bound to domain" rule
		QueueManager::add(
			(int) $args['server_id'],
			$args['contact_email'],
			$args['from_prefix'] . '@' . Database::get_server((int)$args['server_id'])['domain'], // From address reconstruction
			// Subject/Body resolved inside QueueManager/Sender via TemplateEngine
			'', // Subject placeholder, will be filled by TemplateEngine
			[
				'template_name' => $args['template_name'], // Pass name to let QueueManager resolve content
				'prefix' => $args['from_prefix'],
				'conversation_id' => $conv_id
			],
			(int) $args['server_id'] // FORCE SERVER
		);

		// Update History
		ConversationManager::add_template_history( $conv_id, $args['template_name'] );

		Logger::info( "Scenario: Scheduled step sent", $args );

		// Schedule Silence Check if configured
		$scenario = Scenario::get( (int) $conversation['scenario_id'] );
		$reactivation_days = (int) ($scenario['reactivation_delay_days'] ?? 0);

		if ( $reactivation_days > 0 ) {
			as_schedule_single_action(
				time() + ($reactivation_days * 86400),
				'pw_check_contact_silence',
				[ 'conversation_id' => $conv_id ],
				'postal-warmup'
			);
		}
	}

	public static function resolve_template_for_step( array $step, array $conversation ): string {
		if ( empty( $step['anti_repeat'] ) ) {
			return $step['template_name'];
		}

		$history = json_decode( $conversation['templates_sent'] ?? '[]', true ) ?: [];
		$base_name = $step['template_name'];

		// Smart Rotation: Check for base_v2, base_v3...
		$variants = self::get_template_variants( $base_name );

		foreach ( $variants as $variant ) {
			if ( ! in_array( $variant, $history ) ) {
				return $variant; // Found unused variant
			}
		}

		// All used? Reset cycle logic (just pick base or random?)
		// Return base to restart cycle
		return $base_name;
	}

	public static function get_template_variants( string $base_name ): array {
		// Mock logic: look in DB for names starting with base_name
		// Real impl: Database query LIKE 'base_name%'
		// For MVP/Robustness, assume basic + check DB
		// Optimization: cache this query
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';
		$like = $wpdb->esc_like( $base_name ) . '%';

		$results = $wpdb->get_col( $wpdb->prepare( "SELECT name FROM $table WHERE name LIKE %s", $like ) );

		// Sort naturally to get v2, v3 in order
		natsort( $results );
		return $results ?: [ $base_name ];
	}

	public static function handle_unsubscribe_keywords( string $body, string $subject, string $email ): bool {
		$keywords = Settings::get( 'scenario_unsubscribe_keywords', ['unsubscribe', 'stop', 'désabonner', 'arrêt'] );
		$text = strtolower( $subject . ' ' . $body );

		foreach ( $keywords as $kw ) {
			if ( strpos( $text, trim( strtolower( $kw ) ) ) !== false ) {
				ConversationManager::cancel_all_for_contact( $email );
				// Add to suppression list (local or Postal API)
				// Database::add_suppression($email); // Logic to be implemented or rely on Postal
				return true;
			}
		}
		return false;
	}

	public static function handle_contact_silence( int $conversation_id ): void {
		$conversation = ConversationManager::get( $conversation_id );
		if ( ! $conversation || (int)$conversation['waiting_for_reply'] !== 1 ) return;

		$scenario = Scenario::get( (int)$conversation['scenario_id'] );
		if ( ! $scenario || empty( $scenario['reactivation_template'] ) ) return;

		// Check timestamp again
		$last_contact = strtotime( $conversation['last_contact_at'] );
		$delay = (int) ($scenario['reactivation_delay_days'] ?? 7) * 86400;

		if ( (time() - $last_contact) >= $delay ) {
			// Send Reactivation
			QueueManager::add(
				(int) $conversation['server_id'],
				$conversation['contact_email'],
				$conversation['from_prefix'] . '@' . Database::get_server((int)$conversation['server_id'])['domain'],
				'',
				[
					'template_name' => $scenario['reactivation_template'],
					'prefix' => $conversation['from_prefix'],
					'conversation_id' => $conversation_id
				],
				(int) $conversation['server_id']
			);
			Logger::info( "Scenario: Reactivation sent to {$conversation['contact_email']}" );

			// Schedule next check? Or mark reactivated?
			// Mark scheduled to avoid loops unless logic handles multi-reactivation
		}
	}

	public static function daily_check(): void {
		// Cleanup stale conversations
		global $wpdb;
		$table = $wpdb->prefix . 'postal_conversations';

		// orphaned (server deleted)
		// Logic handled in migration/server deletion mainly, but check for zombies here

		// stale paused
		$stale_date = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'orphaned' WHERE status = 'paused' AND updated_at < %s", $stale_date ) );
	}

	public static function find_matching_scenario( array $context ): ?array {
		// Query all active scenarios
		$scenarios = Scenario::get_all( true );

		foreach ( $scenarios as $scenario ) {
			// Trigger check
			if ( $scenario['trigger_event'] !== ($context['trigger_event'] ?? 'reply') ) continue;

			// Server check
			$allowed = json_decode( $scenario['allowed_server_ids'] ?? '[]', true );
			if ( ! empty( $allowed ) && ! in_array( $context['server_id'], $allowed ) ) continue;

			// Content Conditions (subject/body contains) - JSON Logic
			$conditions = json_decode( $scenario['conditions'] ?? '[]', true );
			// TODO: Implement deep condition matching logic

			// If passed all filters, return first match (ordered by priority in get_all)
			return $scenario;
		}

		return null;
	}
}
