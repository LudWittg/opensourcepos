<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

abstract class Report extends CI_Model
{
	function __construct()
	{
		parent::__construct();

		//Make sure the report is not cached by the browser
		$this->output->set_header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		$this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate');
		$this->output->set_header('Cache-Control: post-check=0, pre-check=0', FALSE);
		$this->output->set_header('Pragma: no-cache');
	}

	/*
	 Restricts a report query to the stock locations the logged in account may see report data for.
	 $inputs['location_ids'] is set by the Reports controller: NULL means unrestricted (the account is
	 allowed every location), an empty array means it is allowed none.
	 */
	protected function apply_location_filter(array $inputs, $column = 'sales_items.item_location')
	{
		if(!array_key_exists('location_ids', $inputs) || $inputs['location_ids'] === NULL)
		{
			return;
		}

		if(empty($inputs['location_ids']))
		{
			$this->db->where('1 = 0', NULL, FALSE);

			return;
		}

		$this->db->where_in($column, $inputs['location_ids']);
	}

	// Same restriction as a raw SQL fragment, for the reports that build their queries by hand.
	// Returns an empty string when there is nothing to restrict.
	protected function location_filter_sql(array $inputs, $column)
	{
		if(!array_key_exists('location_ids', $inputs) || $inputs['location_ids'] === NULL)
		{
			return '';
		}

		if(empty($inputs['location_ids']))
		{
			return ' AND 1 = 0 ';
		}

		return ' AND ' . $column . ' IN (' . implode(',', array_map('intval', $inputs['location_ids'])) . ') ';
	}

	/*
	 Restriction for queries that hang off a sale rather than off its line items, e.g. sales taxes,
	 which are recorded per sale. The sale is included when any of its lines is in an allowed location.
	 */
	protected function location_exists_sql(array $inputs, $sale_id_column)
	{
		if(!array_key_exists('location_ids', $inputs) || $inputs['location_ids'] === NULL)
		{
			return '';
		}

		if(empty($inputs['location_ids']))
		{
			return ' AND 1 = 0 ';
		}

		return ' AND EXISTS (SELECT 1 FROM ' . $this->db->dbprefix('sales_items') . ' AS location_items'
			. ' WHERE location_items.sale_id = ' . $sale_id_column
			. ' AND location_items.item_location IN (' . implode(',', array_map('intval', $inputs['location_ids'])) . ')) ';
	}

	// Returns the column names used for the report
	public abstract function getDataColumns();

	// Returns all the data to be populated into the report
	public abstract function getData(array $inputs);

	// Returns key=>value pairing of summary data for the report
	public abstract function getSummaryData(array $inputs);
}
?>
