<?php

namespace Wikibase\Repo\Tests\Maintenance;

use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikibase\Repo\Maintenance\RebuildEntityQuantityUnit;
use Wikibase\Repo\Maintenance\EntityQuantityUnitRebuilder;

// files in maintenance/ are not autoloaded to avoid accidental usage, so load explicitly
require_once __DIR__ . '/../../../maintenance/rebuildEntityQuantityUnit.php';

/**
 * @covers \Wikibase\Repo\Maintenance\RebuildEntityQuantityUnit
 *
 * @group Wikibase
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Deniz Erdogan < deniz.erdogan@wikimedia.de >
 */
class RebuildEntityQuantityUnitTest extends MaintenanceBaseTestCase {
	protected function getMaintenanceClass() {
		return RebuildEntityQuantityUnit::class;
	}

	protected function setUp(): void {
		parent::setUp();
	}

	public function hostProvider(): array
	{
		return [
			'example call' => [
				'from-host' => 'example.localhost',
				'to-host'   => 'example.com',
			],
		];
	}

	/**
	 * @dataProvider hostProvider
	 */
	public function testExecute( $fromHost, $toHost ) {
		$argv = [];

		$argv[] = '--from-host';
		$argv[] = $fromHost;

		$argv[] = '--to-host';
		$argv[] = $toHost;

		$this->maintenance->loadWithArgv( $argv );
		$this->maintenance->execute();
	}
}
