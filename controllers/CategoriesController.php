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
        protected $root_category;

        public function __construct()
        {
            parent::__construct();
            $this->root_category = new Category($this->database, $this->current_user, $this->log, 0);
        }

        public function getAction($request)
        {
            if(isset($request->url_elements[2])) {
                $user_id = (int)$request->url_elements[2];
                if(isset($request->url_elements[3])) {
                    switch($request->url_elements[3]) {
                    case 'friends':
                        $data["message"] = "user " . $user_id . "has many friends";
                        break;
                    default:
                        // do nothing, this is not a supported action
                        break;
                    }
                } else {
                    $data["message"] = "here is the info for user " . $user_id;
                }
            } else {
                $data["message"] = "you want a list of users";
            }
            return $data;
        }

        public function postAction($request) {
            $data = $request->parameters;
            $data['message'] = "This data was submitted";
            return $data;
        }
    }
?>
