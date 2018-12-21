<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See thes
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
 * Factory for creating Counter objects based on the extension configuration.
 * Counters definitions contain three keys:
 * * class (required) - the class implementing the counter
 * * counter_key (required) - string to identify the counter in the DB
 * * target_count (optional) - count required to trigger some behavior
 */
class CounterFactory {

	/**
	 * @param array $config array of counter definitions
	 * @param Dao $dao
	 * @return Counter[] array of Counters
	 */
	public static function createAll( $config, $dao ) {
		return array_map( function ( $definition ) use ( $dao ) {
			return CounterFactory::create( $definition, $dao );
		}, $config );
	}

	/**
	 * @param array $definition counter definition
	 * @param Dao $dao
	 * @return Counter
	 */
	public static function create( $definition, $dao ) {
		return new $definition['class'](
			$definition['counter_key'],
			$definition['target_count'],
			$dao
		);
	}

}
