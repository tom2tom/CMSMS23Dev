<?php

namespace AdminLog;

use AdminLog;
use AdminLog\event;
use AdminLog\storage;
use cms_siteprefs;
use cms_utils;
use CMSMS\IAuditManager;
use function get_userid;
use function get_username;

class auditor implements IAuditManager
{
    private $_mod;
    private $_storage;

    public function __construct( AdminLog $mod, storage $store )
    {
        $this->_mod = $mod;
        $this->_storage = $store;
    }

    protected function get_event_parms()
    {
        $parms = [];
        $parms['uid']= get_userid(FALSE);
        $parms['username'] = get_username(FALSE);
        if( $parms['uid'] ) $parms['ip_addr'] = cms_utils::get_real_ip();
        return $parms;
    }

    public function audit( string $subject, string $msg, $item_id = null )
    {
        $parms = $this->get_event_parms();
        $parms = array_merge($parms,[ 'severity'=>event::TYPE_MSG, 'subject'=>$subject, 'msg'=>$msg, 'item_id'=>$item_id ] );

        $ev = new event( $parms );
        $this->_storage->save( $ev );
    }

    protected function error_log( $severity, $msg )
    {
        switch( $severity ) {
        case event::TYPE_WARNING:
            $sevmsg = 'WARNING';
            break;
        case event::TYPE_ERROR:
            $sevmsg = 'ERROR';
            break;
        default:
            $sevmsg = 'NOTICE';
            break;
        }
        $sitename = cms_siteprefs::get('sitename','CMSMS Site');
        $msg = "$sitename $sevmsg: $msg";
        @error_log( $msg, 0 );
    }

    public function notice( string $msg, string $subject = '' )
    {
        $parms = $this->get_event_parms();
        $parms = array_merge($parms,[ 'severity'=>event::TYPE_NOTICE, 'msg'=>$msg, 'subject'=>$subject ]);
        $ev = new event( $parms );
        $this->_storage->save( $ev );
        $this->error_log( event::TYPE_NOTICE, $msg );
    }

    public function warning( string $msg, string $subject = '' )
    {
        $parms = $this->get_event_parms();
        $parms = array_merge($parms,[ 'severity'=>event::TYPE_WARNING, 'msg'=>$msg, 'subject'=>$subject ]);
        $ev = new event( $parms );
        $this->_storage->save( $ev );
        $this->error_log( event::TYPE_WARNING, $msg );
    }

    public function error( string $msg, string $subject = '' )
    {
        $parms = $this->get_event_parms();
        $parms = array_merge($parms,[ 'severity'=>event::TYPE_ERROR, 'msg'=>$msg, 'subject'=>$subject ]);
        $ev = new event( $parms );
        $this->_storage->save( $ev );
        $this->error_log( event::TYPE_ERROR, $msg );
    }

} // class
