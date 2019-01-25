<?php

namespace MediaWiki\Extension\WikimediaEditorTasks\Maintenance;

use Maintenance;
use MediaWiki\Extension\WikimediaEditorTasks\CounterFactory;
use MediaWiki\Extension\WikimediaEditorTasks\Dao;
use MediaWiki\Extension\WikimediaEditorTasks\Utils;

/**
 * Describe me
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class RegisterCounters extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Registers enabled counters in the counter key table";
		$this->requireExtension( "WikimediaEditorTasks" );
	}

	public function execute() {
		foreach ( CounterFactory::createAll(
			Utils::getEnabledCounterConfig(),
			Dao::instance()
		) as $counter ) {
			$counter->register();
		}
	}

}

$maintClass = 'MediaWiki\Extension\WikimediaEditorTasks\Maintenance\RegisterCounters';
require_once RUN_MAINTENANCE_IF_MAIN;
