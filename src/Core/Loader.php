<?php

namespace PostalWarmup\Core;

declare(strict_types=1);

/**
 * Register all actions and filters for the plugin.
 */
class Loader {

	protected array $actions;
	protected array $filters;

	public function __construct() {
		$this->actions = [];
		$this->filters = [];
	}

	public function add_action( string $hook, $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	public function add_filter( string $hook, $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	private function add( array $hooks, string $hook, $component, string $callback, int $priority, int $accepted_args ): array {
		$hooks[] = [
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args
		];

		return $hooks;
	}

	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], [ $hook['component'], $hook['callback'] ], $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], [ $hook['component'], $hook['callback'] ], $hook['priority'], $hook['accepted_args'] );
		}
	}
}
