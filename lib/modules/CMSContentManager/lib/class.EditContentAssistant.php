<?php
# EditContentAssistant: base class for building edit-content assistant objects
# Copyright (C) 2013-2018 Robert Campbell <calguy1000@cmsmadesimple.org>
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

namespace CMSContentManager;

use CMSMS\ContentBase;
use CMSMS\internal\ContentAssistant;

/**
 * An abstract class for building edit content assistant objects.
 */
abstract class EditContentAssistant implements ContentAssistant
{
	private $_content_obj;

	/**
	 * construct an EditContentAssistant object.
	 *
	 * @param ContentBase Scontent he content-object that we are building an assistant for.
	 */
	public function __construct(ContentBase $content)
	{
		$this->_content_obj = $content;
	}

	/**
	 * Get HTML (including javascript) that should go in the page content when editing this content object.
	 * This could be used for outputting some javascript to enhance the functionality of some content fields.
	 *
	 * @return string
	 */
	abstract public function getExtraCode();
} // class
