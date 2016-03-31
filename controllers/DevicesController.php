<?php
/*
    Copyright (C) 2015 Part-DB Authors (see authors.php)
    https://github.com/sandboxgangster/Part-DB

    This program is free software; you can redistribute it and/or
    modify it under the terms of the GNU General Public License
    as published by the Free Software Foundation; either version 2
    of the License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA

    $Id$

    Changelog (sorted by date):
        [DATE]      [NICKNAME]     [CHANGES]
        2015-09-13  susnux         - created
        2016-03-31  susnux         - updated, moved code to BaseController
*/

class DevicesController extends BaseController
{
    /** @brief Contructor of DevicesController class. */
    public function __construct()
    {
        parent::__construct('Device', 'devices');
        array_push($this->supported_methods, 'GET', 'DELETE', 'POST');
    }

    // get_action, delete_action and post_action from BaseController

    /** @copydoc BaseController::class_to_array($class) */
    protected function class_to_array($class)
    {
        $parts = array();
        foreach ($class->get_parts() as $item)
            $parts[] = $item->get_id();
        return array(   'id' => $class->get_id(),
                        'name' => $class->get_name(),
                        'parent_id' => $class->get_parent_id() == null ? 0 : $class->get_parent_id(),
                        'parts' => $parts);
    }

    /** @copydoc BaseController::array_to_class($array) */
    protected function array_to_class($array)
    {
        if (!isset($array['name']) ||
            (isset($array['parent_id']) && !is_numeric($array['parent_id'])))
        {
            throw InvalidArgumentException("Parameter not set or type invalid.");
        }
        $name = $array['name']:
        $parent_id = isset($array['parent_id']) ? (int)$array['parent_id'] : 0;
        return Device::add($this->database, $this->current_user, $this->log, $name, $parent_id);
    }
}
?>
