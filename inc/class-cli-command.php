<?php

namespace Pantheon_Sessions;

use WP_CLI;

/**
 * Interact with Pantheon Sessions
 */
class CLI_Command extends \WP_CLI_Command {

	/**
	 * List all registered sessions.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count, ids. Default: table
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		global $wpdb;

		if ( ! PANTHEON_SESSIONS_ENABLED ) {
			WP_CLI::error( "Pantheon Sessions is currently disabled." );
		}

		$defaults = array(
			'format'      => 'table',
			'fields'      => 'session_id,user_id,datetime,ip_address,data',
			);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$sessions = array();
		foreach( new \WP_CLI\Iterators\Query( "SELECT * FROM {$wpdb->pantheon_sessions} ORDER BY datetime DESC" ) as $row ) {
			$sessions[] = $row;
		}

		\WP_CLI\Utils\Format_Items( $assoc_args['format'], $sessions, $assoc_args['fields'] );

	}

	/**
	 * Delete one or more sessions.
	 *
	 * [<session-id>...]
	 * : One or more session IDs
	 *
	 * [--date=<date>]
	 * : Delete all sessions before <date>
	 *
	 * [--all]
	 * : Delete all sessions.
	 *
	 * @subcommand delete
	 */
	public function delete( $args, $assoc_args ) {
		global $wpdb;
		$args = array();

		if ( ! PANTHEON_SESSIONS_ENABLED ) {
			WP_CLI::error( "Pantheon Sessions is currently disabled." );
		}

		if ( isset( $assoc_args['date'] ) ) {
			$date = strtotime( $assoc_args['date'] );
			if ( $date === -1 ) {
				if ( ( PHP_MAJOR_VERSION < 5 ) ||
				     ( ( PHP_MAJOR_VERSION == 5 ) && ( PHP_MINOR_VERSION < 1 ) ) ) {
					// Do this to find pre-5.1.0 invalid date response
					$date = false;
				}
			}
			if ( $date === false ) {
				WP_CLI::warning( "Invalid date specified." );
			} else {
				$date = date( 'Y-m-d H:i:s', $date );
				$args = $wpdb->get_col( "SELECT session_id FROM {$wpdb->pantheon_sessions} WHERE `datetime` < '{$date}'" );
				if ( empty( $args ) ) {
					WP_CLI::warning( "No sessions to delete." );
				}
			}
		}

		if ( isset( $assoc_args['all'] ) ) {
			$args = $wpdb->get_col( "SELECT session_id FROM {$wpdb->pantheon_sessions}" );
			if ( empty( $args ) ) {
				WP_CLI::warning( "No sessions to delete." );
			}
		}

		foreach( $args as $session_id ) {
			$session = \Pantheon_Sessions\Session::get_by_sid( $session_id );
			if ( $session ) {
				$session->destroy();
				WP_CLI::log( sprintf( "Session destroyed: %s", $session_id ) );
			} else {
				WP_CLI::warning( sprintf( "Session doesn't exist: %s", $session_id ) );
			}
		}

	}

}

\WP_CLI::add_command( 'pantheon session', '\Pantheon_Sessions\CLI_Command' );
