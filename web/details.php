<?php
	define( 'ABSPATH', dirname( __FILE__ ) );

	require_once( ABSPATH . '/../config.php' );

	require_once( ABSPATH . '/header.php' );
?>

<div class="page-header">
	<h1><?php echo htmlentities( $_GET['nickname'] ); ?></h1>
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
					$logs = $db->query( "
						SELECT
							id,
							nickname,
							message,
							is_question,
							is_appreciation,
							is_docbot,
							time
						FROM
							messages
						WHERE
							nickname = " . $db->quote( $_GET['nickname'] ) . "
						ORDER BY
							id DESC
						LIMIT
							100
					" );
					while ( $log = $logs->fetchObject() ) {
						$icon = '';

						if ( $log->is_question ) {
							$icon .= '<span class="glyphicon glyphicon-question-sign"></span>';
						}
						if ( false != $log->is_appreciation ) {
							$icon .= '<span class="glyphicon glyphicon-ok-sign"></span>';
						}
						if ( $log->is_docbot ) {
							$icon .= '<span class="glyphicon glyphicon-info-sign"></span>';
						}

						echo '
							<tr>
								<td class="text-left timestamp">' . $log->time . '</td>
								<td class="nickname"><a href="details.php?nickname=' . urlencode( $log->nickname ) . '">' . $log->nickname . '</a></td>
								<td class="message">' . htmlspecialchars( $log->message ) . '</td>
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


<?php
	require_once( ABSPATH . '/footer.php' );