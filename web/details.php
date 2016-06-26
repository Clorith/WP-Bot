<?php
	define( 'ABSPATH', dirname( __FILE__ ) );

	require_once( ABSPATH . '/../config.php' );

	require_once( ABSPATH . '/header.php' );
?>

<div class="page-header">
	<?php
		printf(
			'<h1>%s</h1>',
			htmlentities( $_GET['nickname'] )
		);
	?>
</div>

<div class="row">
	<div class="col-xs-12">
	</div>
</div>

<div class="row">
	<?php
		try {
			/**
			 * TODO: Is this query overkill? Look into ways of potentially simplifying it
			 */
			$data = $db->query( "
				SELECT
					(
						SELECT
							COUNT(DISTINCT id)
						FROM
							messages
						WHERE
							nickname = " . $db->quote( $_GET['nickname'] ) . "
						AND
							is_question = 1
					) AS questions,
					(
						SELECT
							COUNT(DISTINCT id)
						FROM
							messages
						WHERE
							nickname = " . $db->quote( $_GET['nickname'] ) . "
						AND
							is_docbot IS NOT NULL
						AND
							is_docbot != ''
					) AS docbot,
					(
						SELECT
							COUNT(DISTINCT id)
						FROM
							messages
						WHERE
							is_appreciation LIKE " . $db->quote( '%"' . $_GET['nickname'] . '"%' ) . "
					) AS appreciation,
					(
						SELECT
							COUNT(DISTINCT id)
						FROM
							messages
						WHERE
							nickname = " . $db->quote( $_GET['nickname'] ) . "
					) AS messages
				FROM
					messages
				LIMIT 1
			" );
			$data = $data->fetchObject();

			/**
			 * Percentage calculation of messages under each data type
			 */
			$appreciation      = ( ( $data->appreciation / $data->messages ) * 100 );
			$questions         = ( ( $data->questions / $data->messages ) * 100 );
			$docbot            = ( ( $data->docbot / $data->messages ) * 100 );

			/**
			 * First pass at contribution score/level thing
			 *
			 * Ideally a way to determine return/persistent contributors
			 * from one off users just passing by
			 *
			 * TODO: Make this "formula" actually relevant in some way (also make a proper formula)
			 */
			$contributor_level = 0;
			if ( ( $docbot + $appreciation ) >= 5 ) {
				$contributor_level = number_format( ( ( ( $docbot + $appreciation + $questions ) / 100 ) * 10 ), 1, '.', ' ' );
			}

			echo '
				<div class="col-xs-12">
					<h3>Contribution breakdown <span class="label label-primary">Based off ' . number_format( $data->messages, 0, ',', ' ' ) . ' messages</span>' . ( $contributor_level > 0 ? ' <span class="label label-success">C-value ' . $contributor_level . '</span>' : '' ) . '</h3>

					<div class="progress">
			';

			if ( $appreciation > 0 ) {
				echo '
					<div class="progress-bar progress-bar-success" style="width: ' . $appreciation . '%;">
						<span class="">Appreciation</span>
					</div>
				';
			}
			if ( $questions > 0 ) {
				echo '
					<div class="progress-bar progress-bar-warning" style="width: ' . $questions . '%;">
						<span class="">Questions</span>
					</div>
				';
			}
			if ( $docbot > 0 ) {
				echo '
					<div class="progress-bar progress-bar-primary" style="width: ' . $docbot . '%;">
						<span class="">doc-bot references</span>
					</div>
				';
			}

            echo '
					</div>
				</div>
			';
		}
		catch ( PDOException $e ) {
			echo "<p>There was an error in the query</p>";
			echo $e->getMessage();
		}
	?>
</div>

	<div class="row">
		<div class="col-xs-12">
			<h3>Recent activity</h3>
		</div>

		<table class="table table-striped">
			<thead>
			<tr>
				<th class="text-left">Timestamp</th>
				<th>Nick</th>
				<th>Message</th>
				<th>&nbsp;</th>
			</tr>
			</thead>
			<tbody>
			<?php
				try {
					$page = ( isset( $_GET['paged'] ) && ctype_digit( $_GET['paged'] ) ? ( $_GET['paged'] ) : 1 );

					$logs = $db->query( "
						SELECT
						SQL_CALC_FOUND_ROWS
							m.id,
							m.nickname,
							m.message,
							m.is_question,
							m.is_appreciation,
							m.is_docbot,
							m.time,
							m.event
						FROM
							messages m
						WHERE
							m.nickname = " . $db->quote( $_GET['nickname'] ) . "
						AND
							m.event NOT IN ( 'mod_request' )
						ORDER BY
							m.id DESC
						LIMIT
							" . ( ( $page - 1 ) * 100 ) . ", 100
					" );

					$query_count = $db->query( "SELECT FOUND_ROWS() as total;" );
					$query_count = $query_count->fetchObject();

					while ( $log = $logs->fetchObject() ) {
						$icon = '';

						if ( $log->is_question ) {
							$icon .= '<i class="fa fa-question-circle"></i>';
						}
						if ( false != $log->is_appreciation ) {
							$icon .= '<i class="fa fa-check-circle"></i>';
						}
						if ( $log->is_docbot ) {
							$icon .= '<i class="fa fa-info-circle"></i>';
						}

						if ( 'message' == $log->event ) {
							$icon .= '<i class="fa fa-commenting"></i>';
						}
						if ( 'part' == $log->event ) {
							$icon .= '<i class="fa fa-sign-out"></i>';
						}
						if ( 'kick' == $log->event ) {
							$icon .= '<i class="fa fa-futbol-o"></i>';
						}
						if ( 'join' == $log->event ) {
							$icon .= '<i class="fa fa-sign-in"></i>';
						}

						if ( empty( $log->message ) ) {
							$log->message = '<i>No message</i>';
						} else {
							$log->message = htmlspecialchars( $log->message );
						}

						echo '
							<tr>
								<td class="text-left timestamp">
									<a href="index.php?date=' . date( "Y-m-d", strtotime( $log->time ) ) . '#' . $log->id . '">
										' . $log->time . '
									</a>
								</td>
								<td class="nickname"><a href="details.php?nickname=' . urlencode( $log->nickname ) . '">' . $log->nickname . '</a></td>
								<td class="message">' . $log->message . '</td>
								<td class="text-right activity">' . $icon . '</td>
							</tr>
						';
					}
				} catch ( PDOException $e ) {
					echo $e->getMessage();
				}
			?>
			</tbody>
		</table>
	</div>

	<div class="row">
		<?php
			$has_next_page = false;
			$has_prev_page = false;

			$total_pages = ceil( $query_count->total / 100 );

			if ( $page > 1 ) {
				$has_prev_page = true;
			}
			if ( $page < $total_pages ) {
				$has_next_page = true;
			}
		?>
		<nav>
			<ul class="pager">
				<li class="previous <?php echo ( ! $has_prev_page ? 'disabled' : '' ); ?>"><a href="details.php?nickname=<?php echo $_GET['nickname']; ?>&paged=<?php echo ( $page - 1 ); ?>"><span aria-hidden="true">&larr;</span> Newer</a></li>
				<li class="next <?php echo ( ! $has_next_page ? 'disabled' : '' ); ?>"><a href="details.php?nickname=<?php echo $_GET['nickname']; ?>&paged=<?php echo ( $page + 1 ); ?>">Older <span aria-hidden="true">&rarr;</span></a></li>
			</ul>
		</nav>
	</div>


<?php
	require_once( ABSPATH . '/footer.php' );