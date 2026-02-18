<?php

namespace PostalWarmup\Services;

use PostalWarmup\Models\Database;
use PostalWarmup\Services\Logger;
use PostalWarmup\API\Sender;
use PostalWarmup\Services\LoadBalancer;
use PostalWarmup\Services\ISPDetector;
use PostalWarmup\Models\Stats;
use PostalWarmup\Admin\ISPManager;
use PostalWarmup\Admin\Settings;

declare(strict_types=1);

class QueueManager {

    /**
     * Ajoute un email à la file d'attente
     */
    public static function add( int $server_id, string $to, string $from, string $subject, array $meta = [] ): int|bool {
        global $wpdb;
        $table = $wpdb->prefix . 'postal_queue';
        
        $scheduled_at = current_time( 'mysql' );

        $delay_min = (int) Settings::get( 'schedule_random_delay_min', 2 );
        $delay_max = (int) Settings::get( 'schedule_random_delay_max', 10 );

        if ( $delay_max < $delay_min ) $delay_max = $delay_min;

        if ( $delay_max > 0 ) {
            $seconds = rand( $delay_min * 60, $delay_max * 60 );
            $scheduled_at = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $seconds );
        }

        $data = [
            'server_id'    => $server_id,
            'template_id'  => $meta['template_id'] ?? null,
            'to_email'     => $to,
            'from_email'   => $from,
            'subject'      => $subject,
            'status'       => 'pending',
            'scheduled_at' => $scheduled_at,
            'created_at'   => current_time( 'mysql' ),
            'meta'         => json_encode( $meta ),
            'attempts'     => 0,
            'isp'          => 'Other'
        ];
        
        if ( class_exists( 'PostalWarmup\Services\ISPDetector' ) ) {
            $data['isp'] = ISPDetector::detect( $to );
        }
        
        $result = $wpdb->insert( $table, $data );
        
        if ( $result ) {
            Logger::info( "Queue: Email ajouté (ID: $wpdb->insert_id)", [ 'to' => $to ] );
            return (int) $wpdb->insert_id;
        }
        
        Logger::error( "Queue: Échec ajout DB", [ 'error' => $wpdb->last_error ] );
        return false;
    }

    /**
     * Traite la file d'attente (Appelé par CRON)
     */
    public static function process_queue(): void {
        if ( get_transient( 'pw_queue_paused' ) ) {
            return;
        }

        $pause_threshold = (int) Settings::get( 'queue_pause_threshold', 50 );
        $health = self::get_health_stats();

        if ( $health['sent_24h'] + $health['failed_24h'] > 10 ) {
            $total = $health['sent_24h'] + $health['failed_24h'];
            $failure_rate = ($health['failed_24h'] / $total) * 100;

            if ( $failure_rate > $pause_threshold ) {
                $resume_delay = (int) Settings::get( 'queue_resume_delay', 30 );
                set_transient( 'pw_queue_paused', true, $resume_delay * 60 );

                Logger::critical( "Queue: Auto-paused for $resume_delay minutes due to high failure rate ({$failure_rate}%)" );

                if ( Settings::get( 'notify_stuck_queue', true ) ) {
                    EmailNotifications::send_stuck_queue_alert( $resume_delay );
                }
                return;
            }
        }

        if ( Settings::get( 'queue_locking_enabled', true ) ) {
            if ( get_transient( 'pw_queue_lock' ) ) {
                return;
            }
            set_transient( 'pw_queue_lock', true, (int) Settings::get( 'queue_lock_timeout', 60 ) );
        }

        try {
            self::do_process_queue();
        } finally {
            if ( Settings::get( 'queue_locking_enabled', true ) ) {
                delete_transient( 'pw_queue_lock' );
            }
        }
    }

    private static function do_process_queue(): void {
        if ( ! Settings::get( 'sending_enabled', true ) ) {
            return;
        }

        $weekend_mode = Settings::get( 'weekend_mode', 'off' );
        $day_of_week = (int) current_time( 'w' );

        if ( $day_of_week === 0 || $day_of_week === 6 ) {
            if ( $weekend_mode === 'off' ) {
                return;
            } elseif ( $weekend_mode === 'reduced' ) {
                if ( rand( 1, 100 ) > 20 ) {
                    return;
                }
            }
        }

        if ( Settings::get( 'lunch_break_enabled', false ) ) {
            $hour = (int) current_time( 'H' );
            if ( $hour >= 12 && $hour < 14 ) {
                if ( rand( 1, 100 ) > 20 ) {
                    return;
                }
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'postal_queue';
        
        $global_tz = wp_timezone_string();

        $start_h = (int) Settings::get( 'schedule_start_hour', 8 );
        $end_h = (int) Settings::get( 'schedule_end_hour', 20 );

        if ( Settings::get( 'human_jitter_enabled', false ) ) {
            $day_seed = (int) date('z');
            srand($day_seed);
            $offset_start = rand(-30, 30) / 60;
            $offset_end = rand(-30, 30) / 60;
            srand();

            if ($offset_start > 0.5) $start_h++;
            if ($offset_start < -0.5) $start_h--;
            if ($offset_end < -0.5) $end_h--;
        }

        $slots = range( $start_h, max($start_h, $end_h - 1) );

        $now_mysql = current_time( 'mysql' );
        $batch_size = (int) Settings::get( 'queue_batch_size', 20 );

        $items = $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM $table WHERE status = 'pending' AND scheduled_at <= %s LIMIT %d",
            $now_mysql,
            $batch_size
        ), ARRAY_A );
        
        if ( empty( $items ) ) return;

        foreach ( $items as $item ) {
            $id = (int) $item['id'];
            $template_id = $item['template_id'] ? (int) $item['template_id'] : null;
            $isp = $item['isp'];
            
            if ( empty( $isp ) || $isp === 'Other' ) {
                $isp = ISPDetector::detect( $item['to_email'] );
                if ( $isp !== 'Other' ) {
                    $wpdb->update( $table, [ 'isp' => $isp ], [ 'id' => $id ] );
                    $item['isp'] = $isp;
                }
            }

            $timezone = $global_tz;
            if ( $template_id ) {
                $tpl_tz = $wpdb->get_var( $wpdb->prepare( "SELECT timezone FROM {$wpdb->prefix}postal_templates WHERE id = %d", $template_id ) );
                if ( ! empty( $tpl_tz ) ) {
                    $timezone = $tpl_tz;
                }
            }

            try {
                $dt = new \DateTime( 'now', new \DateTimeZone( $timezone ) );
                $current_hour = (int) $dt->format( 'G' );
                
                if ( ! in_array( $current_hour, $slots, true ) ) {
                    Logger::debug( "Queue: Item $id postponed (Hour $current_hour not allowed in $timezone)" );
                    self::postpone( $id, '+1 hour' );
                    continue;
                }
            } catch ( \Exception $e ) {
                Logger::error( "Queue: Timezone Error for item $id", [ 'error' => $e->getMessage() ] );
            }

            $strategy_id = null;
            if ( $isp !== 'Other' ) {
                $isp_data = ISPManager::get_by_key( $isp );
                
                if ( $isp_data && ! empty( $isp_data['strategy_id'] ) ) {
                    $strategy_id = (int) $isp_data['strategy_id'];
                }
            }

            $server = LoadBalancer::select_server( $template_id ?: 'default', [ 
                'ignore_limits' => false,
                'isp' => $isp,
                'strategy_id' => $strategy_id
            ] );
            
            if ( ! $server ) {
                Logger::warning( "Queue: No available server for item $id (Limits or Safety)", [ 'isp' => $isp, 'strategy' => $strategy_id ] );
                self::postpone( $id, '+30 minutes' );
                continue;
            }

            $meta = json_decode( $item['meta'], true );
            $prefix = $meta['prefix'] ?? 'contact';
            $domain = $server['domain'];
            $sender_email = $prefix . '@' . $domain;

            $wpdb->update( $table, [ 
                'status' => 'processing', 
                'server_id' => $server['id'],
                'from_email' => $sender_email
            ], [ 'id' => $id ] );

            $sender_service = new Sender();
            $result = $sender_service->process_queue( 
                $item['to_email'], 
                $domain, 
                $prefix, 
                (int) $server['id'],
                $item['attempts'] ?: 0,
                false // handle_retry = false so we handle it here
            );
            
            $success = isset( $result['success'] ) && $result['success'];

            if ( $success ) {
                $wpdb->update( $table, [ 
                    'status' => 'sent', 
                    'updated_at' => current_time( 'mysql' ),
                    'attempts' => ((int)$item['attempts']) + 1
                ], [ 'id' => $id ] );
                
                Logger::info("Queue: Sent Item $id", [
                    'server' => $domain,
                    'isp' => $isp,
                    'strategy' => $strategy_id
                ]);

                do_action( 'pw_queue_item_sent', $id, $item, $server );

            } else {
                $attempts = ((int)$item['attempts']) + 1;
                $max_retries = (int) Settings::get( 'max_retries', 3 );
                
                if ( $attempts < $max_retries ) {
                    $base = (int) Settings::get( 'retry_delay_base', 60 );
                    $strategy = Settings::get( 'retry_strategy', 'fixed' );
                    $max_delay = (int) Settings::get( 'retry_delay_max', 900 );

                    $delay = $base;
                    if ( $strategy === 'linear' ) {
                        $delay = $base * $attempts;
                    } elseif ( $strategy === 'exponential' ) {
                        $delay = (int) ($base * pow( 2, $attempts ));
                    }

                    if ( $delay > $max_delay ) $delay = $max_delay;

                    $next_try = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $delay );

                    $wpdb->update( $table, [
                        'status' => 'pending',
                        'scheduled_at' => $next_try,
                        'updated_at' => current_time( 'mysql' ),
                        'attempts' => $attempts,
                        'error_message' => $result['error'] ?? 'Retry scheduled'
                    ], [ 'id' => $id ] );

                    Logger::warning("Queue: Item $id failed, retrying in {$delay}s (Attempt $attempts/$max_retries)", [
                        'error' => $result['error'] ?? 'Unknown'
                    ]);

                } else {
                    $wpdb->update( $table, [
                        'status' => 'failed',
                        'error_message' => $result['error'] ?? 'Unknown error',
                        'updated_at' => current_time( 'mysql' ),
                        'attempts' => $attempts
                    ], [ 'id' => $id ] );

                    Logger::error("Queue: Failed Item $id (Max retries reached)", [
                        'server' => $domain,
                        'error' => $result['error'] ?? 'Unknown'
                    ]);

                    do_action( 'pw_queue_item_failed', $id, $item, $result['error'] ?? 'Unknown' );
                }
            }

            Stats::increment_server_isp_usage( (int)$server['id'], $isp, $success );
        }
    }

    private static function postpone( int $id, string $delay = '+1 hour' ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'postal_queue';
        $wpdb->update( 
            $table, 
            [ 'scheduled_at' => date( 'Y-m-d H:i:s', strtotime( $delay, current_time( 'timestamp' ) ) ) ], 
            [ 'id' => $id ] 
        );
    }

    public static function cleanup(): void {
        global $wpdb;
        $table_queue = $wpdb->prefix . 'postal_queue';
        $table_logs = $wpdb->prefix . 'postal_logs';
        
        $days_queue = (int) Settings::get('auto_purge_queue_days', 90);
        $date_queue = date('Y-m-d H:i:s', strtotime("-$days_queue days"));
        
        $days_logs = (int) Settings::get('auto_purge_logs_days', 30);
        $date_logs = date('Y-m-d H:i:s', strtotime("-$days_logs days"));
        
        $wpdb->query($wpdb->prepare("DELETE FROM $table_queue WHERE status IN ('sent', 'failed') AND updated_at < %s", $date_queue));
        $wpdb->query($wpdb->prepare("DELETE FROM $table_logs WHERE created_at < %s", $date_logs));
        
        Logger::info("Maintenance: Cleanup done.");
    }

    public static function get_health_stats(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'postal_queue';
        
        $stats = [
            'pending' => 0,
            'processing' => 0,
            'sent_24h' => 0,
            'failed_24h' => 0,
            'top_isp' => 'N/A'
        ];
        
        $counts = $wpdb->get_results("SELECT status, COUNT(*) as count FROM $table GROUP BY status", ARRAY_A);
        foreach ($counts as $row) {
            if (isset($stats[$row['status']])) $stats[$row['status']] = (int) $row['count'];
        }
        
        $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $sent_24 = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = 'sent' AND updated_at >= %s", $yesterday));
        $failed_24 = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = 'failed' AND updated_at >= %s", $yesterday));
        
        $stats['sent_24h'] = (int) $sent_24;
        $stats['failed_24h'] = (int) $failed_24;
        
        $top = $wpdb->get_var("SELECT isp FROM $table WHERE updated_at >= '$yesterday' GROUP BY isp ORDER BY COUNT(*) DESC LIMIT 1");
        if ($top) $stats['top_isp'] = $top;
        
        return $stats;
    }

    public static function get_next_batch_info(): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'postal_queue';
        
        $next_time = $wpdb->get_var("
            SELECT MIN(scheduled_at) 
            FROM $table 
            WHERE status = 'pending' 
            AND scheduled_at > NOW()
        ");
        
        if ( ! $next_time ) {
            $pending_now = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending' AND scheduled_at <= NOW()");
            if ($pending_now > 0) {
                return [
                    'count' => (int) $pending_now,
                    'time' => 'Maintenant',
                    'timestamp' => time(),
                    'is_now' => true
                ];
            }
            return null;
        }
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM $table 
            WHERE status = 'pending' 
            AND scheduled_at BETWEEN %s AND %s
        ", $next_time, date('Y-m-d H:i:59', strtotime($next_time))));
        
        return [
            'count' => (int) $count,
            'time' => $next_time,
            'timestamp' => strtotime($next_time),
            'is_now' => false
        ];
    }
}
