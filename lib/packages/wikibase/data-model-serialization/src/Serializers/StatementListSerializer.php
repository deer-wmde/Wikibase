<?php

namespace Wikibase\DataModel\Serializers;

use Serializers\DispatchableSerializer;
use Serializers\Exceptions\SerializationException;
use Serializers\Exceptions\UnsupportedObjectException;
use Serializers\Serializer;
use Wikibase\DataModel\ByPropertyIdGrouper;
use Wikibase\DataModel\Statement\StatementList;

/**
 * Package private
 *
 * @licence GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class StatementListSerializer implements DispatchableSerializer {

	/**
	 * @var Serializer
	 */
	private $statementSerializer;

	/**
	 * @var bool
	 */
	private $useObjectsForMaps;

	/**
	 * @param Serializer $statementSerializer
	 * @param bool $useObjectsForMaps
	 */
	public function __construct( Serializer $statementSerializer, $useObjectsForMaps ) {
		$this->statementSerializer = $statementSerializer;
		$this->useObjectsForMaps = $useObjectsForMaps;
	}

	/**
	 * @see Serializer::isSerializerFor
	 *
	 * @param mixed $object
	 *
	 * @return bool
	 */
	public function isSerializerFor( $object ) {
		return $object instanceof StatementList;
	}

	/**
	 * @see Serializer::serialize
	 *
	 * @param mixed $object
	 *
	 * @return array
	 * @throws SerializationException
	 */
	public function serialize( $object ) {
		if ( !$this->isSerializerFor( $object ) ) {
			throw new UnsupportedObjectException(
				$object,
				'StatementListSerializer can only serialize StatementList objects'
			);
		}

		return $this->getSerialized( $object );
	}

	private function getSerialized( StatementList $statementList ) {
		$serialization = array();

		$byPropertyIdGrouper = new ByPropertyIdGrouper( $statementList );

		foreach( $byPropertyIdGrouper->getPropertyIds() as $propertyId ) {
			$serialization[$propertyId->getSerialization()] = $this->getSerializedStatements(
				$byPropertyIdGrouper->getByPropertyId( $propertyId )
			);
		}

		if ( $this->useObjectsForMaps ) {
			$serialization = (object)$serialization;
		}

		return $serialization;
	}

	private function getSerializedStatements( array $statements ) {
		$serialization = array();

		foreach ( $statements as $statement ) {
			$serialization[] = $this->statementSerializer->serialize( $statement );
		}

		return $serialization;
	}

}
