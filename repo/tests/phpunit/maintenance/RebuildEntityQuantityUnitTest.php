<?php

namespace Wikibase\Repo\Tests\Maintenance;

use DataValues\QuantityValue;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use PermissionsError;
use User;
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
use Wikibase\Lib\Store\StorageException;
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
class RebuildEntityQuantityUnitTest extends MaintenanceBaseTestCase
{
	use WikibaseTablesUsed;

	/**
	 * @var ItemId[]
	 */
	private $itemIds;

	/**
	 * @var EntityStore
	 */
	private $store;

	/**
	 * @var Property
	 */
	private $quantityUnitProperty;

	/**
	 * @var string
	 */
	private $unitItemId;

	/**
	 * @var User
	 */
	private $user;

	/**
	 * @return string
	 */
	protected function getMaintenanceClass()
	{
		return RebuildEntityQuantityUnit::class;
	}

	/**
	 * @param $unitValue
	 * @return ItemId|null
	 * @throws PermissionsError
	 * @throws StorageException
	 */
	private function createItemWithUnitValue($unitValue)
	{
		$item = new Item();

		$value = QuantityValue::newFromNumber(100, $unitValue);
		$snak = new PropertyValueSnak($this->quantityUnitProperty->getId(), $value);
		$item->setStatements(
			new StatementList(
				new Statement($snak)
			)
		);

		$this->store->saveEntity($item, 'testing', $this->user, EDIT_NEW);

		return $item->getId();
	}

	/**
	 * @param LegacyAdapterItemLookup $entityLookup
	 * @param ItemId $itemId
	 * @return string
	 */
	public function getItemUnitValueFromItem(LegacyAdapterItemLookup $entityLookup, ItemId $itemId): string
	{
		$item = $entityLookup->getItemForId($itemId);
		$itemStatements = $item->getStatements()->getByPropertyId($this->quantityUnitProperty->getId());
		$mainSnak = $itemStatements->getMainSnaks()[0];
		$unitValue = $mainSnak->getDataValue()->getValue()->getUnit();

		return $unitValue;
	}

	protected function setUp(): void
	{
		parent::setUp();

		$this->markTablesUsedForEntityEditing();
		$this->store = WikibaseRepo::getEntityStore();
		$this->user = $this->getTestUser()->getUser();

		$this->quantityUnitProperty = new Property(null, new Fingerprint(new TermList([new Term('en', 'weight')])), 'quantity');
		$this->store->saveEntity($this->quantityUnitProperty, 'testing', $this->user, EDIT_NEW);

		$unitItem = new Item();
		$this->store->saveEntity($unitItem, 'testing', $this->user, EDIT_NEW);

		// this is most likely 'Q1' but let's look it up
		$this->unitItemId = $unitItem->getId()->getSerialization();

		$unitValues = [
			'matches'        => 'http://old.wikibase/entity/' . $this->unitItemId,
			'alreadyCorrect' => 'https://new.wikibase/entity/' . $this->unitItemId,
			'doesNotMatch'   => 'http://unrelated.wikibase/entity/Q1234',
		];

		foreach ($unitValues as $key => $unitValue) {
			$this->itemIds[$key] = $this->createItemWithUnitValue($unitValue);
		}
	}

	public function testExecute()
	{
		$fromValue = 'http://old.wikibase';
		$toValue = 'https://new.wikibase';

		$this->maintenance->loadWithArgv([
			'--from-value',
			$fromValue,

			'--to-value',
			$toValue,

			'--sleep',
			'0',
		]);
		$this->maintenance->execute();

		$entityLookup = new LegacyAdapterItemLookup(
			WikibaseRepo::getStore()->getEntityLookup(Store::LOOKUP_CACHING_DISABLED)
		);

		// test if value changed from 'http://old.wikibase/entity/Q1' to 'https://new.wikibase/entity/Q1'
		$this->assertEquals(
			$toValue . '/entity/' . $this->unitItemId,
			$this->getItemUnitValueFromItem($entityLookup, $this->itemIds['matches'])
		);

		// test if value did NOT change from 'https://new.wikibase/entity/Q1'
		$this->assertEquals(
			$toValue . '/entity/' . $this->unitItemId,
			$this->getItemUnitValueFromItem($entityLookup, $this->itemIds['alreadyCorrect'])
		);

		// test if value did NOT change from 'http://unrelated.wikibase/entity/Q1234'
		$this->assertEquals(
			'http://unrelated.wikibase/entity/Q1234',
			$this->getItemUnitValueFromItem($entityLookup, $this->itemIds['doesNotMatch'])
		);
	}
}
