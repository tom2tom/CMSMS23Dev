<?php
# Class defining folder-specific properties and roles
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

namespace FilePicker;

class ProfileException extends \Exception {}

class Profile extends \CMSMS\FilePickerProfile
{
    private $_data = [ 'id'=>null, 'name'=>null, 'create_date'=>null, 'modified_date'=>null, 'file_extensions'=>null ];

    protected function setValue( $key, $val )
    {
        switch( $key ) {
        case 'name':
        case 'file_extensions':
            $this->_data[$key] = trim($val);
            break;
        case 'create_date':
        case 'modified_date':
            $this->_data[$key] = (int) $val;
            break;
        default:
            parent::setValue( $key, $val );
            break;
        }
    }

    public function __construct(array $in = null)
    {
        if( !is_array( $in ) ) return;

        foreach( $in as $key => $value ) {
            switch( $key ) {
            case 'id':
                $this->_data[$key] = (int) $value;
                break;
            default:
                $this->setValue( $key, $value );
                break;
            }
        }
    }

    public function __get($key)
    {
        switch( $key ) {
        case 'id':
            return (int) $this->_data[$key];

        case 'name':
        case 'file_extensions':
            return trim($this->_data[$key]);

        case 'create_date':
        case 'modified_date':
            return (int) $this->_data[$key];

        case 'relative_top':
        case 'reltop':
            // parent top is checked for relative or absolute
            // return relative to uploads path
            $val = parent::__get('top');
            if( startswith($val,'/') ) {
                $config = \cms_config::get_instance();
                $uploads_path = $config['uploads_path'];
                if( startswith( $val, $uploads_path ) ) $val = substr($val,strlen($uploads_path));
                if( startswith( $val, '/') ) $val = substr($val,1);
            }
            return $val;

        case 'top':
            // parent top is checked for relative or absolute
            // if relative, prepend uploads path
            $val = parent::__get('top');
            if( !startswith($val,'/') ) {
                $config = \cms_config::get_instance();
                $val = $config['uploads_path'].'/'.$val;
            }
            return $val;

        default:
            return parent::__get($key);
        }
    }

    public function validate()
    {
        if( !$this->name ) throw new ProfileException( 'err_profile_name' );
        if( $this->reltop && !is_dir($this->top) ) throw new ProfileException('err_profile_topdir');
    }

    public function withNewId( $new_id = null )
    {
        if( !is_null($new_id) ) {
            $new_id = (int) $new_id;
            if( $new_id < 1 ) throw new \LogicException('Invalid id passed to '.__METHOD__);
        }
        $obj = clone $this;
        $obj->_data['id'] = $new_id;
        $obj->_data['create_date'] = $obj->_data['modified_date'] = time();
        return $obj;
    }

    public function overrideWith( array $params )
    {
        $obj = clone( $this );
        foreach( $params as $key => $val ) {
            switch( $key ) {
            case 'id':
                // cannot set a new id this way
                break;

            default:
                $obj->setValue($key,$val);
                break;
            }
        }
        return $obj;
    }

    public function markModified()
    {
        $obj = clone $this;
        $obj->_data['modified_date'] = time();
        return $obj;
    }

    public function getRawData()
    {
        $data = parent::getRawData();
        $data = array_merge($data,$this->_data);
        return $data;
    }

    public function is_filename_acceptable( $filename )
    {
        if( !parent::is_filename_acceptable( $filename) ) return FALSE;
        if( !$this->file_extensions ) return FALSE;

        // file must have this extension
        $ext = strtolower(substr(strrchr($file_name, '.'), 1));
        if( !$ext ) return FALSE; // uploaded file has no extension.
        $list = explode(',',$this->_profile->file_extensions);

        foreach( $list as $one ) {
            $one = strtolower(trim($one));
            if( !$one ) continue;
            if( startswith( $one, '.') ) $one = substr($one,1);
            if( $ext == $one ) return TRUE;
        }
        debug_to_log('file type is not acceptable');
        return FALSE;
    }
} // class
