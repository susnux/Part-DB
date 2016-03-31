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

/** @file  BaseController.php
 *  @brief Class BaseController
 *
 *  @class BaseController
 *  @brief Base class for all controllers. Used for request handling, common methods and attributes.
 *  @note If you want to extend controllers with a common feature, do it here.
 *
 * @author susnux
 */
abstract class BaseController
{
    /** @brief Database object of this class
     *  @sa Database
     */
    protected $database;
    /** @brief Log object of this class.
     *  @sa Log
     */
    protected $log;
    /** @brief Current logged in user.
      * @todo  Handle userser correctly, after we support users and groups.
      * @sa User
      */
    protected $current_user;
    /** @brief Name of the managed class, e.g. Part when extended as PartsController
    */
    protected $managed_class_name;
    /** @brief Supported HTTP methods by this controller. Needed by 405 failure ('Allow' header).
     *  @note When extending this class, simply add your accepted methods.
     */
    protected $supported_methods;
    /** @brief Allowed query parameters
        Array: NAME - is string
        (STRING => BOOL)
    */
    protected $all_query_parameters;
    /** @brief Name of SQL table
    */
    protected $sql_table;
    /** @brief Converts given class instance to array with membervariables
        @note You need to implement this function if you extend your class from this.
              Example for this function would be:
              class with members "id", "parent_id", "name":
              return array('id' => $class->get_id(), 'parent' => $class->get_parent_id(), '' => $class->get_name());
    */
    abstract protected function class_to_array($class);
    
    abstract protected function array_to_class($array);

    /** @brief Constructor
      *        Sets database, log and current_user
      * @param  class_name Name of the managed class (e.g. Category, User, Part...)
      * @param  query_parameters_whitelist List of valid parameters for query.
      * @throws Exception If database, log or user initialization failed.
      */
    protected function __construct($class_name, $sql_table, $all_query_parameters = array('id'=>false, 'parent_id' => false, 'name' => true))
    {
        $this->database           = new Database();
        $this->log                = new Log($this->database);
        $this->current_user       = new User($this->database, $this->current_user, $this->log, 1); // admin
        $this->supported_methods  = array();
        $this->managed_class_name = $class_name;
        $this->sql_table          = $sql_table;
        $this->all_query_parameters = $all_query_parameters;
    }

    /** @brief Return supported HTTP methods by this controller */
    public function get_supported_methods()
    {
        return $this->supported_methods;
    }

    /** Handles GET requests on categories,
            .../XYZ/{id}          will fetch data about the element with the given id or return code 204 if not exsists
            .../XYZ               will fetch all available elements, you can use the 'Range' header
            .../XYZ?KEY=VALUE     will fetch categories with KEY as attribute with value VALUE.
                                         optional parameters are: sortedBy=+/-KEY,+/-OTHERKEY,...

            @brief Handles GET requests.
            @param request Request object on which we should respond.
            @return Array which contains at least on of this elements:
                    'status'  => INTEGER (HTTP status to send)
                    'body'    => ARRAY   Containing element(s)
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
        $order = '';
        $keywords = array();

        $query_params = array_intersect_key($request->parameters, $this->all_query_parameters);
        // Check if query is given:
        if (count($query_params) > 0)
        {
            foreach ($query_params as $key => $value)
            {
                $query .= $query != '' ? ' AND ' : 'WHERE ';
                if ($this->all_query_parameters[$key] === true) {
                    $query .= "$key LIKE ?";
                    $keywords[] = str_replace('*', '%', $value);
                } else if ($this->all_query_parameters[$key] === false) {
                    if (!is_numeric($value))
                        return array('status' => Http::bad_request);
                    $query .= "$key <=> ?";
                    $keywords[] = $value;
                } else {
                    debug('error', 'Unknown query-parameter given, can not handle it.',
                        __FILE__, __LINE__, __METHOD__);
                    return array('status' => Http::server_error);
                }
            }
        } else {
            debug('info', 'No valid parameters.',
                        __FILE__, __LINE__, __METHOD__);
            return array('status' => Http::bad_request);
        }

        $option_params = array_intersect_key($request->parameters, array('sortedBy' => null));
        if (count($option_params) > 0 && $option_params['sortedBy'] != null)
        {
            $sort = explode(',', $option_params['sortedBy']);
            foreach ($sort as $key)
            {
                $order .= $order != '' ? ', ' : '';
                if (!in_array(substr($key, 1), $this->all_query_parameters) || 
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
                        $headers, $this->sql_table);
    }

    /** @brief creates new device.
        @param request Request object
    */
    public function post_action($request)
    {
        if (isset($request->url_elements[2])) //Too much url arguments
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
        try
        {
            $new_device = array_to_class($request->parameters);
            return array('status' => Http::created,
                            'body' => class_to_array($new_device),
                            'headers' => array('Location' => 'http://' . $request->headers['Host'] . $_SERVER['REQUEST_URI']. '/' . $new_device->get_id()));
        }
        catch (InvalidArgumentException $e)
        {
            debug('info', $e->getMessage(), __FILE__, __LINE__, __METHOD__);
            return array('status' => Http::bad_request);
        }
        catch (Exception $e)
        {
            debug('error', 'Unexpected exception: ' . $e->getMessage(),__FILE__, __LINE__, __METHOD__);
            return array('status' => Http::server_error);
        }
    }

    /** @copydoc BaseController::delete_item */
    public function delete_action($request, $condition = null)
    {
        // Check if element ID is given (e.g. /api.php/fooBar/ID where id is numeric)
        if(isset($request->url_elements[2]) && is_numeric($request->url_elements[2]) && (int)$request->url_elements[2] > 0)
        {
            return $this->delete_item($request->url_elements[2], $condition);
        }
        else
        {
            // No id given -> Invalid request
            return array('status' => Http::bad_request);
        }
    }

    /** @brief Returns result object with information about a single item 
        @param id Id of the element which to fetch.
     */
    protected function get_single_information($id)
    {
        $class = null;
        try
        {
            $class = new $this->managed_class_name($this->database, $this->current_user, $this->log, (int)$id);
        }
        catch (Exception $e)
        {
            if ($e instanceof NoSuchElementException)
            {
                return array('status' => Http::not_found);
            } else {
                debug(  'error', 'Got serious exception, which seems to be a server issue. Return 500.',
                        __FILE__, __LINE__, __METHOD__);
                return array('status' => Http::server_error);
            }
        }
        return array(   'status' => Http::ok,
                        'body' => $this->class_to_array($class));
    }

    /** @brief Returns information for a set of items (by query)
     *  @param $query Array with elements 'query' (the WHERE clauses), 'order' (e.g. ORDER BY) and 'keywords'
     *  @param $range_header Range header given by request (Array 'start' and 'end') or null
     *  @param $table_name Name of the database table to work on (e.g. categories)
     */ 
    protected function get_query_information($query, $range_header, $table_name)
    {
        // Check if Range header is set and if yes handle range
        $range = '';
        $order = '';
        $option_params = isset($request->parameters['sortedBy']) ? $request->parameters['sortedBy'] : null;
        if ($option_params !== null)
        {
            $sort = explode(',', $option_params);
            foreach ($sort as $key)
            {
                $order .= $order != '' ? ', ' : '';
                if (!in_array(substr($key, 1), $this->query_parameters_whitelist) || 
                    (($key[0] != '-') && ($key[0] != '+')))
                    return array('status' => Http::bad_request);
                $order .= substr($key, 1) . ' ' . ($key[0] == '+' ? 'ASC' : 'DESC');
            }
            $order = ' ORDER BY ' . $order;
        }
        $data['headers']['Accept-Ranges'] = 'items';
        if ($range_header != null)
        {
            $count_query = "SELECT COUNT(1) as number_of_elements FROM $table_name " . $query['query'] . $order;
            $result = null;
            try
            {
                $result = $this->database->query($count_query, $query['keywords']);
            }
            catch (Exception $e)
            {
                debug('error', 'Got database exception: ' . $e->getMessage(),
                __FILE__, __LINE__, __METHOD__);
                return array('status' => Http::server_error);
            }
            if ($result[0]['number_of_elements'] == 0)
                return array('status' => Http::no_content);
            if ($range_header['start'] >= $result[0]['number_of_elements'])
            {
                debug('warning', 'Requested Range of: ' . $range_header['start'] .
                '-' . $range_header['end'] . ' Elements could not be satisfied.',
                __FILE__, __LINE__, __METHOD__);
                return array('status' => Http::range_not_satisfiable);
            }
            $duration = $range_header['end'] - $range_header['start'] + 1;
            $end = $range_header['end'];
            // Real end (e.g. header end is greater then we have items)
            if (($result[0]['number_of_elements'] - 1) < $range_header['end'])
            {
                $end = $result[0]['number_of_elements'] - 1;
            }
            $range = ' LIMIT ' . $range_header['start'] . ', ' . $duration;
            $data['status'] = Http::partial_content;
            $data['headers']['Content-Range'] = 'items ' . $range_header['start'] . '-' . $end . '/' . $result[0]['number_of_elements'];
        }
        
        $query_str = "SELECT id FROM $table_name " . $query['query'] . $order . $range;
        $query_data = array();
        try
        {
            $query_data = $this->database->query($query_str, $query['keywords']);
        }
        catch (Exception $e)
        {
            debug('error', 'Got database exception: ' . $e->getMessage(),
            __FILE__, __LINE__, __METHOD__);
            return array('status' => Http::server_error);
        }
        $number = count($query_data);
        if ($number == 0)
            return array('status' => Http::no_content);
        foreach ($query_data as $id)
        {
            $data['body'][] = $this->class_to_array(new $this->managed_class_name($this->database, $this->current_user, $this->log, (int)$id['id']));
        }
        return $data;
    }

    /** @brief Deletes requested element from database.
        @return ARRAY('status' => HTTP_STATUS_CODE)
        @todo Handle users correctly (a general todo)
     */
    protected function delete_item($id, $condition = null)
    {
        if ($condition == null)
        {
            $condition = function($obj)
            {
                return true;
            };
        }
        $class = null;
        try
        {
            $class = new $this->managed_class_name($this->database, $this->current_user, $this->log, (int)$id);
        }
        catch (Exception $e)
        {
            if ($e instanceof NoSuchElementException)
            {
                return array( 'status' => Http::not_found);
            }
            debug(  'error', 'Got serious exception, which seems to be a server issue. Return 500.',
                     __FILE__, __LINE__, __METHOD__);
            return array('status' => Http::server_error);
        }
        if (!$condition($class))
        {
            return array('status' => Http::conflict);
        }
        try
        {
            $class->delete();
            $class = NULL;
        }
        catch (Exception $e)
        {
            debug('error', 'Could not delete item: ' . $class->get_name() . '. Error: ' . $e->getMessage(),
                    __FILE__, __LINE__, __METHOD__);
            return array('status' => Http::server_error); // Handle 401 403 when we add support for users
        }
        return array('status' => Http::no_content);
    }

    /** @brief Edit selected item
     *  @param $attributes Attributes to set
     *  @param $id Id of the given item
    */
    protected function edit_item($attributes, $id)
    {
        try
        {
            $item = new $this->managed_class_name($this->database, $this->current_user, $this->log, $id);
            $item->set_attributes($attributes);
            return array('status' => Http::created,
                         'body' => $this->class_to_array($item),
                         'headers' => array('Location' => 'http://' . $request->headers['Host'] . $_SERVER['REQUEST_URI']. '/' . $item->get_id()));
        }
        catch (Exception $e)
        {
            debug('error', 'Unexpected exception: ' . $e->getMessage(), __FILE__, __LINE__, __METHOD__);
            return array('status' => Http::server_error);
        }
    }

    /** @brief Checks if the 'IF-MATCH' and 'IF-NOT-MATCH' headers match.
     *  @param $header Request-headers
     *  @param $parameters Parameters given in request
     */
    protected function check_match_headers($headers, $parameters)
    {
        if (isset($parameters['id']) && is_numeric($parameters['id']))
        {
            try
            {
                $obj = new $this->managed_class_name($this->database, $this->current_user, $this->log, (int)$parameters['id']);
                $id_exsists = true;
            }
            catch (Exception $e)
            {
                if ($e instanceof NosuchElementException)
                {
                    $id_exsists = false;
                }
                else
                {
                    debug('error', 'Got unexpected exception, message: ' . $e->getMessage(),
                          __FILE__, __LINE__, __METHOD__);
                    throw new Exception();
                }
            }
        }

        if (isset($parameters['name']))
        {
            $found = array();
            $parent_id = (isset($parameters['parent']) && is_numeric($parameters['parent'])) ? (int)$parameters['parent'] : 0;
            try
            {
                $class_name = $this->managed_class_name;
                $found = $class_name::search($this->database, $this->current_user, $this->log,
                                            $parameters['name'], true);
            }
            catch (exception $e)
            {
                debug('error', 'Got unexpected exception, message: ' . $e->getMessage(),
                      __FILE__, __LINE__, __METHOD__);
                throw new Exception();
            }
            if (count($found) == 0)
                $name_exsists = false;
            else
            {
                foreach($found as $possible_match)
                {
                    if ($possible_match->get_parent_id() == $parent_id)
                        $name_exsists = true;
                }
            }
        }

        if (isset($headers['If-None-Match']) && $headers['If-None-Match'] == '*')
        {
            if ((isset($id_exsists) && $id_exsists) || (isset($name_exsists) && $name_exsists))
                return false; // Id or name already exsists (Precondition failed)
        }
        elseif (isset($headers['If-Match']) && $headers['If-Match'] == '*')
        {
            if (isset($id_exsists) && !$id_exsists)
                return false; // Id does not exsists -> Precondition failed
        }
        return true; // Ok no header
    }
}
?>
