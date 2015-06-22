<?php

/**
 * Class DocBot
 *
 * Replace some frequently used doc-bot commands if the bot is
 * missing from the channel for whatever reason
 */
class DocBot {
	function is_doc_bot( &$irc, $channel ) {
		return $irc->isJoined( $channel, 'doc-bot' );
	}
	function message_split( &$irc, $data ) {
		$message_parse = explode( ' ', $data->message, 2 );
		$command = $message_parse[0];
		$message_parse = $message_parse[1];

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
	function google_result( $string ) {
		$search = 'http://www.google.com/search?q=%s&btnI';

		$string = urlencode( $string );
		$search = str_replace( '%s', $string , $search );

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
			'%s: We try to keep this channel PG13, please keep the language appropriate for all ages.',
			$msg->user
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
			'%s: Check out http://underscores.me/ - Underscores is a starter theme ment to be the base of your next awesome theme, try it out!',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}
}
