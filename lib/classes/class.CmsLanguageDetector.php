<?php
#Translation functions/classes
#Copyright (C) 2014-2018 Robert Campbell <calguy1000@cmsmadesimple.org>
#This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>
#
#This program is free software; you can redistribute it and/or modify
#it under the terms of the GNU General Public License as published by
#the Free Software Foundation; either version 2 of the License, or
#(at your option) any later version.
#
#This program is distributed in the hope that it will be useful,
#but WITHOUT ANY WARRANTY; without even the implied warranty of
#MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#GNU General Public License for more details.
#You should have received a copy of the GNU General Public License
#along with this program. If not, see <https://www.gnu.org/licenses/>.

//namespace CMSMS;

/**
 * An abstract class that is used to determine a suitable language for display
 * This may be used by CMSMS on frontend requests to detect a suitable language.
 * modules may supply a language detector to read from preferences etc.
 *
 * @see CmsNlsOperations::set_language_detector()
 * @author Robert Campbell
 * @package CMS
 * @license GPL
 * @since 1.11
 */
abstract class CmsLanguageDetector
{
  /**
   * Abstract function to determine a language.
   * This method may use cookies, session data, user preferences, values from the url
   * or from the browser to determine a language.  The returned language must exist
   * within the CMSMS Install.
   *
   * @return string language name
   */
  abstract public function find_language();

} // class
