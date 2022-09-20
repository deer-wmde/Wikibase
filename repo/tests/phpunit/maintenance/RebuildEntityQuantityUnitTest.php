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

	private $valueMatchesItemId;
	private $valueAlreadyCorrectItemId;
	private $valueDoesNotMatchItemId;

	/**
	 * @var EntityStore
	 */
	private $store;

	/**
	 * @var Property
	 */
	private $quantityUnitProperty;
	private $user;
	private $itemUnit;

	/**
	 * @return string
	 */
	protected function getMaintenanceClass() {
		return RebuildEntityQuantityUnit::class;
	}

	/**
	 * @param LegacyAdapterItemLookup $entityLookup
	 * @param $itemId
	 * @return void
	 */
	public function getItemUnitValue(LegacyAdapterItemLookup $entityLookup, $itemId): string
	{
		$item = $entityLookup->getItemForId($itemId);
		$itemStatements = $item->getStatements()->getByPropertyId($this->quantityUnitProperty->getId());
		$mainSnak = $itemStatements->getMainSnaks()[0];
		$unitValue = $mainSnak->getDataValue()->getValue()->getUnit();

		return $unitValue;
	}

	/**
	 * @param $unitValue
	 * @return ItemId|null
	 * @throws \PermissionsError
	 * @throws \Wikibase\Lib\Store\StorageException
	 */
	private function createItem($unitValue) {
		$item = new Item();

		$value = QuantityValue::newFromNumber(100, $unitValue);
		$snak = new PropertyValueSnak( $this->quantityUnitProperty->getId(), $value);
		$item->setStatements(
			new StatementList(
				new Statement($snak)
			)
		);

		$this->store->saveEntity( $item, 'testing', $this->user, EDIT_NEW );

		return $item->getId();
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->markTablesUsedForEntityEditing();
		$this->store = WikibaseRepo::getEntityStore();
		$this->user = $this->getTestUser()->getUser();

		$this->quantityUnitProperty = new Property(null, new Fingerprint(new TermList([new Term('en', 'weight')])), 'quantity');
		$this->store->saveEntity($this->quantityUnitProperty, 'testing', $this->user, EDIT_NEW);

		$this->itemUnit = new Item();
		$this->store->saveEntity($this->itemUnit, 'testing', $this->user, EDIT_NEW);

		$this->valueMatchesItemId = $this->createItem('http://old.wikibase/entity/'. $this->itemUnit->getId()->getSerialization());
		$this->valueAlreadyCorrectItemId = $this->createItem('https://new.wikibase/entity/'. $this->itemUnit->getId()->getSerialization());
		$this->valueDoesNotMatchItemId = $this->createItem('http://unrelated.wikibase/entity/Q1234');
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

		$this->assertEquals(
			$toValue.'/entity/'.$this->itemUnit->getId()->getSerialization(),
			$this->getItemUnitValue($entityLookup, $this->valueMatchesItemId));

		$this->assertEquals(
			$toValue.'/entity/'.$this->itemUnit->getId()->getSerialization(),
			$this->getItemUnitValue($entityLookup, $this->valueAlreadyCorrectItemId));

		$this->assertEquals(
			'http://unrelated.wikibase/entity/Q1234',
			$this->getItemUnitValue($entityLookup, $this->valueDoesNotMatchItemId)
		);
	}
}
