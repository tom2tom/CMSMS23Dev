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

#Turkish

#Native language name
$nls['language']['tr_TR'] = 'Türkçe';
$nls['englishlang']['tr_TR'] = 'Turkish';

#Possible aliases for language
$nls['alias']['tr'] = 'tr_TR';
$nls['alias']['turkish'] = 'tr_TR' ;
$nls['alias']['trk'] = 'tr_TR' ;
$nls['alias']['tr_TR.ISO8859-9'] = 'tr_TR' ;
$nls['alias']['tr_TR.UTF-8'] = 'tr_TR' ;

#Possible locale for language
$nls['locale']['tr_TR'] = 'tr_TR,tr_TR.utf8,tr_TR.UTF-8,tr_TR.utf-8,turkish,Turkish_Turkey.1254';

#Encoding of the language
$nls['encoding']['tr_TR'] = 'UTF-8';

#Location of the file(s)
$nls['file']['tr_TR'] = array(__DIR__.'/tr_TR/admin.inc.php');

#Language setting for HTML area
# Only change this when translations exist in HTMLarea and plugin dirs
# (please send language files to HTMLarea development)

$nls['htmlarea']['tr_TR'] = 'en';

?>
