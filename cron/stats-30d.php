<?php
define( 'ABSPATH', dirname( __FILE__ ) );

require_once( ABSPATH . '/../config.php' );

$attributes = array(
	PDO::ATTR_PERSISTENT => true,
	PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION
);
$db = new PDO( 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS, $attributes );

$nicknames = array();

/**
 * Start by truncating the existing data
 */
$db->query( "TRUNCATE TABLE `stats_30d`" );

$messages = $db->query( "
	SELECT
		m.nickname,
		m.message,
		m.is_question,
		m.is_appreciation,
		m.is_docbot,
		s.nickname AS stats
	FROM
		messages m
	LEFT JOIN
		stats_30d s
			ON ( s.nickname = m.nickname )
	WHERE
		m.event = 'message'
	AND
		m.time BETWEEN " . $db->quote( date( "Y-m-d 00:00:00", strtotime( '-1 month', time() ) ) ) . " AND " . $db->quote( date( "Y-m-d h:i:s", time() ) ) . "
" );

while ( $message = $messages->fetchObject() ) {
	$message->nickname = trim( $message->nickname );
	if ( ! isset( $nicknames[ $message->nickname ] ) ) {
		$nicknames[ $message->nickname ] = array(
			'messages'     => 0,
			'questions'    => 0,
			'appreciation' => 0,
			'docbot'       => 0,
			'has_stats'    => ( ! empty( $message->stats ) )
		);
	}

	$nicknames[ $message->nickname ]['messages']++;
	if ( '1' == $message->is_question ) {
		$nicknames[ $message->nickname ]['questions']++;
	}
	if ( ! empty( $message->is_appreciation ) ) {
		$nicknames[ $message->nickname ]['appreciation']++;
	}
	if ( ! empty( $message->is_docbot ) ) {
		$nicknames[ $message->nickname ]['docbot']++;
	}
}

foreach( $nicknames AS $nickname => $stats ) {
	if ( ! $stats['has_stats'] ) {
		try {
			$db->query( "
			INSERT INTO
				stats_30d (
					nickname,
					messages,
					appreciation,
					questions,
					docbot
				)
			VALUES (
				" . $db->quote( $nickname ) . ",
				" . $db->quote( $stats['messages'] ) . ",
				" . $db->quote( $stats['appreciation'] ) . ",
				" . $db->quote( $stats['questions'] ) . ",
				" . $db->quote( $stats['docbot'] ) . "
			)
		" );
		} catch ( PDOException $e ) {
			$stats['has_stats'] = true;
		}
	}

	if ( $stats['has_stats'] ) {
		$db->query( "
			UPDATE
				stats_30d
			SET
				messages = " . $db->quote( $stats['messages'] ) . ",
				appreciation = " . $db->quote( $stats['appreciation'] ) . ",
				questions = " . $db->quote( $stats['questions'] ) . ",
				docbot = " . $db->quote( $stats['docbot'] ) . "
			WHERE
				nickname = " . $db->quote( $nickname ) . "
			LIMIT 1
		" );
	}
	else {

	}
}