<?php
#  Copyright 2003-2015 Opmantek Limited (www.opmantek.com)
#
#  ALL CODE MODIFICATIONS MUST BE SENT TO CODE@OPMANTEK.COM
#
#  This file is part of Open-AudIT.
#
#  Open-AudIT is free software: you can redistribute it and/or modify
#  it under the terms of the GNU Affero General Public License as published
#  by the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#
#  Open-AudIT is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU Affero General Public License for more details.
#
#  You should have received a copy of the GNU Affero General Public License
#  along with Open-AudIT (most likely in a file named LICENSE).
#  If not, see <http://www.gnu.org/licenses/>
#
#  For further information on Open-AudIT or for a license other than AGPL please see
#  www.opmantek.com or email contact@opmantek.com
#
# *****************************************************************************

/**
* @category  Model
* @package   Open-AudIT
* @author    Mark Unwin <marku@opmantek.com>
* @copyright 2014 Opmantek
* @license   http://www.gnu.org/licenses/agpl-3.0.html aGPL v3
* @version   3.2.2
* @link      http://www.open-audit.org
 */
class M_networks extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->log = new stdClass();
        $this->log->severity = 7;
        $this->log->status = 'reading data';
        $this->log->type = 'system';
    }

    public function read($id = '')
    {
        $this->log->function = strtolower(__METHOD__);
        stdlog($this->log);
        $CI = & get_instance();
        if ($id == '') {
            $id = intval($CI->response->meta->id);
        } else {
            $id = intval($id);
        }
        $sql = "SELECT networks.*, COUNT(DISTINCT system.id) as `device_count`, orgs.id AS `orgs.id`, orgs.name AS `org_name`, clouds.id AS `clouds.id`, clouds.name AS `clouds.name` FROM networks LEFT JOIN ip ON (networks.network = ip.network) LEFT JOIN system ON (system.id = ip.system_id) LEFT JOIN orgs ON (networks.org_id = orgs.id) LEFT JOIN clouds ON (networks.cloud_id = clouds.id) WHERE networks.id = ?";
        $data = array(intval($id));
        $result = $this->run_sql($sql, $data);
        $result = $this->format_data($result, 'networks');
        return $result;
    }

    public function delete($id = '')
    {
        $this->log->function = strtolower(__METHOD__);
        $this->log->status = 'deleting data';
        stdlog($this->log);
        if ($id == '') {
            $CI = & get_instance();
            $id = intval($CI->response->meta->id);
        } else {
            $id = intval($id);
        }
        $CI = & get_instance();
        $sql = "DELETE FROM `networks` WHERE id = ?";
        $data = array(intval($id));
        $this->run_sql($sql, $data);
        return true;
    }

    public function sub_resource($id = '')
    {
        $this->log->function = strtolower(__METHOD__);
        stdlog($this->log);
        if ($id == '') {
            $CI = & get_instance();
            $id = intval($CI->response->meta->id);
        } else {
            $id = intval($id);
        }
        $sql = "SELECT `network` FROM `networks` WHERE `id` = ?";
        $data = array($id);
        $result = $this->run_sql($sql, $data);
        if (count($result) > 0) {
            $network = $result[0]->network;
            if ($network != '') {
                $sql = "SELECT system.id AS `system.id`, system.icon AS `system.icon`, system.type AS `system.type`, system.name AS `system.name`, system.domain AS `system.domain`, ip.ip AS `ip.ip`, system.description AS `system.description`, system.os_family AS `system.os_family`, system.status AS `system.status` FROM system LEFT JOIN ip ON (system.id = ip.system_id AND ip.current = 'y') WHERE ip.network = ?";
                $data = array((string)$network);
                $result = $this->run_sql($sql, $data);
                $result = $this->format_data($result, 'devices');
                return $result;
            } else {
                return array();
            }
        } else {
            return array();
        }
    }

    public function upsert($network = null)
    {
        $router = & load_class('Router', 'core');
        $model = $router->fetch_class();
        $function = $router->fetch_method();
        $this->log->function = strtolower(__METHOD__);
        $this->log->status = 'upserting data';
        if (is_null($network)) {
            return false;
        }
        if (empty($network->network) and !empty($network->name)) {
            $network->network = $network->name;
        }
        if (empty($network->network)) {
            return false;
        }
        if (empty($network->name)) {
            $network->name = $network->network;
        }
        if (empty($network->org_id)) {
            $network->org_id = 1;
        }
        if (!empty($this->config->config['internal_version']) and $this->config->config['internal_version'] < 20160904) {
            return;
        }
        $sql = "SELECT * FROM networks WHERE networks.org_id = ? AND networks.network = ?";
        $data = array(intval($network->org_id), (string)$network->network);
        $result = $this->run_sql($sql, $data);
        # Note we receive false back from run_sql if it's a select and no rows are returned.
        if ($result == false) {
            # the network does not exist. Log it and insert it
            if (!empty($network->description)) {
                $description = $network->description;
                $this->log->summary = "Inserting " . $network->name . ' (' . $network->network . ') into blessed subnet list.';
            } else {
                $description = '';
                $this->log->summary = "Inserting " . $network->name . ' (' . $network->network . ') into blessed subnet list.';
            }
            $sql = "INSERT INTO `networks` (id, name, network, org_id, type, description, edited_by, edited_date) VALUES (NULL, ?, ?, ?, 'Local Area Network', ?, ?, NOW())";
            $data = array((string)$network->name, (string)$network->network, intval($network->org_id), (string)$description, 'auto-generated by '.@$model.'::'.@$function);
            $result = $this->run_sql($sql, $data);
            return true;
        } else {
            return false;
        }
    }

    # supply a standard ip address - 192.168.1.1
    # supply a list of comma separated subnets - 192.168.1.0/24,172.16.0.0/16 or an emptty string to retrieve from the DB
    # returns true if ip is contained in a subnet, false otherwise
    # TODO - we should take an OrgID (or 1 if not exists)
    public function check_ip($ip_address = '')
    {
        $this->log->function = strtolower(__METHOD__);
        stdlog($this->log);
        if (empty($this->config)) {
            $this->load->model('m_configuration');
            $this->m_configuration->load();
        }
        if (empty($this->config->config['blessed_subnets_use']) or trim(strtolower($this->config->config['blessed_subnets_use'])) != 'y') {
            return true;
        }
        if (empty($ip_address)) {
            return false;
        }
        if ($ip_address === '127.0.0.1' or $ip_address === '127.0.1.1') {
            return true;
        }
        if ($ip_address === '::1') {
            return true;
        }
        # TODO - IPv6 support
        if (stripos($ip_address, ':') !== false) {
            return true;
        }
        $sql = "/* m_networks::check_ip */ " . "SELECT COUNT(id) AS count FROM networks WHERE (-1 << (33 - INSTR(BIN(INET_ATON(cidr_to_mask(SUBSTR(network, LOCATE('/', network)+1)))), '0'))) & INET_ATON(?) = INET_ATON(SUBSTR(network, 1, LOCATE('/', network)-1))";
        $sql = $this->clean_sql($sql);

        $temp_debug = $this->db->db_debug;
        $this->db->db_debug = false;
        $query = $this->db->query($sql, array((string)$ip_address));
        $this->db->db_debug = $temp_debug;

        if ($this->db->_error_message()) {
            # need to log down here for the above so we can use $this->db to get the last insert id
            $db_error = $this->db->_error_message();
            $sqllog = new stdClass();
            $sqllog->function =  'm_networks::check_ip';
            $sqllog->summary = 'check ip failed (allowing submit to continue)';
            $sqllog->type = 'system';
            $sqllog->severity = 3;
            $sqllog->status = 'failure';
            $sqllog->detail = $this->db->last_query();
            $sqllog->detail .= ' - FAILURE - ' . $db_error;
            stdlog($sqllog);
            log_error('ERR-0009', strtolower(@$caller['class'] . '::' . @$caller['function'] . ")"), $db_error);
            return true;
        }

        $result = $query->result();
        if (intval($result[0]->count) > 0) {
            return true;
        } else {
            $this->load->helper('log_helper');
            $log_details = new stdClass();
            $log_details->severity = 5;
            $log_details->file = 'system';
            $log_details->message = 'Audit submission from an IP not in the list of blessed subnets (' . $_SERVER['REMOTE_ADDR'] . ')';
            stdlog($log_details);
            unset($log_details);
            return false;
        }
    }

    public function collection(int $user_id = null, int $response = null)
    {
        $CI = & get_instance();
        if (!empty($user_id)) {
            $org_list = array_unique(array_merge($CI->user->orgs, $CI->m_orgs->get_user_descendants($user_id)));
            $sql = "SELECT * FROM networks WHERE org_id IN (" . implode(',', $org_list) . ")";
            $result = $this->run_sql($sql, array());
            $result = $this->format_data($result, 'networks');
            return $result;
        }
        if (!empty($response)) {
            $total = $this->collection($CI->user->id);
            $CI->response->meta->total = count($total);
            $sql = "SELECT " . $CI->response->meta->internal->properties . ", orgs.id AS `orgs.id`, orgs.name AS `orgs.name`, clouds.id AS `clouds.id`, clouds.name AS `clouds.name`, COUNT(DISTINCT system.id) as `device_count` FROM `networks` LEFT JOIN orgs ON (networks.org_id = orgs.id) LEFT JOIN clouds ON (networks.cloud_id = clouds.id) LEFT JOIN ip ON (networks.network = ip.network AND ip.current = 'y') LEFT JOIN system ON (system.id = ip.system_id) " . $CI->response->meta->internal->filter . " GROUP BY networks.id " . $CI->response->meta->internal->sort . " " . $CI->response->meta->internal->limit;

            if (!empty($CI->response->meta->requestor)) {
            $sql = "SELECT " . $CI->response->meta->internal->properties . ", orgs.id AS `orgs.id`, orgs.name AS `orgs.name`, clouds.id AS `clouds.id`, clouds.name AS `clouds.name`, COUNT(DISTINCT system.id) as `device_count` FROM `networks` LEFT JOIN orgs ON (networks.org_id = orgs.id) LEFT JOIN clouds ON (networks.cloud_id = clouds.id) LEFT JOIN ip ON (networks.network = ip.network AND ip.current = 'y') LEFT JOIN system ON (system.id = ip.system_id AND system.oae_manage = 'y') " . $CI->response->meta->internal->filter . " GROUP BY networks.id " . $CI->response->meta->internal->sort . " " . $CI->response->meta->internal->limit;
            }
            $result = $this->run_sql($sql, array());
            $CI->response->data = $this->format_data($result, 'networks');
            $CI->response->meta->filtered = count($CI->response->data);
        }
    }
}
