<?php
# Interface for file-picking modules
# Copyright (C) 2016-2018 Robert Campbell <calguy1000@cmsmadesimple.org>
# This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <https://www.gnu.org/licenses/>.

namespace CMSMS;

/**
 * Interface for modules that provide filepicker functionality.
 *
 * @package CMS
 * @license GPL
 * @author Robert Campbell <calguy1000@cmsmadesimple.org>
 * @since  2.2
 */
interface FilePicker
{
    /**
     * Given a profile name and other data, return a suitable profile by name, or return a default profile
     *
     * @param string $profile_name the desired profile name to load
     * @param string $dir A suitable top location
     * @param int $uid An optional admin user id.
     * @return CMSMS\FilePickerProfile
     */
    public function get_profile_or_default( $profile_name, $dir = null, $uid = null );

    /**
     * Get the default profile for the specified data.
     * @param string $dir A suitable top location
     * @param int $uid An optional admin user id.
     * @return CMSMS\FilePickerProfile
     */
    public function get_default_profile( $dir = null, $uid = null );

    /**
     * Get the URL required to render the filepicker
     *
     * @return string
     */
    public function get_browser_url();

    /**
     * Generate HTML to display an input field that is initialized with the filepicker plugin.
     *
     * @param string $name The name for the input field.
     * @param string $value the current value for the input filed
     * @param CMSMS\FilePickerProfile $profile The profile to use when building the filepicker interface.
	 * @param bool   $required Optional property ... (see FilePicker::get_html()) default false
     */
    public function get_html( $name, $value, $profile, $required = false );
} // interface
