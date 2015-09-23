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
        [DATE]      [NICKNAME]      [CHANGES]
        2015-09-06  susnux          - created
*/

     /**
     * @file class.Request.php
     * @brief class Request
     *
     * @class Request
     * @brief This class handles REST requests.
     *        It decodes given parameters and method for the controllers.
     *
     * @author susnux
     */
    class Request
    {
        /** @brief Decoded URL elements, e.g. api.php/parts/10 URL gets decoded to {1 => 'parts', 2 => '10'} */
        public $url_elements;
        /** @brief Method of the request, used to decide what to do (Examples are GET for getting values or DELETE for deleting values).*/
        public $method;
        /** @brief Extra parameters given to the server. */
        public $parameters;
        /** @brief Header-parameters given to the server. */
        public $headers;

        /** @brief Constructor
         *         Initialisizes the class attributes, by calling parameter-extraction function on request.
         */
        public function __construct()
        {
            $this->method = $_SERVER['REQUEST_METHOD'];
            $this->url_elements = array();
            if (isset($_SERVER['PATH_INFO'])) {
                $this->url_elements = explode('/', $_SERVER['PATH_INFO']);
            }
            $this->parse_header();
            $this->parse_parameters();
            // initialise json as default format
            $this->format = 'json';
            if(isset($this->parameters['format'])) {
                $this->format = $this->parameters['format'];
            }
            return true;
        }

        /** @brief Extracts parameters and url elements from request. 
         *  @note If we plan to add more supported content formats for requests, add them here.
         */
        protected function parse_parameters()
        {
            $parameters = array();

            // first of all, pull the GET vars
            if (isset($_GET)) {
                $parameters = $_GET;
            }

            // now how about PUT/POST bodies? These override what we got from GET
            $body = file_get_contents("php://input");
            switch($this->headers['Content-Type']) {
                case "application/json":
                    $body_params = json_decode($body);
                    if($body_params) {
                        foreach($body_params as $param_name => $param_value) {
                            $parameters[$param_name] = $param_value;
                        }
                    }
                    $this->format = "json";
                    break;
                case "application/x-www-form-urlencoded":
                    parse_str($body, $postvars);
                    foreach($postvars as $field => $value) {
                        $parameters[$field] = $value;

                    }
                    $this->format = "html";
                    break;
                default:
                    debug('warning', 'Unknown content type given: >>' . $content_type . '<<', __FILE__, __LINE__, __METHOD__);
                    // we could parse other supported formats here
                    break;
            }
            $this->parameters = $parameters;
        }

        /** @brief Parses given headers into an array. */
        protected function parse_header()
        {
            // If function is not available (e.g. nginx server) use our own function
            if (!function_exists('getallheaders'))
            {
                function getallheaders()
                {
                    if (!is_array($_SERVER))
                    {
                        return array();
                    }

                    $headers = array();
                    foreach ($_SERVER as $name => $value)
                    {
                        if (substr($name, 0, 5) == 'HTTP_')
                        {
                            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                        }
                        elseif ($name == 'CONTENT_TYPE' || $name == 'CONTENT_LENGTH')
                        {
                            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))))] = $value;
                        }
                    }
                    return $headers;
                }
            }
            $headers = getallheaders();
            // Handle known headers
            if (isset($headers['Range']))
            {
                $values = explode('-', str_replace('items=', '', $headers['Range']));
                if (count($values) == 2 && is_numeric($values[0]) && is_numeric($values[1]))
                {
                    $headers['Range'] = array('start' => $values[0], 'end' => (int)$values[1]);
                }
            }
            $this->headers = $headers;
        }
    }
?>
