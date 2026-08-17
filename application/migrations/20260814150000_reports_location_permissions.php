<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/*
 Gives the reports module its own per-stock-location permissions, so that what an account can see in
 reports no longer piggybacks on the locations it can sell/receive/stock in.

 Existing accounts are seeded from the union of their sales_/items_/receivings_ location grants, which
 is what the report screens used to read, so nobody loses visibility until an admin narrows it down.
*/
class Migration_reports_location_permissions extends CI_Migration
{
	public function __construct()
	{
		parent::__construct();
	}

	public function up()
	{
		$this->db->trans_start();

		$locations = $this->db->get_where('stock_locations', array('deleted' => 0))->result_array();

		foreach($locations as $location)
		{
			$permission_id = REPORTS_LOCATION_PREFIX . '_' . str_replace(' ', '_', $location['location_name']);

			if($this->db->get_where('permissions', array('permission_id' => $permission_id))->num_rows() === 0)
			{
				$this->db->insert('permissions', array(
					'permission_id' => $permission_id,
					'module_id' => 'reports',
					'location_id' => $location['location_id']));
			}

			foreach($this->get_seed_person_ids($location['location_id']) as $person_id)
			{
				if($this->db->get_where('grants', array('permission_id' => $permission_id, 'person_id' => $person_id))->num_rows() === 0)
				{
					$this->db->insert('grants', array(
						'permission_id' => $permission_id,
						'person_id' => $person_id,
						'menu_group' => '--'));
				}
			}
		}

		$this->db->trans_complete();
	}

	// Everyone holding any location-scoped grant for this location, whichever module it belongs to
	private function get_seed_person_ids($location_id)
	{
		$this->db->distinct();
		$this->db->select('grants.person_id');
		$this->db->from('permissions');
		$this->db->join('grants AS grants', 'grants.permission_id = permissions.permission_id');
		$this->db->where('permissions.location_id', $location_id);
		$this->db->where_in('permissions.module_id', array('sales', 'items', 'receivings'));

		$person_ids = array();
		foreach($this->db->get()->result_array() as $grant)
		{
			$person_ids[] = $grant['person_id'];
		}

		return $person_ids;
	}

	public function down()
	{
		$this->db->like('permission_id', REPORTS_LOCATION_PREFIX, 'after');
		$this->db->delete('permissions');
	}
}
?>
