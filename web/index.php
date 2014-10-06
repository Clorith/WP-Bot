<?php
	define( 'ABSPATH', dirname( __FILE__ ) );

	require_once( ABSPATH . '/../config.php' );

	require_once( ABSPATH . '/header.php' );
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
				id,
				nickname,
				message,
				event,
				is_question,
				is_appreciation,
				is_docbot,
				time
			FROM
				messages
			ORDER BY
				id DESC
			LIMIT
				100
		" );
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
			}
			if ( 'part' == $log->event ) {
				$tr_class[] = 'warning';
			}
			if ( 'kick' == $log->event ) {
				$tr_class[] = 'danger';
			}
			if ( 'join' == $log->event ) {
				$tr_class[] = 'info';
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

<div class="row text-center">
	<ul class="pagination">
		<li class="disabled"><a href="#">&laquo;</a></li>
		<li class="active"><a href="#">1</a></li>
		<li><a href="#">2</a></li>
		<li><a href="#">3</a></li>
		<li><a href="#">4</a></li>
		<li><a href="#">5</a></li>
		<li><a href="#">&raquo;</a></li>
	</ul>
</div>

<?php
	require_once( ABSPATH . '/footer.php' );