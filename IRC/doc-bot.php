<?php

/**
 * Class DocBot
 *
 * Replace some frequently used doc-bot commands if the bot is
 * missing from the channel for whatever reason
 */
class DocBot {

	function __construct() {

	}

	function message_split( $data ) {
		$message_parse = explode( ' ', $data->message, 2 );
		$command = $message_parse[0];
		$message_parse = $message_parse[1];

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
	function google_result( $string ) {
		$search = 'http://www.google.com/search?q=%s&btnI';

		$string = urlencode( $string );
		$search = str_replace( '%s', $string , $search );

		$headers = get_headers( $search, true );
		return $headers['Location'][1];
	}

	function developer( &$irc, &$data ) {
		$msg = $this->message_split( $data );
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
		$msg = $this->message_split( $data );

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
		$msg = $this->message_split( $data );

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
		$msg = $this->message_split( $data );

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
		$msg = $this->message_split( $data );

		$query = urlencode( $msg->message );

		$message = sprintf(
			'%s: http://lmgtfy.com/?q=%s',
			$msg->user,
			$query
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function pastebin( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: Please post your source code with a service like %s or similar for us to look at, and avoid pasting large pieces of code to the channel.',
			$msg->user,
			'http://gist.github.com'
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function do_the_first( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: Please attempt to disable all plugins, and use one of the default (Twenty*) themes. If the problem goes away, enable them one by one to identify the source of your troubles.',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function language( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: Please help us keep %s a family friendly room, and avoid using foul language.',
			$msg->user,
			$data->channel
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function moving( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: If you rename the WordPress directory on your server, switch ports or change the hostname http://codex.wordpress.org/Moving_WordPress applies',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function inspector( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: Please use the built-in Developer Tools of your browser to fix problems with your website. Right click your page and pick “Inspect Element” (Cr, FF, Op) or press F12-button (IE) to track down CSS problems. Use the console to see JavaScript bugs.',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function wordpresscom( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: For support with your blog or site hosted on WordPress.com, please see http://en.support.wordpress.com/contact/',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function make_blog( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: To get started contributing to WordPress, have a look at the Make Blogs over at https://make.wordpress.org',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function count( &$irc, &$data ) {
		$counter = file_get_contents( 'https://wordpress.org/download/counter/?ajaxupdate=1' );

		$message = sprintf(
			'The latest version of WordPress has been downloaded %s times',
			$counter
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function underscores( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: Check out http://underscores.me/ - Underscores is a starter theme meant to be the base of your next awesome theme, try it out!',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function lucky_seven( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: Setting file permissions to 777 is inherently insecure, please read http://codex.wordpress.org/Changing_File_Permissions',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function ftp( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s:  If WordPress keeps asking you for FTP credentials see http://codex.wordpress.org/Updating_WordPress#Automatic_Update and http://codex.wordpress.org/Editing_wp-config.php#WordPress_Upgrade_Constants as well as http://s.sivel.net/wpfsmethod for more information about the file system method selection and http://v007.me/4 for forcing direct file system writes',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function hacked( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: http://codex.wordpress.org/FAQ_My_site_was_hacked , and stop trying to patch up your hacked site. Reinstall or restore your backups. And read http://codex.wordpress.org/Hardening_WordPress',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function next( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: Another happy customer leaves the building :)',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function related( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: It\'s not a WordPress question just because the user uses WordPress',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function css( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: Please /join #css for questions about CSS',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function html( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: Please /join #html for questions about HTML',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function javascript( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: Please /join #javascript for questions about javascript',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function php( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: Please /join ##php for questions about PHP',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function possible( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: Everything is "possible" - If you have questions about how to do something specific, then feel free to ask',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function pages( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: "Pages" in WordPress are innocuous. There are the proper terms for each:  WordPress pages (those are made in the dashboard under add new page) Site Pages (those are whatever exists on the front end of your site but have no WordPress Page such as archive pages, the 404, etc.) and Page Templates (php files you can apply to a WordPress page)',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function md5( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: %s',
			$msg->user,
			md5( $msg->message )
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function ask( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: Don\'t ask to ask, just ask :)',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function donthack( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$message = sprintf(
			'%s: http://codex.wordpress.org/images/b/b3/donthack.jpg',
			$msg->user
		);

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function trac_ticket( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		preg_match_all( '/#([0-9]+?)\b/si', $msg->message, $tickets );

		foreach( $tickets[1] AS $ticket ) {
			$url = sprintf( 'https://core.trac.wordpress.org/ticket/%d', $ticket );

			$message = sprintf(
				'%s: %s',
				$msg->user,
				$url
			);

			$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
		}
	}

	function trac_changeset( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		preg_match_all( '/r([0-9]+?)\b/si', $msg->message, $changes );

		foreach( $changes[1] AS $change ) {
			$url = sprintf( 'https://core.trac.wordpress.org/changeset/%d', $change );

			$message = sprintf(
				'%s: %s',
				$msg->user,
				$url
			);

			$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
		}
	}

	function wpvulndb( &$irc, &$data ) {
		$msg = $this->message_split( $data );

		$api = file_get_contents( 'https://wpvulndb.com/api/v2/plugins/' . $msg->message );
		$api = json_decode( $api );

		if ( isset( $api->{ $msg->message } ) ) {
			$entity = $api->{ $msg->message };
		}

		if ( ! isset( $entity ) || empty( $entity->vulnerabilities ) ) {
			$message = sprintf(
				'%s: %s',
				$msg->user,
				'There are no known vulnerabilities for this plugin'
			);
		}
		else {
			$latest = end( $entity->vulnerabilities );

			$message = sprintf(
				'%s: %s',
				$msg->user,
				sprintf(
					'%s: %s%s',
					$latest->vuln_type,
					$latest->title,
					( ! empty( $latest->vuln ) ? sprintf( '(Fixed in %s)', $latest->vuln ) : '' )
				)
			);
		}

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}
}
