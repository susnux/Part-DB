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
            array_push($this->supported_methods, 'GET', 'DELETE');
            $this->root_category = new Category($this->database, $this->current_user, $this->log, 0);
        }

        /** Handles GET requests on categories,
            .../categories/{id}          will fetch data about the category with the given id or return code 204 if not exsists
            .../categories/{id}/children will fetch all children of category with given id (or return code 204 if non exsist)
            .../categories/{id}/parent   will fetch parent of category with given id
            .../categories               will fetch all available categories, you can use the 'Range' header
            .../categories?KEY=VALUE     will fetch categories with KEY as attribute with value VALUE.
                                         optional parameters are: sortedBy=KEY+/-,OTHERKEY+/-,...

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
                $category = null;
                try
                {
                    $category = new Category($this->database, $this->current_user, $this->log, (int)$request->url_elements[2]);
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

                if(isset($request->url_elements[3]) && $request->url_elements[3] != null)
                {
                    switch($request->url_elements[3])
                    {
                    case 'children':
                        $categories = $category->get_subelements(false);
                        $number = count($categories);
                        if ($number == 0)
                        {
                            // nothing found -> "NO CONTENT"
                            return array('status' => 204);
                        }
                        for($i = 0; $i < $number; $i++)
                        {
                            $data['body'][$i] = array(  'id' => $categories[$i]->get_id(),
                                                        'name' => $categories[$i]->get_name(),
                                                        'parent' => $categories[$i]->get_parent_id());
                        }
                        $data['headers'] = array('Content-Range' => 'items 0-' . ($number-1) . '/' . $number);
                        return $data;
                        break;
                    case 'parent':
                        try
                        {
                            $parent = new Category($this->database, $this->current_user, $this->log, $category->get_parent_id());
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
                        $data['body'] = array(  'id' => $parent->get_id(),
                                                'name' => $parent->get_name(),
                                                'parent' => $parent->get_parent_id());
                        break;
                    default:
                        debug(  'info', 'Got unsupported action for item with id: ' . $request->url_elements[2] . ' action: ' . $request->url_elements[3],
                                __FILE__, __LINE__, __METHOD__); 
                        $data['status'] = 400;
                        break;
                    }
                } else {
                    $data['body']['id'] = $category->get_id();
                    $data['body']['name'] = $category->get_name();
                    $data['body']['parent'] = $category->get_parent_id();
                }
            } else {
                // No id given, so we are asked either to return a list of all, or to answer a query
                // Check if query is given:
                $option_params_whitelist = array('sortedBy');
                $query_params_whitelist = array('id', 'parent', 'name');
                if (count(array_intersect_key($request->parameters, array_flip($query_params_whitelist))) == 0)
                {
                    // NO query params -> Only a list of all
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
                    $data['headers'] = array('Content-Range' => 'items 0-' . ($number-1) . '/' . $number);
                } else {
                    $query_params = array_intersect_key($request->parameters, array_flip($query_params_whitelist));
                    $rest = array_diff_key($request->parameters, $query_params);
                    $option_params  = array_intersect_key($request->parameters, array_flip($option_params_whitelist));
                    $rest = array_diff_key($rest, $option_params);
                    if (count($rest) > 0)
                        return array('status' => 400);

                    $query = "";
                    foreach ($query_params as $key => $value)
                    {
                        $query .= $query != "" ? ' AND ' : '';
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
                    $order = "";
                    if (count($option_params) > 0 && $option_params['sortedBy'] != null)
                    {
                        $sort = explode(',', $option_params['sortedBy']);
                        foreach ($sort as $key)
                        {
                            $order .= $order != "" ? ", " : "";
                            if (!in_array(substr($key, 0 , -1), $query_params_whitelist) || 
                                ((substr($key, -1) != '-') && (substr($key, -1) != '+')))
                                return array('status' => 400);
                            $order .= substr($key, 0 , -1) . ' ' . (substr($key, -1) == '+' ? 'ASC' : 'DESC');
                        }
                        $order = " ORDER BY " . $order;
                    }
                    $query = 'SELECT id, name, parent_id FROM categories WHERE ' . $query . $order;
                    $query_data = $this->database->query($query, $keywords);
                    $number = count($query_data);
                    $i = 0;
                    for($i; $i < $number; $i++)
                    {
                        $query_data[$i]['parent_id'] = $query_data[$i]['parent_id'] != null ? $query_data[$i]['parent_id'] : 0;
                    }
                    $data['body'] = $query_data;
                    $data['headers'] = array('Content-Range' => 'items 0-' . ($number-1) . '/' . $number);
                }
            }
            return $data;
        }
    }
?>
