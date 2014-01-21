<?php


$wgHooks['EditPage::showEditForm:initial'][] = 'efEditButton';

function efEditButton(&$editpage)
{
	$editpage->editFormPageTop .= '<div style="padding:0 0 .5em 0"><input onclick="document.getElementById(\'wpSave\').click();return false" value="Save page" type="submit" title="Save your changes [s]"  />
<input onclick="document.getElementById(\'wpPreview\').click();return false" type="submit" value="Show preview" title="Preview your changes, please use this before saving! [p]" />

<input onclick="document.getElementById(\'wpDiff\').click();return false" type="submit" value="Show changes" accesskey="v" title="Show which changes you made to the text [v]" /></div>';
	

	return true;	
}
