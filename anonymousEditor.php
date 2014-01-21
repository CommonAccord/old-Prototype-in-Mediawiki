<?php

/*$wgHooks['EditPage::attemptSave'][] = 'fnAnonEditorCatchSave';

function fnAnonEditorCatchSave($editpage)
{	//print_r($editpage);
	//exit;
	global $wgUser;
	
	$wgUser->setName('Anonymous Editor');
	
	return true;
}*/

$wgHooks['PageHistoryLineEnding'][] = 'fnAnonEditorHistory';

function fnAnonEditorHistory( $history, $row, $s ) 
{	global $wgUser;
	$userGroups = $wgUser->getEffectiveGroups();
	
	if (!in_array('sysop',$userGroups))
	{
		$s = preg_replace("%<span class='history-user'>.*</span>.*</span>%Us", "Anonymous Editor", $s);
	}
	return true;	
}



$wgHooks['DiffViewHeader'][] = 'fnAnonEditorRev';


function fnAnonEditorRev( $diff, $oldRev, $newRev )
 { 
 	global $wgUser;
	$userGroups = $wgUser->getEffectiveGroups();
	
	if (!in_array('sysop',$userGroups))
	{
 		$oldRev->mUserText = 'Anonymous Editor';
   	$oldRev->mUser = -1;
	}
 	
 	return true;
  }
