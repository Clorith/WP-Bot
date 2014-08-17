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

	/**
	 * The class construct prepares our functions and database connections
	 */
	function __construct() {
		/**
		 * If the database file doesn't exist it indicates this is a new setup
		 */
		if ( ! file_exists( DBFILE ) ) {
			$new_db = '
				CREATE TABLE messages (
				    id              INTEGER         PRIMARY KEY AUTOINCREMENT,
				    userhost        VARCHAR( 255 ),
				    nickname        VARCHAR( 255 ),
				    message         TEXT,
				    is_question     BOOLEAN,
				    is_appreciation TEXT,
				    is_docbot       TEXT,
				    time            DATETIME
				)
			';
		}

		/**
		 * Let's make sure the path to the folder holding our database exists
		 */
		if ( ! is_dir( DBPATH ) ) {
			mkdir( DBPATH, NULL, true );
		}

		/**
		 * Prepare our database connection
		 */
		$this->db = new SQLite3( DBFILE );

		/**
		 * If we detected this as a new database, create our log table
		 */
		if ( isset( $new_db ) && ! empty( $new_db ) ) {
			$this->db->query( $new_db );
		}

		/**
		 * We replace the comma separated list of appreciative terms with pipes
		 * This is done because we run a bit of regex over it to identify words for consistency
		 */
		$this->appreciation = str_replace( ',', '|', strtolower( APPRECIATION ) );
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

		if ( stristr( trim( $data->message ), '?' ) ) {
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

		$this->db->query( "
			INSERT INTO
				messages (
					userhost,
					nickname,
					message,
					is_question,
					is_docbot,
					is_appreciation,
					time
				)
			VALUES (
				'" . $this->db->escapeString( $data->nick . "!" . $data->ident . "@" . $data->host ) . "',
				'" . $this->db->escapeString( $data->nick ) . "',
				'" . $this->db->escapeString( $data->message ) . "',
				'" . ( $is_question ? 1 : 0 ) . "',
				'" . $this->db->escapeString( ( ! $is_docbot ? NULL : $is_docbot ) ) . "',
				'" . $this->db->escapeString( ( is_array( $is_appreciation ) ? serialize( $is_appreciation ) : NULL ) ) . "',
				'" . $this->db->escapeString( date( "Y-m-d H:i:s" ) ) . "'
			)
		" );
	}
}

/**
 * Instantiate our bot class and the SmartIRC framework
 */
$bot = new bot();
$irc = new Net_SmartIRC();

/**
 * Set connection-wide configurations
 */
$irc->setDebugLevel( SMARTIRC_DEBUG_NONE ); // Disable debug output
$irc->setUseSockets( true ); // We want to use actual sockets, if this is false fsock will be used, which is not as ideal
$irc->setChannelSyncing( true ); // Channel sync allows us to get user details which we use in our logs, this is how we can check if users are in the channel or not

/**
 * Set up hooks for events to trigger on
 */
$irc->registerActionHandler( SMARTIRC_TYPE_CHANNEL, '.*', $bot, 'channel_query' );

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