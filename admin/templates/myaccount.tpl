<form action="{$selfurl}{$urlext}" method="post">
  <div class="pageoverflow">
    <p class="pagetext">
      <label for="username">*&nbsp;{lang('username')}:</label>
      {cms_help key2='help_myaccount_username' title=lang('name')}
    </p>
    <p class="pageinput">
      <input type="text" name="user" id="username" maxlength="25" value="{$userobj->username}" class="standard" />
    </p>
  </div>

  <div class="pageoverflow">
    <p class="pagetext">
      <label for="password">{lang('password')}:</label>
      {cms_help key2='help_myaccount_password' title=lang('password')}
    </p>
    <p class="pageinput">
      <input type="password" name="password" id="password" maxlength="100" value="" class="standard" />
    </p>
  </div>

  <div class="pageoverflow">
    <p class="pagetext">
      <label for="passwordagain">{lang('passwordagain')}:</label>
      {cms_help key2='help_myaccount_passwordagain' title=lang('passwordagain')}
    </p>
    <p class="pageinput">
      <input type="password" name="passwordagain" id="passwordagain" maxlength="100" value="" class="standard" />
    </p>
  </div>

  <div class="pageoverflow">
    <p class="pagetext">
      <label for="firstname">{lang('firstname')}:</label>
      {cms_help key2='help_myaccount_firstname' title=lang('firstname')}
    </p>
    <p class="pageinput">
      <input type="text" name="firstname" id="firstname" maxlength="50" value="{$userobj->firstname}" class="standard" />
    </p>
  </div>

  <div class="pageoverflow">
    <p class="pagetext">
      <label for="lastname">{lang('lastname')}:</label>
      {cms_help key2='help_myaccount_lastname' title=lang('lastname')}
    </p>
    <p class="pageinput">
      <input type="text" name="lastname" id="lastname" maxlength="50" value="{$userobj->lastname}" class="standard" />
    </p>
  </div>

  <div class="pageoverflow">
    <p class="pagetext">
      <label for="email">{lang('email')}:</label>
      {cms_help key2='help_myaccount_email' title=lang('email')}
    </p>
    <p class="pageinput">
      <input type="text" name="email" id="email" size="40" maxlength="255" value="{$userobj->email}" class="standard" />
    </p>
  </div>
  <div class="pageinput pregap">
    <button type="submit" name="submit" class="adminsubmit icon check">{lang('submit')}</button>
    <button type="submit" name="cancel" class="adminsubmit icon cancel">{lang('cancel')}</button>
  </div>
</form>
