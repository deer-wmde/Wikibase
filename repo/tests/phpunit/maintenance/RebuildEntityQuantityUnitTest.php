<?php

namespace Wikibase\Repo\Tests\Maintenance;

use DataValues\QuantityValue;
use DataValues\UnboundedQuantityValue;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Services\Lookup\LegacyAdapterItemLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\TermList;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Repo\Maintenance\RebuildEntityQuantityUnit;
use Wikibase\Repo\Store\Store;
use Wikibase\Repo\Tests\WikibaseTablesUsed;
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
	use WikibaseTablesUsed;

	/**
	 * @var ItemId[]
	 */
	private $itemIds = [];

	/**
	 * @var EntityStore
	 */
	private $store;

	/**
	 * @var Property
	 */
	private $quantityUnitProperty;

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

		$this->markTablesUsedForEntityEditing();

		if ( !$this->itemIds ) {
			$this->itemIds = $this->createItems();
		}
	}

	/**
	 * @return ItemId[]
	 */
	private function createItems(): array {
		$testUser = $this->getTestUser()->getUser();

		$this->quantityUnitProperty = new Property(null, new Fingerprint(new TermList([new Term('en', 'weight')])), 'quantity');
		$this->store->saveEntity($this->quantityUnitProperty, 'testing', $testUser, EDIT_NEW);

		$itemUnit = new Item();
		$this->store->saveEntity($itemUnit, 'testing', $testUser, EDIT_NEW);

		// case 1: value matches - needs update
		$itemValueMatches = new Item();

		$value = QuantityValue::newFromNumber(100, 'http://old.wikibase/entity/'.$itemUnit->getId()->getSerialization());
		$snak = new PropertyValueSnak( $this->quantityUnitProperty->getId(), $value);
		$itemValueMatches->setStatements(
			new StatementList(
				new Statement($snak)
			)
		);

		$this->store->saveEntity( $itemValueMatches, 'testing', $testUser, EDIT_NEW );

		// case 2: value is already correct - no update needed
		$itemValueAlreadyCorrect = new Item();

		$value = QuantityValue::newFromNumber(100, 'https://new.wikibase/entity/'.$itemUnit->getId()->getSerialization());
		$snak = new PropertyValueSnak( $this->quantityUnitProperty->getId(), $value);
		$itemValueAlreadyCorrect->setStatements(
			new StatementList(
				new Statement($snak)
			)
		);

		$this->store->saveEntity( $itemValueAlreadyCorrect, 'testing', $testUser, EDIT_NEW );

		// case 3: value doesn't match - mustn't be touched
		$itemValueDoesNotMatch = new Item();

		$value = QuantityValue::newFromNumber(100, 'http://wrong.wikibase/entity/Q1234');
		$snak = new PropertyValueSnak( $this->quantityUnitProperty->getId(), $value);
		$itemValueDoesNotMatch->setStatements(
			new StatementList(
				new Statement($snak)
			)
		);

		$this->store->saveEntity( $itemValueDoesNotMatch, 'testing', $testUser, EDIT_NEW );

		return [
			$itemValueMatches->getId(),
			$itemValueAlreadyCorrect->getId(),
			$itemValueDoesNotMatch->getId(),
		];
	}

	public function testExecute() {
		$fromValue = 'http://old.wikibase';
		$toValue = 'https://new.wikibase';

		$argv = [];

		$argv[] = '--from-value';
		$argv[] = $fromValue;

		$argv[] = '--to-value';
		$argv[] = $toValue;

		$this->maintenance->loadWithArgv( $argv );
		$this->maintenance->execute();

		$entityLookup = new LegacyAdapterItemLookup(
			WikibaseRepo::getStore()->getEntityLookup( Store::LOOKUP_CACHING_DISABLED )
		);

		$itemValueMatches = $entityLookup->getItemForId($this->itemIds[0]);
		$itemValueAlreadyCorrect = $entityLookup->getItemForId($this->itemIds[1]);
		$itemValueDoesNotMatch = $entityLookup->getItemForId($this->itemIds[2]);

		$itemValueMatchesUnit = $itemValueMatches->getStatements()->getByPropertyId($this->quantityUnitProperty->getId())
			->getMainSnaks()[0]->getDataValue()->getValue()->getUnit();

		$itemValueDoesNotMatchUnit = $itemValueDoesNotMatch->getStatements()->getByPropertyId($this->quantityUnitProperty->getId())
			->getMainSnaks()[0]->getDataValue()->getValue()->getUnit();

		$itemValueAlreadyCorrectUnit = $itemValueAlreadyCorrect->getStatements()->getByPropertyId($this->quantityUnitProperty->getId())
			->getMainSnaks()[0]->getDataValue()->getValue()->getUnit();

		$this->assertEquals(
			$toValue.'/entity/'.$itemValueMatches->getId()->getSerialization(),
			$itemValueMatchesUnit
		);

		$this->assertEquals(
			$toValue.'/entity/'.$itemValueAlreadyCorrect->getId()->getSerialization(),
			$itemValueAlreadyCorrectUnit);

		$this->assertEquals(
			'http://unrelated.wikibase/entity/Q1234',
			$itemValueDoesNotMatchUnit
		);
	}
}
