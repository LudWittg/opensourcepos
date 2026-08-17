<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Stock_location class
 */

class Stock_location extends CI_Model
{
	public function exists($location_id = -1)
	{
		$this->db->from('stock_locations');
		$this->db->where('location_id', $location_id);

		return ($this->db->get()->num_rows() >= 1);
	}

	public function get_all()
	{
		$this->db->from('stock_locations');
		$this->db->where('deleted', 0);

		return $this->db->get();
	}

	public function get_undeleted_all($module_id = 'items')
	{
		$this->db->from('stock_locations');
		$this->db->join('permissions AS permissions', 'permissions.location_id = stock_locations.location_id');
		$this->db->join('grants AS grants', 'grants.permission_id = permissions.permission_id');
		$this->db->where('person_id', $this->session->userdata('person_id'));
		$this->db->like('permissions.permission_id', $module_id, 'after');
		$this->db->where('deleted', 0);

		return $this->db->get();
	}

	public function show_locations($module_id = 'items')
	{
		$stock_locations = $this->get_allowed_locations($module_id);

		return count($stock_locations) > 1;
	}

	public function multiple_locations()
	{
		return $this->get_all()->num_rows() > 1;
	}

	public function get_allowed_locations($module_id = 'items')
	{
		$stock = $this->get_undeleted_all($module_id)->result_array();
		$stock_locations = array();
		foreach($stock as $location_data)
		{
			$stock_locations[$location_data['location_id']] = $location_data['location_name'];
		}

		return $stock_locations;
	}

	public function is_allowed_location($location_id, $module_id = 'items')
	{
		$this->db->from('stock_locations');
		$this->db->join('permissions AS permissions', 'permissions.location_id = stock_locations.location_id');
		$this->db->join('grants AS grants', 'grants.permission_id = permissions.permission_id');
		$this->db->where('person_id', $this->session->userdata('person_id'));
		$this->db->like('permissions.permission_id', $module_id, 'after');
		$this->db->where('stock_locations.location_id', $location_id);
		$this->db->where('deleted', 0);

		return ($this->db->get()->num_rows() == 1);
	}

	public function get_default_location_id($module_id = 'items')
	{
		$this->db->from('stock_locations');
		$this->db->join('permissions AS permissions', 'permissions.location_id = stock_locations.location_id');
		$this->db->join('grants AS grants', 'grants.permission_id = permissions.permission_id');
		$this->db->where('person_id', $this->session->userdata('person_id'));
		$this->db->like('permissions.permission_id', $module_id, 'after');
		$this->db->where('deleted', 0);
		$this->db->limit(1);

		return $this->db->get()->row()->location_id;
	}

	public function get_location_name($location_id)
	{
		$this->db->from('stock_locations');
		$this->db->where('location_id', $location_id);

		return $this->db->get()->row()->location_name;
	}

	public function get_location_id($location_name)
	{
		$this->db->from('stock_locations');
		$this->db->where('location_name', $location_name);

		return $this->db->get()->row()->location_id;
	}

	public function save(&$location_data, $location_id)
	{
		$location_name = $location_data['location_name'];

		$location_data_to_save = array('location_name' => $location_name, 'deleted' => 0);

		if(!$this->exists($location_id))
		{
			$this->db->trans_start();

			$this->db->insert('stock_locations', $location_data_to_save);
 			$location_id = $this->db->insert_id();

			$this->_insert_new_permission('items', $location_id, $location_name);
			$this->_insert_new_permission('sales', $location_id, $location_name);
			$this->_insert_new_permission('receivings', $location_id, $location_name);
			$this->_insert_new_permission('reports', $location_id, $location_name, REPORTS_LOCATION_PREFIX);

			// insert quantities for existing items
			$items = $this->Item->get_all();
			foreach($items->result_array() as $item)
			{
				$quantity_data = array('item_id' => $item['item_id'], 'location_id' => $location_id, 'quantity' => 0);
				$this->db->insert('item_quantities', $quantity_data);
			}

			$this->db->trans_complete();

			return $this->db->trans_status();
		}

		$original_location_name = $this->get_location_name($location_id);

		if($original_location_name != $location_name)
		{
			// The permission ids embed the location name, so they have to be recreated on rename.
			// Capture who held each of them first: deleting the permissions cascades the grants away,
			// and re-granting to every employee would silently widen everyone's access.
			$existing_grants = $this->get_location_grants($location_id);

			$this->db->where('location_id', $location_id);
			$this->db->delete('permissions');

			$this->_insert_new_permission('items', $location_id, $location_name, NULL, $existing_grants);
			$this->_insert_new_permission('sales', $location_id, $location_name, NULL, $existing_grants);
			$this->_insert_new_permission('receivings', $location_id, $location_name, NULL, $existing_grants);
			$this->_insert_new_permission('reports', $location_id, $location_name, REPORTS_LOCATION_PREFIX, $existing_grants);
		}

		$this->db->where('location_id', $location_id);

		return $this->db->update('stock_locations', $location_data_to_save);
	}

	/*
	 Permission ids for a module that are scoped to a stock location, e.g. sales_Bottega or
	 reports_location_Bottega. They all carry a non-null location_id, unlike reports_sales & co.
	 */
	public function get_location_permission_ids($module_id)
	{
		$this->db->select('permission_id');
		$this->db->from('permissions');
		$this->db->where('module_id', $module_id);
		$this->db->where('location_id IS NOT NULL', NULL, FALSE);

		$permission_ids = array();
		foreach($this->db->get()->result_array() as $permission)
		{
			$permission_ids[] = $permission['permission_id'];
		}

		return $permission_ids;
	}

	/*
	 Who currently holds the location-scoped permissions of a location, keyed by module id:
	 array('sales' => array(person_id => menu_group, ...), ...)
	 */
	public function get_location_grants($location_id)
	{
		$this->db->select('permissions.module_id, grants.person_id, grants.menu_group');
		$this->db->from('permissions');
		$this->db->join('grants AS grants', 'grants.permission_id = permissions.permission_id');
		$this->db->where('permissions.location_id', $location_id);

		$location_grants = array();
		foreach($this->db->get()->result_array() as $grant)
		{
			$location_grants[$grant['module_id']][$grant['person_id']] = $grant['menu_group'];
		}

		return $location_grants;
	}

	private function _insert_new_permission($module, $location_id, $location_name, $prefix = NULL, $existing_grants = NULL)
	{
		// insert new permission for stock location
		$prefix = ($prefix === NULL) ? $module : $prefix;
		$permission_id = $prefix . '_' . str_replace(' ', '_', $location_name);
		$permission_data = array('permission_id' => $permission_id, 'module_id' => $module, 'location_id' => $location_id);
		$this->db->insert('permissions', $permission_data);

		// On rename the permission id changes, so restore the grants it had rather than granting it to everyone
		if($existing_grants !== NULL)
		{
			$module_grants = isset($existing_grants[$module]) ? $existing_grants[$module] : array();
			foreach($module_grants as $person_id => $menu_group)
			{
				$grants_data = array('permission_id' => $permission_id, 'person_id' => $person_id, 'menu_group' => $menu_group);
				$this->db->insert('grants', $grants_data);
			}

			return;
		}

		// insert grants for new permission
		$employees = $this->Employee->get_all();
		foreach($employees->result_array() as $employee)
		{
			// Retrieve the menu_group assigned to the grant for the module and use that for the new stock locations
			$menu_group = $this->Employee->get_menu_group($module, $employee['person_id']);

			$grants_data = array('permission_id' => $permission_id, 'person_id' => $employee['person_id'], 'menu_group' => $menu_group);
			$this->db->insert('grants', $grants_data);
		}
	}

	/*
	 Deletes one item
	 */
	public function delete($location_id)
	{
		$this->db->trans_start();

		$this->db->where('location_id', $location_id);
		$this->db->update('stock_locations', array('deleted' => 1));

		$this->db->where('location_id', $location_id);
		$this->db->delete('permissions');

		$this->db->trans_complete();

		return $this->db->trans_status();
	}
}
?>
