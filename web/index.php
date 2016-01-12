<?php
	define( 'ABSPATH', dirname( __FILE__ ) );

	require_once( ABSPATH . '/../config.php' );

	require_once( ABSPATH . '/header.php' );

	$date = ( isset( $_GET['date'] ) ? $_GET['date'] : date( "Y-m-d" ) );
?>

<div class="row">
	<nav>
		<ul class="pager">
			<li class="previous"><a href="index.php?date=<?php echo date( "Y-m-d", strtotime( '+1 day', strtotime( $date ) ) ); ?>"><span aria-hidden="true">&larr;</span> Newer</a></li>
			<li class="next"><a href="index.php?date=<?php echo date( "Y-m-d", strtotime( '-1 day', strtotime( $date ) ) ); ?>">Older <span aria-hidden="true">&rarr;</span></a></li>
		</ul>
	</nav>
</div>

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
				$icon .= '<i class="fa fa-question-circle"></i>';
			}
			if ( false != $log->is_appreciation ) {
				$icon .= '<i class="fa fa-check-circle"></i>';
			}
			if ( $log->is_docbot ) {
				$icon .= '<i class="fa fa-info-circle"></i>';
			}

			/**
			 * Check the event type and color code accordingly
			 */
			if ( 'quit' == $log->event ) {
				$tr_class[] = 'warning';
				$tr_class[] = 'status-quit';
				$log->message = '[QUIT] ' . $log->message;
			}
			if ( 'part' == $log->event ) {
				$tr_class[] = 'warning';
				$tr_class[] = 'status-part';
				$log->message = '[PART] ' . $log->message;
				$icon .= '<i class="fa fa-sign-out"></i>';
			}
			if ( 'kick' == $log->event ) {
				$tr_class[] = 'danger';
				$tr_class[] = 'status-kick';
				$log->message = '[KICK] ' . $log->message;
				$icon .= '<i class="fa fa-futbol-o"></i>';
			}
			if ( 'join' == $log->event ) {
				$tr_class[] = 'info';
				$tr_class[] = 'status-join';
				$log->message = '[JOIN] ' . $log->message;
				$icon .= '<i class="fa fa-sign-in"></i>';
			}
			if ( 'message' == $log->event ) {
				$icon .= '<i class="fa fa-commenting"></i>';
			}


			echo '
				<tr class="' . implode( ' ', $tr_class ) . '">
					<td class="activity">' . $icon . '</td>
					<td class="nickname"><a href="details.php?nickname=' . urlencode( $log->nickname ) . '">' . $log->nickname . '</a></td>
					<td class="message">' . htmlspecialchars( $log->message ) . '</td>
					<td class="text-right timestamp">
						<a href="index.php?date=' . $date . '#' . $log->id . '" name="' . $log->id . '">
							' . $log->time . '
						</a>
					</td>
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
				<li class="previous"><a href="index.php?date=<?php echo date( "Y-m-d", strtotime( '+1 day', strtotime( $date ) ) ); ?>"><span aria-hidden="true">&larr;</span> Newer</a></li>
				<li class="next"><a href="index.php?date=<?php echo date( "Y-m-d", strtotime( '-1 day', strtotime( $date ) ) ); ?>">Older <span aria-hidden="true">&rarr;</span></a></li>
			</ul>
		</nav>
	</div>

<?php
	require_once( ABSPATH . '/footer.php' );