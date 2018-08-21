<?php

use CMSMS\Events;

if (!isset($gCms)) exit;

class SearchItemCollection
{
    var $_ary;
    var $maxweight;

    function __construct()
    {
        $this->_ary = [];
        $this->maxweight = 1;
    }

    function AddItem($title, $url, $txt, $weight = 1, $module = '', $modulerecord = 0)
    {
        if( $txt == '' ) $txt = $url;
        $exists = false;

        foreach ($this->_ary as $oneitem) {
            if ($url == $oneitem->url) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $newitem = new StdClass();
            $newitem->url = $url;
            $newitem->urltxt = search_CleanupText($txt);
            $newitem->title = $title;
            $newitem->intweight = (int)$weight;
            if ((int)$weight > $this->maxweight) $this->maxweight = (int)$weight;
            if (!empty($module) ) {
                $newitem->module = $module;
                if((int)$modulerecord > 0 )	$newitem->modulerecord = $modulerecord;
            }
            $this->_ary[] = $newitem;
        }
    }

    function CalculateWeights()
    {
        reset($this->_ary);
        while (list($key) = each($this->_ary)) {
            $oneitem =& $this->_ary[$key];
            $oneitem->weight = (int)($oneitem->intweight / $this->maxweight) * 100;
        }
    }

    function Sort()
    {
        $fn = function($a,$b) {
            if ($a->urltxt == $b->urltxt) return 0;
            return ($a->urltxt < $b->urltxt ? -1 : 1);
        };

        usort($this->_ary, $fn);
    }
} // end of class

/////////////////////////////////////////////////////////////////////////////////////

$template = null;
if( isset($params['resulttemplate']) ) {
    $template = trim($params['resulttemplate']);
}
else {
    $tpl = CmsLayoutTemplate::load_dflt_by_type('Search::searchresults');
    if( !is_object($tpl) ) {
        audit('',$this->GetName(),'No default summary template found');
        return;
    }
    $template = $tpl->get_name();
}

$tpl = $smarty->createTemplate($this->GetTemplateResource($template),null,null,$smarty);

if ($params['searchinput'] != '') {
// $_POST/$_GET parameters are filter_var()'d before passing them here
    // Fix to prevent XSS like behavior. See: http://www.securityfocus.com/archive/1/455417/30/0/threaded
//    $params['searchinput'] = cms_html_entity_decode($params['searchinput'],ENT_COMPAT,'UTF-8');
//    $params['searchinput'] = strip_tags($params['searchinput']);
    Events::SendEvent( 'Search', 'SearchInitiated', [ trim($params['searchinput'])] );

    $searchstarttime = microtime(true);

    $tpl->assign('phrase', $params['searchinput']);
    $words = array_values($this->StemPhrase($params['searchinput']));
    $nb_words = count($words);
    $max_weight = 1;

    $searchphrase = '';
    if ($nb_words > 0) {
        #$searchphrase = implode(' OR ', array_fill(0, $nb_words, 'word = ?'));
        $ary = [];
        foreach ($words as $word) {
            $word = trim($word);
            // $ary[] = "word = " . $db->qstr(cms_htmlentities($word));
            $ary[] = 'word = ' . $db->qstr($word);
        }
        $searchphrase = implode(' OR ', $ary);
    }

    // Update the search words table
    if( $this->GetPreference('savephrases','false') == 'false' ) {
        foreach( $words as $word ) {
            $q = 'SELECT count FROM '.CMS_DB_PREFIX.'module_search_words WHERE word = ?';
            $tmp = $db->GetOne($q,[$word]);
            if( $tmp ) {
                $q = 'UPDATE '.CMS_DB_PREFIX.'module_search_words SET count=count+1 WHERE word = ?';
                $db->Execute($q,[$word]);
            }
            else {
                $q = 'INSERT INTO '.CMS_DB_PREFIX.'module_search_words (word,count) VALUES (?,1)';
                $db->Execute($q,[$word]);
            }
        }
    }
    else {
        $term = trim($params['searchinput']);
        $q = 'SELECT count FROM '.CMS_DB_PREFIX.'module_search_words WHERE word = ?';
        $tmp = $db->GetOne($q,[$term]);
        if( $tmp ) {
            $q = 'UPDATE '.CMS_DB_PREFIX.'module_search_words SET count=count+1 WHERE word = ?';
            $db->Execute($q,[$term]);
        }
        else {
            $q = 'INSERT INTO '.CMS_DB_PREFIX.'module_search_words (word,count) VALUES (?,1)';
            $db->Execute($q,[$term]);
        }
    }

    $val = 100 * 100 * 100 * 100 * 25;
    $query = 'SELECT DISTINCT i.module_name, i.content_id, i.extra_attr, COUNT(*) AS nb, SUM(idx.count) AS total_weight
FROM '.CMS_DB_PREFIX.'module_search_items i INNER JOIN '.CMS_DB_PREFIX.'module_search_index idx ON i.id = idx.item_id
WHERE ('.$searchphrase.') AND (i.expires IS NULL OR i.expires >= NOW())';
    if( isset( $params['modules'] ) ) {
        $modules = explode(',',$params['modules']);
        for( $i = 0, $n = count($modules); $i < $n; $i++ ) {
            $modules[$i] = $db->qstr($modules[$i]);
        }
        $query .= ' AND i.module_name IN ('.implode(',',$modules).')';
    }
    $query .= ' GROUP BY i.module_name, i.content_id, i.extra_attr';
    if( !isset($params['use_or']) || $params['use_or'] == 0 ) {
        //This makes it an AND query
        $query .= " HAVING count(*) >= $nb_words";
    }
    $query .= ' ORDER BY nb DESC, total_weight DESC';

    $result = $db->Execute($query);
    $hm = $gCms->GetHierarchyManager();
    $col = new SearchItemCollection();

    while ($result && !$result->EOF) {
        //Handle internal (templates, content, etc) first...
        if ($result->fields['module_name'] == $this->GetName()) {
            if ($result->fields['extra_attr'] == 'content') {
                //Content is easy... just grab it out of hierarchy manager and toss the url in
                $node = $hm->sureGetNodeById($result->fields['content_id']);
                if (isset($node)) {
                    $content = $node->GetContent();
                    if (isset($content) && $content->Active()) $col->AddItem($content->Name(), $content->GetURL(), $content->Name(), $result->fields['total_weight']);
                }
            }
        }
        else {
            $thepageid = $this->GetPreference('resultpage',-1);
            if( $thepageid == -1 ) $thepageid = $returnid;
            if( isset($params['detailpage']) ) {
                $tmppageid = '';
                $manager = $gCms->GetHierarchyManager();
                $node = $manager->sureGetNodeByAlias($params['detailpage']);
                if (isset($node)) {
                    $tmppageid = $node->getID();
                }
                else {
                    $node = $manager->sureGetNodeById($params['detailpage']);
                    if (isset($node)) $tmppageid= $params['detailpage'];
                }
                if( $tmppageid ) $thepageid = $tmppageid;
            }
            if( $thepageid == -1 ) $thepageid = $returnid;

            //Start looking at modules...
            $modulename = $result->fields['module_name'];
            $moduleobj = $this->GetModuleInstance($modulename);
            if ($moduleobj != FALSE) {
                if (method_exists($moduleobj, 'SearchResultWithParams' )) {
                    // search through the params, for all the passthru ones
                    // and get only the ones matching this module name
                    $parms = [];
                    foreach( $params as $key => $value ) {
                        $str = 'passthru_'.$modulename.'_';
                        if( preg_match( "/$str/", $key ) > 0 ) {
                            $name = substr($key,strlen($str));
                            if( $name != '' ) $parms[$name] = $value;
                        }
                    }
                    $searchresult = $moduleobj->SearchResultWithParams( $thepageid, $result->fields['content_id'],
                                                                        $result->fields['extra_attr'], $parms);
                    if (count($searchresult) == 3) {
                        $col->AddItem($searchresult[0], $searchresult[2], $searchresult[1],
                                      $result->fields['total_weight'], $modulename, $result->fields['content_id']);
                    }
                }
                else if (method_exists($moduleobj, 'SearchResult')) {
                    $searchresult = $moduleobj->SearchResult( $thepageid, $result->fields['content_id'], $result->fields['extra_attr']);
                    if (count($searchresult) == 3) {
                        $col->AddItem($searchresult[0], $searchresult[2], $searchresult[1],
                                      $result->fields['total_weight'], $modulename, $result->fields['content_id']);
                    }
                }
            }
        }

        $result->MoveNext();
    }

    $col->CalculateWeights();
    if ($this->GetPreference('alpharesults', 'false') == 'true') $col->Sort();

    // now we're gonna do some post processing on the results
    // and replace the search terms with <span class="searchhilite">term</span>

    $results = $col->_ary;
    $newresults = [];
    foreach( $results as $result ) {
        $title = cms_htmlentities($result->title);
        $txt = cms_htmlentities($result->urltxt);
        foreach( $words as $word ) {
            $word = preg_quote($word);
            $title = preg_replace('/\b('.$word.')\b/i', '<span class="searchhilite">$1</span>', $title);
            $txt = preg_replace('/\b('.$word.')\b/i', '<span class="searchhilite">$1</span>', $txt);
        }
        $result->title = $title;
        $result->urltxt = $txt;
        $newresults[] = $result;
    }
    $col->_ary = $newresults;

    Events::SendEvent( 'Search', 'SearchCompleted', [ &$params['searchinput'], &$col->_ary ] );

    $tpl->assign('searchwords',$words)
     ->assign('results', $col->_ary)
     ->assign('itemcount', count($col->_ary));

    $searchendtime = microtime(true);
    $tpl->assign('timetook', ($searchendtime - $searchstarttime));
}
else {
    $tpl->assign('phrase', '')
     ->assign('results', 0)
     ->assign('itemcount', 0)
     ->assign('timetook', 0);
}

$tpl->assign('use_or_text', $this->Lang('use_or'))
 ->assign('searchresultsfor', $this->Lang('searchresultsfor'))
 ->assign('noresultsfound', $this->Lang('noresultsfound'))
 ->assign('timetaken', $this->Lang('timetaken'));
$tpl->display();

