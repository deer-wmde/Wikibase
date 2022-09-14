<?php

namespace Wikibase\Repo\Tests\Maintenance;

use DataValues\QuantityValue;
use DataValues\UnboundedQuantityValue;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\TermList;
use Wikibase\DataModel\Term\Term;
use Wikibase\Repo\Maintenance\RebuildEntityQuantityUnit;
use Wikibase\Repo\WikibaseRepo;

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
	/**
	 * @var ItemId[]
	 */
	private $itemIds = [];

	/**
	 * @var WikibaseRepo
	 */
	private $store;

	/**
	 * @return string
	 */
	protected function getMaintenanceClass() {
		return RebuildEntityQuantityUnit::class;
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->store = WikibaseRepo::getEntityStore();

		// TODO are these tables the right ones?
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'wb_items_per_site';

		if ( !$this->itemIds ) {
			$this->itemIds = $this->createItems();
		}
	}

	/**
	 * @return ItemId[]
	 */
	private function createItems(): array {
		$testUser = $this->getTestUser()->getUser();
		$quantityUnitProperty = new Property(null, new Fingerprint( new TermList( [ new Term( 'en', 'test') ] ) ), 'quantity');
		$this->store->saveEntity($quantityUnitProperty, 'testing', $testUser, EDIT_NEW);

		// case 1: host matches - needs update
		$itemHostMatches = new Item();

		$value = UnboundedQuantityValue::newFromNumber(100, 'foo');
		$snak = new PropertyValueSnak( $quantityUnitProperty->getId(), $value);
		$itemHostMatches->setStatements(
			new StatementList(
				new Statement($snak)
			)
		);

		$this->store->saveEntity( $itemHostMatches, 'testing', $testUser, EDIT_NEW );

		// case 2: host doesn't match - mustn't be touched
		/*
		$itemHostDoesNotMatch = new Item();
		// TODO add Statements
		$this->store->saveEntity( $itemHostDoesNotMatch, 'testing', $testUser, EDIT_NEW );
		//*/

		// case 3: host is already correct - no update needed
		/*
		$itemHostMatches = new Item();
		// TODO add Statements
		$this->store->saveEntity( $itemHostMatches, 'testing', $testUser, EDIT_NEW );
		//*/

		return [
			$itemHostMatches->getId(),
//			$itemHostDoesNotMatch->getId(),
//			$itemHostMatches->getId()
		];
	}

	/**
	 * @return string[][]
	 */
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

		// TODO assert expected quantity unit values in item statements
//		$itemHostMatches = $this->store->
//		$this->assertEquals('', );
	}
}
