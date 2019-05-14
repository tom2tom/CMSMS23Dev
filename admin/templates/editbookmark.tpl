<form action="{$selfurl}{$urlext}" enctype="multipart/form-data" method="post">
  <input type="hidden" name="bookmark_id" value="{$bookmark_id}" />

  <div class="pageoverflow">
    <p class="pagetext">{lang('title')}:</p>
    <p class="pageinput">
      <input type="text" name="title" maxlength="255" value="{$title}" />
    </p>
  </div>
  <div class="pageoverflow">
    <p class="pagetext">{lang('url')}:</p>
    <p class="pageinput">
      <input type="text" name="url" size="80" maxlength="255" value="{$url}" />
    </p>
  </div>
  <div class="pageinput pregap">
    <button type="submit" name="editbookmark" class="adminsubmit icon check">{lang('submit')}</button>
    <button type="submit" name="cancel" class="adminsubmit icon cancel">{lang('cancel')}</button>
  </div>
</form>
