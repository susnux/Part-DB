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
        2015-09-06  susnux         - created
    */

class PartsController extends BaseController
{
    public function __construct()
    {
        parent::__construct('Part', 'parts',
            array('id' => false, 'name' => true, 'description' => true,
                'id_category' => false, 'id_storelocation' => false,
                'id_manufacturer' => false, 'id_footprint' => false,
                'comment' => true
            ));
        array_push($this->supported_methods, 'GET', 'DELETE', 'POST', 'PUT');
    }

    public function get_action($request)
    {
        // Check valid ID
        if(isset($request->url_elements[2]) && is_numeric($request->url_elements[2]) && $request->url_elements[2] == 0)
        {
            return array('status' => Http::bad_request);
        } else {
            // Use BaseController::get_action (generic one)
            return BaseController::get_action($request);
        }
    }

    /** @copydoc BaseController::class_to_array($class) */
    protected function class_to_array($class)
    {
        return array(   'id' => $class->get_id(),
                        'name' => $class->get_name(),
                        'id_category' => ($class->get_category() == null ? 0 : (int)$class->get_category()->get_id()),
                        'description' => $class->get_description(),
                        'instock' => $class->get_instock(),
                        'mininstock' => $class->get_mininstock(),
                        'id_storelocation' => ($class->get_storelocation() == null ? 0 : (int)$class->get_storelocation()->get_id()),
                        'id_manufacturer'  => ($class->get_manufacturer() == null ? 0 : (int)$class->get_manufacturer()->get_id()),
                        'id_footprint' => ($class->get_footprint() == null ? 0 : (int)$class->get_footprint()->get_id()),
                        'comment' => $class->get_comment(),
                        'visible' => (boolean)$class->get_visible()
                    );
    }

    protected function array_to_class($array)
    {
        if (!isset($array['name']))
        {
            throw InvalidArgumentException("Name not set.");
        }
        $name = (string)$array['name'];
        $category_id = isset($array['id_category']) ? $array['id_category'] : 0;
        $description = isset($array['description']) ? $array['description'] : "";
        $instock = isset($array['instock']) ? (int)$array['instock'] : 0;
        $mininstock = isset($array['mininstock']) ? (int)$array['mininstock'] : 0;
        $storelocation_id = isset($array['id_storelocation']) ? (int)$array['id_storelocation'] : 0;
        $manufacturer_id = isset($array['id_manufacturer']) ? (int)$array['id_manufacturer'] : 0;
        $footprint_id = isset($array['id_footprint']) ? (int)$array['id_footprint'] : 0;
        $comment = isset($array['comment']) ? $array['comment'] : "";
        $visible = isset($array['visible']) ? (boolean)$array['visible'] : true;
        return Part::add($this->database, $this->current_user, $this->log,
            $name, $category_id, $description, $instock,
            $mininstock, $storelocation_id, $manufacturer_id,
            $footprint_id, $comment, $visible);
    }
}

?>
