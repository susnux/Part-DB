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

    class CategoriesController extends BaseController
    {
        /** @brief Contructor of CategoriesController class. */
        public function __construct()
        {
            parent::__construct('Category', 'categories');
            array_push($this->supported_methods, 'GET', 'DELETE', 'POST', 'PUT');
        }

        /** @copydoc BaseController::delete_item */
        public function delete_action($request)
        {
            // Check if element ID is given (e.g. /api.php/fooBar/ID where id is numeric)
            return BaseController::delete_action($request,
                function ($object)
                    {
                        $count = count($object->get_parts());
                        if ($count > 0)
                        {
                            debug('warning', 'Category not empty, can not delete it.', __FILE__, __LINE__, __METHOD__);
                            return false;
                        }
                        return true;
                    });
        }

        /** @brief creates new category.
            @param request Request object
        */
        public function put_action($request)
        {
            if (isset($request->url_elements[3])     || //Too much url arguments
                !isset($request->parameters['name']) || // No name given
                (!isset($request->url_elements[2]) || !is_numeric($request->url_elements[2])) || // To few arguments or not a number
                (isset($request->parameters['parent']) && !is_numeric($request->parameters['parent'])))  // Parent given but not a number
            {
                return array('status' => Http::bad_request);
            }
            // Check headers:
            try
            {
                if(!$this->check_match_headers($request->headers, $request->parameters))
                {
                    return array('status' => Http::precondition_failed); // given header If-Match or If-Non-Match failed
                }
            }
            catch (Exception $e)
            {
                debug('error', 'Unexpected exception: ' . $e->getMessage(), __FILE__, __LINE__, __METHOD__);
                return array('status' => Http::server_error);
            }
            $name                   = $request->parameters['name'];
            $parent_id              = isset($request->parameters['parent_id']) ? (int)$request->parameters['parent_id'] : 0;
            $disable_footprints     = isset($request->parameters['footprints']) ? (boolean)$request->parameters['footprints'] : false;
            $disable_manufacturers  = isset($request->parameters['manufacturers']) ? (boolean)$request->parameters['manufacturers'] : false;
            $disable_autodatasheets = isset($request->parameters['autodatasheets']) ? (boolean)$request->parameters['autodatasheets'] : false;
            return $this->edit_item(array('name'                     => $name,
                                          'parent_id'                => $parent_id,
                                          'disable_footprints'       => $disable_footprints,
                                          'disable_manufacturers'    => $disable_manufacturers,
                                          'disable_autodatasheets'   => $disable_autodatasheets), (int)$request->url_elements[2]);
        }

        /** @copydoc BaseController::class_to_array($class) */
        protected function class_to_array($class)
        {
            return array(   'id' => $class->get_id(),
                            'name' => $class->get_name(),
                            'parent_id' => (int)$class->get_parent_id(),
                            'manufacturers' => (boolean)$class->get_disable_manufacturers(),
                            'autodatasheets' => (boolean)$class->get_disable_autodatasheets(),
                            'footprints' => (boolean)$class->get_disable_footprints());
        }

        /** @copydoc BaseController::array_to_class($array) */
        protected function array_to_class($array)
        {
            if (!isset($array['name']) || // No name given
                (isset($array['parent_id']) && !is_numeric($array['parent_id'])))  // Parent given but not a number
            {
                throw InvalidArgumentException("Parameter not set or type invalid.");
            }
            $name                   = $array['name'];
            $parent_id              = isset($array['parent_id']) ? (int)$array['parent_id'] : 0;
            $disable_footprints     = isset($array['footprints']) ? (boolean)$array['footprints'] : false;
            $disable_manufacturers  = isset($array['manufacturers']) ? (boolean)$array['manufacturers'] : false;
            $disable_autodatasheets = isset($array['autodatasheets']) ? (boolean)$array['autodatasheets'] : false;
            return Category::add($this->database,
                                $this->current_user,
                                $this->log,
                                $name,
                                $parent_id, $disable_footprints,
                                $disable_manufacturers, $disable_autodatasheets);
        }
    }
?>
