<?php
/* H */
$lang['help_group_permissions'] = <<<EOT
<h4>CMSMS Admin Permission Model</h4>
<ul>
<li>CMSMS Uses a system of named permissions.  Access to these permissions determines a users ability to perform different functions in the CMSMS admin console.</li>
<li>The CMSMS core creates several permissions on installation <em>(occasionally permissions are added or deleted during an upgrade process)</em>.  Non-core modules might create additional permissions.</li>
<li>Permissions are associated with user groups.  An authorized user can adjust the permissions that are associated with certain member groups <em>(including the permission to change a group permissions)</em>.  The <strong>Admin</strong> group is a special group.  Members of this group will have all permissions.</li>
<li>Admin user accounts can be members of zero or more groups.  It might be possible for a user account that is not a member of any groups to still perform various functionality <em>(please read about ownership and additional-editors in the Content Manager help, and Design Manager help).</em>.  The first user account <em>(uid == 1)</em>, which is typically named &quot;Admin&quot; is a special user account and will have all permissions.</li>
</ul>
EOT;

$lang['help_myaccount_admincallout'] = 'If enabled administrative bookmarks <em>(bookmarks)</em> will be enabled allowing you to manage a list of frequently used actions in the admin console.';
$lang['help_myaccount_admintheme'] = 'Select an administration theme to use.  Different administration themes have different menu layouts, work better for mobile displays, and have various additional features.';
$lang['help_myaccount_ce_navdisplay'] = 'Select which content field should be displayed in content lists.  Options include the page title, or menu text.  If &quot;None&quot; is selected, then the site preference will be used';
$lang['help_myaccount_dateformat'] = 'Specify a date format string to use when dates are displayed.  This string uses <a href="http://php.net/manual/en/function.strftime.php" class="external" target="_blank">strftime</a> format.  <strong>Note:</strong> some independently-developed modules and plugins might ignore this setting.</strong>';
$lang['help_myaccount_dfltparent'] = 'Specify the default parent page for creating a new content page.  The use of this setting also depends on your content editing permissions.<br/><br/>Drill down to the selected default parent page by selecting the topmost parent, and successive child pages from the provided dropdowns.<br/><br/>The text field on the right will always indicate which page is currently selected.';
$lang['help_myaccount_email'] = 'Specify an email address.  This is used for the lost password functionality, and for any notification emails sent by the system (or add-on modules).';
$lang['help_myaccount_enablenotifications'] = 'If enabled, the system will display various notifications about things that need to be taken care of in the navigation';
$lang['help_myaccount_firstname'] = 'Optionally specify your given name.  This might be used in the Admin theme, or to personally address emails to you';
$lang['help_myaccount_hidehelp'] = 'If enabled the system will hide module help links from the admin console.  In most circumstances the help provided with modules is targeted towards site developers and might not be useful to content editors.';
$lang['help_myaccount_homepage'] = 'You may select a page to automatically direct to when you login to the CMSMS admin console.  This might be useful when you primarily use one function.';
$lang['help_myaccount_ignoremodules'] = 'If Admin notifications are enabled you can select to ignore notifications from some modules';
$lang['help_myaccount_indent'] = 'This option will indent the content list view to illustrate the parent and child page relationship';
$lang['help_myaccount_language'] = 'Select the language to display for the Admin interface.  The list of available languages might vary on each CMSMS install';
$lang['help_myaccount_lastname'] = 'Optionally specify your surname.  This might be used in the Admin theme, or to personally address emails to you';
$lang['help_myaccount_password'] = 'Please enter a unique, and secure password for this website.  The password should be more more than six characters long, and should use a combination of upper case, lower case, non alphanumeric, and digits.  Please leave this field blank if you do no wish to change your password.';
$lang['help_myaccount_passwordagain'] = 'To reduce errors, please enter your password again.  Leave this field empty if you do not wish to change your password.';
$lang['help_myaccount_syntax'] = 'Select which syntax highlighting module to use when editing HTML, or smarty code.  The list of available modules might change depending on what your site administrator has configured';
$lang['help_myaccount_username'] = 'Your username is your unique name for the CMSMS Admin panel.  Please use only alphanumeric characters and the underscore';
$lang['help_myaccount_wysiwyg'] = 'Select which WYSIWYG <em>(What You See Is What You Get)</em> module to use when editing HTML content.  You might also select &quot;None&quot; if you are comfortable with HTML.  The list of available WYSIWYG editors might change depending on what the site administrator has configured.';

/* S */
$lang['settings_adminlog_lifetime'] = 'This setting indicates the maximum amount of time that entries in the Admin log should be retained.';
$lang['settings_autoclearcache'] = 'This option allows you to specify the maximum age <em>(in days)</em> before files in the cache directory will be deleted.<br/><br/>This option is useful to ensure that cached files are regenerated periodically, and that the file system does not become polluted with old and unnecessary files.  An ideal value for this field is 14 or 30 days.<br /><br /><strong>Note:</strong> Cached files are cleared at most once per day.';
$lang['settings_autocreate_flaturls'] = 'If SEF/pretty URLs are enabled, and the option to auto-create URLs is enabled, this option indicates hat those auto-created URLS should be flat <em>(i.e: identical to the page alias)</em>.  <strong>Note:</strong> The two values do not need to remain identical, the URL value can be changed to be different than the page alias in subsequent page edits';
$lang['settings_autocreate_url'] = 'When editing content pages, should SEF/pretty URLS be auto-created?  Auto creating URLS will have no effect if pretty urls are not enabled in the CMSMS config.php file.';
$lang['settings_badtypes'] = 'Select which content types to remove from the content type dropdown when editing or adding content.  This feature is useful if you do not want editors to be able to create certain types of content.  Use CTRL+Click to select, unselect items.  Having no selected items will indicate that all content types are allowed. <em>(applies to all users)</em>';
$lang['settings_basicattribs2'] = 'This field allows you to specify which content properties that users without the &quot;Manage All Content&quot; permission are allowed to edit.<br />This feature is useful when you have content editors with restricted permission and want to permit editing of additional content properties.';
$lang['settings_browsercache'] = 'Applicable only to cachable pages, this setting indicates that browsers should be allowed to cache the pages for an amount of time.  If enabled repeat visitors to your site might not immediately see changes to the content of the pages, however enabling this option can seriously improve the performance of this website.';
$lang['settings_browsercache_expiry'] = 'Specify the amount of time (in minutes) that browsers should cache pages for.  Setting this value to 0 disables the functionality.  In most circumstances you should specify a value greater than 30';
$lang['settings_checkversion'] = 'If enabled, the system will perform a daily check for a new release of CMSMS';
$lang['settings_contentimage_path'] = 'This setting is used when a page template contains the {content_image} tag.  The directory specified here is used to provide a selection of images to associate with the tag.<br /><br />Relative to the uploads path, specify a directory name that contains the paths containing files for the {content_image} tag.  This value is used as a default for the dir parameter';
$lang['settings_cssnameisblockname'] = 'If enabled, the content block name <em>(id)</em> will be used as a default value for the cssname parameter for each content block.<br/><br/>This is useful for WYSIWYG editors.  The stylesheet (block name) can be loaded by the WYSIWYG editor and provide an appearance that is closer to that of the front web page.<br/><br/><strong>Note:</strong> WYSIWYG Editors might not read information from the supplied stylesheets (if they exist) depending upon their settings and capabilities.';
$lang['settings_disablesafemodewarn'] = 'This option will disable a warning notice if CMSMS detects that <a href="http://php.net/manual/en/features.safe-mode.php" class="external" target="_blank">PHP Safe Mode</a> has been detected.<br /><br /><strong>Note:</strong> Safe mode has been deprecated as of PHP 5.3.0 and removed for PHP 5.4.0.  CMSMS Does not support operation under safe mode, and our support team will not render any technical assistance for installs where safe mode is active';
$lang['settings_editor'] = 'Select one of these, to use for text editing with syntax hightlighting and many other advanced capabilities.<br /><br />Each such editor requires a substantial download at runtime, and if that is a problem, disable this capability.';
$lang['settings_editortheme'] = 'Specify the theme name (lower case, any \' \' replaced by \'_\').';
$lang['settings_enablenotifications'] = 'This option will enable notifications being shown at the top of the page in each Admin request.  This is useful for important notifications about the system that might require user action.  It is possible for each Admin user to turn off notifications in their preferences.';
//$lang['settings_enablesitedown'] = 'This option allow you to toggle the website as "down for maintenance" for website visitor';
$lang['settings_enablewysiwyg'] = 'Enable WYSIWYG editor in the text area below';
$lang['settings_help_url'] = 'Specify an URL (e.g. email or website) to open when a site-help link is activated. Leave blank to use the CMSMS website support-page.';
$lang['settings_imagefield_path'] = 'This setting is used when editing content.  The directory specified here is used to provide a list of images from which to associate an image with the content page.<br/></br/>Relative to the image uploads path, specify a directory name that contains the paths containing files for the image field';
$lang['settings_lock_refresh'] = 'Enter a value (in seconds) for the lock-refresh interval. This the default used when no context-specific interval applies.';
$lang['settings_lock_timeout'] = 'Enter a value (in minutes) for locks\' lifetime. This the default used when no context-specific timeout value applies.';
$lang['settings_login_module'] = 'This allows you to customise the way admin console logins are handled. The current theme\'s login mechanism, or a suitable module (if any), may be used.';
$lang['settings_mailprefs_from'] = 'This option controls the <em>default<em> address that CMSMS will use to send email messages.  This cannot just be any email address.  It must match the domain that CMSMS is providing.  Specifying a personal email address from a different domain is known as &quot;<a href="https://en.wikipedia.org/wiki/Open_mail_relay" class="external" target="_blank">relaying</a>&quot; and will most probably result in emails not being sent, or not being accepted by the recipient email server.  A typical good example for this field is noreply@mydomain.com';
$lang['settings_mailprefs_fromuser'] = 'Here you can specify a name to be associated with the email address specified above.  This name can be anything but should reasonably correspond to the email address.  i.e: &quot;Do Not Reply&quot;';
$lang['settings_mailprefs_mailer'] = 'This choice controls how CMSMS will send mail.  Using PHPs mail function, sendmail, or by communicating directly with an SMTP server.<br/><br/>The &quot;mail&quot; option should work on most shared hosts, however it almost certainly will not work on most self hosted windows installations.<br/><br/>The &quot;sendmail&quot; option should work on most properly configured self hosted Linux servers.  However it might not work on shared hosts.<br/><br/>The SMTP Option requires configuration information from your host.';
$lang['settings_mailprefs_sendmail'] = 'If using the &quot;sendmail&quot; mailer method, you must specify the complete path to the sendmail binary program.  A typical value for this field is &quot;/usr/sbin/sendmail&quot;.  This option is typically not used on windows hosts.<br/><br/><strong>Note:</strong> If using this option your host must allow the popen and pclose PHP functions which are often disabled on shared hosts.';
$lang['settings_mailprefs_smtpauth'] = 'When using the SMTP mailer, this option indicates that the SMTP server requires authentication to send emails.  You then must specify <em>(at a minimum)</em> a username, and password.  Your host should indicate whether SMTP authentication is required, and if so provide you with a username and password, and optionally an encryption method.<br/><br/><strong>Note:</strong> SMTP authentication is required if your domain is using Google apps for email.';
$lang['settings_mailprefs_smtphost'] = 'When using the SMTP mailer this option specifies the hostname <em>(or IP address)</em> of the SMTP server to use when sending email.  You might need to contact your host for the proper value.';
$lang['settings_mailprefs_smtppassword'] = 'This is the password for connecting to the SMTP server if SMTP authentication is enabled.';
$lang['settings_mailprefs_smtpport'] = 'When using the SMTP mailer this option specifies the integer port number for the SMTP server.  In most cases this value is 25, though you might need to contact your host for the proper value.';
$lang['settings_mailprefs_smtpsecure'] = 'This option, when using SMTP authentication specifies an encryption mechanism to use when communicating with the SMTP server.  Your host should provide this information if SMTP authentication is required.';
$lang['settings_mailprefs_smtptimeout'] = 'When using the SMTP mailer, this option specifies the number of seconds before an attempted connection to the SMTP server will fail.  A typical value for this setting is 60.<br/><br/><strong>Note:</strong> If you need a longer value here it probably indicates an underlying DNS, routing or firewall problem, and you might need to contact your host.';
$lang['settings_mailprefs_smtpusername'] = 'This is the username for connecting to the SMTP server if SMTP authentication is enabled.';
$lang['settings_mailtest_testaddress'] = 'Specify a valid email address that will receive the test email';
$lang['settings_mandatory_urls'] = 'If SEF/pretty URLs are enabled, this option indicates whether page URLS are a required field in the content editor.';
$lang['settings_nosefurl'] = 'To configure <strong>S</strong>each <strong>E</strong>ngine <strong>F</strong>riendly <em>(pretty)</em> URLs you need to edit a few lines in your config.php file and possibly to edit a .htaccess file or your web servers configuration.   You can read more about configuring pretty URLS <a href="https://docs.cmsmadesimple.org/configuration/pretty-url" class="external" target="blank"><u>here</u></a> &raquo;';
$lang['settings_pseudocron_granularity'] = 'This setting indicates how often the system will attempt to handle regularly scheduled tasks.';
$lang['settings_searchmodule'] = 'Select the module that should be used to index words for searching, and will provide the site search capabilities';
$lang['settings_sitedownexcludeadmins'] = 'Do show the website to users logged in to the CMSMS admin console. This allows administrators to work on the site without interference';
$lang['settings_sitedownexcludes'] = 'Do show the website to these IP addresses';
$lang['settings_sitedownmessage'] = 'The message shown to website visitors when the site is &quot;down for maintenance&quot;';

//$lang['settings_smartycaching'] = 'When enabled, the output from various plugins will be cached to increase performance. Additionally, most portions of compiled templates will be cached. This only applies to output on content pages marked as cachable, and only for non-admin users.  Note, this functionality might interfere with the behavior of some modules or plugins, or plugins that use non-inline forms.<br/><br/><strong>Note:</strong> When smarty caching is enabled, global content blocks <em>(GCBs)</em> are always cached by smarty, and User Defined Tags <em>(UDTs)</em> are never cached.  Additionally, content blocks are never cached.';
$lang['settings_smartycachelife'] = 'Enter a value >= 0, or leave blank to use the smarty default (3600). 0 effectively disables caching.';
$lang['settings_smartycompilecheck'] = 'During each request, smarty normally checks each used template to determine whether it has changed since the last time it was compiled.
When templates are stable, such checks just slow things down.
Note that if this option is deselected and a template is later changed, such change will not be displayed until the cache is cleared or times out (normally 1-hour).';

$lang['settings_thumbfield_path'] = 'This setting is used when editing content.  The directory specified here is used to provide a list of images from which to associate a thumbnail with the content page.<br/><br/>Relative to the image uploads path, specify a directory name that contains the paths containing files for the image field.  Usually this will be the same as the path above.';
$lang['settings_umask'] = 'The &quot;umask&quot; is an octal value that is used to specify the default permission for newly created files (this is used for files in the cache directory, and uploaded files.  For more information see the appropriate <a href="http://en.wikipedia.org/wiki/Umask" class="external" target="_blank">Wikipedia article.</a>';

$lang['siteprefs_backendwysiwyg'] = 'Select the WYSIWYG editor for newly created Admin user accounts.  Admin users will be able to select their preferred WYSIWYG editor from within the user preference panel.';
$lang['siteprefs_dateformat'] = '<p>Specify the date format string in <a href="http://ca2.php.net/manual/en/function.strftime.php" class="external" target="_blank"><u>PHP strftime</u></a> format that will be used <em>(by default)</em> to display dates and times on the pages of this website.</p><p>Admin users can adjust these settings in the user preferences panel.</p><p><strong>Note:</strong> Some modules might choose to display times and dates differently</p>';
$lang['siteprefs_frontendlang'] = 'The language normally used for translated strings on frontend pages.  This can be changed as required, using smarty tags i.e: <code>{cms_set_language}</code>';
$lang['siteprefs_frontendwysiwyg'] = 'When WYSIWYG editors are provided on frontend forms, what WYSIWYG module should be used?  Or none.';
$lang['siteprefs_globalmetadata'] = 'This text area provides the ability to enter meta information that is relevant to all content pages.  This is an ideal location for meta tags such as Generator, and Author, etc.';
$lang['siteprefs_nogcbwysiwyg'] = 'This option will disable the WYSIWYG editor on all global content blocks independent of user settings, or of the individual global content blocks';
$lang['siteprefs_lockrefresh'] = 'This field specifies the minimum frequency (in minutes) the Ajax based locking mechanism should &quot;touch&quot; a lock.  An ideal value for this field is 5.';
$lang['siteprefs_locktimeout'] = 'This field specifies the number of minutes of inactivity before a lock times out.  After a lock times out other users might steal the lock.  In order for a lock to not time-out it must be &quot;touched&quot; before its expiry time.  This resets the expiry time of the lock.  Under most circumstances a 60 minute lock should be suitable.';
$lang['siteprefs_logintheme'] = 'Select a theme (from those installed) to be used to generate the admin login form, and as the default theme for new admin user accounts.  Admin users will be able to select their own preferred admin theme in the user-preferences panel.';
$lang['siteprefs_sitename'] = 'This is a human-readable name for this website, such as the business, club, or organization name';
$lang['siteprefs_sitelogo'] = 'Optional image file, to be displayed on login pages and admin console pages. Enter an absolute URL (typically somewhere in the site\'s uploads folder), or an uploaded-images-folder-relative URL for the file.';
$lang['siteprefs_thumbwidth'] = 'Specify a width <em>(in pixels)</em> to be used by default when generating thumbnails from uploaded image files.  Thumbnails are typically displayed in the Admin panel in the FileManager module or when selecting an image to insert into page content.  However, some modules might use the thumbnails on the website frontend.<br/><br/><strong>Note:</strong> Some modules might have additional preferences for how to generate thumbnails, and ignore this setting.';
$lang['siteprefs_thumbheight'] = 'Specify a height <em>(in pixels)</em> to be used by default when generating thumbnails from uploaded image files.  Thumbnails are typically displayed in the Admin panel in the FileManager module or when selecting an image to insert into page content.  However, some modules might use the thumbnails on the website frontend.<br/><br/><strong>Note:</strong> Some modules might have additional preferences for how to generate thumbnails, and ignore this setting.';

