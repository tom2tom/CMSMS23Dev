<?php
#...
#Copyright (C) 2004-2018 Ted Kulp <ted@cmsmadesimple.org>
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

#NLS (National Language System) array.

#The basic idea and values was taken from then Horde Framework (http://horde.org)
#The original filename was horde/config/nls.php.
#The modifications to fit it for Gallery were made by Jens Tkotz
#(http://gallery.meanalto.com) 

#Ideas from Gallery's implementation made to CMS by Ted Kulp

#Finnish
#Created by: Jani Mikkonen <jani@mikkonen.org>
#Maintained by: Jani Mikkonen <jani@mikkonen.org>

#Native language name
$nls['language']['fi_FI'] = 'Suomi';
$nls['englishlang']['fi_FI'] = 'Finnish';

#Possible aliases for language
$nls['alias']['fi'] = 'fi_FI';
$nls['alias']['finnish'] = 'fi_FI' ;
$nls['alias']['fin'] = 'fi_FI' ;
$nls['alias']['fi_FI.ISO8859-1'] = 'fi_FI' ;
$nls['alias']['fi_FI.ISO8859-15'] = 'fi_FI' ;

#Possible locale for language
$nls['locale']['fi_FI'] = 'fi_FI,fi_FI.utf8,fi_FI.utf-8,fi_FI.UTF-8,fi_FI@euro,finnish,Finnish_Finland.1252';

#Encoding of the language
$nls['encoding']['fi_FI'] = 'UTF-8';

#Location of the file(s)
$nls['file']['fi_FI'] = array(__DIR__.'/fi_FI/admin.inc.php');

#Language setting for HTML area
# Only change this when translations exist in HTMLarea and plugin dirs
# (please send language files to HTMLarea development)

$nls['htmlarea']['fi_FI'] = 'en';

?>
