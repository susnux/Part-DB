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
            parent::__construct('Category');
            array_push($this->supported_methods, 'GET', 'DELETE', 'POST');
            $this->root_category = new Category($this->database, $this->current_user, $this->log, 0);
        }

        /** Handles GET requests on categories,
            .../categories/{id}          will fetch data about the category with the given id or return code 204 if not exsists
            .../categories/{id}/children will fetch all children of category with given id (or return code 204 if non exsist)
            .../categories/{id}/parent   will fetch parent of category with given id
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
            // An empty query is the same as request a list of all
            $query = '';
            $order = '';
            $range = '';
            $keywords = array();
            $data['headers']['Accept-Ranges'] = 'items';

            $query_params_whitelist = array('id', 'parent', 'name');

            // Check if element ID is given (e.g. /api.php/fooBar/ID where id is numeric)
            if(isset($request->url_elements[2]) && is_numeric($request->url_elements[2]))
            {
                // Is given, so we should return information about the item (maybe specified by a further uri element)
                $category = null;
                try
                {
                    $category = new Category($this->database, $this->current_user, $this->log, (int)$request->url_elements[2]);
                }
                catch (Exception $e)
                {
                    if ($e instanceof NoSuchElementException)
                    {
                        $data['status'] = 404;
                    } else {
                        debug(  'error', 'Got serious exception, which seems to be a server issue. Return 500.',
                                __FILE__, __LINE__, __METHOD__);
                        $data['status'] = 500;
                    }
                    return $data;
                }

                if(isset($request->url_elements[3]) && $request->url_elements[3] != null)
                {
                    switch($request->url_elements[3])
                    {
                    case 'children':
                        $query = 'WHERE parent_id <=> ?';
                        $keywords[] = $category->get_id();
                        break;
                    case 'parent':
                        $query = 'WHERE id <=> ?';
                        $keywords[] = $category->get_parent_id();
                        break;
                    default:
                        debug(  'info', 'Got unsupported action for item with id: ' . $category->get_id() . ' action: ' . $request->url_elements[3],
                                __FILE__, __LINE__, __METHOD__); 
                        return array('status' => 400);
                    }
                } else {
                    $data['body'] = array(  'id'     => $category->get_id(),
                                            'name'   => $category->get_name(),
                                            'parent' => $category->get_parent_id());
                    return $data;
                }
            } else {
                // No id given, so we are asked either to return a list of all, or to answer a query
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
                                    return array('status' => 400);
                                $query .= "id <=> ?";
                                $keywords[] = $value;
                                break;
                            case 'name':
                                $query .= "name LIKE ?";
                                $keywords[] = str_replace('*', '%', $value);
                                break;
                            case 'parent':
                                if (!is_numeric($value))
                                    return array('status' => 400);
                                $query .= "parent_id <=> ?";
                                $keywords[] = $value == 0 ? null : $value;
                                break;
                            default:
                                debug('error', 'Unknown query-parameter given, can not handle it.',
                                 __FILE__, __LINE__, __METHOD__);
                                 return array('status' => 500);
                        }
                    }
                }
            }
            $order_params_whitelist = array('sortedBy');
            $option_params = array_intersect_key($request->parameters, array_flip($order_params_whitelist));
            if (count($option_params) > 0 && $option_params['sortedBy'] != null)
            {
                $sort = explode(',', $option_params['sortedBy']);
                foreach ($sort as $key)
                {
                    $order .= $order != '' ? ', ' : '';
                    if (!in_array(substr($key, 1), $query_params_whitelist) || 
                        (($key[0] != '-') && ($key[0] != '+')))
                        return array('status' => 400);
                    $order .= substr($key, 1) . ' ' . ($key[1] == '+' ? 'ASC' : 'DESC');
                }
                $order = ' ORDER BY ' . $order;
            }
            // Check if Range header is set and if yes handle range
            if (isset($request->headers['Range']))
            {
                $count_query = 'SELECT COUNT(1) as number_of_categories FROM categories ' . $query . $order;
                $result = null;
                try
                {
                    $result = $this->database->query($count_query, $keywords);
                }
                catch (Exception $e)
                {
                    debug('error', 'Got database exception: ' . $e->getMessage(),
                                __FILE__, __LINE__, __METHOD__);
                    return array('status' => 500);
                }
                if ($request->headers['Range']['start'] >= $result[0]['number_of_categories'])
                {
                    debug('warning', 'Requested Range of: ' . $request->headers['Range']['start'] .
                                     '-' . $request->headers['Range']['end'] . ' Categories could not be satisfied.',
                                __FILE__, __LINE__, __METHOD__);
                    return array('status' => 416);
                }
                $duration = $request->headers['Range']['end'] - $request->headers['Range']['start'] + 1;
                $end = $request->headers['Range']['end'];
                // Real end (e.g. header end is greater then we have items)
                if (($result[0]['number_of_categories'] - 1) < $request->headers['Range']['end'])
                {
                    $end = $result[0]['number_of_categories'] - 1;
                }
                $range = ' LIMIT ' . $request->headers['Range']['start'] . ', ' . $duration;
                $data['status'] = 206; //Partitional Content (Range)
                $data['headers']['Content-Range'] = 'items ' . $request->headers['Range']['start'] . '-' . $end . '/' . $result[0]['number_of_categories'];
            }
            
            $query = 'SELECT id, name, parent_id AS parent FROM categories ' . $query . $order . $range;
            $query_data = array();
            try
            {
                $query_data = $this->database->query($query, $keywords);
            }
            catch (Exception $e)
            {
                debug('error', 'Got database exception: ' . $e->getMessage(),
                                __FILE__, __LINE__, __METHOD__);
                return array('status' => 500);
            }
            $number = count($query_data);
            if ($number == 0)
                return array('status' => 204);
            $i = 0;
            for($i; $i < $number; $i++)
            {
                $query_data[$i]['parent'] = $query_data[$i]['parent'] != null ? $query_data[$i]['parent'] : 0;
            }
            $data['body'] = $query_data;
            return $data;
        }

        /** @brief Deletes requested element from database.
            @return ARRAY('status' => HTTP_STATUS_CODE) with HTTP_STATUS_CODE:
                        204 on succes
                        400 if id is invalid
                        401 if not logged in
                        403 if logged in but user does not has permission to delete it
                        404 if given id is not found
                        409 if category is not empty
                        500 on server error
            @todo Handle users correctly (a general todo)*/
        public function delete_action($request)
        {
            // Check if element ID is given (e.g. /api.php/fooBar/ID where id is numeric)
            if(isset($request->url_elements[2]) && is_numeric($request->url_elements[2]) && (int)$request->url_elements[2] > 0)
            {
                $category = null;
                try
                {
                    $category = new Category($this->database, $this->current_user, $this->log, (int)$request->url_elements[2]);
                }
                catch (Exception $e)
                {
                    if ($e instanceof NoSuchElementException)
                    {
                        $data['status'] = 404;
                    } else {
                        debug(  'error', 'Got serious exception, which seems to be a server issue. Return 500.',
                                __FILE__, __LINE__, __METHOD__);
                        $data['status'] = 500;
                    }
                    return $data;
                }
                $count = count($category->get_parts());

                if ($count > 0)
                {
                    debug('warning', 'Category not empty, can not delete it.', __FILE__, __LINE__, __METHOD__);
                    return array('status' => 409); // CONFLICT
                }

                try
                {
                    $category->delete();
                    $category = NULL;
                }
                catch (Exception $e)
                {
                    debug('error', 'Category could not delete category: ' . $category->get_name() . '. Error: ' . $e->getMessage(),
                            __FILE__, __LINE__, __METHOD__);
                    return array('status' => 500); // Handle 401 403 when we add support for users
                }
                return array('status' => 204);
            }
            else
            {
                // No id given -> Invalid request
                return array('status' => 400);
            }
        }

        /** @brief creates new category.*/
        public function post_action($request)
        {
            if (isset($request->url_elements[2]))
                return array('status' => 400);
            // Check headers:
            $header_check = $this->check_match_headers($request->headers, $request->parameters);
            if ($header_check != 200)
            {
                return array('status' => $header_check);
            }
            if (!isset($request->parameters['name']) ||
                (isset($request->parameters['parent']) && !is_numeric($request->parameters['parent'])))
                return array('status' => 400);
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
                return array('status' => 201,
                             'body' => array('id'     => $new_category->get_id(),
                                             'name'   => $new_category->get_name(),
                                             'parent' => $new_category->get_parent_id()),
                             'headers' => array('Location' => 'http://' . $request->headers['Host'] . $_SERVER['REQUEST_URI']. '/' . $new_category->get_id()));
            }
            catch (Exception $e)
            {
                debug('error', 'Unexpected exception: ' . $e->getMessage(), __FILE__, __LINE__, __METHOD__);
                return array('status' => 500);
            }
        }
    }
?>
