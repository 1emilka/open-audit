<?php
/**
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
*
* PHP version 5.3.3
* 
* @category  Model
* @package   Collection
* @author    Mark Unwin <marku@opmantek.com>
* @copyright 2014 Opmantek
* @license   http://www.gnu.org/licenses/agpl-3.0.html aGPL v3
* @version   GIT: Open-AudIT_3.3.0
* @link      http://www.open-audit.org
*/

/**
* Base Model Collection
*
* @access   public
* @category Model
* @package  Collection
* @author   Mark Unwin <marku@opmantek.com>
* @license  http://www.gnu.org/licenses/agpl-3.0.html aGPL v3
* @link     http://www.open-audit.org
 */
class M_collection extends MY_Model
{
    /**
    * Constructor
    *
    * @access public
    */
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('log');
        $this->log = new stdClass();
        $this->log->status = 'reading data';
        $this->log->type = 'system';
        $this->load->library('encrypt');
    }

    public function reset($collection = '')
    {
        $this->log->function = strtolower(__METHOD__);
        $this->log->summary = 'start';
        stdlog($this->log);

        if ($collection === '') {
            log_error('ERR-0010', 'm_collection::collection (no collection)');
            $this->log->summary = 'finish';
            stdlog($this->log);
            return false;
        }

        $temp_debug = $this->db->db_debug;
        $this->db->db_debug = false;

        $sql = "SELECT count(*) AS `count` FROM `$collection`";
        $query = $this->db->query($sql);
        $result = @$query->result();
        if ($this->db->_error_message()) {
            $this->log->severity = 3;
            $this->log->status = 'fail';
            $this->log->summary = 'Query fail';
            $db_error = @$this->db->_error_message();
            $error = '';
            if (!empty($db_error)) {
                $error = 'Error: ' . $db_error . ', ';
            }
            $this->log->detail = $error . 'Query: ' . $this->db->last_query();
            stdlog($this->log);
            log_error('ERR-0009', strtolower(@$caller['class'] . '::' . @$caller['function'] . ")"), $db_error);
            $this->db->db_debug = $temp_debug;
            $this->log->summary = 'finish';
            stdlog($this->log);
            return false;
        }

        $count = intval($result[0]->count);
        if ($count !== 0) {
            $this->log->severity = 3;
            $this->log->status = 'fail';
            $this->log->summary = 'Table not empty';
            $this->log->detail = 'Cannot run reset on ' . $collection . ' as the table still has data.';
            stdlog($this->log);
            $this->db->db_debug = $temp_debug;

            $this->log->severity = 7;
            $this->log->status = '';
            $this->log->detail = '';
            $this->log->summary = 'finish';
            stdlog($this->log);
            return false;
        }

        $sql = "ALTER TABLE `$collection` AUTO_INCREMENT = 1";
        $query = $this->db->query($sql);
        if ($this->db->_error_message()) {
            $this->log->severity = 3;
            $this->log->status = 'failure';
            $this->log->summary = $this->db->last_query();
            $this->log->detail = 'Query fail - ' . @$this->db->_error_message();
            stdlog($this->log);
            log_error('ERR-0009', strtolower(@$caller['class'] . '::' . @$caller['function'] . ")"), $db_error);
            $this->db->db_debug = $temp_debug;
            return false;
        }

        $sql = "OPTIMIZE TABLE `$collection`";
        $query = $this->db->query($sql);
        if ($this->db->_error_message()) {
            $this->log->severity = 3;
            $this->log->status = 'failure';
            $this->log->summary = $this->db->last_query();
            $this->log->detail = 'Query fail - ' . @$this->db->_error_message();
            stdlog($this->log);
            log_error('ERR-0009', strtolower(@$caller['class'] . '::' . @$caller['function'] . ")"), $db_error);
            $this->db->db_debug = $temp_debug;
            return false;
        }

        $this->db->db_debug = $temp_debug;
        $this->log->severity = 7;
        $this->log->detail = $collection . ' table has been reset successfully';
        stdlog($this->log);
        return true;
    }

    public function collection_total($collection)
    {
        $CI = & get_instance();

        if (empty($collection)) {
            $collection = @$CI->response->meta->collection;
        }
        if (empty($collection)) {
            log_error('ERR-0010', 'm_collection::collection_total No collection received.');
            return false;
        }

        if ($collection == 'devices') {
            $collection == 'system';
        }

        $total = 0;

        if ($collection != 'database') {
            if ($collection == 'orgs') {
                # Orgs don't have an org_id, they have an id
                $sql = "SELECT COUNT(*) as `count` FROM `" . $collection . "` WHERE id IN (" . $CI->user->org_list . ")";
            } else if ($collection == 'logs') {
                # logs are special as we have 2x different types
                $type = 'system';
                if (!empty($CI->response->meta->filter)) {
                    foreach ($CI->response->meta->filter as $filter) {
                        if ($filter->name == 'logs.type') {
                            $type = $filter->value;
                        }
                    }
                }
                if ($type != 'system' and $type != 'access') {
                    $type = 'system';
                }
                $sql = "SELECT count(*) AS `count` FROM `logs` WHERE `logs`.`type` = '" . $type . "'";
            } else if ($this->db->field_exists('org_id', $collection)) {
                # Anything else with an org_id
                $sql = "SELECT COUNT(*) as `count` FROM `" . $collection . "` WHERE org_id IN (" . $CI->user->org_list . ")";
            } else {
                # Anythng left that has no org_id
                $sql = "SELECT COUNT(*) as `count` FROM `" . $collection . "`";
            }
            $sql = $this->clean_sql($sql);
            $query = $this->db->query($sql);
            $result = $query->result();
            if (!empty($result[0]->count)) {
                $total = intval($result[0]->count);
            }
        } else {
            $tables = $this->db->list_tables();
            $total = intval(count($tables));
        }
        return $total;
    }

    public function create($data = null, $collection = '')
    {
        $CI = & get_instance();

        if (empty($collection)) {
            $collection = @$CI->response->meta->collection;
        }
        if (empty($collection)) {
            log_error('ERR-0010', 'm_collection::create No collection received.');
            return false;
        }

        if (empty($data)) {
            $data = @$CI->response->meta->received_data->attributes;
        }
        if (empty($data)) {
            log_error('ERR-0010', 'm_collection::create (' . @$collection . ') No attributes received.');
            return false;
        }

        $this->log->function = strtolower(__METHOD__);
        $this->log->status = 'creating data (' . $collection . ')';
        stdlog($this->log);

        if ($collection === 'clouds') {
            if (!empty($data->credentials) and is_string($data->credentials)) {
                $data->credentials = (string)simpleEncrypt($data->credentials);
            } else {
                $data->credentials = (string)simpleEncrypt(json_encode($data->credentials));
            }
        }

        if ($collection === 'credentials') {
            if (!empty($data->credentials) and is_string($data->credentials)) {
                $data->credentials = (string)simpleEncrypt($data->credentials);
            } else {
                $data->credentials = (string)simpleEncrypt(json_encode($data->credentials));
            }
        }

        if ($collection === 'dashboards') {
            if (!empty($CI->response->meta->received_data->attributes->options) and is_string($CI->response->meta->received_data->attributes->options)) {
                $data->options = $CI->response->meta->received_data->attributes->options;
            } else {
                if (empty($CI->response->meta->received_data->attributes->options)) {
                    $options = new stdClass();
                    $options->widget_count = 0;
                    $options->widgets = new stdClass();
                } else {
                    $options = $CI->response->meta->received_data->attributes->options;
                }
                $my_options = new stdClass();
                $my_options->layout = '3x2';
                if (!empty($options->widget_count)) {
                    $my_options->widget_count = intval($options->widget_count);
                } else {
                    $my_options->widget_count = 0;
                }
                $my_options->widgets = array();
                for ($i=1; $i <= $my_options->widget_count; $i++) {
                    $widget = new stdClass();
                    foreach ($options->widgets->$i as $key => $value) {
                        $widget->{$key} = $value;
                    }
                    $my_options->widgets[] = $widget;
                }
                $data->options = json_encode($my_options);
            }
        }
        if ($collection === 'discoveries') {
            if (empty($data->devices_assigned_to_org)) {
                unset($data->devices_assigned_to_org);
            }
            if (empty($data->devices_assigned_to_location)) {
                unset($data->devices_assigned_to_location);
            }

            if (!empty($data->other) and is_string($data->other)) {
                $data->other = json_decode($data->other);
            }

            if (empty($data->other)) {
                $data->other = new stdClass();
            }

            if (empty($data->other->nmap)) {
                $data->other->nmap = new stdClass();
                if (empty($this->config->config['discovery_default_scan_option'])) {
                    $this->config->config['discovery_default_scan_option'] = 1;
                }
                $sql = "SELECT `id` AS 'discovery_scan_option_id', ping, service_version, filtered, timeout, timing, nmap_tcp_ports, nmap_udp_ports, tcp_ports, udp_ports, exclude_tcp_ports, exclude_udp_ports, exclude_ip, ssh_ports FROM discovery_scan_options WHERE id = " . intval($this->config->config['discovery_default_scan_option']);
                $sql = $this->clean_sql($sql);
                $query = $this->db->query($sql);
                $result = $query->result();
                if (!empty($result[0])) {
                    $data->other->nmap = $result[0];
                } else {
                    $json = '{"exclude_ip":"","exclude_tcp_ports":"","exclude_udp_ports":"","filtered":"n","nmap_tcp_ports":"0","nmap_udp_ports":"0","ping":"y","discovery_scan_option_id":"0","service_version":"n","tcp_ports":"22,135,62078","timing":"4","udp_ports":"161","ssh_ports":"22"}';
                    $data->other->nmap = json_decode($json);
                }
            }

            if ($data->type == 'subnet') {
                if (!empty($data->other->subnet) and !preg_match('/^[\d,\.,\/,-]*$/', $data->other->subnet)) {
                    log_error('ERR-0024', 'm_collection::create (discoveries)', 'Invalid field data supplied for subnet');
                    $this->session->set_flashdata('error', 'Discovery could not be created - invalid Subnet supplied.');
                    $data->other->subnet = '';
                    if ($CI->response->meta->format == 'screen') {
                        redirect('/discoveries');
                    } else {
                        output($CI->response);
                        exit();
                    }
                }
                if (empty($data->other->subnet)) {
                    log_error('ERR-0024', 'm_collection::create (discoveries)', 'Missing field: subnet');
                   // $this->session->set_flashdata('error', 'Object in ' . $this->response->meta->collection . ' could not be created - no Subnet supplied.');
                    #redirect('/discoveries');
                } else {
                    $data->description = 'Subnet - ' . $data->other->subnet;
                }
            } elseif ($data->type == 'active directory') {
                if (empty($data->other->ad_server) or empty($data->other->ad_domain)) {
                    $temp = "Active Directory Domain";
                    if (empty($data->other->ad_server)) {
                        $temp = "Active Directory Server";
                    }
                    log_error('ERR-0024', 'm_collection::create (ad discoveries)');
                    $this->session->set_flashdata('error', 'Object in discoveries could not be created - no ' . $temp . ' supplied.');
                    #redirect('/discoveries');
                } else {
                    $data->description = 'Active Directory - ' . $data->other->ad_domain;
                }
            } else {
                $data->description = '';
            }
            $this->load->model('m_networks');
            $this->load->helper('network');

            if ($data->type == 'subnet' and !empty($data->other->subnet) and stripos($data->other->subnet, '-') === false and filter_var($data->other->subnet, FILTER_VALIDATE_IP) !== false) {
                # We have a single IP - ie 192.168.1.1
                # TODO - we should pass the OrgID
                $test = $this->m_networks->check_ip($data->other->subnet);
                if (!$test) {
                    # This IP is not in any existing subnets - insert a /30
                    # TODO - account for Org ID in existing as check_ip returns only true/false, and does not acount for orgs
                    $temp = network_details($data->other->subnet.'/30');
                    $network = new stdClass();
                    $network->name = $temp->network.'/'.$temp->network_slash;
                    $network->network = $temp->network.'/'.$temp->network_slash;
                    $network->org_id = $data->org_id;
                    $network->description = $data->name;
                    $this->m_networks->upsert($network);
                }
            }

            if ($data->type == 'subnet' and !empty($data->other->subnet) and stripos($data->other->subnet, '-') === false and strpos($data->other->subnet, '/') !== false) {
                # We have a regular subnet - ie 192.168.1.0/24
                $temp = network_details($data->other->subnet);
                if (!empty($temp->error)) {
                    $this->session->set_flashdata('error', 'Object in ' . $this->response->meta->collection . ' could not be created - invalid subnet attribute supplied.');
                    log_error('ERR-0010', 'm_collections::create (networks) invalid subnet supplied');
                    return;
                }
                $network = new stdClass();
                $network->name = $temp->network.'/'.$temp->network_slash;
                $network->network = $temp->network.'/'.$temp->network_slash;
                $network->org_id = $data->org_id;
                $network->description = $data->name;
                $this->m_networks->upsert($network);
            }

            if ($data->type == 'subnet' and stripos($data->other->subnet, '-') !== false) {
                # We have a range and cannot insert a network
                $warning = 'IP range, instead of subnet supplied. No network entry created.';
                if ($this->config->config['blessed_subnets_use'] != 'n') {
                    $warning .= '<br />Because you are using blessed subnets, please ensure a valid network for this range exists.';
                }
                $this->session->set_flashdata('warning', $warning);
            }
            $data->other = json_encode($data->other);
        }

        if ($collection === 'integrations') {
            if (!empty($data->options)) {
                $data->options = json_encode($data->options);
            }
        }

        if ($collection === 'ldap_servers') {
            if (!empty($data->dn_password)) {
                $data->dn_password = (string)simpleEncrypt($data->dn_password);
            }
        }

        if ($collection === 'orgs') {
            if (!empty($data->name)) {
                $data->ad_group = 'open-audit_orgs_' . strtolower(str_replace(' ', '_', $data->name));
            }
        }

        if ($collection === 'rack_devices') {
            $sql = "SELECT name, org_id FROM system WHERE id = " . intval($data->system_id);
            $sql = $this->clean_sql($sql);
            $query = $this->db->query($sql);
            $result = $query->result();
            if (!empty($result)) {
                $data->name = $result[0]->name;
                $data->org_id = $result[0]->org_id;
            }
        }

        if ($collection === 'roles') {
            $data->ad_group = 'open-audit_roles_' . strtolower(str_replace(' ', '_', $data->name));
            if (empty($data->permissions)) {
                # No permissions
                $data->permissions = new stdClass();
                $data->permissions = json_encode($data->permissions);
            } else if (!empty($data->permissions) and gettype($data->permissions) === 'string') {
                # We have a CSV submitted item
                # Replace quotes as it should already be stringified JSON
                $item->permissions = str_replace("'", '"', $item->permissions);
            } else if (!empty($data->permissions) and gettype($data->permissions) === 'object') {
                # We have a submitted form
                # Build up our permissions
                $permissions = new stdClass();
                foreach ($data->permissions as $endpoint => $object) {
                    $permissions->{$endpoint} = '';
                    foreach ($object as $key => $value) {
                        $permissions->{$endpoint} .= $key;
                    }
                }
                $data->permissions = json_encode($permissions);
            }
        }

        if ($collection === 'rules') {
            if (is_array($data->inputs) or is_object($data->inputs)) {
                $new_inputs = array();
                foreach ($data->inputs as $input) {
                    $item = new stdClass();
                    foreach ($input as $key => $value) {
                        $item->{$key} = $value;
                    }
                    $new_inputs[] = $item;
                }
                $data->inputs = json_encode($new_inputs);
            }

            if (is_array($data->outputs) or is_object($data->outputs)) {
                $new_outputs = array();
                foreach ($data->outputs as $output) {
                    $item = new stdClass();
                    foreach ($output as $key => $value) {
                        $item->{$key} = $value;
                    }
                    $new_outputs[] = $item;
                }
                $data->outputs = json_encode($new_outputs);
            }
        }

        if ($collection === 'scripts') {
            if (empty($data->options) and !empty($CI->response->meta->received_data->options)) {
                $data->options = $CI->response->meta->received_data->options;
            }
            if (!is_string($data->options)) {
                $data->options = json_encode($data->options);
            }
        }

        if ($collection === 'tasks') {
            if (empty($data->options) and !empty($CI->response->meta->received_data->options)) {
                $data->options = $CI->response->meta->received_data->options;
            }
            if (!empty($data->options)) {
                if (gettype($data->options) == 'string') {
                    $data->options = str_replace('\"', '"', $data->options);
                    $data->options = my_json_decode($data->options);
                }
                $data->options = json_encode($data->options);
            } else {
                $data->options = '';
            }
            if (!empty($data->minute) and is_array($data->minute)) {
                $data->minute = implode(',', $data->minute);
            }
            if (!empty($data->hour) and is_array($data->hour)) {
                $data->hour = implode(',', $data->hour);
            }
            if (!empty($data->day_of_month) and is_array($data->day_of_month)) {
                $data->day_of_month = implode(',', $data->day_of_month);
            }
            if (!empty($data->month) and is_array($data->month)) {
                $data->month = implode(',', $data->month);
            }
            if (!empty($data->day_of_week) and is_array($data->day_of_week)) {
                $data->day_of_week = implode(',', $data->day_of_week);
            }
            if (empty($data->uuid)) {
                $data->uuid = $this->config->config['uuid'];
            }
        }

        if ($collection === 'users') {
            if (!empty($data->password)) {
                set_include_path($CI->config->config['base_path'] . '/code_igniter/application/third_party/random_compat');
                require_once "lib/random.php";
                $salt = bin2hex(random_bytes(32));
                $data->password = $salt.hash("sha256", $salt.(string)$data->password);
                unset($salt);
            }
        }

        $id = $this->insert_collection($collection, $data);

        if (!empty($id) and $collection == 'locations') {
            # Need to insert default entries for buildings, floors, rooms and rows
            $org_id = 1;
            if (!empty($data->attributes->org_id)) {
                $org_id = intval($data->attributes->org_id);
            }
            $location_id = $id;
            $sql = "INSERT INTO `buildings` VALUES (NULL, 'Default Building', ?, ?, 'The default entry for a building at this location.', '', '', '', ?, NOW())";
            $data_array = array($org_id, $location_id, $CI->user->full_name);
            $building_id = intval($this->run_sql($sql, $data_array));

            $sql = "INSERT INTO `floors` VALUES (NULL, 'Ground Floor', ?, ?, 'The default entry for a floor at this location.', '', '', '', ?, NOW())";
            $data_array = array($org_id, $building_id, $CI->user->full_name);
            $floor_id = intval($this->run_sql($sql, $data_array));

            $sql = "INSERT INTO `rooms` VALUES (NULL, 'Default Room', ?, ?, 'The default entry for a room at this location.', '', '', '', ?, NOW())";
            $data_array = array($org_id, $floor_id, $CI->user->full_name);
            $room_id = intval($this->run_sql($sql, $data_array));

            $sql = "INSERT INTO `rows` VALUES (NULL, 'Default Row', ?, ?, 'The default entry for a row at this location.', '', '', '', ?, NOW())";
            $data_array = array($org_id, $room_id, $CI->user->full_name);
            $this->run_sql($sql, $data_array);
        }

        if (!empty($id)) {
            if (!empty($CI->session)) {
                $CI->session->set_flashdata('success', 'New object in ' . $collection . ' created "' . htmlentities($data->name) . '".');
            }
            return ($id);
        } else {
            # TODO - log a better error
            if (!empty($CI->session)) {
                $CI->session->set_flashdata('failure', 'Failed to create resource (please see detailed logs).');
            }
            log_error('ERR-0023', 'Database error in resource create routine.');
            return false;
        }
    }


    public function update($data = null, $collection = '')
    {
        $this->log->function = strtolower(__METHOD__);
        $this->log->status = 'updating data';
        stdlog($this->log);
        $CI = & get_instance();

        if (is_null($data)) {
            if (!empty($CI->response->meta->received_data->attributes)) {
                $data = $CI->response->meta->received_data->attributes;
                $data->id = $CI->response->meta->id;
                $collection = $CI->response->meta->collection;
            } else {
                log_error('ERR-0010', 'm_collection::update');
                return false;
            }
        }

        if ($collection === '') {
            log_error('ERR-0010', 'm_collection::update');
            return false;
        } else {
            $db_table = $collection;
        }

        if ($collection === 'credentials') {
            if (!empty($data->credentials)) {
                $received_credentials = new stdClass();
                foreach ($data->credentials as $key => $value) {
                        $received_credentials->$key = $value;
                }
                $select = "SELECT * FROM credentials WHERE id = ?";
                $query = $this->db->query($select, array($data->id));
                $result = $query->result();
                $existing_credentials = json_decode(simpleDecrypt($result[0]->credentials));
                $new_credentials = new stdClass();
                if (!empty($existing_credentials)) {
                    foreach ($existing_credentials as $existing_key => $existing_value) {
                        if (isset($received_credentials->$existing_key)) {
                            $new_credentials->$existing_key = $received_credentials->$existing_key;
                        } else {
                            $new_credentials->$existing_key = $existing_credentials->$existing_key;
                        }
                    }
                }
                $data->credentials = (string)simpleEncrypt(json_encode($new_credentials));
            }
        }

        if ($collection === 'dashboards') {
            if (!empty($data->options)) {
                $select = "SELECT * FROM dashboards WHERE id = ?";
                $query = $this->db->query($select, array($data->id));
                $result = $query->result();
                $existing = new stdClass();
                if (!empty($result[0]->options)) {
                    $existing = json_decode($result[0]->options);
                }
                if (!empty($data->options->layout)) {
                    $existing->layout = $data->options->layout;
                }
                if (!empty($data->options->widgets->position)) {
                    foreach ($data->options->widgets->position as $key => $value) {
                        $widget_position = $key;
                        $widget_id = $value;
                    }
                }
                foreach ($existing->widgets as $widget) {
                    if ($widget->position == $widget_position) {
                        $widget->widget_id = $widget_id;
                    }
                }
                $data->options = (string)json_encode($existing);
            }
        }

        if ($collection === 'discoveries') {

            $all_options = array('ping', 'service_version', 'filtered', 'timeout', 'timing', 'nmap_tcp_ports', 'nmap_udp_ports', 'tcp_ports', 'udp_ports', 'exclude_tcp_ports', 'exclude_udp_ports', 'exclude_ip', 'ssh_ports');

            $query = $this->db->query("SELECT * FROM discoveries WHERE id = ?", array($data->id));
            $result = $query->result();
            $db_discovery = $result[0];
            $other = json_decode($db_discovery->other);

            if (!empty($data->other)) {
                $received_other = new stdClass();
                foreach ($data->other as $key => $value) {
                        $received_other->$key = $value;
                }

                if (!empty($received_other->subnet) and !preg_match('/^[\d,\.,\/,-]*$/', $received_other->subnet)) {
                    log_error('ERR-0024', 'm_collection::create (discoveries)', 'Invalid field data supplied for subnet');
                    $this->session->set_flashdata('error', 'Discovery could not be updated - invalid Subnet supplied.');
                    $data->other->subnet = '';
                    if ($CI->response->meta->format == 'screen') {
                        redirect('/discoveries');
                    } else {
                        output($CI->response);
                        exit();
                    }
                }

                $discovery_scan_options = '';
                if (isset($received_other->nmap->discovery_scan_option_id)) {
                    if (!is_numeric($received_other->nmap->discovery_scan_option_id) and $received_other->nmap->discovery_scan_option_id != '') {
                        log_error('ERR-0024', 'm_collection::create (discoveries)', 'Invalid field data supplied for discovery_scan_option_id (non-numeric)');
                        $this->session->set_flashdata('error', 'Discovery could not be updated - invalid discovery_scan_option_id (non-numeric) supplied.');
                        $data->other->subnet = '';
                        if ($CI->response->meta->format == 'screen') {
                            redirect('/discoveries');
                        } else {
                            output($CI->response);
                            exit();
                        }
                    } else {
                        if ($received_other->nmap->discovery_scan_option_id != '' and $received_other->nmap->discovery_scan_option_id != '0') {
                            $select = "SELECT * FROM discovery_scan_options WHERE id = ?";
                            $data_array = array(intval($received_other->nmap->discovery_scan_option_id));
                            $query = $this->db->query($select, $data_array);
                            $result = $query->result();
                            if (empty($result)) {
                                log_error('ERR-0024', 'm_collection::create (discoveries)', 'Invalid field data supplied for discovery_scan_option_id (invalid value)');
                                $this->session->set_flashdata('error', 'Discovery could not be updated - invalid discovery_scan_option_id (invalid value) supplied.');
                                if ($CI->response->meta->format == 'screen') {
                                    redirect('/discoveries');
                                } else {
                                    output($CI->response);
                                    exit();
                                }
                            } else {
                                $discovery_scan_options = $result[0];
                            }
                        }
                    }
                }

                # If any of the below are changed, we're not using a default
                if (!empty($received_other->nmap->filtered)) {
                    $received_other->nmap->discovery_scan_option_id = '0';
                }
                if (!empty($received_other->nmap->ping)) {
                    $received_other->nmap->discovery_scan_option_id = '0';
                }
                if (!empty($received_other->nmap->service_version)) {
                    $received_other->nmap->discovery_scan_option_id = '0';
                }
                if (!empty($received_other->nmap->timing)) {
                    $received_other->nmap->discovery_scan_option_id = '0';
                }
                if (!empty($received_other->nmap->nmap_tcp_ports)) {
                    $received_other->nmap->discovery_scan_option_id = '0';
                }
                if (!empty($received_other->nmap->nmap_udp_ports)) {
                    $received_other->nmap->discovery_scan_option_id = '0';
                }

                if (!empty($received_other->nmap->tcp_ports)) {
                    if (!preg_match('/^[\d,\/,\/-]*$/', $received_other->nmap->tcp_ports)) {
                        // Invalid TCP ports
                        log_error('ERR-0024', 'm_collection::create (discoveries)', 'Invalid field data supplied for tcp_ports');
                        $this->session->set_flashdata('error', 'Discovery could not be updated - invalid tcp_ports supplied.');
                        $data->other->nmap->tcp_ports = '';
                        if ($CI->response->meta->format == 'screen') {
                            redirect('/discoveries');
                        } else {
                            output($CI->response);
                            exit();
                        }
                    } else {
                        // Valid TCP ports
                        $received_other->nmap->discovery_scan_option_id = '0';
                    }
                }

                if (!empty($received_other->nmap->udp_ports)) {
                    if (!preg_match('/^[\d,\/,\/-]*$/', $received_other->nmap->udp_ports)) {
                        // Invalid UDP ports
                        log_error('ERR-0024', 'm_collection::create (discoveries)', 'Invalid field data supplied for udp_ports');
                        $this->session->set_flashdata('error', 'Discovery could not be updated - invalid udp_ports supplied.');
                        $data->other->nmap->udp_ports = '';
                        if ($CI->response->meta->format == 'screen') {
                            redirect('/discoveries');
                        } else {
                            output($CI->response);
                            exit();
                        }
                    } else {
                        // Valid UDP ports
                        $received_other->nmap->discovery_scan_option_id = '0';
                    }
                }

                if (!empty($received_other->nmap->exclude_tcp_ports)) {
                    if (!preg_match('/^[\d,\/,\/-]*$/', $received_other->nmap->exclude_tcp_ports)) {
                        // Invalud Exclude TCP ports
                        log_error('ERR-0024', 'm_collection::create (discoveries)', 'Invalid field data supplied for exclude_tcp_ports');
                        $this->session->set_flashdata('error', 'Discovery could not be updated - invalid exclude_tcp_ports supplied.');
                        $data->other->nmap->exclude_tcp_ports = '';
                        if ($CI->response->meta->format == 'screen') {
                            redirect('/discoveries');
                        } else {
                            output($CI->response);
                            exit();
                        }
                    } else {
                        // Valid Exclude TCP ports
                    }
                }

                if (!empty($received_other->nmap->exclude_udp_ports)) {
                    if (!preg_match('/^[\d,\/,\/-]*$/', $received_other->nmap->exclude_udp_ports)) {
                        // Invalid Exclude UDP ports
                        log_error('ERR-0024', 'm_collection::create (discoveries)', 'Invalid field data supplied for exclude_udp_ports');
                        $this->session->set_flashdata('error', 'Discovery could not be updated - invalid exclude_udp_ports supplied.');
                        $data->other->nmap->exclude_udp_ports = '';
                        if ($CI->response->meta->format == 'screen') {
                            redirect('/discoveries');
                        } else {
                            output($CI->response);
                            exit();
                        }
                    } else {
                        // Valid Exclude UDP ports
                    }
                }

                if (!empty($received_other->nmap->exclude_ip)) {
                    if (!preg_match('/^[\d,\.,\/,-]*$/', $received_other->nmap->exclude_ip)) {
                        // Invalid Exclude IP
                        log_error('ERR-0024', 'm_collection::create (discoveries)', 'Invalid field data supplied for exclude_ip');
                        $this->session->set_flashdata('error', 'Discovery could not be updated - invalid exclude_ip supplied.');
                        $data->other->nmap->exclude_ip = '';
                        if ($CI->response->meta->format == 'screen') {
                            redirect('/discoveries');
                        } else {
                            output($CI->response);
                            exit();
                        }
                    } else {
                        // Valid Exclude IP
                    }
                }

                // top level - subnet, ad_domain, ad_server
                if (!empty($received_other->subnet)) {
                    $other->subnet = $received_other->subnet;
                    $data->description = 'Subnet - ' . $received_other->subnet;
                    if (stripos($received_other->subnet, '-') === false and stripos($received_other->subnet, ',') === false) {
                        $this->load->helper('network');
                        $temp = network_details($received_other->subnet);
                        if (!empty($temp->error) and filter_var($received_other->subnet, FILTER_VALIDATE_IP) === false) {
                            $this->session->set_flashdata('error', 'Object in ' . $this->response->meta->collection . ' could not be updated - invalid subnet attribute supplied.');
                            log_error('ERR-0010', 'm_collections::create (invalid subnet supplied)');
                            return;
                        }
                    }
                }
                if (!empty($received_other->ad_domain)) {
                    $other->ad_domain = $received_other->ad_domain;
                    $data->description = 'Active Directory - ' . $received_other->ad_domain;
                }

                if (!empty($received_other->ad_server)) {
                    $other->ad_server = $received_other->ad_server;
                }

                if (empty($other->nmap) or count((array)$other->nmap) == 0) {
                    $other->nmap = new stdClass();
                }

                if (!empty($received_other->nmap)) {
                    foreach ($received_other->nmap as $key => $value) {
                        $other->nmap->{$key} = $value;
                    }
                }

                if (empty($other->match) or count((array)$other->match) == 0) {
                    $other->match = new stdClass();
                }

                if (!empty($received_other->match)) {
                    foreach ($received_other->match as $key => $value) {
                        $other->match->{$key} = $received_other->match->{$key};
                    }
                }

                if (!empty($discovery_scan_options)) {
                    # We have set a discovery options - reset all
                    foreach ($all_options as $field) {
                        $other->nmap->{$field} = $discovery_scan_options->{$field};
                    }
                }

                unset($data->other);
                $data->other = (string)json_encode($other);

            }
            if(!empty($data->killed)){
                unset($data->killed);
                $log = new stdClass();
                $log->discovery_id = $data->id;
                $log->system_id = null;
                $log->timestamp = $this->config->config['timestamp'];
                $log->severity = 6;
                $log->function = "logs";
                $log->command_status = "stopped";
                $log->pid = getmypid();
                $log->message = "Discovery process has been manually stopped.";
                discovery_log($log);
            }
        }

        if ($collection === 'integrations' and !empty($data->options)) {
            $select = "/* m_collection::update */ " . "SELECT * FROM integrations WHERE id = ?";
            $query = $this->db->query($select, array($data->id));
            $result = $query->result();
            $existing = new stdClass();
            if (!empty($result[0]->options)) {
                $original = json_decode($result[0]->options);
            }
            $submitted = $data->options;
            $merged = $this->deep_merge($original, $submitted);
            $data->options = (string)json_encode($merged);
        }

        if ($collection === 'ldap_servers') {
            if (!empty($data->dn_password)) {
                $data->dn_password = (string)simpleEncrypt($data->dn_password);
            }
        }

        if ($collection === 'scripts') {
            if (!empty($data->options)) {
                $select = "SELECT * FROM scripts WHERE id = ?";
                $query = $this->db->query($select, array($data->id));
                $result = $query->result();
                $existing = new stdClass();
                if (!empty($result[0]->options)) {
                    $existing = json_decode($result[0]->options);
                }
                foreach ($data->options as $key => $value) {
                    $existing->$key = $value;
                }
                $data->options = (string)json_encode($existing);
            }
        }

        if ($collection === 'tasks') {
            if (!empty($data->options)) {
                $received = new stdClass();
                if (gettype($data->options) === "object" or gettype($data->options) === "array") {
                    foreach ($data->options as $key => $value) {
                            $received->$key = $value;
                    }
                }
                $existing = new stdClass();
                if (!empty($data->id)) {
                    $select = "SELECT * FROM tasks WHERE id = ?";
                    $query = $this->db->query($select, array($data->id));
                    $result = $query->result();
                    if (!empty($result[0]->options)) {
                        $existing = json_decode($result[0]->options);
                    }
                }
                $new = new stdClass();
                foreach ($existing as $existing_key => $existing_value) {
                    if (isset($received->$existing_key)) {
                        $new->$existing_key = $received->$existing_key;
                    } else {
                        $new->$existing_key = $existing->$existing_key;
                    }
                }
                $data->options = (string)json_encode($new);
            }
            if (!empty($data->{'minute[]'}) and is_array($data->{'minute[]'})) {
                $data->minute = implode(',', $data->{'minute[]'});
                unset($data->{'minute[]'});
            }
            if (!empty($data->{'hour[]'}) and is_array($data->{'hour[]'})) {
                $data->hour = implode(',', $data->{'hour[]'});
                unset($data->{'hour[]'});
            }
            if (!empty($data->{'day_of_month[]'}) and is_array($data->{'day_of_month[]'})) {
                $data->day_of_month = implode(',', $data->{'day_of_month[]'});
                unset($data->{'day_of_month[]'});
            }
            if (!empty($data->{'month[]'}) and is_array($data->{'month[]'})) {
                $data->month = implode(',', $data->{'month[]'});
                unset($data->{'month[]'});
            }
            if (!empty($data->{'day_of_week[]'}) and is_array($data->{'day_of_week[]'})) {
                $data->day_of_week = implode(',', $data->{'day_of_week[]'});
                unset($data->{'day_of_week[]'});
            }
        }

        if ($collection === 'users') {
            if (!empty($data->password)) {
                set_include_path($CI->config->config['base_path'] . '/code_igniter/application/third_party/random_compat');
                require_once "lib/random.php";
                $salt = bin2hex(random_bytes(32));
                $data->password = $salt.hash("sha256", $salt.(string)$data->password);
                unset($salt);
            }
        }

        $update_fields = update_fields($collection);
        $sql = '';
        $items = array();
        foreach ($data as $key => $value) {
            if (in_array($key, $update_fields)) {
                if ($sql == '') {
                    $sql = "SET `" . $key . "` = ?";
                    $items[] = $value;
                } else {
                    $sql .= ", `" . $key . "` = ?";
                    $items[] = $value;
                }
            }
        }
        if ($this->db->field_exists('edited_by', $db_table)) {
            $sql .= ", `edited_by` = '" . $CI->user->full_name . "'";
        }
        if ($this->db->field_exists('edited_date', $db_table)) {
            $sql .= ", `edited_date` = NOW()";
        }
        $sql = "UPDATE `" . $db_table . "` " . $sql . " WHERE id = " . intval($data->id);
        $test = $this->run_sql($sql, $items);
        return $test;
    }
}
