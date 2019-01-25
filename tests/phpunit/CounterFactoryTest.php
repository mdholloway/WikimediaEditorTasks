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

use MediaWiki\Extension\WikimediaEditorTasks\CounterFactory;
use MediaWiki\Extension\WikimediaEditorTasks\Dao;
use MediaWikiTestCase;

/**
 * @covers \MediaWiki\Extension\WikimediaEditorTasks\Utils
 */
class CounterFactoryTest extends MediaWikiTestCase {

	public function testGetEnabledCounters() {
		$counters = CounterFactory::createAll( TestConstants::COUNTER_CONFIG, Dao::instance() );
		$this->assertEquals( 2, count( $counters ) );

		$decrementingCounter = $counters[0];
		$this->assertTrue( $decrementingCounter instanceof DecrementOnRevertTestCounter );
		$this->assertEquals( 'decrement_on_revert', $decrementingCounter->getKey() );
		$this->assertEquals( 3, $decrementingCounter->getTargetCount() );

		$resettingCounter = $counters[1];
		$this->assertTrue( $resettingCounter instanceof ResetOnRevertTestCounter );
		$this->assertEquals( 'reset_on_revert', $resettingCounter->getKey() );
		$this->assertEquals( 3, $resettingCounter->getTargetCount() );
	}

}
