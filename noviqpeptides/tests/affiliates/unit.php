<?php
/**
 * Money math. No WordPress, no WooCommerce.
 *
 *   php tests/affiliates/unit.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

$plugin = getenv( 'NOVIQ_PLUGIN_PATH' );
if ( ! is_string( $plugin ) || '' === $plugin ) {
	$plugin = '/var/www/html/wp-content/plugins/noviq-peptides';
}
require $plugin . '/src/Affiliates/Money.php';

use Noviq\Core\Affiliates\Money;

$failed = 0;

function aff_eq( mixed $expected, mixed $actual, string $label ): void {
	global $failed;
	if ( $expected === $actual ) {
		echo "PASS {$label}\n";
		return;
	}
	++$failed;
	echo "FAIL {$label}: expected " . var_export( $expected, true ) . ' got ' . var_export( $actual, true ) . "\n";
}

aff_eq( 10000, Money::to_cents( '100' ), '100 dollars' );
aff_eq( 10000, Money::to_cents( '100.00' ), '100.00 dollars' );
aff_eq( 2250, Money::to_cents( '22.5' ), '22.5 dollars' );
aff_eq( 2256, Money::to_cents( '22.555' ), 'half up third decimal' );
aff_eq( 2255, Money::to_cents( '22.554' ), 'half up does not fire at 4' );
aff_eq( -1010, Money::to_cents( '-10.10' ), 'negative' );
aff_eq( 123450, Money::to_cents( '1,234.50' ), 'thousands separator' );
aff_eq( 10000, Money::to_cents( '99.995' ), 'carry into dollars' );
aff_eq( 0, Money::to_cents( '' ), 'empty' );
aff_eq( 0, Money::to_cents( 'nope' ), 'garbage' );

aff_eq( 2250, Money::apply_rate( 9000, Money::REP_BP ), '25% of 9000' );
aff_eq( 630, Money::apply_rate( 9000, Money::RECRUITER_BP ), '7% of 9000' );
aff_eq( 0, Money::apply_rate( 1, Money::REP_BP ), '25% of 1 cent rounds down' );
aff_eq( 1, Money::apply_rate( 2, Money::REP_BP ), '25% of 2 cents rounds half up' );
aff_eq( 0, Money::apply_rate( 0, Money::REP_BP ), 'zero base' );

aff_eq( 33, Money::prorate( 100, 1, 3 ), '1/3 of 100' );
aff_eq( 67, Money::prorate( 100, 2, 3 ), '2/3 of 100' );
aff_eq( 100, Money::prorate( 100, 3, 3 ), 'full prorate' );

aff_eq( -33, Money::refund_delta( 100, 3, 1, 0 ), 'first third' );
aff_eq( -34, Money::refund_delta( 100, 3, 2, 33 ), 'second third picks up rounding' );
aff_eq( -33, Money::refund_delta( 100, 3, 3, 67 ), 'final third clears the remainder' );
aff_eq( 0, Money::refund_delta( 100, 3, 3, 100 ), 'nothing left to reverse' );
aff_eq( -2250, Money::refund_delta( 2250, 9000, 9000, 0 ), 'full refund of the example rep commission' );
aff_eq( -1125, Money::refund_delta( 2250, 9000, 4500, 0 ), 'half refund of the example' );
aff_eq( -1125, Money::refund_delta( 2250, 9000, 9000, 1125 ), 'second half clears the rest' );
aff_eq( 0, Money::refund_delta( 2250, 9000, 9000, 2250 ), 'second full refund is a no-op' );

aff_eq( '$22.50', Money::format_plain( 2250 ), 'format commission' );
aff_eq( '-$6.30', Money::format_plain( -630 ), 'format negative' );
aff_eq( '$1,234.50', Money::format_plain( 123450 ), 'format thousands' );

if ( $failed > 0 ) {
	fwrite( STDERR, "{$failed} failed\n" );
	exit( 1 );
}

echo "unit ok\n";
