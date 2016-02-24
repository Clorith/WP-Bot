<?php
define( 'ABSPATH', dirname( __FILE__ ) );

require_once( ABSPATH . '/../config.php' );
require_once( ABSPATH . '/IRC-framework/SmartIRC.php' );

/**
 * Grab dependencies
 */
require_once( ABSPATH . '/doc-bot.php' );


/**
 * Class bot
 *
 * Contains our custom IRC functions
 */
class Bot {
	public $appreciation = array();
	public $tell         = array();
	public $db;

	/**
	 * The class construct prepares our functions and database connections
	 */
	function __construct() {
		/**
		 * Prepare our initial database connection
		 */
		$this->db_connector();

		/**
		 * We replace the comma separated list of appreciative terms with pipes
		 * This is done because we run a bit of regex over it to identify words for consistency
		 */
		$this->appreciation = str_replace( ',', '|', strtolower( APPRECIATION ) );

		$this->prepare_tell_notifications();
	}

	function db_connector() {
		/**
		 * Prepare our database connection
		 */
		$attributes = array(
			PDO::ATTR_PERSISTENT => true,
			PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION
		);
		$this->db   = new PDO( 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS, $attributes );
	}

	function pdo_ping() {
		try {
			$this->db->query( "SELECT 1" );
		} catch ( PDOException $e ) {
			$this->db_connector();
		}
	}

	/**
	 * Function for cleaning up nicknames. Clears out commonly used characters
	 * that are not valid in a nickname but are often used in relation with them
	 *
	 * @param $nick
	 *
	 * @return string
	 */
	function cleanNick( $nick ) {
		return str_replace( array( '@', '%', '+', '~', ':', ',', '<', '>' ), '', $nick );
	}

	function channel_query( &$irc, &$data ) {
		$is_docbot       = false;
		$is_question     = false;
		$is_appreciation = false;

		if ( '?' == substr( trim( $data->message ), - 1 ) ) {
			$is_question = true;
		}

		if ( preg_match( "/(" . $this->appreciation . ")/i", $data->message ) ) {
			$is_appreciation = array();

			$string = explode( " ", $data->message );
			foreach ( $string AS $word ) {
				$word = $this->cleanNick( $word );

				if ( $irc->isJoined( $data->channel, $word ) ) {
					$is_appreciation[] = $word;
				}
			}

			/**
			 * If no users are mentioned in the appreciative message,
			 * there's no reason for us to try and track it
			 */
			if ( empty( $is_appreciation ) ) {
				$is_appreciation = false;
			}
		}

		/**
		 * We look to identify doc-bot references only if we've not already done a successful match
		 */
		if ( ! $is_appreciation && ! $is_question ) {
			/**
			 * If block denoting if the first letter is the doc-bot command trigger
			 */
			if ( '.' == substr( $data->message, 0, 1 ) ) {
				$string  = explode( " ", $data->message );
				$is_nick = $this->cleanNick( array_pop( $string ) );

				/**
				 * If the last word is a user on the channel, this was a reference sent to help a user
				 */
				if ( $irc->isJoined( $data->channel, $is_nick ) ) {
					$is_appreciation = array( $data->nick );
					$is_docbot       = $is_nick;
				}
			}
		}

		/**
		 * Ping the server first to make sure we still have a connection
		 */
		$this->pdo_ping();

		try {
			/**
			 * Insert the log entry
			 */
			$this->db->query( "
			INSERT INTO
				messages (
					userhost,
					nickname,
					message,
					event,
					channel,
					is_question,
					is_docbot,
					is_appreciation,
					time
				)
			VALUES (
				" . $this->db->quote( $data->nick . "!" . $data->ident . "@" . $data->host ) . ",
				" . $this->db->quote( $data->nick ) . ",
				" . $this->db->quote( $data->message ) . ",
				'message',
				" . $this->db->quote( $data->channel ) . ",
				" . $this->db->quote( ( $is_question ? 1 : 0 ) ) . ",
				" . $this->db->quote( ( ! $is_docbot ? null : $is_docbot ) ) . ",
				" . $this->db->quote( ( is_array( $is_appreciation ) ? serialize( $is_appreciation ) : null ) ) . ",
				" . $this->db->quote( date( "Y-m-d H:i:s" ) ) . "
			)
		" );
		} catch ( PDOException $e ) {
			echo 'PDO Exception: ' . $e->getMessage();
		}
	}

	function message_split( $data ) {
		$message_parse = explode( ' ', $data->message, 2 );
		$command = $message_parse[0];
		$message_parse = ( count( $message_parse ) > 1 ? $message_parse[1] : '' );

		$user = $data->nick;

		$message_parse = explode( '>', $message_parse );
		if ( isset( $message_parse[1] ) && ! empty( $message_parse[1] ) ) {
			$send_to = trim( $message_parse[1] );
			$user = $send_to;
		}
		$message = trim( $message_parse[0] );

		$result = (object) array(
				'user'    => $user,
				'message' => $message,
				'command' => $command
		);

		return $result;
	}

	function log_event( $event, &$irc, &$data ) {
		$this->pdo_ping();

		$this->db->query( "
		INSERT INTO
			messages (
				userhost,
				nickname,
				message,
				event,
				channel,
				time
			)
		VALUES (
			" . $this->db->quote( $data->nick . "!" . $data->ident . "@" . $data->host ) . ",
			" . $this->db->quote( $data->nick ) . ",
			" . $this->db->quote( $data->message ) . ",
			" . $this->db->quote( $event ) . ",
			" . $this->db->quote( $data->channel ) . ",
			" . $this->db->quote( date( "Y-m-d H:i:s" ) ) . "
		)
	" );
	}

	function log_kick( &$irc, &$data ) {
		$this->log_event( 'kick', $irc, $data );
	}

	function log_part( &$irc, &$data ) {
		$this->log_event( 'part', $irc, $data );
	}

	function log_quit( &$irc, &$data ) {
		$this->log_event( 'quit', $irc, $data );
	}

	function log_join( &$irc, &$data ) {
		$this->log_event( 'join', $irc, $data );

		$this->tell( $irc, $data );
	}

	function help_cmd( &$irc, &$data ) {
		$message = sprintf( 'For WPBot Help, see %s',
			HELP_URL
		);
		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function prepare_tell_notifications() {
		$this->tell = array();

		$this->pdo_ping();

		try {
			$entries = $this->db->query( "
				SELECT
					t.id,
					t.time,
					t.recipient,
					t.sender,
					t.message
				FROM
					tell t
				WEHERE
					t.told = 0
			" );

			while ( $entry = $entries->fetchObject() ) {
				$this->add_tell_notification( $entry->id, $entry->recipient, $entry->time, $entry->sender, $entry->message );
			}
		} catch( PDOException $e ) {
			echo 'PDO Exception: ' . $e->getMessage();
		}
	}

	function add_tell_notification( $id, $recipient, $time, $sender, $message ) {
		if ( ! isset( $this->tell[ $recipient ] ) ) {
			$this->tell[ $recipient ] = array();
		}

		$this->tell[ $recipient ][] = (object) array(
				'id'      => $id,
				'time'    => $time,
				'sender'  => $sender,
				'message' => $message
		);
	}

	function add_tell( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$this->pdo_ping();

		$time = date( "Y-m-d H:i:s" );

		$this->db->query( "
			INSERT INTO
				tell (
					`time`,
					`recipient`,
					`sender`,
					`message`
				)
			VALUES (
				" . $this->db->quote( $time ) . ",
				" . $this->db->quote( $msg->user ) . ",
				" . $this->db->quote( $data->nick ) . ",
				" . $this->db->quote( $msg->message ) . "
			)
		" );

		$id = $this->db->lastInsertId();

		$this->add_tell_notification( $id, $msg->user, $time, $data->nick, $msg->message );
	}

	function tell( &$irc, &$data ) {
		if ( isset( $this->tell[ $data->nick ] ) ) {
			$unset = array();
			foreach( $this->tell[ $data->nick ] AS $tell ) {
				$message = sprintf(
					'(Tell) %s - %s @ %s: %s',
					$data->nick,
					$tell->sender,
					date( "Y-m-d H:i", strtotime( $tell->time ) ),
					$tell->message
				);

				$unset[] = $tell->id;

				$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
			}

			unset( $this->tell[ $data->nick ] );

			$this->pdo_ping();

			$this->db->query( "
				UPDATE
					tell t
				SET
					t.told = 1
				WHERE
					t.id IN (" . implode( ',', $unset ) . ")
			" );
		}
	}

	function new_user_guidelines( &$irc, &$data ) {
		$message = sprintf(
			'Welcome to #WordPress, %s. Please review our guidelines available at %s, and if you at any time see behavior you feel needs a second pair of eyes, you may utilize the %s command, either in the channel or in a private message to me.',
			$data->nick,
			'https://codex.wordpress.org/IRC/Channel_Guidelines',
			chr(2) . '.ops' . chr(2)
		);

		$irc->message( SMARTIRC_TYPE_NOTICE, $data->nick, $message );
	}

	function request_ops( &$irc, &$data ) {
		// Break out early if there's no Slack API
		if ( ! defined( 'SLACK_API' ) ) {
			return;
		}

		// Get the most recent log event from the channel for use in our Slack message
		$last_entry = $this->db->query( "
			SELECT
				m.id
			FROM
				messages m
			WHERE
				event = 'message'
			ORDER BY
				m.id DESC
			LIMIT 1
		" );
		$last_entry = $last_entry->fetchObject();

		/*
		 * Log the use of the command
		 * This is to avoid abuse of the system and allow us to move in and block abusing users
		 */
		$this->log_event( 'mod_request', $irc, $data );

		$msg = $this->message_split( $data );

		$log_link = sprintf(
			'http://contribot.clorith.net/?date=%s#%d',
			date( "Y-m-d" ),
			$last_entry->id
		);

		$note = ( ! empty( $msg->message ) ? sprintf( ' [%s]', $msg->message ) : '' );

		$simple_message = sprintf(
			'*IRC* assistance requested in <%s|#WordPress>%s - <%s|See logs>',
			'https://webchat.freenode.net/?channels=#wordpress',
			$note,
			$log_link
		);

		$this->send_slack_alert( $simple_message );
	}


	function send_slack_alert( $message ) {
		// Break out early if there's no Slack API
		if ( ! defined( 'SLACK_API' ) ) {
			return;
		}

		$request = array(
			'channel'    => '#forums',
			'username'   => 'WPBot',
			'icon_emoji' => ':hash:',
			'text'       => $message,
		);

		$request = json_encode( $request );

		$slack = curl_init( SLACK_API );
		curl_setopt( $slack, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $slack, CURLOPT_POSTFIELDS, "payload=" . $request );
		curl_setopt( $slack, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $slack, CURLOPT_SSL_VERIFYPEER, false );

		$result = curl_exec( $slack );
		if ( false === $result ) {
			echo "Slack cURL error: " . curl_error( $slack ) . "\n";
			echo $request . "\n";
		}

		curl_close( $slack );
	}

	function send_rich_slack_alert( $message, $log_link, $note = null ) {
		// Break out early if there's no Slack API
		if ( ! defined( 'SLACK_API' ) ) {
			return;
		}

		$logs = $this->get_logs( 5 );
		$logs = array_reverse( $logs );

		$fields = array();
		if ( ! empty( $note ) ) {
			$fields[] = array(
				'title'  => 'Note',
				'value'  => $note,
				'short'  => false
			);
		}

		$request = array(
			'channel'     => '#forums',
			'username'    => 'WPBot',
			'icon_emoji'  => ':hash:',
			'attachments' => array(
				array(
					'fallback'   => $message,
					'color'      => 'warning',
					'pretext'    => $message,
					'title'      => 'Log excerpt - View logs',
					'title_link' => $log_link,
					'text'       => implode( "\n", $logs ),
					'mrkdwn_in'  => array( 'pretext' ),
					'fields'     => $fields
				)
			)
		);

		$request = json_encode( $request );

		$slack = curl_init( SLACK_API );
		curl_setopt( $slack, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $slack, CURLOPT_POSTFIELDS, "payload=" . $request );
		curl_setopt( $slack, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $slack, CURLOPT_SSL_VERIFYPEER, false );

		$result = curl_exec( $slack );
		if ( false === $result ) {
			echo "Slack cURL error: " . curl_error( $slack ) . "\n";
			echo $request . "\n";
		}

		curl_close( $slack );
	}

	function get_logs( $count ) {
		$output = array();

		$entries = $this->db->query( "
			SELECT
				m.id,
				m.nickname,
				m.message,
				m.time
			FROM
				messages m
			WHERE
				event = 'message'
			ORDER BY
				m.id DESC
			LIMIT " . $count . "
		" );
		while( $entry = $entries->fetchObject() ) {
			$output[] = sprintf(
				'[%s] %s: %s',
				date( "H:i:s", strtotime( $entry->time ) ),
				$entry->nickname,
				$entry->message
			);
		}

		return $output;
	}
}

/**
 * Instantiate our bot class and the SmartIRC framework
 */
$bot = new WPBot();
$irc = new Net_SmartIRC();

/**
 * Set connection-wide configurations
 */
$irc->setDebugLevel( SMARTIRC_DEBUG_ALL ); // Set debug mode
$irc->setUseSockets( true ); // We want to use actual sockets, if this is false fsock will be used, which is not as ideal
$irc->setChannelSyncing( true ); // Channel sync allows us to get user details which we use in our logs, this is how we can check if users are in the channel or not

/**
 * Set up hooks for events to trigger on
 */
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '/./', $bot, 'channel_query' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '/^(!|\.)tell\b/', $bot, 'add_tell' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '/^(!|\.)ops/', $bot, 'request_ops' );
$irc->registerActionHandler( SMARTIRC_TYPE_QUERY, '/^(!|\.)ops/', $bot, 'request_ops' );
$irc->registerActionHandler( SMARTIRC_TYPE_ACTION, '/./', $bot, 'channel_query' );
$irc->registerActionHandler( SMARTIRC_TYPE_KICK, '/./', $bot, 'log_kick' );
$irc->registerActionHandler( SMARTIRC_TYPE_PART, '/./', $bot, 'log_part' );
$irc->registerActionHandler( SMARTIRC_TYPE_QUIT, '/./', $bot, 'log_quit' );
$irc->registerActionHandler( SMARTIRC_TYPE_JOIN, '/(.*)/', $bot, 'log_join' );
$irc->registerActionHandler( SMARTIRC_TYPE_JOIN, '/(.*)/', $bot, 'new_user_guidelines' );

/**
 * Generic commands associated purely with WPBot
 */
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)h(elp)?\b', $bot, 'help_cmd' );

/**
 * DocBot class hooks
 */
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)d(eveloper)?\b', $bot, 'developer' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)c(odex)?\b', $bot, 'codex' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)p(lugin)?\b', $bot, 'plugin' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)g(oogle)?\b', $bot, 'google' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)l(mgtfy)?\b', $bot, 'lmgtfy' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)language\b', $bot, 'language' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)count\b', $bot, 'count' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)md5\b', $bot, 'md5' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)vuln\b', $bot, 'wpvulndb' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '^(!|\.)scan\b', $bot, 'sucuri_scan' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '\b#[0-9]+?\b', $bot, 'trac_ticket' );
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '\br[0-9]+?\b', $bot, 'trac_changeset' );

/**
 * DocBot common replies
 */
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '/./', $bot, 'is_predefined_message' );
$irc->registerActionHandler( SMARTIRC_TYPE_QUERY, '/\.predef-reload/', $bot, 'prepare_predefined_messages' );


/**
 * Start the connection to an IRC server
 */
$irc->connect( IRC_NETWORK, IRC_PORT );
$irc->login( BOTNICK, BOTNAME . ' - version ' . BOTVERSION, 0, BOTNICK, BOTPASS );
$irc->join( array( IRC_CHANNELS ) );
$irc->listen();

/**
 * Shut down and clean up once we've disconnected
 */
$irc->disconnect();
