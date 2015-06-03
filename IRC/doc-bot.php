<?php

/**
 * Class DocBot
 *
 * Replace some frequently used doc-bot commands if the bot is
 * missing from the channel for whatever reason
 */
class DocBot {
	function is_doc_bot( $irc, $channel ) {
		return $irc->isJoined( $channel, 'doc-bot' );
	}

	function developer( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$message_parse = explode( ' ', $data->message, 2 );
		$message_parse = $message_parse[1];

		$user = $data->nick;

		$message_parse = explode( '>', $message_parse );
		if ( isset( $message_parse[1] ) && ! empty( $message_parse[1] ) ) {
			$send_to = trim( $message_parse[1] );
			if ( $irc->isJoined( $data->channel, $send_to ) ) {
				$user = $send_to;
			}
		}

		$search = 'https://developer.wordpress.org/?s=%s&post_type%5B%5D=wp-parser-function&post_type%5B%5D=wp-parser-hook&post_type%5B%5D=wp-parser-class&post_type%5B%5D=wp-parser-method';
		$string = trim( $message_parse[0] );

		$string = str_replace( array( ' ' ), array( '+' ), $string );
		$search = sprintf( $search, $string );

		$headers = get_headers( $search, true );

		if ( ! isset( $headers['Location'] ) || empty( $headers['Location'] ) ) {
			$message = sprintf(
				'%s: No exact match found for \'%s\' - See the full set of results at %s',
				$user,
				trim( $message_parse[0] ),
				$search
			);
		}
		else {
			$message = sprintf(
				'%s: %s',
				$user,
				$headers['Location']
			);
		}

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}

	function codex( &$irc, &$data ) {
		if ( $this->is_doc_bot( $irc, $data->channel ) ) {
			return;
		}
		$message_parse = explode( ' ', $data->message, 2 );
		$message_parse = $message_parse[1];

		$user = $data->nick;

		$message_parse = explode( '>', $message_parse );
		if ( isset( $message_parse[1] ) && ! empty( $message_parse[1] ) ) {
			$send_to = trim( $message_parse[1] );
			if ( $irc->isJoined( $data->channel, $send_to ) ) {
				$user = $send_to;
			}
		}

		$search = 'http://codex.wordpress.org/index.php?title=Special:Search&search=';
		$string = trim( $message_parse[0] );

		$string = str_replace( array( ' ' ), array( '+' ), $string );
		$search .= $string;

		$headers = get_headers( $search, true );

		if ( ! isset( $headers['Location'] ) || empty( $headers['Location'] ) ) {
			$message = sprintf(
				'%s: No exact match found for \'%s\' - See the full set of results at %s',
				$user,
				trim( $message_parse[0] ),
				$search
			);
		}
		else {
			$message = sprintf(
				'%s: %s',
				$user,
				$headers['Location']
			);
		}

		$irc->message( SMARTIRC_TYPE_CHANNEL, $data->channel, $message );
	}
}