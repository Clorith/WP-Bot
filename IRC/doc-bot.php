<?php

/**
 * Class DocBot
 *
 * Replace some frequently used doc-bot commands if the bot is
 * missing from the channel for whatever reason
 */
class DocBot extends bot {

	function __construct() {
		parent::__construct();
	}

	/**
     * Returns human-readable string for the difference between two dates
     *
     * @param string $start
     * @param string $end
     * @return string
     * @access public
     */
	function diffdate( $start, $end = false ) {
		if ( ! $end ) $end = date( 'Y-m-d H:i:s' );
		$datediff = strtotime( $end ) - strtotime( $start );
		$ago = array( 'years' => floor( $datediff / ( 365 * 60 * 60 * 24 ) ) );
		$ago['months'] 	= floor( ( $datediff - $ago['years'] * 365 * 60 * 60 * 24 ) / ( 30 * 60 * 60 * 24 ) );
		$ago['days']	= floor( $datediff / 86400 );
		$ago['hours']	= floor( ( $datediff - ( $ago['days'] * 86400 ) ) / 3600 );
		$ago['minutes']	= floor( ( $datediff - ( $ago['days'] * 86400 ) - ( $ago['hours'] * 3600 ) ) / 60 );
		$ago['seconds']	= floor( ( $datediff - ( $ago['days'] * 86400 ) - ( $ago['hours'] * 3600 ) - ( $ago['minutes'] * 60 ) ) );

		// Build date diff string with date units that have values only
		$agostr = array();
		foreach ( $ago as $unit => $val ) {
			if ( $val > 0 ) $agostr[] = $val . ' ' . $unit;
		}
		$agostr = implode( ', ', $agostr ) . ' ago';
		$agostr = substr_replace( $agostr, ' and ', strrpos( $agostr, ', ' ), strlen( ', ' ) );

		return $agostr;
	}

	function is_doc_bot( &$irc, $channel ) {
		return $irc->isJoined( $channel, 'doc-bot' );
	}

	function message_split( &$irc, $data ) {
		$message_parse = explode( ' ', $data->message, 2 );
		$command = $message_parse[0];
		$message_parse = @$message_parse[1];

		$user = $data->nick;

		$message_parse = explode( '>', $message_parse );
		if ( isset( $message_parse[1] ) && ! empty( $message_parse[1] ) ) {
			$send_to = trim( $message_parse[1] );
			if ( $irc->isJoined( $data->channel, $send_to ) ) {
				$user = $send_to;
			}
		}
		$message = trim( $message_parse[0] );

		$result = (object) array(
			'user'    => $user,
			'message' => $message,
			'command' => $command
		);

		return $result;
	}

	function in_channel( $username, &$irc ) {
		if ( is_array( $irc->channel ) ) {
			foreach ( $irc->channel as $channel => $channel_obj ) {
				if ( ! empty( $channel_obj->users ) ) {
					foreach ( $channel_obj->users as $user => $user_obj ) {
						if ( $username == $user ) return $channel;
					}
				}
			}
		}
		return false;
	}

	function joined( &$irc, &$data ) {
		$this->check_messages( $irc, $data );
	}

	function nickchange( &$irc, &$data ) {
		$this->check_messages( $irc, $data, $data->message );
	}

	// Function to check and deliver messages for a nick (messages issued from .tell command)
	function check_messages( &$irc, &$data, $nick = false ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		if ( ! $nick ) $nick = $data->nick;
		$channel = $data->channel;

		// Query the messages for any mail events (from .tell command)
		$this->pdo_ping();
		try {
			$statement = $this->db->prepare( "SELECT * FROM messages WHERE nickname = :nickname AND event = 'mail'" );
			$statement->execute( array( ':nickname' => $nick ) );
			$tells = $statement->fetchAll( PDO::FETCH_OBJ );

			// Process result and set seen string
			if ( ! empty( $tells ) ) {
				if ( empty( $channel ) ) $channel = $tells[0]->channel;
				// Deliver the messages
				foreach ( $tells as $tell ) {
					$irc->message( SMARTIRC_TYPE_CHANNEL, $nick, '[' . date( 'Y-m-d H:i:s' ) . '] ' . $tell->nickname . ': ' . $tell->message );
				}

				// Delete the messages we just delivered
				try {
					$delete = $this->db->prepare( "DELETE FROM messages WHERE nickname = :nickname AND event = 'mail'" );
					$delete = $delete->execute( array( ':nickname' => $nick ) );
				} catch ( PDOException $e ) {
					echo 'PDO Exception: ' . $e->getMessage();
				}

				// Set the chat message on join
				$message = sprintf(
					'%s: %s',
					$nick,
					'You received ' . count( $tells ) . ' message' . ( count( $tells ) > 1 ? 's' : '' ) . ' while you were away'
				);
			}
		} catch ( PDOException $e ) {
			echo 'PDO Exception: ' . $e->getMessage();
		}

		if ( ! empty( $message ) ) {
			$irc->message( SMARTIRC_TYPE_CHANNEL, $channel, $message );
		}
	}

	function google_result( $string ) {
		$search = 'http://www.google.com/search?q=%s&btnI';

		$string = urlencode( $string );
		$search = str_replace( '%s', $string, $search );

		$headers = get_headers( $search, true );

		return $headers['Location'][1];
	}

	function developer( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );
		$string = trim( $msg->message );

		$search = 'https://developer.wordpress.org/?s=%s';
		$lookup = false;
		if ( stristr( $string, '-f' ) ) {
			$lookup = true;
			$string = str_replace( '-f', '', $string );
			$search .= '&post_type%5B%5D=wp-parser-function';
		}
		if ( stristr( $string, '-h' ) ) {
			$lookup = true;
			$string = str_replace( '-h', '', $string );
			$search .= '&post_type%5B%5D=wp-parser-hook';
		}
		if ( stristr( $string, '-c' ) ) {
			$lookup = true;
			$string = str_replace( '-c', '', $string );
			$search .= '&post_type%5B%5D=wp-parser-class';
		}
		if ( stristr( $string, '-m' ) ) {
			$lookup = true;
			$string = str_replace( '-m', '', $string );
			$search .= '&post_type%5B%5D=wp-parser-method';
		}

		if ( ! $lookup ) {
			$search .= '&post_type%5B%5D=wp-parser-function';
		}

		$string = trim( $string );
		$string = str_replace( array( ' ' ), array( '+' ), $string );
		$search = str_replace( '%s', $string , $search );

		$headers = get_headers( $search, true );

		if ( ! isset( $headers['Location'] ) || empty( $headers['Location'] ) ) {
			$message = sprintf(
				'%s: No exact match found for \'%s\' - See the full set of results at %s',
				$msg->user,
				$string,
				$search
			);
		}
		else {
			$message = sprintf(
				'%s: %s',
				$msg->user,
				$headers['Location']
			);
		}

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function codex( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$google = $this->google_result( $msg->message . ' site:codex.wordpress.org' );

		if ( preg_match( '/codex\.wordpress\.org\/(.{2,5}:).+?/i', $google, $language ) ) {
			$google = str_ireplace( $language[1], '', $google );
		}

		$message = sprintf(
			'%s: %s',
			$msg->user,
			$google
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function plugin( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$url    = 'https://wordpress.org/plugins/' . str_replace( ' ', '-', $msg->message );
		$search = 'https://wordpress.org/plugins/search.php?q=';

		if ( preg_match( "/-l\b/i", $msg->message ) ) {
			$msg->message = trim( str_replace( '-l', '', $msg->message ) );
			$message = sprintf(
				'%s: See a list of plugins relating to %s at %s',
				$msg->user,
				$msg->message,
				$search . str_replace( ' ', '+', $msg->message )
			);

			$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
			return;
		}

		$first_pass = get_headers( $url, true );

		if ( isset( $first_pass['Status'] ) && ! stristr( $first_pass['Status'], '404 Not Found' ) ) {
			$message = sprintf(
				'%s: %s',
				$msg->user,
				$url
			);
			$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
			return;
		}

		$page = file_get_contents( $search . str_replace( ' ', '+', $msg->message ) );
		preg_match_all( "/plugin-card-top.+?column-name.+?<a.+?href=\"(.+?)\">(.+?)</msi", $page, $matches );

		if ( ! empty( $matches[1] ) ) {
			$message = sprintf(
				'%s: %s - %s',
				$msg->user,
				$matches[2][0],
				$matches[1][0]
			);
		}
		else {
			$message = sprintf(
				'%s: No results found',
				$msg->user
			);
		}

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function google( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$google = $this->google_result( $msg->message );

		$message = sprintf(
			'%s: Google result for %s - %s',
			$msg->user,
			$msg->message,
			$google
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function lmgtfy( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$query = urlencode( $msg->message );

		$message = sprintf(
			'%s: http://lmgtfy.com/?q=%s',
			$msg->user,
			$query
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function pastebin( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: Please post your source code with a service like %s or similar for us to look at, and avoid pasting large pieces of code to the channel.',
			$msg->user,
			'http://gist.github.com'
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function do_the_first( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irtc, $data );

		$message = sprintf(
			'%s: Please attempt to disable all plugins, and use one of the default (Twenty*) themes. If the problem goes away, enable them one by one to identify the source of your troubles.',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function language( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: Please help us keep %s a family friendly room, and avoid using foul language.',
			$msg->user,
			$data->channel
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function moving( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: If you rename the WordPress directory on your server, switch ports or change the hostname http://codex.wordpress.org/Moving_WordPress applies',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function inspector( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: Please use the built-in Developer Tools of your browser to fix problems with your website. Right click your page and pick “Inspect Element” (Cr, FF, Op) or press F12-button (IE) to track down CSS problems. Use the console to see JavaScript bugs.',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function wordpresscom( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: For support with your blog or site hosted on WordPress.com, please see http://en.support.wordpress.com/contact/',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function make_blog( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: To get started contributing to WordPress, have a look at the Make Blogs over at https://make.wordpress.org',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function count( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}

		$counter = file_get_contents( 'https://wordpress.org/download/counter/?ajaxupdate=1' );

		$message = sprintf(
			'The latest version of WordPress has been downloaded %s times',
			$counter
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function underscores( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: Check out http://underscores.me/ - Underscores is a starter theme meant to be the base of your next awesome theme, try it out!',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function lucky_seven( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: Setting file permissions to 777 is inherently insecure, please read http://codex.wordpress.org/Changing_File_Permissions',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function ftp( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s:  If WordPress keeps asking you for FTP credentials see http://codex.wordpress.org/Updating_WordPress#Automatic_Update and http://codex.wordpress.org/Editing_wp-config.php#WordPress_Upgrade_Constants as well as http://s.sivel.net/wpfsmethod for more information about the file system method selection and http://v007.me/4 for forcing direct file system writes',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function hacked( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: http://codex.wordpress.org/FAQ_My_site_was_hacked , and stop trying to patch up your hacked site. Reinstall or restore your backups. And read http://codex.wordpress.org/Hardening_WordPress',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function next( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: Another happy customer leaves the building :)',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function related( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: It\'s not a WordPress question just because the user uses WordPress',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function css( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: Please /join #css for questions about CSS',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function html( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: Please /join #html for questions about HTML',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function javascript( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: Please /join #javascript for questions about javascript',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function php( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: Please /join ##php for questions about PHP',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function possible( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: Everything is "possible" - If you have questions about how to do something specific, then feel free to ask',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function md5( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: %s',
			$msg->user,
			md5( $msg->message )
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function ask( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: Don\'t ask to ask, just ask :)',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function donthack( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );

		$message = sprintf(
			'%s: http://codex.wordpress.org/images/b/b3/donthack.jpg',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function seen( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );
		$seenstr = '';

		// Check if the user is being silly
		if ( $msg->message == $msg->user ) $seenstr = "That's you... hilarious...";

		// Check if the user is currently in the channel
		if ( empty( $seenstr) ) {
			$channel = $this->in_channel( $msg->message, $irc );
			if ( $channel ) $seenstr = $msg->message . ' is currently in ' . $channel;
		}

		// Select the last row in the messages for the queried user
		if ( empty( $seenstr ) ) {
			$this->pdo_ping();
			try {
				$statement = $this->db->prepare( "SELECT * FROM messages WHERE nickname = :nickname AND event IN ('quit', 'part', 'nickchange') ORDER BY id DESC LIMIT 1" );
				$statement->execute( array( ':nickname' => $msg->message ) );
				$seen = $statement->fetch( PDO::FETCH_OBJ );

				// Process result and set seen string
				if ( is_object( $seen ) ) {
					$seenstr = $msg->message . ' was last seen ' . $this->diffdate( $seen->time );
					if ( $seen->event == 'nickchange' ) {
						$seenstr .= ' changing their nick to ' . $seen->message;
					} else {
						$seenstr .= ' ' . $seen->event . 'ing ' . $seen->channel . ' with ' . ( empty( $seen->message ) ? 'no ' . $seen->event . ' message' : 'the message: ' . $seen->message );
					}
				}

			} catch ( PDOException $e ) {
				echo 'PDO Exception: ' . $e->getMessage();
			}
			if ( empty( $seenstr ) ) $seenstr = "I've never seen {$msg->message} before.";
		}

		// Send seen result
		$message = sprintf(
			'%s: %s',
			$msg->user,
			$seenstr
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function tell( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$msg = $this->message_split( $irc, $data );
		$message = '';

		$tell = explode( ' ', $msg->message, 2 );
		if ( count( $tell ) >= 2 ) {
			$nick = $tell[0];
			$message = $tell[1];
			$in_channel = $this->in_channel( $nick, $irc );
			if ( $nick == $msg->user ) {
				$message = "That's you... hilarious...";
			} else if ( $in_channel ) {
				$message = $nick . ' is currently in ' . $in_channel . ' - how about you tell them yourself?';
			} else {
				// Check for previously queued messages
				$this->pdo_ping();
				try {
					$statement = $this->db->prepare( "SELECT * FROM messages WHERE nickname = :nickname AND event = 'mail'" );
					$statement->execute( array( ':nickname' => $nick ) );
					$tells = $statement->fetchAll( PDO::FETCH_OBJ );

					// Process result and set seen string
					if ( is_array( $tells ) && count( $tells ) >= 5 ) {
						$message = "There are too many messages in queue for $nick";
					} else {
						$newdata = new stdClass;
						$newdata->nick = $nick;
						$newdata->ident = $data->ident;
						$newdata->host = $data->host;
						$newdata->channel = $data->channel;
						$newdata->message = $message;
						$this->log_event( 'mail', $irc, $newdata );
						$message = "Your message has been queued for $nick";
					}
				} catch ( PDOException $e ) {
					echo 'PDO Exception: ' . $e->getMessage();
				}
			}
		} else {
			$message = "I cannot deliver a blank message";
		}

		// Send seen result
		$message = sprintf(
			'%s: %s',
			$msg->user,
			$message
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	//numnumnum
}
