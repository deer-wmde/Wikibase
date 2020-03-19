<?php

namespace Wikibase\Client\Tests\DataAccess\ParserFunctions;

use Parser;
use MediaWiki\MediaWikiServices;
use ParserOptions;
use PPFrame;
use PPNode;
use Title;
use Wikibase\Client\DataAccess\DataAccessSnakFormatterFactory;
use Wikibase\Client\DataAccess\ParserFunctions\Runner;
use Wikibase\Client\DataAccess\ParserFunctions\StatementGroupRenderer;
use Wikibase\Client\DataAccess\ParserFunctions\StatementGroupRendererFactory;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\RestrictedEntityLookup;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\SiteLinkLookup;

/**
 * @covers \Wikibase\Client\DataAccess\ParserFunctions\Runner
 *
 * @group Wikibase
 * @group WikibaseClient
 * @group WikibaseDataAccess
 *
 * @license GPL-2.0-or-later
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Marius Hoch < hoo@online.de >
 */
class RunnerTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @dataProvider wikitextTypeProvider
	 */
	public function testRunPropertyParserFunction( $type ) {
		$itemId = new ItemId( 'Q3' );

		$runner = new Runner(
			$this->getStatementGroupRendererFactory( $itemId, 'Cat', $type ),
			$this->getSiteLinkLookup( $itemId ),
			new ItemIdParser(),
			$this->getRestrictedEntityLookup(),
			'enwiki',
			true
		);

		$parser = $this->getParser();
		$frame = $parser->getPreprocessor()->newFrame();
		$result = $runner->runPropertyParserFunction( $parser, $frame, [ 'Cat' ], $type );

		$expected = [
			'meow!',
			'noparse' => false,
			'nowiki' => false
		];

		$this->assertEquals( $expected, $result );
		$this->assertSame( 0, $parser->mExpensiveFunctionCount );
	}

	public function wikitextTypeProvider() {
		return [
			[ DataAccessSnakFormatterFactory::TYPE_ESCAPED_PLAINTEXT ],
			[ DataAccessSnakFormatterFactory::TYPE_RICH_WIKITEXT ],
		];
	}

	public function testRunPropertyParserFunction_arbitraryAccess() {
		$itemId = new ItemId( 'Q42' );

		$runner = new Runner(
			$this->getStatementGroupRendererFactory( $itemId, 'Cat', DataAccessSnakFormatterFactory::TYPE_ESCAPED_PLAINTEXT ),
			$this->createMock( SiteLinkLookup::class ),
			new ItemIdParser(),
			$this->getRestrictedEntityLookup(),
			'enwiki',
			true
		);

		$parser = $this->getParser();
		$frame = $this->getFromFrame( $itemId->getSerialization() );

		$result = $runner->runPropertyParserFunction(
			$parser,
			$frame,
			[ 'Cat', $this->createMock( PPNode::class ) ]
		);

		$expected = [
			'meow!',
			'noparse' => false,
			'nowiki' => false
		];

		$this->assertEquals( $expected, $result );
		$this->assertSame( 1, $parser->mExpensiveFunctionCount );
	}

	public function testRunPropertyParserFunction_onlyExpensiveOnce() {
		$itemId = new ItemId( 'Q42' );

		// Our entity has already been loaded.
		$restrictedEntityLookup = $this->getRestrictedEntityLookup();
		$restrictedEntityLookup->getEntity( $itemId );

		$runner = new Runner(
			$this->getStatementGroupRendererFactory( $itemId, 'Cat', DataAccessSnakFormatterFactory::TYPE_ESCAPED_PLAINTEXT ),
			$this->createMock( SiteLinkLookup::class ),
			new ItemIdParser(),
			$restrictedEntityLookup,
			'enwiki',
			true
		);

		$parser = $this->getParser();
		$frame = $this->getFromFrame( $itemId->getSerialization() );
		$runner->runPropertyParserFunction(
			$parser,
			$frame,
			[ 'Cat', $this->createMock( PPNode::class ) ]
		);

		// Still 0 as the entity has been loaded before
		$this->assertSame( 0, $parser->mExpensiveFunctionCount );
	}

	public function testRunPropertyParserFunction_expensiveParserFunctionLimitExceeded() {
		$itemId = new ItemId( 'Q42' );

		$runner = new Runner(
			$this->getStatementGroupRendererFactory( $itemId, 'Cat', DataAccessSnakFormatterFactory::TYPE_ESCAPED_PLAINTEXT ),
			$this->createMock( SiteLinkLookup::class ),
			new ItemIdParser(),
			$this->getRestrictedEntityLookup(),
			'enwiki',
			true
		);

		$parser = $this->getParser();
		$parser->mExpensiveFunctionCount = PHP_INT_MAX;

		$frame = $this->getFromFrame( $itemId->getSerialization() );
		$result = $runner->runPropertyParserFunction(
			$parser,
			$frame,
			[ 'Cat', $this->createMock( PPNode::class ) ]
		);

		// No result, as we exceeded the expensive parser function limit
		$expected = [
			'',
			'noparse' => false,
			'nowiki' => false
		];

		$this->assertEquals( $expected, $result );
	}

	public function testRunPropertyParserFunction_arbitraryAccessNotFound() {
		$rendererFactory = $this->getMockBuilder( StatementGroupRendererFactory::class )
			->disableOriginalConstructor()
			->getMock();

		$runner = new Runner(
			$rendererFactory,
			$this->createMock( SiteLinkLookup::class ),
			new ItemIdParser(),
			$this->getRestrictedEntityLookup(),
			'enwiki',
			true
		);

		$parser = $this->getParser();
		$frame = $this->getFromFrame( 'ThisIsNotQuiteAnEntityId' );

		$result = $runner->runPropertyParserFunction(
			$parser,
			$frame,
			[ 'Cat', $this->createMock( PPNode::class ) ]
		);

		$expected = [
			'',
			'noparse' => false,
			'nowiki' => false
		];

		$this->assertEquals( $expected, $result );
	}

	/**
	 * @return RestrictedEntityLookup
	 */
	private function getRestrictedEntityLookup() {
		return new RestrictedEntityLookup( $this->createMock( EntityLookup::class ), 200 );
	}

	/**
	 * @param ItemId $itemId
	 *
	 * @return SiteLinkLookup
	 */
	private function getSiteLinkLookup( ItemId $itemId ) {
		$siteLinkLookup = $this->createMock( SiteLinkLookup::class );

		$siteLinkLookup->expects( $this->once() )
			->method( 'getItemIdForLink' )
			->will( $this->returnValue( $itemId ) );

		return $siteLinkLookup;
	}

	/**
	 * @param string $itemIdSerialization
	 *
	 * @return PPFrame
	 */
	private function getFromFrame( $itemIdSerialization ) {
		$frame = $this->getMockBuilder( PPFrame::class )
			->getMock();
		$frame->expects( $this->once() )
			->method( 'expand' )
			->with( 'Cat' )
			->will( $this->returnValue( 'Cat' ) );

		$childFrame = $this->getMockBuilder( PPFrame::class )
			->getMock();
		$childFrame->expects( $this->once() )
			->method( 'getArgument' )
			->with( 'from' )
			->will( $this->returnValue( $itemIdSerialization ) );

		$frame->expects( $this->once() )
			->method( 'newChild' )
			->will( $this->returnValue( $childFrame ) );

		return $frame;
	}

	/**
	 * @param EntityId $entityId
	 * @param string $propertyLabelOrId
	 * @param string $type
	 *
	 * @return StatementGroupRendererFactory
	 */
	private function getStatementGroupRendererFactory( EntityId $entityId, $propertyLabelOrId, $type ) {
		$renderer = $this->getRenderer( $entityId, $propertyLabelOrId );

		$rendererFactory = $this->getMockBuilder( StatementGroupRendererFactory::class )
			->disableOriginalConstructor()
			->getMock();

		$rendererFactory->expects( $this->any() )
			->method( 'newRendererFromParser' )
			->with( $this->isInstanceOf( Parser::class ), $type )
			->will( $this->returnValue( $renderer ) );

		return $rendererFactory;
	}

	/**
	 * @param EntityId $entityId
	 * @param string $propertyLabelOrId
	 *
	 * @return StatementGroupRenderer
	 */
	private function getRenderer( EntityId $entityId, $propertyLabelOrId ) {
		$renderer = $this->createMock( StatementGroupRenderer::class );

		$renderer->expects( $this->any() )
			->method( 'render' )
			->with( $entityId, $propertyLabelOrId )
			->will( $this->returnValue( 'meow!' ) );

		return $renderer;
	}

	private function getParser() {
		$title = Title::newFromText( 'Cat' );
		$popt = ParserOptions::newFromAnon();

		$parser = MediaWikiServices::getInstance()->getParserFactory()->create();
		$parser->startExternalParse( $title, $popt, Parser::OT_HTML );

		return $parser;
	}

}
