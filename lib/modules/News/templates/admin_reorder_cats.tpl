<script type="text/javascript">
{literal}//<![CDATA[
function parseTree(ul) {
  var tags = [];
  ul.children('li').each(function() {
    var subtree = $(this).children('ul');
    if(subtree.size() > 0) {
      tags.push([$(this).attr('id'), parseTree(subtree)]);
    } else {
      tags.push($(this).attr('id'));
    }
  });
  return tags;
}

$(document).ready(function() {
  $(document).on('click','[name={$actionid}submit]',function() {
    var tree = JSON.stringify(parseTree($('ul.sortable'))); //IE8+
    $('#submit_data').val(tree);
  });

  $('ul.sortable').nestedSortable({
    disableNesting: 'no-nest',
    forcePlaceholderSize: true,
    handle: 'div',
    items: 'li',
    opacity: 0.6,
    placeholder: 'placeholder',
    tabSize: 25,
    tolerance: 'pointer',
    listType: 'ul',
    toleranceElement: '> div'
  });
});
{/literal}//]]>
</script>

{function category_tree parent=-1 depth=1}{strip}
<ul{if $depth==1} class="sortableList sortable"{/if}>
{foreach $allcats as $cat}
  {if $cat.parent_id == $parent}
  <li id="cat_{$cat.news_category_id}">
    <div class="label">{$cat.news_category_name}</div>
    {category_tree parent=$cat.news_category_id depth=$depth+1}
  </li>
  {/if}
{/foreach}
</ul>
{/strip}{/function}

<h3>{$mod->Lang('reorder_categories')}</h3>
<div class="information">{$mod->Lang('info_reorder_categories')}</div>
{category_tree}
<br />
{form_start id="reorder_form"}
<input type="hidden" name="{$actionid}submit_type" id="submit_type" value=""/>
<input type="hidden" name="{$actionid}data" id="submit_data" value=""/>
<div class="pageoverflow">
  <p class="pageinput">
    <button type="submit" name="{$actionid}submit" class="adminsubmit iconcheck">{$mod->Lang('submit')}</button>
    <button type="submit" name="{$actionid}cancel" class="adminsubmit iconcancel">{$mod->Lang('cancel')}</button>
  </p>
</div>
{form_end}
