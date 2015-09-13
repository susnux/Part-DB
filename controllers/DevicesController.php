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
*/

    class DevicesController extends BaseController
    {
        /** @brief Contructor of DevicesController class. */
        public function __construct()
        {
            parent::__construct('Device');
            array_push($this->supported_methods, 'GET', 'DELETE', 'POST');
        }

        /** @copydoc CategoriesController::get_action()*/
        public function get_action($request)
        {
            // Check if element ID is given (e.g. /api.php/fooBar/ID where id is numeric)
            if(isset($request->url_elements[2]) && is_numeric($request->url_elements[2]))
            {
                // Is given, so we should return information about the item
                return $this->get_single_information($request->url_elements[2]);
            }
            // No id given, so we are asked either to return a list of all, or to answer a query
            $query = ''; // An empty query is the same as request a list of all
            $order = '';
            $keywords = array();

            $query_params_whitelist = array('id', 'parent', 'name');
            // Check if query is given:
            if (count(array_intersect_key($request->parameters, array_flip($query_params_whitelist))) > 0)
            {
                $query_params = array_intersect_key($request->parameters, array_flip($query_params_whitelist));
                foreach ($query_params as $key => $value)
                {
                    $query .= $query != '' ? ' AND ' : 'WHERE ';
                    switch ($key)
                    {
                        case 'id':
                            if (!is_numeric($value))
                                return array('status' => Http::bad_request);
                            $query .= "id <=> ?";
                            $keywords[] = $value;
                            break;
                        case 'name':
                            $query .= "name LIKE ?";
                            $keywords[] = str_replace('*', '%', $value);
                            break;
                        case 'parent':
                            if (!is_numeric($value))
                                return array('status' => Http::bad_request);
                            $query .= "parent_id <=> ?";
                            $keywords[] = $value == 0 ? null : $value;
                            break;
                        default:
                            debug('error', 'Unknown query-parameter given, can not handle it.',
                                __FILE__, __LINE__, __METHOD__);
                                return array('status' => Http::server_error);
                    }
                }
            }

            $option_params = array_intersect_key($request->parameters, array('sortedBy' => null));
            if (count($option_params) > 0 && $option_params['sortedBy'] != null)
            {
                $sort = explode(',', $option_params['sortedBy']);
                foreach ($sort as $key)
                {
                    $order .= $order != '' ? ', ' : '';
                    if (!in_array(substr($key, 1), $query_params_whitelist) || 
                        (($key[0] != '-') && ($key[0] != '+')))
                        return array('status' => Http::bad_request);
                    $order .= substr($key, 1) . ' ' . ($key[1] == '+' ? 'ASC' : 'DESC');
                }
                $order = ' ORDER BY ' . $order;
            }
            $headers = null;
            if (isset($request->headers['Range']))
            {
                $headers = $request->headers['Range'];
            }
            return $this->get_query_information(
                            array(  'query' => $query,
                                    'order' => $order,
                                    'keywords' => $keywords),
                            $headers, 'devices');
        }

        /** @copydoc BaseController::delete_item */
        public function delete_action($request)
        {
            // Check if element ID is given (e.g. /api.php/fooBar/ID where id is numeric)
            if(isset($request->url_elements[2]) && is_numeric($request->url_elements[2]) && (int)$request->url_elements[2] > 0)
            {
                // Can delete device always, so no condition
                return $this->delete_item($request->url_elements[2], null);
            }
            else
            {
                // No id given -> Invalid request
                return array('status' => Http::bad_request);
            }
        }

        /** @brief creates new device.
            @param request Request object
        */
        public function post_action($request)
        {
            if (isset($request->url_elements[2])     || //Too much url arguments
                !isset($request->parameters['name']) || // No name given
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
            $parent_id              = isset($request->parameters['parent']) ? (int)$request->parameters['parent'] : 0;
            try
            {
                $new_device = Device::add(  $this->database, $this->current_user, $this->log, $name, $parent_id);
                return array('status' => Http::created,
                             'body' => array('id'     => $new_device->get_id(),
                                             'name'   => $new_device->get_name(),
                                             'parent' => $new_device->get_parent_id()),
                             'headers' => array('Location' => 'http://' . $request->headers['Host'] . $_SERVER['REQUEST_URI']. '/' . $new_device->get_id()));
            }
            catch (Exception $e)
            {
                debug('error', 'Unexpected exception: ' . $e->getMessage(), __FILE__, __LINE__, __METHOD__);
                return array('status' => Http::server_error);
            }
        }

        /** @copydoc BaseController::class_to_array($class) */
        protected function class_to_array($class)
        {
            $parts = array();
            foreach ($class->get_parts() as $item)
                $parts[] = $item->get_id();
            return array(   'id' => $class->get_id(),
                            'name' => $class->get_name(),
                            'parent' => $class->get_parent_id() == null ? 0 : $class->get_parent_id(),
                            'parts' => $parts);
        }
    }
?>
