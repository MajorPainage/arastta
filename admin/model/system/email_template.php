<?php
/**
 * @package		Arastta eCommerce
 * @copyright	Copyright (C) 2015 Arastta Association. All rights reserved. (arastta.org)
 * @license		GNU General Public License version 3; see LICENSE.txt
 */

class ModelSystemEmailtemplate extends Model {

	public function editEmailTemplate($email_template, $data) {
		$this->trigger->fire('pre.admin.emailTempalte.edit', $data);

	    $email_template = $this->request->get['email_template'];
		
		$item = explode("_", $email_template);
		
		$sql  = "SELECT id FROM " . DB_PREFIX . "email WHERE type = '" . $item[0] ."' AND text_id = '" . (int)$item[1] ."'";
		$query = $this->db->query($sql);
		$email_id = $query->row['id'];

		$sql  = "SELECT id FROM " . DB_PREFIX . "email_description WHERE email_id = '" . (int)$email_id . "'";
		$query = $this->db->query($sql);
		
		if(!empty($query->row)) {
			foreach ($data['email_template_description'] as $language_id => $value) {
				$sql  = "UPDATE " . DB_PREFIX . "email_description SET";
				$sql .= " name = '". $this->db->escape($value['name']) . "', description = '". $this->db->escape($value['description']) . "'";
				$sql .= " WHERE email_id = '" . (int)$email_id . "' AND language_id = '" . (int)$language_id . "' ";
				
				$this->db->query($sql);
			}
		} else {
			foreach ($data['email_template_description'] as $language_id => $value) {
				$sql  = "INSERT INTO " . DB_PREFIX . "email_description SET";
				$sql .= " email_id = '" . (int)$email_id . "', name = '". $this->db->escape($value['name']) . "', description = '". $this->db->escape($value['description']) . "',";
				$sql .= " status = '1', language_id = '". (int)$language_id . "'";				
				$this->db->query($sql);
			}	
		}

		$this->trigger->fire('post.admin.emailTempalte.edit', $email_template);
	}

	public function getEmailTempalte($email_template) {
		$item = explode("_", $email_template);
		
		$sql  = "SELECT * FROM " . DB_PREFIX . "email AS e";
		$sql .= " LEFT JOIN " . DB_PREFIX . "email_description AS ed ON ed.email_id = e.id";
		$sql .= " WHERE e.type = '{$item[0]}' AND e.text_id = '{$item[1]}'";
		
		$query = $this->db->query($sql);
		
		foreach ($query->rows as $result) {
			$email_tempalte_data[$result['language_id']] = array(
				'text'         => $result['text'],
				'text_id'      => $result['text_id'],
				'type'         => $result['type'],
				'context'      => $result['context'],
				'name'         => $result['name'],
				'description'  => $result['description'],
				'status'       => $result['status']
			);
		}

		return $email_tempalte_data;
	}

	public function getEmailTempaltes($data = array()) {
		$sql = "SELECT * FROM `" . DB_PREFIX . "email` AS e";
		
		$isWhere = 0;
		$_sql = array();
		
		if (isset($data['filter_name']) && !is_null($data['filter_name'])) {
			$isWhere = 1;
			
			$sql .= " LEFT JOIN `" . DB_PREFIX . "email_description` AS ed ON e.id = ed.email_id ";
			$_sql[] = "ed.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}
		
		if (isset($data['filter_text']) && !is_null($data['filter_text'])) {
			$isWhere = 1;
			
			$_sql[] = "e.text LIKE '" . $this->db->escape($data['filter_text']) . "%'";
		}

		if (isset($data['filter_context']) && !is_null($data['filter_context'])) {
			$isWhere = 1;
			
			$_sql[] = "e.context LIKE '" . $this->db->escape($data['filter_context']) . "%'";
		}

		if (isset($data['filter_type']) && !is_null($data['filter_type'])) {
			$isWhere = 1;
			
			$filterType = $this->_getEmailTypes( $data['filter_type'] );
			
			$_sql[] = "e.type = '" . $this->db->escape($filterType) . "'";
		}
				
		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$isWhere = 1;
			
			$_sql[] = "e.status LIKE '" . $this->db->escape($data['filter_status']) . "%'";
		}

		if($isWhere) {
			$sql .= " WHERE " . implode(" AND ", $_sql);
		}


		$sort_data = array(
			'name',
			'sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY e.type";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}


		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

        if(!empty($query->num_rows)){
            foreach($query->rows as $key => $email_temp){
                if($email_temp['type'] == 'order') {
                    $_result = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_status` WHERE order_status_id ='". $email_temp['text_id'] ."' AND language_id ='" . $this->config->get('config_language_id') ."'") ;
                    if(!empty($_result->num_rows) && !empty($_result->row['name'])) {
                        $query->rows[$key]['text'] = $_result->row['name'];
                    }
                }
            }
        }

		$result = $query->rows;

		return $result;
	}
	
	protected function _getEmailTypes( $item ) {
		$result = array ( 'order', 'customer', 'affiliate', 'Contact', 'contact', 'cron', 'mail' );
		if($item < 1  || $item > 7) {
			$item = 1;
		}
		return $result[$item-1];
	}	
	
	public function getEmailTempaltesStores($email_template) {
		$manufacturer_store_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "manufacturer_to_store WHERE email_template = '" . (int)$email_template . "'");

		foreach ($query->rows as $result) {
			$manufacturer_store_data[] = $result['store_id'];
		}

		return $manufacturer_store_data;
	}

	public function getTotalEmailTempaltes( $data ) {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "email AS e";

		$isWhere = 0;
		$_sql = array();
		
		if (isset($data['filter_name']) && !is_null($data['filter_name'])) {
			$isWhere = 1;
			
			$sql .= " LEFT JOIN `" . DB_PREFIX . "email_description` AS ed ON e.id = ed.email_id ";
			$_sql[] = "ed.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}
		
		if (isset($data['filter_text']) && !is_null($data['filter_text'])) {
			$isWhere = 1;
			
			$_sql[] = "e.text LIKE '" . $this->db->escape($data['filter_text']) . "%'";
		}

		if (isset($data['filter_context']) && !is_null($data['filter_context'])) {
			$isWhere = 1;
			
			$_sql[] = "e.context LIKE '" . $this->db->escape($data['filter_context']) . "%'";
		}

		if (isset($data['filter_type']) && !is_null($data['filter_type'])) {
			$isWhere = 1;
			
			$filterType = $this->_getEmailTypes( $data['filter_type'] );
			
			$_sql[] = "e.type = '" . $this->db->escape($filterType) . "'";
		}
				
		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$isWhere = 1;
			
			$_sql[] = "e.status LIKE '" . $this->db->escape($data['filter_status']) . "%'";
		}
				
		if($isWhere) {
			$sql .= " WHERE " . implode(" AND ", $_sql);
		}				

		$query = $this->db->query($sql);	
		
		return $query->row['total'];
	}
}