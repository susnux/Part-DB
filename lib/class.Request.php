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
        /** @brief Header-parameter given to the server. */
        public $header;

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
            $this->parse_parameters();
            $this->parse_header();
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
            $content_type = false;
            if(isset($_SERVER['CONTENT_TYPE'])) {
                $content_type = $_SERVER['CONTENT_TYPE'];
            }
            switch($content_type) {
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
                    debug('error', 'Unknown content type given: >>' . $content_type . '<<', __FILE__, __LINE__, __METHOD__);
                    // we could parse other supported formats here
                    break;
            }
            $this->parameters = $parameters;
        }

        /** @brief Parses given headers into an array. */
        protected function parse_header()
        {
            // Get headers from request...
            $headers = array();
            foreach($_SERVER as $key => $value) {
                if (substr($key, 0, 5) <> 'HTTP_')
                    continue;
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$header] = $value;
            }
            return $headers;
        }
    }
?>
