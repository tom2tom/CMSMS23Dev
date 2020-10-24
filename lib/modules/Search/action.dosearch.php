<?php
# Search module action: dosearch
# Copyright (C) 2004-2020 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

use CMSMS\Events;
use CMSMS\TemplateOperations;
use Search\ItemCollection;

if (!isset($gCms)) exit;

$template = null;
if (isset($params['resulttemplate'])) {
    $template = trim($params['resulttemplate']);
} else {
    $tpl = TemplateOperations::get_default_template_by_type('Search::searchresults');
    if (!is_object($tpl)) {
        audit('',$this->GetName(),'No default summary template found');
        return '';
    }
    $template = $tpl->get_name();
}

$tpl = $smarty->createTemplate($this->GetTemplateResource($template)); //,null,null,$smarty);

if ($params['searchinput'] != '') {
// $_POST/$_GET parameters are filter_var()'d before passing them here
    // Fix to prevent XSS like behavior. See: http://www.securityfocus.com/archive/1/455417/30/0/threaded
//    $params['searchinput'] = cms_html_entity_decode($params['searchinput'],ENT_COMPAT,'UTF-8');
//    $params['searchinput'] = strip_tags($params['searchinput']);
    Events::SendEvent( 'Search', 'SearchInitiated', [trim($params['searchinput'])]);

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
            // $ary[] = "word = " . $db->qStr(cms_htmlentities($word));
            $ary[] = 'word = ' . $db->qStr($word);
        }
        $searchphrase = implode(' OR ', $ary);
    }

    // Update the search words table
    if (!$this->GetPreference('savephrases',1)) {
        foreach ($words as $word) {
            $query = 'SELECT count FROM '.CMS_DB_PREFIX.'module_search_words WHERE word = ?';
            $tmp = $db->GetOne($query,[$word]);
            if ($tmp) {
                $query = 'UPDATE '.CMS_DB_PREFIX.'module_search_words SET count=count+1 WHERE word = ?';
                $db->Execute($query,[$word]);
            }
            else {
                $query = 'INSERT INTO '.CMS_DB_PREFIX.'module_search_words (word,count) VALUES (?,1)';
                $db->Execute($query,[$word]);
            }
        }
    } else {
        $term = trim($params['searchinput']);
        $query = 'SELECT count FROM '.CMS_DB_PREFIX.'module_search_words WHERE word = ?';
        $tmp = $db->GetOne($query,[$term]);
        if ($tmp) {
            $query = 'UPDATE '.CMS_DB_PREFIX.'module_search_words SET count=count+1 WHERE word = ?';
            $db->Execute($query,[$term]);
        }
        else {
            $query = 'INSERT INTO '.CMS_DB_PREFIX.'module_search_words (word,count) VALUES (?,1)';
            $db->Execute($query,[$term]);
        }
    }

//    $val = 100000000 * 25;
    $pref = CMS_DB_PREFIX;
    $query = <<<EOS
SELECT DISTINCT i.module_name, i.content_id, i.extra_attr, COUNT(*) AS nb, SUM(idx.count) AS total_weight
FROM {$pref}module_search_items i
INNER JOIN {$pref}module_search_index idx ON i.id = idx.item_id
WHERE ($searchphrase) AND (i.expires IS NULL OR i.expires >= NOW())
EOS;
    if (isset( $params['modules'])) {
        $modules = explode(',', $params['modules']);
        for ($i = 0, $n = count($modules); $i < $n; $i++) {
            $modules[$i] = $db->qStr($modules[$i]);
        }
        $query .= ' AND i.module_name IN ('.implode(',',$modules).')';
    }
    $query .= ' GROUP BY i.module_name, i.content_id, i.extra_attr';
    if (empty($params['use_or'])) {
        //this is an AND query
        $query .= " HAVING count(*) >= $nb_words";
    }
    $query .= ' ORDER BY nb DESC, total_weight DESC';

    $rst = $db->Execute($query);
    $hm = $gCms->GetHierarchyManager();
    $col = new ItemCollection();

    while ($rst && !$rst->EOF) {
        //Handle internal (templates, content, etc) first...
        if ($rst->fields['module_name'] == $this->GetName()) {
            if ($rst->fields['extra_attr'] == 'content') {
                //Content is easy... just grab it out of hierarchy manager and toss the url in
                $node = $hm->find_by_tag('id',$rst->fields['content_id']);
                if (isset($node)) {
                    $content = $node->getContent();
                    if ($content && $content->Active()) $col->AddItem($content->Name(), $content->GetURL(), $content->Name(), $rst->fields['total_weight']);
                }
            }
        } else {
            $thepageid = $this->GetPreference('resultpage',-1);
            if ($thepageid == -1) $thepageid = $returnid;
            if (isset($params['detailpage'])) {
                $tmppageid = $hm->find_by_identifier($params['detailpage'],false);
                if ($tmppageid) $thepageid = $tmppageid;
            }
            if ($thepageid == -1) $thepageid = $returnid;

            //Start looking at modules...
            $modulename = $rst->fields['module_name'];
            $moduleobj = $this->GetModuleInstance($modulename);
            if ($moduleobj != FALSE) {
                if (method_exists($moduleobj, 'SearchResultWithParams')) {
                    // search through the params, for all the passthru ones
                    // and get only the ones matching this module name
                    $parms = [];
                    foreach( $params as $key => $value) {
                        $str = 'passthru_'.$modulename.'_';
                        if (preg_match( "/$str/", $key) > 0) {
                            $name = substr($key,strlen($str));
                            if ($name != '') $parms[$name] = $value;
                        }
                    }
                    $searchresult = $moduleobj->SearchResultWithParams( $thepageid, $rst->fields['content_id'],
                                                                        $rst->fields['extra_attr'], $parms);
                    if (count($searchresult) == 3) {
                        $col->AddItem($searchresult[0], $searchresult[2], $searchresult[1],
                                      $rst->fields['total_weight'], $modulename, $rst->fields['content_id']);
                    }
                } elseif (method_exists($moduleobj, 'SearchResult')) {
                    $searchresult = $moduleobj->SearchResult($thepageid, $rst->fields['content_id'], $rst->fields['extra_attr']);
                    if (count($searchresult) == 3) {
                        $col->AddItem($searchresult[0], $searchresult[2], $searchresult[1],
                                      $rst->fields['total_weight'], $modulename, $rst->fields['content_id']);
                    }
                }
            }
        }
        if (!$rst->MoveNext()) {
            break;
        }
    }
    if ($rst) $rst->Close();

    $col->CalculateWeights();
    if ($this->GetPreference('alpharesults', 0)) $col->Sort();

    // now we're gonna do some post processing on the results
    // and replace the search terms with <span class="searchhilite">term</span>

    $results = $col->_ary;
    $newresults = [];
    foreach ($results as $result) {
        $title = cms_htmlentities($result->title);
        $txt = cms_htmlentities($result->urltxt);
        foreach( $words as $word) {
            $word = preg_quote($word);
            $title = preg_replace('/\b('.$word.')\b/i', '<span class="searchhilite">$1</span>', $title);
            $txt = preg_replace('/\b('.$word.')\b/i', '<span class="searchhilite">$1</span>', $txt);
        }
        $result->title = $title;
        $result->urltxt = $txt;
        $newresults[] = $result;
    }
    $col->_ary = $newresults;

    Events::SendEvent( 'Search', 'SearchCompleted', [ &$params['searchinput'], &$col->_ary ]);

    $tpl->assign('searchwords',$words)
     ->assign('results', $col->_ary)
     ->assign('itemcount', count($col->_ary));

    $searchendtime = microtime(true);
    $tpl->assign('timetook', ($searchendtime - $searchstarttime));
} else {
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
return '';
