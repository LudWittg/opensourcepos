<?php $this->load->view("partial/header"); ?>

<div id="page_title"><?php echo $title ?></div>

<div id="page_subtitle"><?php echo $subtitle ?></div>

<div id="table_holder">
	<table id="table"></table>
</div>

<div id="report_summary">
	<?php
	foreach($overall_summary_data as $name=>$value)
	{
	?>
		<div class="summary_row"><?php echo $this->lang->line('reports_'.$name). ': '.to_currency($value); ?></div>
	<?php
	}
	?>
</div>

<script type="text/javascript">
	$(document).ready(function()
	{
	 	<?php $this->load->view('partial/bootstrap_tables_locale'); ?>

		var details_data = <?php echo json_encode($details_data); ?>;
		<?php
		if($this->config->item('customer_reward_enable') == TRUE && !empty($details_data_rewards))
		{
		?>
			var details_data_rewards = <?php echo json_encode($details_data_rewards); ?>;
		<?php
		}
		?>
		var init_dialog = function() {
			<?php
			if(isset($editable))
			{
			?>
				table_support.submit_handler('<?php echo site_url("reports/get_detailed_" . $editable . "_row")?>');
				dialog_support.init("a.modal-dlg");
			<?php
			}
			?>
		};

		var resolve_sale_id = function(row) {
			return (!isNaN(row.id) && row.id) || $(row[0] || row.id).text().replace(/(POS|RECV)\s*/g, '');
		};

		var render_detail_table = function ($detail, sale_id) {
			$detail.html('<table></table>').find("table").bootstrapTable({
				columns: <?php echo transform_headers_readonly($headers['details']); ?>,
				data: details_data[sale_id]
			});

			<?php
			if($this->config->item('customer_reward_enable') == TRUE && !empty($details_data_rewards))
			{
			?>
				$detail.append('<table></table>').find("table").bootstrapTable({
					columns: <?php echo transform_headers_readonly($headers['details_rewards']); ?>,
					data: details_data_rewards[sale_id]
				});
			<?php
			}
			?>

			// Wire any per-line edit anchors that were just rendered into this
			// expanded sub-row. The outer table's onPostBody only catches anchors
			// in the summary table.
			dialog_support.init($detail.find("a.modal-dlg"));
		};

		// After the per-line edit modal saves, re-fetch the items list for the
		// affected sale and re-render the sub-row in place. The summary row
		// itself is refreshed by table_support.handle_submit + the existing
		// reports/get_detailed_sales_row endpoint, which races with this call —
		// updateByUniqueId rebuilds the row and removes its `.detail-view`
		// sibling, so we cannot rely on the prior expanded state. Solution:
		// load fresh details into details_data, then poll briefly until the
		// summary row exists in the DOM (so updateByUniqueId has settled) and
		// expand it. expandRow on a row that's already expanded is harmless.
		window.refresh_detailed_sale_items = function(sale_id) {
			$.get("<?php echo site_url('sales/get_sale_lines_for_report'); ?>/" + sale_id, function(rows) {
				details_data[sale_id] = rows;

				var attempts = 0;
				var tick = function() {
					attempts++;
					var $row = $("#table tr[data-uniqueid='" + sale_id + "']");
					if(!$row.length) {
						if(attempts < 20) return setTimeout(tick, 50);
						return;
					}
					$('#table').bootstrapTable('expandRow', $row.data('index'));
				};
				setTimeout(tick, 100);
			}, 'json');
		};

		$('#table')
			.addClass("table-striped")
			.addClass("table-bordered")
			.bootstrapTable({
				columns: <?php echo transform_headers($headers['summary'], TRUE); ?>,
				stickyHeader: true,
				stickyHeaderOffsetLeft: $('#table').offset().left + 'px',
				stickyHeaderOffsetRight: $('#table').offset().right + 'px',
				pageSize: <?php echo $this->config->item('lines_per_page'); ?>,
				pagination: true,
				sortable: true,
				showColumns: true,
				uniqueId: 'id',
				showExport: true,
				exportDataType: 'all',
				exportTypes: ['json', 'xml', 'csv', 'txt', 'sql', 'excel', 'pdf'],
				data: <?php echo json_encode($summary_data); ?>,
				iconSize: 'sm',
				paginationVAlign: 'bottom',
				detailView: true,
				escape: false,
				search: true,
				onPageChange: init_dialog,
				onPostBody: function() {
					dialog_support.init("a.modal-dlg");
				},
				onExpandRow: function (index, row, $detail) {
					render_detail_table($detail, resolve_sale_id(row));
				}
		});

		init_dialog();
	});
</script>

<?php $this->load->view("partial/footer"); ?>
