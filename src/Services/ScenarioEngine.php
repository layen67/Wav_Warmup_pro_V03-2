<?php
// src/Services/ScenarioEngine.php

declare(strict_types=1);

namespace PostalWarmup\Services;

use PostalWarmup\Models\Scenario;
use PostalWarmup\Models\ReplyTemplateRule;
use PostalWarmup\Models\Database;
use PostalWarmup\Core\TemplateEngine;
use PostalWarmup\API\Sender;
use PostalWarmup\API\Client;

/**
 * Engine to execute scenario steps.
 */
class ScenarioEngine {

	public static function run_maintenance(): void {
		// Process active conversations that are due
		$conversations = ConversationManager::get_active_conversations( 20 ); // Process in batches

		foreach ( $conversations as $conv ) {
			self::process_conversation( $conv );
		}
	}

	public static function process_conversation( array $conv ): void {
		$scenario = Scenario::get( (int)$conv['scenario_id'] );
		if ( ! $scenario || ! $scenario['active'] ) {
			// Scenario disabled or deleted, maybe pause conversation?
			return;
		}

		// 1. Check if waiting for reply
		if ( $conv['waiting_for_reply'] ) {
			// If reply received (pending_reply=1), we can proceed
			if ( $conv['pending_reply'] ) {
				// Proceed to next step logic
			} else {
				// Still waiting. Check timeout?
				// For now, just exit.
				return;
			}
		}

		// 2. Determine Next Step
		$current_stage = (int)$conv['current_stage'];
		$steps = $scenario['steps'] ?? [];

		// If we are past the last step
		if ( $current_stage >= count( $steps ) ) {
			// Check Loop Logic
			if ( $scenario['loop_max_cycles'] > 0 && $conv['loop_cycle'] < $scenario['loop_max_cycles'] ) {
				// Loop back
				// Reset to stage loop_back_to_stage
				// Increment cycle
				// TODO: Implement looping
			} else {
				// End of scenario
				// Maybe mark as completed
			}
			return;
		}

		$step = $steps[ $current_stage ] ?? null;
		if ( ! $step ) return;

		// 3. Execute Step
		// Example step: { "action": "send_email", "template": "follow_up_1", "delay": 24 (hours) }

		// Check delay since last contact
		$last_contact = strtotime( $conv['last_contact_at'] );
		$delay_hours = (int)( $step['delay'] ?? 0 );
		$now = time();

		if ( ($now - $last_contact) < ($delay_hours * 3600) ) {
			// Too early
			return;
		}

		if ( $step['action'] === 'send_email' ) {
			$template_name = $step['template'];
			$server = Database::get_server( (int)$conv['server_id'] );

			// Inject Reply-To for tracking
			$tracking_reply_to = Client::get_tracking_reply_to( $server['domain'], (int)$conv['id'] );

			// Override template Reply-To... this requires Sender to accept override or template modification
			// For now, let's assume Sender can handle it or we pass it in 'meta'
			// Actually TemplateEngine prepares the template. We might need to hook into 'pw_email_payload' filter or modify prepare_template args.

			// Let's use the filter in Sender.php: `apply_filters( 'pw_email_payload', $payload, $prepared, [] );`
			add_filter( 'pw_email_payload', function( $payload ) use ( $tracking_reply_to ) {
				$payload['reply_to'] = $tracking_reply_to;
				return $payload;
			}, 10, 1 );

			// Threading headers
			$extra_headers = [];
			if ( ! empty( $conv['thread_id'] ) ) {
				// If we have a thread ID (initial Message-ID), set it as References/In-Reply-To to maintain thread
				// Note: Ideally we track the LAST message ID received to reply to IT.
				// For now, simple threading using the original ID.
				$extra_headers['In-Reply-To'] = $conv['thread_id'];
				$extra_headers['References']  = $conv['thread_id'];
			}

			$result = Sender::send(
				$conv['contact_email'],
				$server['domain'],
				$conv['from_prefix'], // 'support', 'contact', etc.
				$server,
				$extra_headers
			);

			// Remove filter to avoid pollution
			remove_all_filters( 'pw_email_payload' );

			if ( isset( $result['queued'] ) || ( isset( $result['success'] ) && $result['success'] ) ) {
				ConversationManager::log_template_sent( (int)$conv['id'], $template_name );
				ConversationManager::update_stage( (int)$conv['id'], $current_stage + 1 );
			}
		}
	}

	public static function handle_reply( int $conversation_id, string $body, ?string $message_id = null ): void {
		$conv = ConversationManager::get_by_id( $conversation_id );
		if ( ! $conv ) return;

		// Update Thread ID if we have a new Message-ID from the reply
		if ( $message_id ) {
			ConversationManager::update_thread_id( $conversation_id, $message_id );
		}

		// Log the reply
		ConversationManager::mark_reply_received( $conversation_id, $body );

		// Trigger immediate processing if needed
		self::process_conversation( ConversationManager::get_by_id( $conversation_id ) );
	}

	/**
	 * Process incoming email against Reply Rules (Unsolicited or New Threads)
	 */
	public static function process_rules( int $server_id, string $subject, string $body, string $prefix, string $from ): void {
		// Find matching rule
		$rule = ReplyTemplateRule::match_rule( $server_id, $subject, $body, $prefix );

		if ( ! $rule ) return;

		Logger::info( "Rule matched for incoming email", [ 'rule' => $rule['name'], 'from' => $from ] );

		// Execute Action: Send Reply (Template)
		if ( ! empty( $rule['response_template_name'] ) ) {
			// Create a new conversation to track this interaction?
			// Or just send a one-off reply?
			// Ideally create a conversation to allow scenarios to follow up.

			// Create Conversation
			$scenario_id = (int)( $rule['scenario_id'] ?? 0 );
			$conv_id = ConversationManager::create_conversation( $from, $server_id, $scenario_id );

			if ( $conv_id ) {
				// Send the Auto-Reply immediately
				$server = Database::get_server( $server_id );
				$tracking_reply_to = Client::get_tracking_reply_to( $server['domain'], $conv_id );

				add_filter( 'pw_email_payload', function( $payload ) use ( $tracking_reply_to ) {
					$payload['reply_to'] = $tracking_reply_to;
					return $payload;
				}, 10, 1 );

				Sender::send( $from, $server['domain'], $prefix, $server );

				remove_all_filters( 'pw_email_payload' );

				// Advance stage if scenario exists
				if ( $scenario_id ) {
					ConversationManager::update_stage( $conv_id, 1 );
				}
			}
		}
	}
}
