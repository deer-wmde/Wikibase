<?php

namespace Wikibase\Repo\Tests\ChangeOp\Deserialization;

use Wikibase\Repo\ChangeOp\Deserialization\AliasesChangeOpDeserializer;
use Wikibase\Repo\ChangeOp\Deserialization\ChangeOpDeserializerFactory;
use Wikibase\Repo\ChangeOp\Deserialization\ClaimsChangeOpDeserializer;
use Wikibase\Repo\ChangeOp\Deserialization\DescriptionsChangeOpDeserializer;
use Wikibase\Repo\ChangeOp\Deserialization\LabelsChangeOpDeserializer;
use Wikibase\Repo\ChangeOp\Deserialization\SiteLinkBadgeChangeOpSerializationValidator;
use Wikibase\Repo\ChangeOp\Deserialization\SiteLinksChangeOpDeserializer;
use Wikibase\Repo\ChangeOp\Deserialization\TermChangeOpSerializationValidator;
use Wikibase\Repo\SiteLinkTargetProvider;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \Wikibase\Repo\ChangeOp\Deserialization\ChangeOpDeserializerFactory
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class ChangeOpDeserializerFactoryTest extends \PHPUnit\Framework\TestCase {

	public function testGetLabelsChangeOpDeserializer() {
		$this->assertInstanceOf(
			LabelsChangeOpDeserializer::class,
			$this->newWikibaseChangeOpDeserializerFactory()->getLabelsChangeOpDeserializer()
		);
	}

	public function testGetDescriptionsChangeOpDeserializer() {
		$this->assertInstanceOf(
			DescriptionsChangeOpDeserializer::class,
			$this->newWikibaseChangeOpDeserializerFactory()->getDescriptionsChangeOpDeserializer()
		);
	}

	public function testGetAliasesChangeOpDeserializer() {
		$this->assertInstanceOf(
			AliasesChangeOpDeserializer::class,
			$this->newWikibaseChangeOpDeserializerFactory()->getAliasesChangeOpDeserializer()
		);
	}

	public function testGetClaimsChangeOpDeserializer() {
		$this->assertInstanceOf(
			ClaimsChangeOpDeserializer::class,
			$this->newWikibaseChangeOpDeserializerFactory()->getClaimsChangeOpDeserializer()
		);
	}

	public function testGetSiteLinksChangeOpDeserializer() {
		$this->assertInstanceOf(
			SiteLinksChangeOpDeserializer::class,
			$this->newWikibaseChangeOpDeserializerFactory()->getSiteLinksChangeOpDeserializer()
		);
	}

	private function newWikibaseChangeOpDeserializerFactory() {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$changeOpFactoryProvider = $wikibaseRepo->getChangeOpFactoryProvider();

		return new ChangeOpDeserializerFactory(
			$changeOpFactoryProvider->getFingerprintChangeOpFactory(),
			$changeOpFactoryProvider->getStatementChangeOpFactory(),
			$changeOpFactoryProvider->getSiteLinkChangeOpFactory(),
			new TermChangeOpSerializationValidator( $wikibaseRepo->getTermsLanguages() ),
			new SiteLinkBadgeChangeOpSerializationValidator(
				WikibaseRepo::getEntityTitleLookup(),
				[]
			),
			$wikibaseRepo->getExternalFormatStatementDeserializer(),
			new SiteLinkTargetProvider( $wikibaseRepo->getSiteLookup(), [] ),
			WikibaseRepo::getEntityIdParser(),
			$wikibaseRepo->getStringNormalizer(),
			[]
		);
	}

}
