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
        /** @brief Root category, only used as parent for "toplevel"-categories (e.g. getting them by parent_id) */
        protected $root_category;

        /** @brief Contructor of CategoriesController class. */
        public function __construct()
        {
            parent::__construct();
            // Use array_push because it is faster when adding multiple elements at once then loop $arr[] = $XX
            array_push($this->supported_methods, 'GET', 'DELETE');
            $this->root_category = new Category($this->database, $this->current_user, $this->log, 0);
        }

        /** Handles GET requests on categories,
            .../categories/{id}          will fetch data about the category with the given id or return code 204 if not exsists
            .../categories/{id}/children will fetch all children of category with given id (or return code 204 if non exsist)
            .../categories               will fetch all available categories, you can use the 'Range' header
            .../categories?KEY=VALUE     will fetch categories with KEY as attribute with value VALUE.
                                         optional parameters are: sortedBy=KEY+/-,OTHERKEY+/-

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
                // Is given, so we should return information about the item (maybe specified by a further uri element)
                $id = (int)$request->url_elements[2];

                if(isset($request->url_elements[3]) && $request->url_elements[3] != null)
                {
                    switch($request->url_elements[3])
                    {
                    case 'children':
                        $data = $this->get_children_of($id);
                        break;
                    default:
                        debug(  'info', 'Got unsupported action for item with id: ' . $id . ' action: ' . $request->url_elements[3],
                                __FILE__, __LINE__, __METHOD__); 
                        $data['status'] = 400;
                        break;
                    }
                } else {
                    $category = null;
                    try
                    {
                        $category = new Category($this->database, $this->current_user, $this->log, $id);
                    }
                    catch (Exception $e)
                    {
                        if ($e instanceof NoSuchElementException)
                        {
                            $data['status'] = 204;
                        } else {
                            debug(  'error', 'Got serious exception, which seems to be a server issue. Return 500.',
                                    __FILE__, __LINE__, __METHOD__);
                            $data['status'] = 500;
                        }
                        return $data;
                    }
                    $data['body']['id'] = $category->get_id();
                    $data['body']['name'] = $category->get_name();
                    $data['body']['parent'] = $category->get_parent_id();
                }
            } else {
                // No id given, so we are asked either to return a list of all, or to answer a query
                // Check if query is given:
                if ($request->parameters == null)
                {
                    // Only a list of all
                    $categories = $this->root_category->get_subelements(true);
                    $number = count($categories);
                    $i = 0;
                    for($i; $i < $number; $i++)
                    {
                        $category = array(  'id' => $categories[$i]->get_id(),
                                            'name' => $categories[$i]->get_name(),
                                            'parent' => $categories[$i]->get_parent_id());
                        $data['body'][$i] = $category;
                    }
                } else {
                    if (!$this->can_handle_parameters($request->parameters))
                    {
                        // Missformed request (wrong parameters)
                        $data['status'] = 400;
                        return $data;
                    }
                    if (isset($request->parameters['parent']) && is_numeric($request->parameters['parent']))
                    {
                        // Search categories where parent == given id
                        $data = $this->get_children_of($request->parameters['parent']);
                    } else {
                        $data['status'] = 400;
                    }
                }
            }
            return $data;
        }

        protected function get_children_of($id)
        {
            $categories = null;
            try {
                $category = new Category($this->database, $this->current_user, $this->log, $id);
                $categories = $category->get_subelements(false);
            } catch (Exception $e) {
                if ($e instanceof NoSuchElementException) {
                    $data['status'] = 204;
                } else {
                    debug(  'error', 'Got serious exception, which seems to be a server issue. Return 500.',
                            __FILE__, __LINE__, __METHOD__);
                    $data['status'] = 500; //Todo handle exception!!
                }
            }
            $number = count($categories);
            if ($number == 0) {
                // nothing found -> "NO CONTENT"
                $data['status'] = 204;
                return $data;
            }
            for($i = 0; $i < $number; $i++) {
                $category = array(  'id' => $categories[$i]->get_id(),
                                    'name' => $categories[$i]->get_name(),
                                    'parent' => $categories[$i]->get_parent_id());
                $data['body'][$i] = $category;
            }
            return $data;
        }

        protected $get_query_params = array('id', 'parent');
        protected $get_option_params = array('sortBy', 'start', 'sort');
        protected function can_handle_parameters($parameters)
        {
            foreach($parameters as $key => $value) {
                if (!in_array($key, $this->get_query_params)) {
                    if (!in_array($key, $this->get_option_params)) {
                        print("Does not exsists:" . $key);
                        return false;
                    }
                }
            }
            return true;
        }
    }
?>
