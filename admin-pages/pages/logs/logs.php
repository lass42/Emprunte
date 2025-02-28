<?php
class HbAdminPageLogs extends HbAdminPage {

	public function __construct( $page_id, $hbdb, $utils, $options_utils ) {
		$this->data = array(
			'hb_text' => array()
		);
		parent::__construct( $page_id, $hbdb, $utils, $options_utils );
	}

	public function display() {
	?>

	<div class="wrap">

		<h1><?php esc_html_e( 'Logs', 'hbook-admin' ); ?></h1>

		<hr/><br/>

		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th width="4%">Id</th>
					<th width="24%">Status</th>
					<th width="24%">Event</th>
					<th width="24%">Details</th>
					<th width="24%">Logged on</th>
				</tr>
			</thead>

			<tbody>
				<?php
				if ( ! isset( $_GET['resa_id'] ) ) {
					$logs = array_reverse( $this->hbdb->get_all( 'resa_logs' ) );
				} else {
					$resa_is_parent = 0;
					if ( isset( $_GET['resa_is_parent'] ) ) {
						$resa_is_parent = $_GET['resa_is_parent'];
					}
					$logs = array_reverse( $this->hbdb->get_resa_logs_by_resa_id( $_GET['resa_id'], $resa_is_parent ) );
				}
				foreach ( $logs as $log ) {
				?>
				<tr>
					<td>
						<?php
						if ( $log['is_parent'] ) {
							echo( '#' );
						}
						echo( esc_html( $log['resa_id'] ) );
						?>
					</td>
					<td>
						<?php
						echo( esc_html( $log['previous_status'] ) );
						if ( $log['previous_status'] ) {
							echo( ' => ' );
						}
						echo( esc_html( $log['status'] ) );
						?>
					</td>
					<td><?php echo( esc_html( $log['event'] ) ); ?></td>
					<td><?php echo( esc_html( $log['msg'] ) ); ?></td>
					<td><?php echo( esc_html( $log['logged_on'] ) ); ?></td>
				</tr>
				<?php } ?>
			</tbody>

		</table>

	</div><!-- end .wrap -->

	<?php
	}

}