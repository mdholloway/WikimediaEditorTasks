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

/**
 * Base counter class containing most of the logic for interacting with the DAO.  Subclasses must
 * implement onEditSuccess and onRevert methods containing any custom filtering logic according to
 * the counts they are meant to maintain (e.g., in-app description edits).  It is expected that
 * subclasses will call this class' DAO interaction methods (e.g., incrementForLang) after any
 * required filtering.
 */
abstract class Counter {

	/** @var Dao */
	private $dao;

	/** @var string */
	private $key;

	/** @var int */
	private $targetCount;

	/**
	 * @param string $key edit counter key
	 * @param int|null $targetCount target count for the counter (if any)
	 * @param Dao $dao
	 */
	public function __construct( $key, $targetCount, Dao $dao ) {
		$this->key = $key;
		$this->targetCount = $targetCount;
		$this->dao = $dao;
	}

	/**
	 * Specifies the action to take when a successful edit is made.
	 * E.g., increment a counter if the edit is an in-app Wikidata description edit.
	 * @param int $centralId central ID user who edited
	 * @param Request $request the request object
	 */
	abstract public function onEditSuccess( $centralId, $request );

	/**
	 * Specifies the action to take when a revert is performed.
	 * E.g., decrement or reset an editor's counter if the reverted edit is an in-app Wikidata
	 *  description edit.
	 * Note: this is called specifically in response to undo and rollback actions, although in
	 *  principle this class is agnostic with respect to the definition used.
	 * @param int $centralId central ID of the user who was reverted
	 * @param int $revId ID of the reverted request
	 */
	abstract public function onRevert( $centralId, $revId );

	/**
	 * Register this counter in the wikimedia_editor_tasks_keys table
	 * @return bool true if no exception was thrown
	 */
	public function register() {
		return $this->dao->registerCounter( $this->key );
	}

	public function isRegistered() {
		return $this->dao->isCounterRegistered( $this->key );
	}

	/**
	 * Get the string used to identify the counter in the DB.
	 * Defined for each counter in the extension configuration.
	 * @return string $key
	 */
	public function getKey() {
		return $this->key;
	}

	/**
	 * Get the target count for this counter, if any.
	 * Example: user must make 50 qualifying edits to unlock feature X.
	 * Defined for each counter in the extension configuration.
	 * @return int|null $targetCount
	 */
	public function getTargetCount() {
		return $this->targetCount;
	}

	/**
	 * Gets the numeric ID for the counter from the key-ID table.
	 * @return int counter ID from DB
	 */
	public function getCounterId() {
		return $this->dao->getCounterIdForKey( $this->key );
	}

	/**
	 * Get count for lang for user
	 * @param int $centralId central ID of the user
	 * @param string $lang language code
	 * @return int|bool value of counter, or false if row does not exist
	 */
	public function getCountForLang( $centralId, $lang ) {
		$count = $this->dao->getCountForKeyAndLang( $centralId, $this->key, $lang );
		if ( $count ) {
			return (int)$count;
		}
		return false;
	}

	/**
	 * Set count for lang for user
	 * @param int $centralId
	 * @param string $lang language code
	 * @param int $count value to set
	 * @return bool true if no exception was thrown
	 */
	public function setCountForLang( $centralId, $lang, $count ) {
		$keyId = $this->getCounterId();
		return $this->dao->setCountForKeyAndLang( $centralId, $keyId, $lang, $count );
	}

	/**
	 * Increment count for lang and user
	 * @param int $centralId central ID of the user
	 * @param string $lang language code
	 */
	public function incrementForLang( $centralId, $lang ) {
		$keyId = $this->getCounterId();

		if ( !$this->getCountForLang( $centralId, $lang ) ) {
			$this->setCountForLang( $centralId, $lang, 0 );
		}

		$this->dao->incrementCountForKeyAndLang( $centralId, $keyId, $lang );

		$this->conditionallySetTargetPassed( $centralId );
	}

	/**
	 * Decrement count for user
	 * @param int $centralId central ID of the user
	 * @param string $lang language code
	 */
	public function decrementForLang( $centralId, $lang ) {
		$keyId = $this->getCounterId();
		$this->dao->decrementCountForKeyAndLang( $centralId, $keyId, $lang );
	}

	/**
	 * Reset count for user
	 * @param int $centralId central ID of the user
	 */
	public function reset( $centralId ) {
		$keyId = $this->getCounterId();
		$this->dao->deleteAllCountsForKey( $centralId, $keyId );
	}

	/**
	 * Get whether the user has passed the target count for this counter (if any)
	 * @param int $centralId central ID of the user
	 * @return bool whether the user passed the target
	 */
	public function isTargetPassed( $centralId ) {
		return $this->dao->getTargetPassed( $centralId, $this->key );
	}

	/**
	 * Set the target passed for a user
	 * @param int $centralId central ID of the user
	 * @param int $delay delay to apply before passing the target takes effect
	 * @return bool true if the operation completed successfully
	 */
	public function setTargetPassed( $centralId, $delay = 0 ) {
		$keyId = $this->getCounterId();
		return $this->dao->setTargetPassed( $centralId, $keyId, $delay );
	}

	/**
	 * Get the tag summary for a revision ID.
	 * @param int $revId revision ID
	 * @return string tag summary
	 */
	public function getTagSummary( $revId ) {
		return $this->dao->getTagSummary( $revId );
	}

	/**
	 * Mark the target passed for this counter if the total of all per-language counts is greater
	 * than or equal to the target count.
	 * @param int $centralId central ID of this user
	 */
	private function conditionallySetTargetPassed( $centralId ) {
		if ( $this->targetCount !== null && !$this->isTargetPassed( $centralId ) ) {
			$allCounts = array_values( $this->dao->getAllCountsForKey( $centralId, $this->key ) );
			if ( array_sum( $allCounts ) >= $this->targetCount ) {
				$this->setTargetPassed( $centralId );
			}
		}
	}
}
