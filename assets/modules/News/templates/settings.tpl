{tab_header name='categories' label=$mod->Lang('categories')}
{tab_header name='customfields' label=$mod->Lang('customfields')}
{tab_header name='options' label=$mod->Lang('options')}
{tab_start name='categories'}
{include file='module_file_tpl:News;categorylist.tpl'}
{tab_start name='customfields'}
{include file='module_file_tpl:News;customfieldstab.tpl'}
{tab_start name='options'}
{include file='module_file_tpl:News;adminprefs.tpl'}
{tab_end}
