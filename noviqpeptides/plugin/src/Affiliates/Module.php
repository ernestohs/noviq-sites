<?php
/**
 * Wires the affiliate subsystem.
 *
 * @package Noviq\Core
 */

declare(strict_types=1);

namespace Noviq\Core\Affiliates;

use Noviq\Core\Affiliates\Account\Dashboard;
use Noviq\Core\Affiliates\Admin\AffiliatesPage;
use Noviq\Core\Affiliates\Cli\AffiliatesCommand;

defined( 'ABSPATH' ) || exit;

final class Module {

	public static function init(): void {
		Schema::init();
		Codes::init();
		PromotionGuard::init();
		Attribution::init();
		Commissions::init();
		AffiliatesPage::init();
		Dashboard::init();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'noviq affiliates', AffiliatesCommand::class );
		}
	}
}
