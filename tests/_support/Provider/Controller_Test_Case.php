<?php
/**
 * The base test case to test controllers extending the `TEC\Common\Provider\Controller` class.
 */

namespace TEC\Common\Tests\Provider;

use Codeception\TestCase\WPTestCase;
use TEC\Common\Provider\Controller;
use Tribe\Tests\Traits\With_Uopz;
use Tribe__Container as Container;

/**
 * Class Controller_Test_Case.
 *
 * @since 5.0.17
 *
 * @package TEC\Tickets\Flexible_Tickets;
 */
class Controller_Test_Case extends WPTestCase {
	use With_Uopz;

	/**
	 * A reference to the container used to create the controller and run the tests.
	 *
	 * @var Container
	 */
	protected Container $test_container;

	/**
	 * A set of logs collected after the Controller has been registered.
	 * This will only contain logs generated by the Controller itself.
	 *
	 * @var array<string,array>> An array of log entries, each entry is an array with the keys `level`, `message` and
	 *                         `context`.
	 */
	protected array $controller_logs = [];
	/**
	 * A set of logs collected after the Controller has been registered, this will contain all logs, including those
	 * generated by the Controller itself.
	 *
	 * @var array<string,array>> An array of log entries, each entry is an array with the keys `level`, `message` and
	 *                         `context`.
	 */
	protected $logs = [];

	/**
	 * Creates a controller instance and sets up a dedicated Service Locator for it.
	 *
	 * In the context of the dedicated Service Locator the controller is not yet registered.
	 *
	 * @since 5.0.17
	 *
	 * @param class-string<Controller>|null $controller_class The controller class to create an instance of, or `null`
	 *                                                        to build from the `controller_class` property.
	 *
	 * @return Controller The controller instance, built on a dedicated testing Service Locator.
	 */
	protected function make_controller( string $controller_class = null ): Controller {
		if ( ! ( $controller_class || property_exists( $this, 'controller_class' ) ) ) {
			throw new \RuntimeException( 'Each Controller test case must define a controller_class property.' );
		}

		$controller_class = $controller_class ?: $this->controller_class;

		/** @var Controller $original_controller */
		$original_controller = tribe( $controller_class );
		// Unregister the original controller to avoid actions and filters hooking twice.
		$original_controller->unregister();
		// Create a container that will provide the context for the controller cloning the original Service Locator.
		$this->test_container = clone tribe();
		// When code interacts with the Service Locator, use the test one.
		$test_container = $this->test_container;
		$this->set_fn_return( 'tribe', function ( $id = null ) use ( $test_container ) {
			return $id ? $test_container->make( $id ) : $test_container;
		}, true );
		// Register the test container in the test container.
		$this->test_container->singleton( get_class( $this->test_container ), $this->test_container );
		$this->test_container->singleton( \tad_DI52_Container::class, $this->test_container );
		// The controller will NOT have registered in this container.
		$this->test_container->setVar( $controller_class . '_registered', false );
		// Unset the previous, maybe, bound and resolved instance of the controller.
		unset( $this->test_container[ $controller_class ] );
		// Nothing should be bound in the container for the controller.
		$this->assertFalse( $this->test_container->isBound( $controller_class ) );
		$this->assertFalse( $controller_class::is_registered() );
		// From now on, ingest all logging.
		global $wp_filter;
		$wp_filter['tribe_log'] = new \WP_Hook();
		add_action( 'tribe_log', function ( $level, $message, $context ) {
			if ( isset($context['controller']) && $context['controller'] === $this->controller_class ) {
				// Log the controller logs.
				$this->controller_logs[] = [
					'level'   => $level,
					'message' => $message,
					'context' => $context,
				];
			}

			// Log everything.
			$this->logs[] = [
				'level'   => $level,
				'message' => $message,
				'context' => $context,
			];

		}, 10, 3 );

		// Due to the previous unset, the container will build this as a prototype.
		return $this->test_container->make( $controller_class );
	}

	/**
	 * It should register and unregister correctly
	 *
	 * This method will run by default to make sure the Controller will clean up after itself upon unregistration.
	 *
	 * @test
	 */
	public function should_register_and_unregister_correctly(): void {
		// Run this now to check the `controller_class` property is set.
		$controller = $this->make_controller();

		$added_filters    = [];
		$controller_class = $this->controller_class;

		$this->set_fn_return( 'add_filter', function (
			string $tag, callable $callback, int $priority = 10, int $args = 1
		) use (
			$controller_class, &$added_filters
		) {
			if ( is_array( $callback ) && $callback[0] instanceof $controller_class ) {
				$added_filters[] = [ $tag, $callback, $priority ];
			}
			add_filter( $tag, $callback, $priority, $args );
		}, true );
		$this->set_fn_return( 'remove_filter', function (
			string $tag, callable $callback, int $priority = 10
		) use (
			$controller_class, &$added_filters
		) {
			if (
				is_array( $callback )
				&& $callback[0] instanceof $controller_class
			) {
				$found = array_search( [ $tag, $callback, $priority ], $added_filters, true );
				if ( $found !== false ) {
					unset( $added_filters[ $found ] );
				}
			}
			remove_filter( $tag, $callback, $priority );
		}, true );

		$controller->register();
		$controller->unregister();

		$this->assertCount(
			0,
			$added_filters,
			'The controller should have removed all its filters and actions: '
			. PHP_EOL . json_encode( $added_filters, JSON_PRETTY_PRINT )
		);
	}

	/**
	 * @before
	 */
	public function reset_logs(): void {
		$this->logs            = [];
		$this->controller_logs = [];
	}

	/**
	 * Asserts the controller logged a message with the specified level and message.
	 *
	 * @since 5.0.17
	 *
	 * @param string $level  The log level.
	 * @param string $needle The message to look for, or a part of it.
	 *
	 * @return void
	 */
	protected function assert_controller_logged( string $level, string $needle ): void {
		$found              = false;
		$correct_level_logs = array_filter( $this->controller_logs, static fn( $log ) => $log['level'] === $level );
		foreach ( $correct_level_logs as $log ) {
			if ( strpos( $log['message'], $needle ) !== false ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, "Could not find a log with level {$level} and message matching {$needle}" );
	}

	/**
	 * Asserts a message with the specified level and message was logged.
	 *
	 * This assertion will look in all logs, including the ones logged by the controller.
	 *
	 * @since 5.0.17
	 *
	 * @param string $level  The log level.
	 * @param string $needle The message to look for, or a part of it.
	 *
	 * @return void
	 */
	protected function assert_logged( string $level, string $needle ): void {
		$found              = false;
		$correct_level_logs = array_filter( $this->logs, static fn( $log ) => $log['level'] === $level );
		foreach ( $correct_level_logs as $log ) {
			if ( strpos( $log['message'], $needle ) !== false ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, "Could not find a log with level {$level} and message matching {$needle}" );
	}
}
