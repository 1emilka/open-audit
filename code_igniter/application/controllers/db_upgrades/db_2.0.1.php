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
**/

$this->log_db('Upgrade database to 2.0.1 commenced');

# summaries
$this->alter_table('summaries', 'expose', "`menu_display` enum('y','n') NOT NULL DEFAULT 'y' AFTER `menu_category`");

# A config item for other Opmantek installed modules
$sql = "INSERT INTO `configuration` VALUES (NULL, 'modules', '', 'n', 'system','2000-01-01 00:00:00', 'The list of installed Opmantek modules.')";
$this->db->query($sql);
$this->log_db($this->db->last_query());

# A config item for other the commercial application name
$sql = "INSERT INTO `configuration` VALUES (NULL, 'oae_product', 'Open-AudIT Community', 'n', 'system','2000-01-01 00:00:00', 'The name of the installed commercial application.')";
$this->db->query($sql);
$this->log_db($this->db->last_query());

# Update our URL if it's the default
$sql = "UPDATE `configuration` SET `value` = '/omk/open-audit' WHERE `name` = 'oae_url' and value = '/omk/oae'";
$this->db->query($sql);
$this->log_db($this->db->last_query());
$sql = "UPDATE `configuration` SET `value` = '/omk/open-audit/map' WHERE `name` = 'maps_url' and value = '/omk/oae/map'";
$this->db->query($sql);
$this->log_db($this->db->last_query());

# set our versions
$sql = "UPDATE `configuration` SET `value` = '20170620' WHERE `name` = 'internal_version'";
$this->db->query($sql);
$this->log_db($this->db->last_query());

$sql = "UPDATE `configuration` SET `value` = '2.0.1' WHERE `name` = 'display_version'";
$this->db->query($sql);
$this->log_db($this->db->last_query());

#$this->db->db_debug = $temp_debug;
$this->log_db("Upgrade database to 2.0.1 completed");
$this->config->config['internal_version'] = '20170620';
$this->config->config['display_version'] = '2.0.1';