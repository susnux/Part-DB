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
            parent::__construct('Category', array('id', 'parent', 'name'));
            array_push($this->supported_methods, 'GET', 'DELETE', 'POST');
        }

        /** Handles GET requests on categories,
            .../categories/{id}          will fetch data about the category with the given id or return code 204 if not exsists
            .../categories               will fetch all available categories, you can use the 'Range' header
            .../categories?KEY=VALUE     will fetch categories with KEY as attribute with value VALUE.
                                         optional parameters are: sortedBy=+/-KEY,+/-OTHERKEY,...

            @brief Handles GET requests on categories.
            @param request Request object on which we should respond.
            @return Array which contains at least on of this elements:
                    'status'  => INTEGER (HTTP status to send)
                    'body'    => ARRAY   Containing category element(s)
                    'headers' => ARRAY   Containing various headers to set.*/
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
            $keywords = array();

            // Check if query is given:
            if (count(array_intersect_key($request->parameters, array_flip($this->query_parameters_whitelist))) > 0)
            {
                $query_params = array_intersect_key(
                    $request->parameters, array_flip($this->query_parameters_whitelist)
                );
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

            $headers = null;
            if (isset($request->headers['Range']))
            {
                $headers = $request->headers['Range'];
            }
            return $this->get_query_information(
                            array(  'query' => $query,
                                    'keywords' => $keywords),
                            $headers, 'categories');
        }

        /** @copydoc BaseController::delete_item */
        public function delete_action($request)
        {
            // Check if element ID is given (e.g. /api.php/fooBar/ID where id is numeric)
            if(isset($request->url_elements[2]) && is_numeric($request->url_elements[2]) && (int)$request->url_elements[2] > 0)
            {
                function condition_function ($object)
                {
                    $count = count($object->get_parts());
                    if ($count > 0)
                    {
                        debug('warning', 'Category not empty, can not delete it.', __FILE__, __LINE__, __METHOD__);
                        return false;
                    }
                    return true;
                }
                $condition = 'condition_function';
                return $this->delete_item($request->url_elements[2], $condition);
            }
            else
            {
                // No id given -> Invalid request
                return array('status' => Http::bad_request);
            }
        }

        /** @brief creates new category.
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
            $disable_footprints     = isset($request->parameters['footprints']) ? (boolean)$request->parameters['footprints'] : false;
            $disable_manufacturers  = isset($request->parameters['manufacturers']) ? (boolean)$request->parameters['manufacturers'] : false;
            $disable_autodatasheets = isset($request->parameters['autodatasheets']) ? (boolean)$request->parameters['autodatasheets'] : false;
            try
            {
                $new_category = Category::add(  $this->database, $this->current_user, $this->log, $name,
                                                $parent_id, $disable_footprints,
                                                $disable_manufacturers, $disable_autodatasheets);
                return array('status' => Http::created,
                             'body' => array('id'     => $new_category->get_id(),
                                             'name'   => $new_category->get_name(),
                                             'parent' => $new_category->get_parent_id()),
                             'headers' => array('Location' => 'http://' . $request->headers['Host'] . $_SERVER['REQUEST_URI']. '/' . $new_category->get_id()));
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
            return array(   'id' => $class->get_id(),
                            'name' => $class->get_name(),
                            'parent' => $class->get_parent_id(),
                            'manufacturers' => (boolean)$class->get_disable_manufacturers(),
                            'autodatasheets' => (boolean)$class->get_disable_autodatasheets(),
                            'footprints' => (boolean)$class->get_disable_footprints());
        }
    }
?>
