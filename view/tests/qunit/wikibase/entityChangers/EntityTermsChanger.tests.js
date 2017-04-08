/**
 * @license GPL-2.0+
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 */
( function( sinon, wb, $ ) {
	'use strict';

	QUnit.module( 'wikibase.entityChangers.EntityTermsChanger', QUnit.newMwEnvironment() );

	var EntityTermsChanger = wb.entityChangers.EntityTermsChanger;
	var Fingerprint = wb.datamodel.Fingerprint;
	var TermMap = wb.datamodel.TermMap;
	var Term = wb.datamodel.Term;
	var Item = wb.datamodel.Item;

	var REVISION_ID = 9;

	QUnit.test( 'is a function', function( assert ) {
		assert.expect( 1 );
		assert.equal(
			typeof EntityTermsChanger,
			'function',
			'is a function.'
		);
	} );

	QUnit.test( 'is a constructor', function( assert ) {
		assert.expect( 1 );
		assert.ok( new EntityTermsChanger() instanceof EntityTermsChanger );
	} );

	QUnit.test( 'save performs correct API calls for new label', function( assert ) {
		assert.expect( 2 );
		var done = assert.async();
		var api = {
			setLabel: sinon.spy( function () {
				var result = apiResponseForRevision( REVISION_ID )
					.withLabel( 'some-lang', 'some label' );
				return $.Deferred().resolve( result ).promise();
			} )
		};
		var entityTermsChanger = new EntityTermsChanger(
			api,
			stubRevisionStoreForRevision( REVISION_ID ),
			new Item( 'Q1' )
		);

		entityTermsChanger.save(
			new Fingerprint( new TermMap( {
				language: new Term( 'some-lang', 'some label' )
			} ) ),
			new Fingerprint()
		).then( function () {
			assert.ok( api.setLabel.calledOnce );
			sinon.assert.calledWith(
				api.setLabel,
				'Q1',
				REVISION_ID,
				'some label',
				'some-lang'
			);
		} )
			.fail( failOnError( assert ) )
			.always( done );
	} );

	QUnit.test( 'save performs correct API calls for changed label', function( assert ) {
		assert.expect( 2 );
		var done = assert.async();
		var api = {
			setLabel: sinon.spy( function() {
				var result = apiResponseForRevision( REVISION_ID )
					.withLabel( 'some-lang', 'new label' );

				return $.Deferred().resolve( result ).promise();
			} )
		};
		var entityTermsChanger = new EntityTermsChanger(
			api,
			stubRevisionStoreForRevision( REVISION_ID ),
			new Item( 'Q1' )
		);

		entityTermsChanger.save(
			new Fingerprint( new TermMap( {
				language: new Term( 'some-lang', 'new label' )
			} ) ),
			new Fingerprint( new TermMap( {
				language: new Term( 'some-lang', 'old label' )
			} ) )
		).then( function () {
			assert.ok( api.setLabel.calledOnce );
			sinon.assert.calledWith( api.setLabel, 'Q1', REVISION_ID, 'new label', 'some-lang' );
		} )
			.fail( failOnError( assert ) )
			.always( done );
	} );

	QUnit.test( 'save performs correct API calls for removed label', function( assert ) {
		assert.expect( 2 );
		var done = assert.async();
		var api = {
			setLabel: sinon.spy( function() {
				return $.Deferred().resolve( {
					entity: {
						lastrevid: REVISION_ID,
						labels: {
							language: {}
						}
					}
				} ).promise();
			} )
		};
		var entityTermsChanger = new EntityTermsChanger(
			api,
			stubRevisionStoreForRevision( REVISION_ID ),
			new Item( 'Q1' )
		);

		entityTermsChanger.save(
			new Fingerprint(),
			new Fingerprint( new TermMap( {
				language: new Term( 'language', 'old label' )
			} ) )
		).then( function () {
			assert.ok( api.setLabel.calledOnce );
			sinon.assert.calledWith( api.setLabel, 'Q1', REVISION_ID, '', 'language' );
		} ).fail( failOnError( assert ) ).always( done );
	} );

	QUnit.test( 'save correctly handles API response for labels', function( assert ) {
		assert.expect( 1 );
		var api = {
			setLabel: sinon.spy( function() {
				return $.Deferred().resolve( {
					entity: {
						labels: {
							language: {
								value: 'normalized label'
							},
							lastrevid: 'lastrevid'
						}
					}
				} ).promise();
			} )
		};
		var entityTermsChanger = new EntityTermsChanger(
			api,
			stubRevisionStoreForRevision( REVISION_ID ),
			new Item( 'Q1' )
		);

		return entityTermsChanger.save(
			new Fingerprint( new TermMap( {
				language: new Term( 'language', 'label' )
			} ) ),
			new Fingerprint()
		).done( function( savedFingerprint ) {
			assert.equal( savedFingerprint.getLabelFor( 'language' ).getText(), 'normalized label' );
		} );
	} );

	QUnit.test( 'save correctly handles API failures for labels', function( assert ) {
		var done = assert.async();
		assert.expect( 4 );
		var api = {
			setLabel: sinon.spy( function() {
				return $.Deferred().reject( 'errorCode', { error: { code: 'errorCode' } } ).promise();
			} )
		};
		var entityTermsChanger = new EntityTermsChanger(
			api,
			stubRevisionStoreForRevision( REVISION_ID ),
			new Item( 'Q1' )
		);

		entityTermsChanger.save(
			new Fingerprint( new TermMap( {
				language: new Term( 'language', 'label' )
			} ) ),
			new Fingerprint()
		).done( function( savedFingerprint ) {
			assert.ok( false, 'save should have failed' );
		} )
		.fail( function( error ) {
			assert.ok( error instanceof wb.api.RepoApiError, 'save did not fail with a RepoApiError' );
			assert.equal( error.code, 'errorCode' );
			assert.equal( error.context.type, 'label' );
			assert.ok( error.context.value.equals( new Term( 'language', 'label' ) ) );
		} )
		.always( done );
	} );

	QUnit.test( 'save performs correct API calls for new description', function( assert ) {
		assert.expect( 2 );
		var done = assert.async();
		var revisionId = 9;
		var api = {
			setDescription: sinon.spy( function() {
				var result = {
					entity: {
						lastrevid: revisionId,
						descriptions: {
							'some-lang': { value: 'description' }
						}
					}
				};
				return $.Deferred().resolve( result ).promise();
			} )
		};
		var entityTermsChanger = new EntityTermsChanger(
			api,
			stubRevisionStoreForRevision( revisionId ),
			new Item( 'Q1' )
		);

		entityTermsChanger.save(
			new Fingerprint(
				null,
				new TermMap( {
					language: new Term( 'some-lang', 'description' )
				} )
			),
			new Fingerprint()
		).then( function () {
			assert.ok( api.setDescription.calledOnce );
			sinon.assert.calledWith(
				api.setDescription,
				'Q1',
				revisionId,
				'description',
				'some-lang'
			);
		} ).fail( failOnError( assert ) ).always( done );
	} );

	QUnit.test( 'save performs correct API calls for changed description', function( assert ) {
		assert.expect( 2 );
		var done = assert.async();

		var api = {
			setDescription: sinon.spy( function() {
				var apiResponse = apiResponseForRevision( REVISION_ID )
					.withDescription( 'some-lang', 'new description' );
				return $.Deferred().resolve( apiResponse ).promise();
			} )
		};
		var entityTermsChanger = new EntityTermsChanger(
			api,
			stubRevisionStoreForRevision( REVISION_ID ),
			new Item( 'Q1' )
		);

		entityTermsChanger.save(
			new Fingerprint(
				null,
				new TermMap( {
					language: new Term( 'some-lang', 'new description' )
				} )
			),
			new Fingerprint(
				null,
				new TermMap( {
					language: new Term( 'some-lang', 'old description' )
				} )
			)
		).then( function () {
			assert.ok( api.setDescription.calledOnce );
			sinon.assert.calledWith(
				api.setDescription,
				'Q1',
				REVISION_ID,
				'new description',
				'some-lang'
			);
		} ).fail( failOnError( assert ) ).always( done );
	} );

	QUnit.test( 'save performs correct API calls for removed description', function( assert ) {
		assert.expect( 2 );
		var done = assert.async();
		var api = {
			setDescription: sinon.spy( function() {
				return $.Deferred().resolve( {
					entity: {
						descriptions: {
							language: {}
						}
					}
				} ).promise();
			} )
		};
		var entityTermsChanger = new EntityTermsChanger(
			api,
			stubRevisionStoreForRevision( REVISION_ID ),
			new Item( 'Q1' )
		);

		entityTermsChanger.save(
			new Fingerprint(),
			new Fingerprint(
				null,
				new TermMap( {
					language: new Term( 'language', 'old description' )
				} )
			)
		).then( function () {
			assert.ok( api.setDescription.calledOnce );
			sinon.assert.calledWith( api.setDescription, 'Q1', REVISION_ID, '', 'language' );
		} ).fail( failOnError( assert ) ).always( done );
	} );

	QUnit.test( 'save correctly handles API response for descriptions', function( assert ) {
		assert.expect( 1 );
		var api = {
			setDescription: sinon.spy( function() {
				return $.Deferred().resolve( {
					entity: {
						descriptions: {
							language: {
								value: 'normalized description'
							},
							lastrevid: 'lastrevid'
						}
					}
				} ).promise();
			} )
		};
		var entityTermsChanger = new EntityTermsChanger(
			api,
			stubRevisionStoreForRevision( REVISION_ID ),
			new Item( 'Q1' )
		);

		var done = assert.async();

		entityTermsChanger.save(
			new Fingerprint(
				null,
				new TermMap( {
					language: new Term( 'language', 'description' )
				} )
			),
			new Fingerprint()
		).done( function( savedFingerprint ) {
			assert.equal( savedFingerprint.getDescriptionFor( 'language' ).getText(), 'normalized description' );
		} ).fail( failOnError( assert ) ).always( done );
	} );

	QUnit.test( 'save correctly handles API failures for descriptions', function( assert ) {
		assert.expect( 4 );
		var api = {
			setDescription: sinon.spy( function() {
				return $.Deferred().reject( 'errorCode', { error: { code: 'errorCode' } } ).promise();
			} )
		};
		var entityTermsChanger = new EntityTermsChanger(
			api,
			stubRevisionStoreForRevision( REVISION_ID ),
			new Item( 'Q1' )
		);

		var done = assert.async();

		entityTermsChanger.save(
			new Fingerprint(
				null,
				new TermMap( {
					language: new Term( 'language', 'description' )
				} )
			),
			new Fingerprint()
		).done( function( savedFingerprint ) {
			assert.ok( false, 'save should have failed' );
		} )
		.fail( function( error ) {
			assert.ok( error instanceof wb.api.RepoApiError, 'save did not fail with a RepoApiError' );
			assert.equal( error.code, 'errorCode' );
			assert.equal( error.context.type, 'description' );
			assert.ok( error.context.value.equals( new Term( 'language', 'description' ) ) );
		} ).always( done );
	} );

	QUnit.test( 'save performs correct API calls for new aliases', function( assert ) {
		assert.expect( 2 );
		var revisionId = 9;
		var done = assert.async();
		var api = {
			setAliases: sinon.spy( function() {
				var result = apiResponseForRevision( revisionId )
					.withAliases( 'language', ['alias'] );
				return $.Deferred().resolve( result ).promise();
			} )
		};
		var entityTermsChanger = new EntityTermsChanger(
			api,
			stubRevisionStoreForRevision( revisionId ),
			new Item( 'Q1' )
		);

		entityTermsChanger.save(
			new Fingerprint(
				null,
				null,
				new wb.datamodel.MultiTermMap( {
					language: new wb.datamodel.MultiTerm( 'language', [ 'alias' ] )
				} )
			),
			new Fingerprint()
		).then( function () {
			assert.ok( api.setAliases.calledOnce );
			sinon.assert.calledWith( api.setAliases, 'Q1', revisionId, ['alias'], [], 'language' );
		} ).fail( failOnError( assert ) ).always( done );
	} );

	QUnit.test( 'save performs correct API calls for changed aliases', function( assert ) {
		assert.expect( 2 );
		var done = assert.async();
		var api = {
			setAliases: sinon.spy( function() {
				var result = apiResponseForRevision( REVISION_ID )
					.withAliases( 'language', ['new alias'] );
				return $.Deferred().resolve( result ).promise();
			} )
		};
		var entityTermsChanger = new EntityTermsChanger(
			api,
			stubRevisionStoreForRevision( REVISION_ID ),
			new Item( 'Q1' )
		);

		entityTermsChanger.save(
			new Fingerprint(
				null,
				null,
				new wb.datamodel.MultiTermMap( {
					language: new wb.datamodel.MultiTerm( 'language', [ 'new alias' ] )
				} )
			),
			new Fingerprint(
				null,
				null,
				new wb.datamodel.MultiTermMap( {
					language: new wb.datamodel.MultiTerm( 'language', [ 'old alias' ] )
				} )
			)
		).then( function () {
			assert.ok( api.setAliases.calledOnce );
			sinon.assert.calledWith(
				api.setAliases,
				'Q1',
				REVISION_ID,
				['new alias'],
				['old alias'],
				'language'
			);
		} ).fail( failOnError( assert ) ).always( done );
	} );

	QUnit.test( 'save performs correct API calls for removed aliases', function( assert ) {
		assert.expect( 2 );
		var done = assert.async();
		var api = {
			setAliases: sinon.spy( function() {
				return $.Deferred().resolve( apiResponseForRevision( REVISION_ID ) ).promise();
			} )
		};
		var entityTermsChanger = new EntityTermsChanger(
			api,
			stubRevisionStoreForRevision( REVISION_ID ),
			new Item( 'Q1' )
		);

		entityTermsChanger.save(
			new Fingerprint(),
			new Fingerprint(
				null,
				null,
				new wb.datamodel.MultiTermMap( {
					language: new wb.datamodel.MultiTerm( 'language', [ 'old alias' ] )
				} )
			)
		).then( function () {
			assert.ok( api.setAliases.calledOnce );
			sinon.assert.calledWith(
				api.setAliases,
				'Q1',
				REVISION_ID,
				[],
				['old alias'],
				'language'
			);
		} ).fail( failOnError( assert ) ).always( done );
	} );

	QUnit.test( 'save correctly handles API response for aliases', function( assert ) {
		assert.expect( 1 );
		var done = assert.async();
		var api = {
			setAliases: sinon.spy( function() {
				var result = apiResponseForRevision( 'lastrevid' )
					.withAliases( 'language', ['normalized alias'] );
				return $.Deferred().resolve( result ).promise();
			} )
		};
		var entityTermsChanger = new EntityTermsChanger(
			api,
			stubRevisionStoreForRevision( 'lastrevid' ),
			new Item( 'Q1' )
		);

		return entityTermsChanger.save(
			new Fingerprint(
				null,
				null,
				new wb.datamodel.MultiTermMap( {
					language: new wb.datamodel.MultiTerm( 'language', [ 'alias' ] )
				} )
			),
			new Fingerprint()
		).done( function( savedFingerprint ) {
			assert.deepEqual( savedFingerprint.getAliasesFor( 'language' ).getTexts(), [ 'normalized alias' ] );
		} ).fail( failOnError( assert ) ).always( done );
	} );

	QUnit.test( 'save correctly handles API failures for aliases', function( assert ) {
		assert.expect( 4 );
		var api = {
			setAliases: sinon.spy( function() {
				return $.Deferred().reject( 'errorCode', { error: { code: 'errorCode' } } ).promise();
			} )
		};
		var entityTermsChanger = new EntityTermsChanger(
			api,
			stubRevisionStoreForRevision( REVISION_ID ),
			new Item( 'Q1' )
		);

		var done = assert.async();

		entityTermsChanger.save(
			new Fingerprint(
				null,
				null,
				new wb.datamodel.MultiTermMap( {
					language: new wb.datamodel.MultiTerm( 'language', [ 'alias' ] )
				} )
			),
			new Fingerprint()
		).done( function( savedFingerprint ) {
			assert.ok( false, 'save should have failed' );
		} )
		.fail( function( error ) {
			assert.ok( error instanceof wb.api.RepoApiError, 'save did not fail with a RepoApiError' );
			assert.equal( error.code, 'errorCode' );
			assert.equal( error.context.type, 'aliases' );
			assert.ok( error.context.value.equals( new wb.datamodel.MultiTerm( 'language', [ 'alias' ] ) ) );
		} ).always( done );
	} );

	function failOnError( assert ) {
		return function ( error ) {
			assert.ok( false, error.stack || error );
		};
	}

	function stubRevisionStoreForRevision( revisionId ) {
		return {
			getLabelRevision: function () {
				return revisionId;
			},
			setLabelRevision: function () {
			},
			getDescriptionRevision: function () {
				return revisionId;
			},
			setDescriptionRevision: function () {
			},
			getAliasesRevision: function () {
				return revisionId;
			},
			setAliasesRevision: function () {
			}
		};
	}

	function apiResponseForRevision( revisionId ) {
		function ApiResponse( revisionId ) {
			this.entity = {
				lastrevid: revisionId
			};
		}

		ApiResponse.prototype.withLabel = function ( language, value ) {
			if ( !this.entity.labels ) {
				this.entity.labels = {};
			}
			this.entity.labels[language] = { value: value };
			return this;
		};

		ApiResponse.prototype.withDescription = function ( language, value ) {
			if ( !this.entity.descriptions ) {
				this.entity.descriptions = {};
			}
			this.entity.descriptions[language] = { value: value };
			return this;
		};

		ApiResponse.prototype.withAliases = function ( language, aliases ) {
			if ( !this.entity.aliases ) {
				this.entity.aliases = {};
			}
			this.entity.aliases[language] = aliases.map( function ( alias ) {
				return { value: alias };
			} );
			return this;
		};

		return new ApiResponse( revisionId );
	}

} )( sinon, wikibase, jQuery );
