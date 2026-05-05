<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once("Summary_report.php");

class Summary_payments extends Summary_report
{
	protected function _get_data_columns()
	{
		return array(
			array('trans_group' => $this->lang->line('reports_trans_group')),
			array('trans_type' => $this->lang->line('reports_trans_type')),
			array('trans_sales' => $this->lang->line('reports_sales')),
			array('trans_amount' => $this->lang->line('reports_trans_amount')),
			array('trans_payments' => $this->lang->line('reports_trans_payments')),
			array('trans_refunded' => $this->lang->line('reports_trans_refunded')),
			array('trans_due' => $this->lang->line('reports_trans_due')));
	}

	// The two main queries in getData() below don't join sales_items, so they can't
	// use the parent's _where() (which references sales_items.item_location). Apply
	// the date filter inline and let the location filter flow through the temp tables.
	private function _apply_date_filter(array $inputs)
	{
		if(empty($this->config->item('date_or_time_format')))
		{
			$this->db->where('DATE(sales.sale_time) BETWEEN ' . $this->db->escape($inputs['start_date']) . ' AND ' . $this->db->escape($inputs['end_date']));
		}
		else
		{
			$this->db->where('sales.sale_time BETWEEN ' . $this->db->escape(rawurldecode($inputs['start_date'])) . ' AND ' . $this->db->escape(rawurldecode($inputs['end_date'])));
		}
	}

	public function getData(array $inputs)
	{
		$cash_payment = $this->lang->line('sales_cash');

		$separator[] = array(
			'trans_group' => '<HR>',
			'trans_type' => '',
			'trans_sales' => '',
			'trans_amount' => '',
			'trans_payments' => '',
			'trans_refunded' => '',
			'trans_due' => ''
		);

		$where = '';

		if(empty($this->config->item('date_or_time_format')))
		{
			$where .= 'DATE(sale_time) BETWEEN ' . $this->db->escape($inputs['start_date']) . ' AND ' . $this->db->escape($inputs['end_date']);
		}
		else
		{
			$where .= 'sale_time BETWEEN ' . $this->db->escape(rawurldecode($inputs['start_date'])) . ' AND ' . $this->db->escape(rawurldecode($inputs['end_date']));
		}

		$this->create_summary_payments_temp_tables($where, $inputs['location_id']);

		$select = '\'' . $this->lang->line('reports_trans_sales') . '\' AS trans_group, ';
		$select .= '(CASE sale_type WHEN ' . SALE_TYPE_POS . ' THEN \'' . $this->lang->line('reports_code_pos')
			. '\' WHEN ' . SALE_TYPE_INVOICE . ' THEN \'' . $this->lang->line('sales_invoice')
			. '\' WHEN ' . SALE_TYPE_RETURN . ' THEN \'' . $this->lang->line('sales_return')
			. '\' END) AS trans_type, ';
		$select .= 'COUNT(sales.sale_id) AS trans_sales, ';
		$select .= 'SUM(sumpay_items.trans_amount) AS trans_amount, ';
		$select .= 'IFNULL(SUM(sumpay_payments.total_payments),0) AS trans_payments, ';
		$select .= 'IFNULL(SUM(sumpay_payments.total_cash_refund),0) AS trans_refunded, ';
		$select .= 'SUM(CASE WHEN sumpay_items.trans_amount - IFNULL(sumpay_payments.total_payments,0) > 0 THEN sumpay_items.trans_amount - IFNULL(sumpay_payments.total_payments,0) ELSE 0 END) as trans_due ';

		$location_filter_active = ($inputs['location_id'] !== 'all');

		$this->db->select($select);
		$this->db->from('ospos_sales AS sales');
		$this->db->join('sumpay_items_temp AS sumpay_items', 'sales.sale_id = sumpay_items.sale_id', 'left outer');
		$this->db->join('sumpay_payments_temp AS sumpay_payments', 'sales.sale_id = sumpay_payments.sale_id', 'left outer');
		if($location_filter_active)
		{
			$this->db->join('sumpay_location_share AS loc_share', 'sales.sale_id = loc_share.sale_id', 'inner');
		}
		$this->db->where('sales.sale_status', COMPLETED);
		$this->_apply_date_filter($inputs);

		$this->db->group_by('trans_type');

		$sales = $this->db->get()->result_array();

		// At this point in time refunds are assumed to be cash refunds.
		$total_cash_refund = 0;
		foreach($sales as $key => $sale_summary)
		{
			if($sale_summary['trans_refunded'] <> 0)
			{
				$total_cash_refund += $sale_summary['trans_refunded'];
			}
		}

		$share = $location_filter_active ? ' * loc_share.share' : '';

		$select = '\'' . $this->lang->line('reports_trans_payments') . '\' AS trans_group, ';
		$select .= 'sales_payments.payment_type as trans_type, ';
		$select .= 'COUNT(sales.sale_id) AS trans_sales, ';
		$select .= 'SUM((payment_amount - cash_refund)' . $share . ') AS trans_amount,';
		$select .= 'SUM(payment_amount' . $share . ') AS trans_payments,';
		$select .= 'SUM(cash_refund' . $share . ') AS trans_refunded, ';
		$select .= '0 AS trans_due ';

		$this->db->select($select);
		$this->db->from('sales AS sales');
		$this->db->join('sales_payments AS sales_payments', 'sales.sale_id = sales_payments.sale_id', 'left outer');
		if($location_filter_active)
		{
			$this->db->join('sumpay_location_share AS loc_share', 'sales.sale_id = loc_share.sale_id', 'inner');
		}
		$this->db->where('sales.sale_status', COMPLETED);
		$this->_apply_date_filter($inputs);

		$this->db->group_by('sales_payments.payment_type');

		$payments = $this->db->get()->result_array();

		// consider Gift Card as only one type of payment and do not show "Gift Card: 1, Gift Card: 2, etc." in the total
		$gift_card_count = 0;
		$gift_card_amount = 0;
		foreach($payments as $key => $payment)
		{
			if(strstr($payment['trans_type'], $this->lang->line('sales_giftcard')) !== FALSE)
			{
				$gift_card_count  += $payment['trans_sales'];
				$gift_card_amount += $payment['trans_amount'];

				// Remove the "Gift Card: 1", "Gift Card: 2", etc. payment string
				unset($payments[$key]);
			}
		}

		if($gift_card_count > 0)
		{
			$payments[] = array('trans_group' => $this->lang->line('reports_trans_payments'), 'trans_type' => $this->lang->line('sales_giftcard'), 'trans_sales' => $gift_card_count,
				'trans_amount' => $gift_card_amount, 'trans_payments' => $gift_card_amount, 'trans_refunded' => 0, 'trans_due' => 0);
		}

		return array_merge($sales, $separator, $payments);
	}

	protected function create_summary_payments_temp_tables($where, $location_id = 'all')
	{
		$decimals = totals_decimals();

		$line_amount = 'CASE WHEN sales_items.discount_type = ' . PERCENT
			. " THEN sales_items.quantity_purchased * sales_items.item_unit_price - ROUND(sales_items.quantity_purchased * sales_items.item_unit_price * sales_items.discount / 100, $decimals) "
			. ' ELSE sales_items.quantity_purchased * (sales_items.item_unit_price - sales_items.discount) END';

		$location_filter_active = ($location_id !== 'all');
		$loc = (int)$location_id;

		// When a specific location is chosen, build a per-sale share fraction:
		//   share = (sum of line amounts at this location) / (sum of all line amounts)
		// All other temp tables INNER JOIN this share table so sales with no item
		// at the location are dropped, and remaining $ values are scaled proportionally.
		if($location_filter_active)
		{
			$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('sumpay_location_share') .
				' (PRIMARY KEY(sale_id)) ENGINE=MEMORY
				(
					SELECT sales.sale_id,
						SUM(CASE WHEN sales_items.item_location = ' . $loc . ' THEN ' . $line_amount . ' ELSE 0 END) /
						NULLIF(SUM(' . $line_amount . '), 0) AS share
					FROM ' . $this->db->dbprefix('sales') . ' AS sales
					INNER JOIN ' . $this->db->dbprefix('sales_items') . ' AS sales_items
						ON sales.sale_id = sales_items.sale_id
					WHERE ' . $where . '
					GROUP BY sales.sale_id
					HAVING share > 0
				)'
			);
		}

		$taxes_select = $location_filter_active
			? 'SUM(sales_taxes.sale_tax_amount * loc_share.share) AS total_taxes'
			: 'SUM(sales_taxes.sale_tax_amount) AS total_taxes';
		$taxes_join = $location_filter_active
			? 'INNER JOIN ' . $this->db->dbprefix('sumpay_location_share') . ' AS loc_share ON sales.sale_id = loc_share.sale_id '
			: '';

		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('sumpay_taxes_temp') .
			' (INDEX(sale_id)) ENGINE=MEMORY
			(
				SELECT sales.sale_id, ' . $taxes_select . '
				FROM ' . $this->db->dbprefix('sales') . ' AS sales
				LEFT OUTER JOIN ' . $this->db->dbprefix('sales_taxes') . ' AS sales_taxes
					ON sales.sale_id = sales_taxes.sale_id
				' . $taxes_join . '
				WHERE ' . $where . ' AND sales_taxes.tax_type = \'1\'
				GROUP BY sale_id
			)'
		);

		// Items temp: when filter active, sum only line amounts at the chosen location
		// (numerically equivalent to multiplying by share, no extra join needed here).
		$items_amount = $location_filter_active
			? 'SUM(CASE WHEN sales_items.item_location = ' . $loc . ' THEN ' . $line_amount . ' ELSE 0 END) AS trans_amount'
			: 'SUM(' . $line_amount . ') AS trans_amount';

		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('sumpay_items_temp') .
			' (INDEX(sale_id)) ENGINE=MEMORY
			(
				SELECT sales.sale_id, ' . $items_amount
			. ' FROM ' . $this->db->dbprefix('sales') . ' AS sales '
			. 'LEFT OUTER JOIN ' . $this->db->dbprefix('sales_items') . ' AS sales_items '
			. 'ON sales.sale_id = sales_items.sale_id '
			. 'LEFT OUTER JOIN ' . $this->db->dbprefix('sumpay_taxes_temp') . ' AS sumpay_taxes '
			. 'ON sales.sale_id = sumpay_taxes.sale_id '
			. 'WHERE ' . $where . ' GROUP BY sale_id
			)'
		);

		$this->db->query('UPDATE ' . $this->db->dbprefix('sumpay_items_temp') . ' AS sumpay_items '
			. 'SET trans_amount = trans_amount + IFNULL((SELECT total_taxes FROM ' . $this->db->dbprefix('sumpay_taxes_temp')
			. ' AS sumpay_taxes WHERE sumpay_items.sale_id = sumpay_taxes.sale_id),0)');

		$share_factor   = $location_filter_active ? ' * loc_share.share' : '';
		$payments_join  = $location_filter_active
			? 'INNER JOIN ' . $this->db->dbprefix('sumpay_location_share') . ' AS loc_share ON sales.sale_id = loc_share.sale_id '
			: '';

		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('sumpay_payments_temp') .
			' (INDEX(sale_id)) ENGINE=MEMORY
			(
				SELECT sales.sale_id, COUNT(sales.sale_id) AS number_payments,
				SUM(CASE WHEN sales_payments.cash_adjustment = 0 THEN sales_payments.payment_amount' . $share_factor . ' ELSE 0 END) AS total_payments,
				SUM(CASE WHEN sales_payments.cash_adjustment = 1 THEN sales_payments.payment_amount' . $share_factor . ' ELSE 0 END) AS total_cash_adjustment,
				SUM(sales_payments.cash_refund' . $share_factor . ') AS total_cash_refund
				FROM ' . $this->db->dbprefix('sales') . ' AS sales
				LEFT OUTER JOIN ' . $this->db->dbprefix('sales_payments') . ' AS sales_payments
					ON sales.sale_id = sales_payments.sale_id
				' . $payments_join . '
				WHERE ' . $where . '
				GROUP BY sale_id
			)'
		);
	}
}
?>
