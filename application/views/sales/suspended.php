<style>
@media (min-width: 768px)
{
	.modal-dlg .modal-dialog
	{
		width: 750px !important;
	}
}
.preview-items-table {
	margin: 5px 0;
	font-size: 12px;
	width: 100%;
}
.preview-items-table th {
	background: #f5f5f5;
	padding: 4px 8px;
	border-bottom: 1px solid #ddd;
}
.preview-items-table td {
	padding: 3px 8px;
	border-bottom: 1px solid #eee;
}
.preview-row td {
	background: #fafafa !important;
	padding: 5px 15px !important;
}
.btn-preview {
	cursor: pointer;
	font-size: 14px;
	margin-right: 8px;
	color: #555;
}
.btn-preview:hover {
	color: #333;
}
</style>
<div class="form-group" style="margin-bottom:10px;">
	<input type="text" id="suspended_customer_filter" class="form-control"
		placeholder="<?php echo $this->lang->line('common_search'); ?>"
		autocomplete="off">
</div>
<table id="suspended_sales_table" class="table table-striped table-hover">
	<thead>
		<tr bgcolor="#CCC">
			<th></th>
			<th><?php echo $this->lang->line('sales_suspended_doc_id'); ?></th>
			<th><?php echo $this->lang->line('sales_date'); ?></th>
			<?php
			if($this->config->item('dinner_table_enable') == TRUE)
			{
			?>
				<th><?php echo $this->lang->line('sales_table'); ?></th>
			<?php
			}
			?>
			<th><?php echo $this->lang->line('sales_customer'); ?></th>
			<th><?php echo $this->lang->line('sales_employee'); ?></th>
			<th><?php echo $this->lang->line('sales_comments'); ?></th>
			<th><?php echo $this->lang->line('sales_unsuspend_and_delete'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		$colspan = 7;
		if($this->config->item('dinner_table_enable') == TRUE) { $colspan = 8; }
		foreach($suspended_sales as $suspended_sale)
		{
			$customer_name = '';
			if(isset($suspended_sale['customer_id']))
			{
				$customer = $this->Customer->get_info($suspended_sale['customer_id']);
				$customer_name = $customer->first_name . ' ' . $customer->last_name;
			}
		?>
			<tr data-customer="<?php echo html_escape(strtolower($customer_name)); ?>">
				<td>
					<span class="glyphicon glyphicon-eye-open btn-preview" id="preview_btn_<?php echo $suspended_sale['sale_id']; ?>"
						onclick="togglePreview(<?php echo $suspended_sale['sale_id']; ?>)" title="Preview items"></span>
				</td>
				<td><?php echo $suspended_sale['doc_id']; ?></td>
				<td><?php echo date($this->config->item('dateformat'), strtotime($suspended_sale['sale_time'])); ?></td>
				<?php
				if($this->config->item('dinner_table_enable') == TRUE)
				{
				?>
					<td><?php echo $this->Dinner_table->get_name($suspended_sale['dinner_table_id']); ?></td>
				<?php
				}
				?>
				<td>
					<?php
					if($customer_name !== '')
					{
						echo $customer_name;
					}
					else
					{
					?>
						&nbsp;
					<?php
					}
					?>
				</td>
				<td>
					<?php
					if(isset($suspended_sale['employee_id']))
					{
						$employee = $this->Employee->get_info($suspended_sale['employee_id']);
						echo $employee->first_name . ' ' . $employee->last_name;
					}
					else
					{
					?>
						&nbsp;
					<?php
					}
					?>
				</td>
				<td><?php echo $suspended_sale['comment']; ?></td>
				<td>
					<?php echo form_open('sales/unsuspend', array('style' => 'display:inline')); ?>
						<?php echo form_hidden('suspended_sale_id', $suspended_sale['sale_id']); ?>
						<input type="submit" name="submit" value="<?php echo $this->lang->line('sales_unsuspend'); ?>" id="submit_<?php echo $suspended_sale['sale_id']; ?>" class="btn btn-primary btn-xs pull-right">
					<?php echo form_close(); ?>
				</td>
			</tr>
			<tr class="preview-row" id="preview_row_<?php echo $suspended_sale['sale_id']; ?>" style="display:none;">
				<td colspan="<?php echo $colspan; ?>">
					<div style="display:flex; align-items:center; margin-bottom:4px;">
						<strong style="flex:1;">Items — Sale #<?php echo $suspended_sale['doc_id']; ?></strong>
						<span class="glyphicon glyphicon-remove btn-preview" onclick="togglePreview(<?php echo $suspended_sale['sale_id']; ?>)" title="Close preview"></span>
					</div>
					<div id="preview_content_<?php echo $suspended_sale['sale_id']; ?>">
						<em>Loading...</em>
					</div>
				</td>
			</tr>
		<?php
		}
		?>
	</tbody>
</table>

<script>
function togglePreview(saleId) {
	var row = document.getElementById('preview_row_' + saleId);
	var btn = document.getElementById('preview_btn_' + saleId);

	if (row.style.display === 'none') {
		row.style.display = '';
		btn.className = 'glyphicon glyphicon-eye-close btn-preview';

		var content = document.getElementById('preview_content_' + saleId);
		content.innerHTML = '<em>Loading...</em>';

		var xhr = new XMLHttpRequest();
		xhr.open('GET', '<?php echo site_url("sales/preview_suspended_sale"); ?>?sale_id=' + saleId, true);
		xhr.onreadystatechange = function() {
			if (xhr.readyState === 4) {
				if (xhr.status === 200) {
					var items = JSON.parse(xhr.responseText);
					if (items.length === 0) {
						content.innerHTML = '<em>No items found.</em>';
						return;
					}
					var html = '<table class="preview-items-table">';
					html += '<thead><tr><th>Item</th><th style="text-align:right">Qty</th><th style="text-align:right">Price</th><th style="text-align:right">Total</th></tr></thead><tbody>';
					for (var i = 0; i < items.length; i++) {
						html += '<tr><td>' + items[i].name + '</td>';
						html += '<td style="text-align:right">' + items[i].quantity + '</td>';
						html += '<td style="text-align:right">' + items[i].price + '</td>';
						html += '<td style="text-align:right">' + items[i].total + '</td></tr>';
					}
					html += '</tbody></table>';
					content.innerHTML = html;
				} else {
					content.innerHTML = '<em>Error loading items.</em>';
				}
			}
		};
		xhr.send();
	} else {
		row.style.display = 'none';
		btn.className = 'glyphicon glyphicon-eye-open btn-preview';
	}
}

(function() {
	var input = document.getElementById('suspended_customer_filter');
	if (!input) return;

	function fuzzy(needle, hay) {
		if (!needle) return true;
		var hi = 0;
		for (var i = 0; i < needle.length; i++) {
			var ch = needle.charAt(i);
			if (ch === ' ') continue;
			var found = hay.indexOf(ch, hi);
			if (found === -1) return false;
			hi = found + 1;
		}
		return true;
	}

	input.addEventListener('input', function() {
		var q = this.value.toLowerCase().trim();
		var rows = document.querySelectorAll('#suspended_sales_table tbody > tr');
		for (var i = 0; i < rows.length; i++) {
			var r = rows[i];
			if (r.classList.contains('preview-row')) continue;
			var name = r.getAttribute('data-customer') || '';
			var show = fuzzy(q, name);
			r.style.display = show ? '' : 'none';
			var next = r.nextElementSibling;
			if (next && next.classList.contains('preview-row') && !show) {
				next.style.display = 'none';
			}
		}
	});
})();
</script>
