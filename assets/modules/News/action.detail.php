<?php

use News\Article;
use News\Ops;

if (!isset($gCms)) exit;

//
// initialization
//
$query = null;
$article = null;
$preview = false;
$articleid = $params['articleid'] ?? -1;

$template = null;
if (isset($params['detailtemplate'])) {
    $template = trim($params['detailtemplate']);
}
else {
    $tpl = CmsLayoutTemplate::load_dflt_by_type('News::detail');
    if( !is_object($tpl) ) {
        audit('',$this->GetName(),'No default summary template found');
        return;
    }
    $template = $tpl->get_name();
}

if( $id == '_preview_' && isset($_SESSION['news_preview']) && isset($params['preview']) ) {
    // see if our data matches.
    if( md5(serialize($_SESSION['news_preview'])) == $params['preview'] ) {
        $fname = TMP_CACHE_LOCATION.DIRECTORY_SEPARATOR.$_SESSION['news_preview']['fname'];
        if( is_file($fname) && (md5_file($fname) == $_SESSION['news_preview']['checksum']) ) {
            $data = unserialize(file_get_contents($fname), ['allowed_classes'=>false]);
            if( is_array($data) ) {
                // get passed data into a standard format.
                $article = new Article();
                $article->set_linkdata($id,$params);
                Ops::fill_article_from_formparams($article,$data,false,false);
                $preview = true;
            }
        }
    }
}

if( isset($params['articleid']) && $params['articleid'] == -1 ) {
    $article = Ops::get_latest_article();
}
elseif( isset($params['articleid']) && (int)$params['articleid'] > 0 ) {
    $show_expired = $this->GetPreference('expired_viewable',1);
    if( isset($params['showall']) ) $show_expired = 1;
    $article = Ops::get_article_by_id((int)$params['articleid'],true,$show_expired);
}
if( !$article ) {
    throw new CmsError404Exception('Article '.(int)$params['articleid'].' not found, or otherwise unavailable');
}
$article->set_linkdata($id,$params);

$return_url = $this->CreateReturnLink($id, isset($params['origid'])?$params['origid']:$returnid, $this->lang('news_return'));

$tpl = $smarty->createTemplate($this->GetTemplateResource($template),null,null,$smarty);
$tpl->assign('return_url', $return_url)
 ->assign('entry', $article);

$catName = '';
if (isset($params['category_id'])) {
    $catName = $db->GetOne('SELECT news_category_name FROM '.CMS_DB_PREFIX . 'module_news_categories where news_category_id=?',[(int)$params['category_id']]);
}
$tpl->assign('category_name',$catName);
unset($params['article_id']);
$tpl->assign('category_link',$this->CreateLink($id, 'default', $returnid, $catName, $params))

 ->assign('category_label', $this->Lang('category_label'))
 ->assign('author_label', $this->Lang('author_label'))
 ->assign('extra_label', $this->Lang('extra_label'));

$tpl->display();
