<?php

/**
 * Tests for the SiteObject class.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @since 1.20
 *
 * @ingroup Site
 * @ingroup Test
 *
 * @group Site
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class SiteRowTest extends ORMRowTest {

	/**
	 * @see ORMRowTest::getRowClass
	 * @since 1.20
	 * @return string
	 */
	protected function getRowClass() {
		return 'SiteObject';
	}

	/**
	 * @see ORMRowTest::getTableInstance
	 * @since 1.20
	 * @return IORMTable
	 */
	protected function getTableInstance() {
		return SitesTable::singleton();
	}

	/**
	 * @see ORMRowTest::constructorTestProvider
	 * @since 1.20
	 * @return array
	 */
	public function constructorTestProvider() {
		$rows = array(
			array( 'en', SITE_GROUP_WIKIPEDIA, 'https://en.wikipedia.org', '/wiki/$1', 'en' ),
			array( 'en', SITE_GROUP_WIKIPEDIA, 'https://en.wikipedia.org', '/wiki/$1', 'en', SITE_TYPE_MEDIAWIKI ),
			array(
				'en', SITE_GROUP_WIKIPEDIA, 'https://en.wikipedia.org',
				'/wiki/$1', 'en', SITE_TYPE_MEDIAWIKI, '/w/'
			),
			array(
				'en', SITE_GROUP_WIKIPEDIA, 'https://en.wikipedia.org',
				'/wiki/$1', 'en', SITE_TYPE_MEDIAWIKI, '/w/', array()
			),
			array(
				'en', SITE_GROUP_WIKIPEDIA, 'https://en.wikipedia.org',
				'/wiki/$1', 'en', SITE_TYPE_MEDIAWIKI, '/w/', array( 'foo' => 'bar', 'baz' => 42 )
			),
		);

		foreach ( $rows as &$args ) {
			$fields = array(
				'global_key' => $args[0],
				'group' => $args[1],
				'url' => $args[2],
				'page_path' => $args[3],
				'language' => $args[4],
			);

			if ( array_key_exists( 5, $args ) ) {
				$fields['type'] = $args[5];
			}

			if ( array_key_exists( 6, $args ) ) {
				$fields['file_path'] = $args[6];
			}

			if ( array_key_exists( 7, $args ) ) {
				$fields['data'] = $args[7];
			}

			$args = array( $fields, true );
		}

		return $rows;
	}

	/**
	 * @dataProvider constructorTestProvider
	 */
	public function testConstructorEvenMore( array $fields ) {
		$site = \SitesTable::singleton()->newRow( $fields, true );

		$functionMap = array(
			'getGlobalId',
			'getGroup',
			'getUrl',
			'getRelativePagePath',
			'getLanguage',
		);

		if ( array_key_exists( 'type', $fields ) ) {
			$functionMap[] = 'getType';
		}

		if ( array_key_exists( 'file_path', $fields ) ) {
			$functionMap[] = 'getRelativeFilePath';
		}

		if ( array_key_exists( 'data', $fields ) ) {
			$functionMap[] = 'getExtraData';
		}

		reset( $fields );

		foreach ( $functionMap as $functionName ) {
			$this->assertEquals( current( $fields ), call_user_func( array( $site, $functionName ) ) );
			next( $fields );
		}

		$this->assertEquals( $fields['url'] . $fields['page_path'], $site->getPagePath() );
	}

	public function pathProvider() {
		return array(
			// url, filepath, path arg, expected
			array( 'https://en.wikipedia.org', '/w/$1', 'api.php', 'https://en.wikipedia.org/w/api.php' ),
			array( 'https://en.wikipedia.org', '/w/', 'api.php', 'https://en.wikipedia.org/w/' ),
			array( 'https://en.wikipedia.org', '/foo/page.php?name=$1', 'api.php', 'https://en.wikipedia.org/foo/page.php?name=api.php' ),
			array( 'https://en.wikipedia.org', '/w/$1', '', 'https://en.wikipedia.org/w/' ),
			array( 'https://en.wikipedia.org', '/w/$1', 'foo/bar/api.php', 'https://en.wikipedia.org/w/foo/bar/api.php' ),
		);
	}

	/**
	 * @dataProvider pathProvider
	 */
	public function testGetPath( $url, $filePath, $pathArgument, $expected ) {
		$site = SitesTable::singleton()->newRow( array(
			'global_key' => 'en',
			'url' => $url,
			'file_path' => $filePath,
			'page_path' => '',
			'language' => 'en',
		), true );
		$this->assertEquals( $expected, $site->getFilePath( $pathArgument ) );
	}

	public function pageUrlProvider() {
		return array(
			// url, filepath, path arg, expected
			array( 'https://en.wikipedia.org', '/wiki/$1', 'Berlin', 'https://en.wikipedia.org/wiki/Berlin' ),
			array( 'https://en.wikipedia.org', '/wiki/', 'Berlin', 'https://en.wikipedia.org/wiki/' ),
			array( 'https://en.wikipedia.org', '/wiki/page.php?name=$1', 'Berlin', 'https://en.wikipedia.org/wiki/page.php?name=Berlin' ),
			array( 'https://en.wikipedia.org', '/wiki/$1', '', 'https://en.wikipedia.org/wiki/' ),
			array( 'https://en.wikipedia.org', '/wiki/$1', 'Berlin/sub page', 'https://en.wikipedia.org/wiki/Berlin%2Fsub%20page' ),
			array( 'https://en.wikipedia.org', '/wiki/$1', 'Cork (city)', 'https://en.wikipedia.org/wiki/Cork%20%28city%29' ),
		);
	}

	/**
	 * @dataProvider pageUrlProvider
	 */
	public function testGetPagePath( $url, $urlPath, $pageName, $expected ) {
		$site = SitesTable::singleton()->newRow( array(
			'global_key' => 'en',
			'url' => $url,
			'file_path' => '',
			'page_path' => $urlPath,
			'language' => 'en',
		), true );

		$this->assertEquals( $expected, $site->getPagePath( $pageName ) );
	}

	/**
	 * @dataProvider constructorTestProvider
	 */
	public function testGetExtraData( array $fields ) {
		$site = SitesTable::singleton()->newRow( $fields, true );

		$this->assertInternalType( 'array', $site->getExtraData() );
		$this->assertEquals(
			array_key_exists( 'data', $fields ) ? $fields['data'] : array(),
			$site->getExtraData()
		);
	}

}