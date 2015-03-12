<?php
define( 'ABSPATH', dirname( __FILE__ ) );

require_once( ABSPATH . '/../config.php' );

require_once( ABSPATH . '/header.php' );
?>

	<div class="row">
		<div class="col-xs-12">
			<h3>30 day activity</h3>
		</div>

		<table class="table table-striped">
			<thead>
			<tr>
				<th class="text-left">Nickname</th>
				<th>Messages</th>
				<th>Questions</th>
				<th>Appreciation</th>
				<th>Doc-bot references</th>
			</tr>
			</thead>
			<tbody>
			<?php
			try {
				$logs = $db->query( "
						SELECT
							s.nickname,
							s.messages,
							s.appreciation,
							s.questions,
							s.docbot
						FROM
							stats_30d s
						ORDER BY
							s.messages DESC
					" );

				while ( $log = $logs->fetchObject() ) {
					echo '
							<tr>
								<td>
									<a href="details.php?nickname=' . urlencode( $log->nickname ) . '">' . $log->nickname . '</a>
								</td>
								<td>' . $log->messages . '</td>
								<td>' . $log->questions . '</td>
								<td>' . $log->appreciation . '</td>
								<td>' . $log->docbot . '</td>
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