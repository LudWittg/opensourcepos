<div id="required_fields_message"><?php echo $this->lang->line('common_fields_required_message'); ?></div>

<ul id="error_message_box" class="error_message_box"></ul>

<?php echo form_open('sales/save_sale_item/'.$sale_id.'/'.$line, array('id' => 'sale_item_edit_form', 'class' => 'form-horizontal')); ?>
	<?php echo form_hidden('delete', '0'); ?>

	<fieldset id="sale_item_basic_info">
		<div class="form-group form-group-sm">
			<?php echo form_label($this->lang->line('sales_item'), 'item_name', array('class' => 'control-label col-xs-4')); ?>
			<div class='col-xs-7'>
				<p class="form-control-static"><?php echo $item['item_name']; ?> (#<?php echo $item['item_number']; ?>)</p>
			</div>
		</div>

		<div class="form-group form-group-sm">
			<?php echo form_label($this->lang->line('sales_quantity'), 'quantity_purchased', array('class' => 'control-label col-xs-4')); ?>
			<div class='col-xs-7'>
				<?php echo form_input(array('name' => 'quantity_purchased', 'id' => 'quantity_purchased', 'class' => 'form-control input-sm', 'value' => to_quantity_decimals($item['quantity_purchased']))); ?>
			</div>
		</div>

		<div class="form-group form-group-sm">
			<?php echo form_label($this->lang->line('sales_price'), 'item_unit_price', array('class' => 'control-label col-xs-4')); ?>
			<div class='col-xs-7'>
				<?php echo form_input(array('name' => 'item_unit_price', 'id' => 'item_unit_price', 'class' => 'form-control input-sm', 'value' => to_decimals($item['item_unit_price']))); ?>
			</div>
		</div>

		<div class="form-group form-group-sm">
			<?php echo form_label($this->lang->line('sales_cost_price'), 'item_cost_price', array('class' => 'control-label col-xs-4')); ?>
			<div class='col-xs-7'>
				<?php echo form_input(array('name' => 'item_cost_price', 'id' => 'item_cost_price', 'class' => 'form-control input-sm', 'value' => to_decimals($item['item_cost_price']))); ?>
			</div>
		</div>

		<div class="form-group form-group-sm">
			<?php echo form_label($this->lang->line('sales_discount'), 'discount', array('class' => 'control-label col-xs-4')); ?>
			<div class='col-xs-4'>
				<?php echo form_input(array('name' => 'discount', 'id' => 'discount', 'class' => 'form-control input-sm', 'value' => $item['discount_type'] ? to_currency_no_money($item['discount']) : to_decimals($item['discount']))); ?>
			</div>
			<div class='col-xs-3'>
				<?php echo form_dropdown('discount_type', $discount_types, $item['discount_type'], array('class' => 'form-control input-sm')); ?>
			</div>
		</div>

		<?php if(count($stock_locations) > 1): ?>
			<div class="form-group form-group-sm">
				<?php echo form_label($this->lang->line('sales_location'), 'item_location', array('class' => 'control-label col-xs-4')); ?>
				<div class='col-xs-7'>
					<?php echo form_dropdown('item_location', $stock_locations, $item['item_location'], array('class' => 'form-control input-sm')); ?>
				</div>
			</div>
		<?php else: ?>
			<?php echo form_hidden('item_location', $item['item_location']); ?>
		<?php endif; ?>

		<div class="form-group form-group-sm">
			<?php echo form_label($this->lang->line('sales_description'), 'description', array('class' => 'control-label col-xs-4')); ?>
			<div class='col-xs-7'>
				<?php echo form_input(array('name' => 'description', 'id' => 'description', 'class' => 'form-control input-sm', 'value' => $item['description'])); ?>
			</div>
		</div>

		<div class="form-group form-group-sm">
			<?php echo form_label($this->lang->line('sales_serial'), 'serialnumber', array('class' => 'control-label col-xs-4')); ?>
			<div class='col-xs-7'>
				<?php echo form_input(array('name' => 'serialnumber', 'id' => 'serialnumber', 'class' => 'form-control input-sm', 'value' => $item['serialnumber'])); ?>
			</div>
		</div>

		<?php echo form_hidden('print_option', $item['print_option']); ?>
	</fieldset>
<?php echo form_close(); ?>

<script type="text/javascript">
$(document).ready(function()
{
	// Wire the modal's "Delete" button (data-btn-delete) to flip the hidden flag
	// and submit the same form, so the controller can route to delete_sale_item.
	$('button#delete').click(function() {
		if(!confirm("<?php echo $this->lang->line('sales_delete_sale_item_confirm'); ?>"))
		{
			return false;
		}
		$('#sale_item_edit_form input[name=delete]').val('1');
		$('#sale_item_edit_form').submit();
	});

	$('#sale_item_edit_form').validate({
		submitHandler: function(form) {
			$(form).ajaxSubmit({
				success: function(response)
				{
					dialog_support.hide();
					table_support.handle_submit("<?php echo site_url('reports/get_detailed_sales_row'); ?>", response);

					if(response.success && typeof window.refresh_detailed_sale_items == 'function')
					{
						window.refresh_detailed_sale_items(response.id);
					}
				},
				dataType: 'json'
			});
		},
		errorLabelContainer: '#error_message_box'
	});
});
</script>
