<?php
	define( 'ABSPATH', dirname( __FILE__ ) );

	require_once( ABSPATH . '/../config.php' );

	require_once( ABSPATH . '/header.php' );

	$date = ( isset( $_GET['date'] ) ? $_GET['date'] : date( "Y-m-d" ) );
?>

<div class="row">
	<table class="table table-striped">
		<thead>
			<tr>
				<th>&nbsp;</th>
				<th>Nick</th>
				<th>Message</th>
				<th class="text-right">Timestamp</th>
			</tr>
		</thead>
		<tbody>
	<?php
		$logs = $db->query( "
			SELECT
			SQL_CALC_FOUND_ROWS
				m.id,
				m.nickname,
				m.message,
				m.event,
				m.is_question,
				m.is_appreciation,
				m.is_docbot,
				m.time
			FROM
				messages m
			WHERE
				m.time BETWEEN " . $db->quote( $date . ' 00:00:00' ) . " AND " . $db->quote( $date . ' 23:59:59' ) . "
			ORDER BY
				m.id DESC
		" );

		$query_count = $db->query( "SELECT FOUND_ROWS() as total;" );
		$query_count = $query_count->fetchObject();

		while ( $log = $logs->fetchObject() ) {
			$tr_class = array();
			$icon     = '';

			/**
			 * Check for message states and add icons
			 */
			if ( $log->is_question ) {
				$icon .= '<span class="glyphicon glyphicon-question-sign"></span>';
			}
			if ( false != $log->is_appreciation ) {
				$icon .= '<span class="glyphicon glyphicon-ok-sign"></span>';
			}
			if ( $log->is_docbot ) {
				$icon .= '<span class="glyphicon glyphicon-info-sign"></span>';
			}

			/**
			 * Check the event type and color code accordingly
			 */
			if ( 'quit' == $log->event ) {
				$tr_class[] = 'warning';
				$log->message = '[QUIT] ' . $log->message;
			}
			if ( 'part' == $log->event ) {
				$tr_class[] = 'warning';
				$log->message = '[PART] ' . $log->message;
			}
			if ( 'kick' == $log->event ) {
				$tr_class[] = 'danger';
				$log->message = '[KICK] ' . $log->message;
			}
			if ( 'join' == $log->event ) {
				$tr_class[] = 'info';
				$log->message = '[JOIN] ' . $log->message;
			}


			echo '
				<tr class="' . implode( ' ', $tr_class ) . '">
					<td class="activity">' . $icon . '</td>
					<td class="nickname"><a href="details.php?nickname=' . urlencode( $log->nickname ) . '">' . $log->nickname . '</a></td>
					<td class="message">' . htmlspecialchars( $log->message ) . '</td>
					<td class="text-right timestamp">' . $log->time . '</td>
				</tr>
			';
		}
	?>
		</tbody>
	</table>
</div>

<div class="row">
	<nav>
		<ul class="pager">
			<li class="previous"><a href="details.php?date=<?php echo date( "Y-m-d", strtotime( '+1 day', strtotime( $date ) ) ); ?>"><span aria-hidden="true">&larr;</span> Newer</a></li>
			<li class="next"><a href="details.php?date=<?php echo date( "Y-m-d", strtotime( '-1 day', strtotime( $date ) ) ); ?>">Older <span aria-hidden="true">&rarr;</span></a></li>
		</ul>
	</nav>
</div>

<?php
	require_once( ABSPATH . '/footer.php' );