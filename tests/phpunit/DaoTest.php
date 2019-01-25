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

use MediaWiki\Extension\WikimediaEditorTasks\Dao;
use MediaWikiTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\WikimediaEditorTasks\Dao
 */
class DaoTest extends MediaWikiTestCase {

	const KEY = 'test';
	const LANG = 'test';

	/** @var Dao */
	private $dao;

	/** @var int */
	private $userId;

	public function setUp() {
		parent::setUp();
		$this->tablesUsed = array_merge( $this->tablesUsed, [
			'wikimedia_editor_tasks_keys',
			'wikimedia_editor_tasks_counts',
			'wikimedia_editor_tasks_targets_passed'
		] );
		$this->dao = Dao::instance();
		$this->userId = $this::getTestUser()->getUser()->getId();
		$this->dao->registerCounter( self::KEY );
	}

	public function testEmpty() {
		$this->assertEquals( [], $this->dao->getAllCounts( $this->userId ) );
		$this->assertEquals( [], $this->dao->getAllTargetsPassed( $this->userId ) );
	}

	public function testCounts() {
		$this->dao->setCountForKeyAndLang( $this->userId, $this->getCounterId(), self::LANG, 0 );
		$this->dao->incrementCountForKeyAndLang( $this->userId, $this->getCounterId(), self::LANG );
		$this->assertEquals( 1, $this->dao->getCountForKeyAndLang( $this->userId, self::KEY,
			self::LANG ) );
		$this->dao->decrementCountForKeyAndLang( $this->userId, $this->getCounterId(), self::LANG );
		$this->assertEquals( 0, $this->dao->getCountForKeyAndLang( $this->userId, self::KEY,
			self::LANG ) );
	}

	public function testAchievements() {
		$this->assertFalse( $this->dao->getTargetPassed( $this->userId, self::KEY ) );
		$this->dao->setTargetPassed( $this->userId, $this->getCounterId(), self::LANG );
		$this->assertTrue( $this->dao->getTargetPassed( $this->userId, self::KEY ) );
		$this->dao->deleteTargetPassed( $this->userId, $this->getCounterId() );
		$this->assertFalse( $this->dao->getTargetPassed( $this->userId, self::KEY ) );
	}

	private function getCounterId() {
		return $this->dao->getCounterIdForKey( self::KEY );
	}

}
