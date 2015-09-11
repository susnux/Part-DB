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
        2015-09-11  susnux         - created
*/

/** @file class.Http.php
    @brief Class Http

    @class Http
    @brief Abstract class containing HTTP Status codes
    @note Usage: Http::not_found
*/
abstract class Http
{
    const ok                    = 200;
    const created               = 201;
    const no_content            = 204;
    const partial_content       = 206;
    const bad_request           = 400;
    const not_found             = 404;
    const method_not_allowed    = 405;
    const precondition_failed   = 412;
    const range_not_satisfiable = 416;
    const server_error          = 500;
}

?>
