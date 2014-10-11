<?php
define( 'ABSPATH', dirname( __FILE__ ) );

require_once( ABSPATH . '/../config.php' );
require_once( ABSPATH . '/IRC-framework/SmartIRC.php' );

/**
 * Class bot
 *
 * Contains our custom IRC functions
 */
class bot {
	private $appreciation = array();
	private $db;

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
	}

	function db_connector() {
		/**
		 * Prepare our database connection
		 */
		$attributes = array(
			PDO::ATTR_PERSISTENT => true,
			PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION
		);
		$this->db = new PDO( 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS, $attributes );
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

		if ( '?' == substr( trim( $data->message ), -1 ) ) {
			$is_question = true;
		}

		if ( preg_match( "/(" . $this->appreciation . ")/i", $data->message ) ) {
			$is_appreciation = array();

			$string = explode( " ", $data->message );
			foreach( $string AS $word ) {
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
				$string = explode( " ", $data->message );
				$is_nick = $this->cleanNick( array_pop( $string ) );

				/**
				 * If the last word is a user on the channel, this was a reference sent to help a user
				 */
				if ( $irc->isJoined( $data->channel, $is_nick ) ) {
					$is_appreciation = array( $data->nick );
					$is_docbot = $is_nick;
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

	function log_event( $event, &$irc, &$data ) {
		$this->pdo_ping();

		$this->db->query( "
			INSERT INTO
				messages (
					userhost,
					nickname,
					message,
					event,
					time
				)
			VALUES (
				" . $this->db->quote( $data->nick . "!" . $data->ident . "@" . $data->host ) . ",
				" . $this->db->quote( $data->nick ) . ",
				" . $this->db->quote( $data->message ) . ",
				" . $this->db->quote( $event ) . ",
				" . $this->db->quote( date( "Y-m-d H:i:s" ) ) . "
			)
		" );
	}

	function log_kick( &$irc, &$data ) { $this->log_event( 'kick', $irc, $data ); }
	function log_part( &$irc, &$data ) { $this->log_event( 'part', $irc, $data ); }
	function log_quit( &$irc, &$data ) { $this->log_event( 'quit', $irc, $data ); }
	function log_join( &$irc, &$data ) { $this->log_event( 'join', $irc, $data ); }
}

/**
 * Instantiate our bot class and the SmartIRC framework
 */
$bot = new bot();
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
$irc->registerActionHandler( SMARTIRC_TYPE_ACTION, '/./', $bot, 'channel_query' );
$irc->registerActionHandler( SMARTIRC_TYPE_KICK, '/./', $bot, 'log_kick' );
$irc->registerActionHandler( SMARTIRC_TYPE_PART, '/./', $bot, 'log_part' );
$irc->registerActionHandler( SMARTIRC_TYPE_QUIT, '/./', $bot, 'log_quit' );
$irc->registerActionHandler( SMARTIRC_TYPE_JOIN, '/./', $bot, 'log_join' );

/**
 * Start the connection to an IRC server
 */
$irc->connect( IRC_NETWORK, IRC_PORT );
$irc->login( BOTNICK, BOTNAME . ' - version ' . BOTVERSION );
$irc->join( array( IRC_CHANNELS ) );
$irc->listen();

/**
 * Shut down and clean up once we've disconnected
 */
$irc->disconnect();