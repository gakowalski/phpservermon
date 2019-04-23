<?php
/**
 * PHP Server Monitor
 * Monitor your servers and websites.
 *
 * This file is part of PHP Server Monitor.
 * PHP Server Monitor is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PHP Server Monitor is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PHP Server Monitor.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package     phpservermon
 * @author      Kuliievych Oleksandr <borove@gmail.com>
 * @copyright   Copyright (c) 2018 Kuliievych Oleksandr <borove@gmail.com>
 * @license     http://www.gnu.org/licenses/gpl.txt GNU GPL v3
 * @version     Release: @package_version@
 * @link        http://www.phpservermonitor.org/
 * @since       phpservermon 3.5.0
 **/

namespace psm\Util\Environment;

/**
 * The ServerValidator helps you to check input data for servers.
 */
class EnvironmentValidator {

    /**
     * Database service
     * @var \psm\Service\Database $db
     */
    protected $db;

    public function __construct(\psm\Service\Database $db) {
        $this->db = $db;
    }

    /**
     * Check if the environment id exists
     * @param int|\PDOStatement $environment_id
     * @return boolean
     * @throws \InvalidArgumentException
     */
    public function environmentId($environment_id) {
        $environment = $this->db->selectRow(PSM_DB_PREFIX.'environments', array('environment_id' => $environment_id), array('environment_id'));

        if (empty($environment)) {
            throw new \InvalidArgumentException('environment_no_match');
        }
        return true;
    }

    /**
     * Check name
     * @param string $name
     * @return boolean
     * @throws \InvalidArgumentException
     */
    public function name($name) {
        $name = trim($name);
        if (empty($name) || strlen($name) > 45)
            throw new \InvalidArgumentException('environment_name_bad_length');


        $environment = $this->db->selectRow(PSM_DB_PREFIX.'environments', array('name' => $name));

        if(!empty($environment))
            throw new \InvalidArgumentException('environment_name_already_taken');


        return true;
    }


}
