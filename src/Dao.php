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
namespace MediaWiki\Extension\WikimediaEditorTasks;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\DBConnRef;

class Dao {

	/** @var DBConnRef */
	private $dbw;

	/** @var DBConnRef */
	private $dbr;

	/**
	 * Dao constructor.
	 * @param DBConnRef $dbw handle to DB_MASTER for writes
	 * @param DBConnRef $dbr handle to DB_REPLICA for reads
	 */
	public function __construct( $dbw, $dbr ) {
		$this->dbw = $dbw;
		$this->dbr = $dbr;
	}

	/**
	 * Get a Dao instance.
	 * @return Dao
	 */
	public static function instance() {
		$services = MediaWikiServices::getInstance();
		return new Dao(
			Utils::getDB( DB_MASTER, $services ),
			Utils::getDB( DB_REPLICA, $services )
		);
	}

	/**
	 * Register a counter in the wikimedia_editor_tasks_keys table for $key
	 * @param string $key counter key
	 * @return bool true if no exception was thrown
	 */
	public function registerCounter( $key ) {
		return $this->dbw->upsert(
			'wikimedia_editor_tasks_keys',
			[ 'wet_key' => $key ],
			[ 'wet_id' ],
			[ 'wet_key' => $key ],
			__METHOD__
		);
	}

	/**
	 * Test if counter $key is registered
	 * @param string $key counter key
	 * @return bool whether the counter is registered
	 */
	public function isCounterRegistered( $key ) {
		return (bool)$this->dbr->selectRowCount(
			'wikimedia_editor_tasks_keys',
			'*',
			[ 'wet_key' => $key ],
			__METHOD__
		);
	}

	/**
	 * Get counter ID for key string
	 * @param string $key counter key
	 * @return int counter ID
	 */
	public function getCounterIdForKey( $key ) {
		return $this->dbr->selectField(
			'wikimedia_editor_tasks_keys',
			'wet_id',
			[ 'wet_key' => $key ],
			__METHOD__
		);
	}

	/**
	 * Get all stored counts by lang for the user.
	 * @param int $centralId central user ID
	 * @return array all counts for all langs for all keys
	 */
	public function getAllCounts( $centralId ) {
		$wrapper = $this->dbr->select(
			[ 'wikimedia_editor_tasks_counts', 'wikimedia_editor_tasks_keys' ],
			[ 'wet_key', 'wetc_lang', 'wetc_count' ],
			[ 'wetc_user' => $centralId ],
			__METHOD__,
			[],
			[
				'wikimedia_editor_tasks_keys' => [
					'LEFT JOIN',
					'wet_id=wetc_key_id',
				],
			]
		);
		$result = [];
		foreach ( $wrapper as $row ) {
			if ( !$result['wet_key'] ) {
				$result['wet_key'] = [];
			}
			$result[$row->wet_key][$row->wetc_lang] = $row->wetc_count;
		}
		return $result;
	}

	/**
	 * Get all counts by lang for a specific key for a user.
	 * @param int $centralId central user ID
	 * @param string $key counter key
	 * @return array counts for all langs for the specified key
	 */
	public function getAllCountsForKey( $centralId, $key ) {
		$wrapper = $this->dbr->select(
			[ 'wikimedia_editor_tasks_counts', 'wikimedia_editor_tasks_keys' ],
			[ 'wetc_lang', 'wetc_count' ],
			[
				'wetc_user' => $centralId,
				'wet_key' => $key,
			],
			__METHOD__,
			[],
			[
				'wikimedia_editor_tasks_keys' => [
					'LEFT JOIN',
					'wet_id=wetc_key_id',
				],
			]
		);
		$result = [];
		foreach ( $wrapper as $row ) {
			$result[$row->wetc_lang] = $row->wetc_count;
		}
		return $result;
	}

	/**
	 * Get a single count by key and lang for a user.
	 * @param int $centralId central user ID
	 * @param string $key counter key
	 * @param string $lang language code
	 * @return int|bool count for the specified key, or false if not found
	 */
	public function getCountForKeyAndLang( $centralId, $key, $lang ) {
		return $this->dbr->selectField(
			[ 'wikimedia_editor_tasks_counts', 'wikimedia_editor_tasks_keys' ],
			'wetc_count',
			[
				'wetc_user' => $centralId,
				'wet_key' => $key,
				'wetc_lang' => $lang,
			],
			__METHOD__,
			[],
			[
				'wikimedia_editor_tasks_keys' => [
					'LEFT JOIN',
					'wet_id=wetc_key_id',
				],
			]
		);
	}

	/**
	 * Get a single count by key and lang for a user.
	 * @param int $centralId central user ID
	 * @param int $keyId ID for counter key
	 * @return bool true if no exception was thrown
	 */
	public function deleteAllCountsForKey( $centralId, $keyId ) {
		return $this->dbw->delete(
			'wikimedia_editor_tasks_counts',
			[
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
			],
			__METHOD__
		);
	}

	/**
	 * Increment a user's count for a key and language.
	 * @param int $centralId central user ID
	 * @param int $keyId
	 * @param string $lang language code for this count
	 * @return bool true if no exception was thrown
	 */
	public function incrementCountForKeyAndLang( $centralId, $keyId, $lang ) {
		return $this->dbw->update(
			'wikimedia_editor_tasks_counts',
			[ 'wetc_count = wetc_count + 1' ],
			[
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
				'wetc_lang' => $lang,
			],
			__METHOD__
		);
	}

	/**
	 * Decrement a user's count for a key and language.
	 * @param int $centralId central user ID
	 * @param int $keyId
	 * @param string $lang language code for this count
	 * @return bool true if no exception was thrown
	 */
	public function decrementCountForKeyAndLang( $centralId, $keyId, $lang ) {
		return $this->dbw->update(
			'wikimedia_editor_tasks_counts',
			[ 'wetc_count = wetc_count - 1' ],
			[
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
				'wetc_lang' => $lang,
			],
			__METHOD__
		);
	}

	/**
	 * Set the count for a given counter.
	 * @param int $centralId central user ID
	 * @param int $keyId counter key ID
	 * @param string $lang language code for this count
	 * @param int $count new count
	 * @return bool true if no exception was thrown
	 */
	public function setCountForKeyAndLang( $centralId, $keyId, $lang, $count ) {
		return $this->dbw->upsert(
			'wikimedia_editor_tasks_counts',
			[
				'wetc_user' => $centralId,
				'wetc_key_id' => $keyId,
				'wetc_lang' => $lang,
				'wetc_count' => $count,
			],
			[ 'wetc_user', 'wetc_key_id', 'wetc_lang' ],
			[ 'wetc_count' => $count ],
			__METHOD__
		);
	}

	/**
	 * Get list of all counters for which the user has reached the target count.
	 * @param int $centralId central user ID
	 * @return string[] list of counters with target met
	 */
	public function getAllTargetsPassed( $centralId ) {
		return $this->dbr->selectFieldValues(
			[ 'wikimedia_editor_tasks_targets_passed', 'wikimedia_editor_tasks_keys' ],
			'wet_key',
			[ 'wettp_user' => $centralId ],
			__METHOD__,
			[],
			[
				'wikimedia_editor_tasks_keys' => [
					'LEFT JOIN',
					'wet_id=wettp_key_id',
				],
			]
		);
	}

	/**
	 * Get whether the user has passed the target count for a single counter.
	 * @param int $centralId central user ID
	 * @param string $key counter key
	 * @return bool true if there is a target and the user passed it
	 */
	public function getTargetPassed( $centralId, $key ) {
		return (bool)$this->dbr->selectRowCount(
			[ 'wikimedia_editor_tasks_targets_passed', 'wikimedia_editor_tasks_keys' ],
			'*',
			[
				'wettp_user' => $centralId,
				'wet_key' => $key,
			],
			__METHOD__,
			[],
			[
				'wikimedia_editor_tasks_keys' => [
					'LEFT JOIN',
					'wet_id=wettp_key_id',
				],
			]
		);
	}

	/**
	 * Add a row to configurable_counters_targets to mark the target passed
	 * @param int $centralId central user ID
	 * @param int $keyId counter key ID
	 * @param int $delay time, in ms, to delay the effects of passing the target from taking
	 * 	effect
	 * @return bool true if the operation completed successfully
	 */
	public function setTargetPassed( $centralId, $keyId, $delay = 0 ) {
		return $this->dbw->insert(
			'wikimedia_editor_tasks_targets_passed',
			[
				'wettp_user' => $centralId,
				'wettp_key_id' => $keyId,
				'wettp_effective_time' => $this->dbw->timestamp( time() + $delay ),
			],
			__METHOD__
		);
	}

	/**
	 * Delete a record of the user having passed a target.
	 * @param int $centralId central user ID
	 * @param int $keyId counter key ID
	 * @return bool true if the operation completed successfully
	 */
	public function deleteTargetPassed( $centralId, $keyId ) {
		return $this->dbw->delete(
			'wikimedia_editor_tasks_targets_passed',
			[
				'wettp_user' => $centralId,
				'wettp_key_id' => $keyId,
			],
			__METHOD__
		);
	}

}
