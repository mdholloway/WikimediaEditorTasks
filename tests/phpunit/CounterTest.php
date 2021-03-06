<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

namespace MediaWiki\Extension\WikimediaEditorTasks\Test;

use MediaWiki\Extension\WikimediaEditorTasks\Counter;
use MediaWiki\Extension\WikimediaEditorTasks\Dao;
use MediaWiki\Extension\WikimediaEditorTasks\CounterFactory;
use MediaWikiTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\WikimediaEditorTasks\Counter
 * @covers \MediaWiki\Extension\WikimediaEditorTasks\CounterFactory
 */
class CounterTest extends MediaWikiTestCase {

	const LANG = 'test';

	/** @var Counter[] */
	private $counters;

	/** @var int */
	private $userId;

	public function setUp() {
		parent::setUp();
		$this->tablesUsed = array_merge( $this->tablesUsed, [
			'wikimedia_editor_tasks_keys',
			'wikimedia_editor_tasks_counts',
			'wikimedia_editor_tasks_targets_passed'
		] );

		$this->counters = CounterFactory::createAll( [
			[
				"class" => "MediaWiki\\Extension\\WikimediaEditorTasks\\Test\\"
						   . "DecrementOnRevertTestCounter",
				"counter_key" => "decrement_on_revert",
				"target_count" => 3
			],
			[
				"class" => "MediaWiki\\Extension\\WikimediaEditorTasks\\Test\\"
						   . "ResetOnRevertTestCounter",
				"counter_key" => "reset_on_revert",
				"target_count" => 3
			]
		], Dao::instance() );

		foreach ( $this->counters as $counter ) {
			$counter->register();
		}

		$this->userId = $this::getTestUser()->getUser()->getId();
	}

	public function testInitialState() {
		foreach ( $this->counters as $counter ) {
			$this->assertTrue( $counter->isRegistered() );
			$this->assertEquals( 0, $counter->getCountForLang( $this->userId, self::LANG ) );
			$this->assertFalse( $counter->isTargetPassed( $this->userId ) );
		}
	}

	public function testIncrementDecrement() {
		foreach ( $this->counters as $counter ) {
			$counter->incrementForLang( $this->userId, self::LANG );
			$this->assertEquals( 1, $counter->getCountForLang( $this->userId, self::LANG ) );
			$counter->decrementForLang( $this->userId, self::LANG );
			$this->assertEquals( 0, $counter->getCountForLang( $this->userId, self::LANG ) );
		}
	}

	public function testReset() {
		foreach ( $this->counters as $counter ) {
			$counter->incrementForLang( $this->userId, self::LANG );
			$this->assertEquals( 1, $counter->getCountForLang( $this->userId, self::LANG ) );
			$counter->reset( $this->userId );
			$this->assertEquals( 0, $counter->getCountForLang( $this->userId, self::LANG ) );
		}
	}

	public function testOnEditSuccess() {
		foreach ( $this->counters as $counter ) {
			$counter->onEditSuccess( $this->userId, self::LANG );
			$this->assertEquals( 1, $counter->getCountForLang( $this->userId, self::LANG ) );
		}
	}

	public function testOnRevert() {
		foreach ( $this->counters as $counter ) {
			$counter->onEditSuccess( $this->userId, self::LANG );
			$counter->onEditSuccess( $this->userId, self::LANG );
			$this->assertEquals( 2, $counter->getCountForLang( $this->userId, self::LANG ) );
			$counter->onRevert( $this->userId, self::LANG );
		}
		$decrementOnRevertCounter = $this->counters[0];
		$this->assertEquals( 1, $decrementOnRevertCounter->getCountForLang( $this->userId, self::LANG ) );
		$resetOnRevertCounter = $this->counters[1];
		$this->assertEquals( 0, $resetOnRevertCounter->getCountForLang( $this->userId, self::LANG ) );
	}

	public function testSetTargetPassed() {
		foreach ( $this->counters as $counter ) {
			$counter->setTargetPassed( $this->userId );
			$this->assertTrue( $counter->isTargetPassed( $this->userId ) );
		}
	}

	public function testOnEditSuccessTargetPassed() {
		foreach ( $this->counters as $counter ) {
			foreach ( range( 1, $counter->getTargetCount() ) as $i ) {
				$counter->onEditSuccess( $this->userId, self::LANG );
			}
			$this->assertTrue( $counter->isTargetPassed( $this->userId ) );
		}
	}

}
