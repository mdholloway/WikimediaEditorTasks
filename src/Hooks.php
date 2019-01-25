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

use DatabaseUpdater;
use Revision;
use User;
use WikiPage;

/**
 * Hooks for WikimediaEditorTasks extension
 */
class Hooks {

	/**
	 * Handler for PageContentSaveComplete hook
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentSaveComplete
	 *
	 * @param WikiPage &$wikiPage modified WikiPage
	 * @param User &$user User who edited
	 * @param Content $content New article text
	 * @param string $summary Edit summary
	 * @param bool $minoredit Minor edit or not
	 * @param bool $watchthis Watch this article?
	 * @param string $sectionanchor Section that was edited
	 * @param int &$flags Edit flags
	 * @param Revision $revision Revision that was created
	 * @param Status &$status
	 * @param int $baseRevId
	 * @param int $undidRevId
	 *
	 * @return bool true in all cases
	 */
	public static function onPageContentSaveComplete(
		&$wikiPage,
		&$user,
		$content,
		$summary,
		$minoredit,
		$watchthis,
		$sectionanchor,
		&$flags,
		$revision,
		&$status,
		$baseRevId,
		$undidRevId = 0
	) {
		if ( !$revision ) {
			return true;
		}

		// unless status is "good" (not only ok, also no warnings or errors), we
		// probably shouldn't process it at all (e.g. null edits)
		if ( !$status->isGood() ) {
			return true;
		}

		if ( $user && $user->isLoggedIn() ) {
			// We need to check the underlying request headers to determine if this is an app edit
			global $wgRequest;

			foreach ( self::getCounters() as $counter ) {
				$counter->onEditSuccess( Utils::getCentralId( $user ), $wgRequest );
			}
		}

		if ( !$undidRevId ) {
			return true;
		}

		$undidRev = Revision::newFromId( $undidRevId );
		if ( !$undidRev ) {
			return;
		}
		$undidUserId = $undidRev->getUser();
		if ( !$undidUserId ) {
			return;
		}
		if ( $undidRev->getTitle()->equals( $wikiPage->getTitle() ) ) {
			$undidUser = User::newFromId( $undidUserId );
			foreach ( self::getCounters() as $counter ) {
				$counter->onRevert( Utils::getCentralId( $undidUser ), $undidRevId );
			}
		}

		return true;
	}

	/**
	 * Handler for ArticleRollbackComplete hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleRollbackComplete
	 *
	 * @param WikiPage $wikiPage The article that was edited
	 * @param User $agent The user who did the rollback
	 * @param Revision $newRevision The revision the page was reverted back to
	 * @param Revision $oldRevision The revision of the top edit that was reverted
	 *
	 * @return bool true in all cases
	 */
	public static function onRollbackComplete( WikiPage $wikiPage, $agent, $newRevision,
											   $oldRevision ) {
		$victimId = $oldRevision->getUser();
		if (
			 // Ignore anonymous users and null rollbacks
			$victimId && !$oldRevision->getContent()->equals( $newRevision->getContent() )
		) {
			$victim = User::newFromId( $victimId );
			foreach ( self::getCounters() as $counter ) {
				$counter->onRevert( Utils::getCentralId( $victim ), $oldRevision->getId() );
			}
		}
		return true;
	}

	/**
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$baseDir = dirname( __DIR__ );
		$updater->addExtensionTable( 'wikimedia_editor_tasks_keys', "$baseDir/sql/keys.sql" );
		$updater->addExtensionTable( 'wikimedia_editor_tasks_counts', "$baseDir/sql/counts.sql" );
		$updater->addExtensionTable( 'wikimedia_editor_tasks_targets_passed',
			"$baseDir/sql/targets_passed.sql" );
		return true;
	}

	private static function getCounters() {
		return CounterFactory::createAll( Utils::getEnabledCounterConfig(), Dao::instance() );
	}

}
