<?php
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
  die("Nope.");
}

$plugins->add_hook("showthread_start","atu_change");
$plugins->add_hook("forumdisplay_start","atu_change");
$plugins->add_hook("showthread_start","atu_showthread");
$plugins->add_hook("forumdisplay_end","atu_forumdisplay");
$plugins->add_hook("newreply_do_newreply_end","block_atu");

function atu_info()
{
	return array(
		"name"			=> "Auto Thread Updater",
		"description"	=> "Automatically updates thread via AJAX when a new reply is posted.",
		"website"		=> "http://mybbpl.us",
		"author"		=> "Paul H.",
		"authorsite"	=> "http://www.paulhedman.com",
		"version"		=> "1.0",
		"guid" 			=> "",
		"compatibility" => "*"
	);
}

function atu_install()
{
	global $db;
	
	//delete old settings
	atu_uninstall();

	$group = array(
		'gid'			=> 'NULL',
		'name'			=> 'atugroup',
		'title'			=> 'Auto Thread Updater Settings',
		'description'	=> 'Settings for the Pokes plugin.',
		'disporder'		=> "1",
		'isdefault'		=> 'no',
	);

	$db->insert_query('settinggroups', $group);

	$gid = $db->insert_id();
	
	$setting = array(
		'name'			=> 'atu_refreshrate',
		'title'			=> 'Refresh rate',
		'description'	=> 'The refresh rate, in milliseconds.  E.g. 1000 = one second, 20000 = 20 seconds.',
		'optionscode'	=> 'text',
		'value'			=> '15000',
		'disporder'		=> 1,
		'gid'			=> intval($gid),
	);

	$db->insert_query('settings', $setting);

	$setting = array(
		'name'			=> 'atu_tf_wlbl',
		'title'			=> 'On/off selection',
		'description'	=> 'This one is a bit complicated.  To start, put whether you want auto updating activated on a per forum ("forums") or per thread ("threads") basis.  Add a |.  Then put whether you want a whitelist (only forums/threads you specify use auto updating) or a blacklist (forums/threads you specify are NOT auto updated).  Add another |.  After that, add a CSV of forum IDs or thread IDs to be in the whitelist/blacklist.<br /><strong>Examples</strong><br />forums|whitelist|2,3 <em>Only threads in the forums with FIDs 2 and 3 will update</em><br />threads|blacklist|1337,7655 <em>All threads but the ones with TIDs 1337 and 7566 will update.</em><br />Use "all" (no quotes) to allow auto updating all the time.',
		'optionscode'	=> 'text',
		'value'			=> 'forums|whitelist|2,3',
		'disporder'		=> 2,
		'gid'			=> intval($gid),
	);

	$db->insert_query('settings', $setting);
	
		$setting = array(
		'name'			=> 'atu_usergroups',
		'title'			=> 'Usergroups',
		'description'	=> "A CSV of group IDs that can/can\'t use the auto updater.  Specify whitelist/blacklist afterwards, seperated by a |.  <br />Use \"all\" (no quotes) to allow auto updating by all usergroups.",
		'optionscode'	=> 'text',
		'value'			=> '1,5,7|blacklist',
		'disporder'		=> 3,
		'gid'			=> intval($gid),
	);

	$db->insert_query('settings', $setting);

	rebuild_settings();
}

function atu_is_installed()
{
	global $mybb;
	return isset($mybb->settings['atu_refreshrate']);
}

function atu_activate()
{
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";

	//get rid of old templates
	atu_deactivate();
	
	find_replace_templatesets('showthread',
		'#' . preg_quote('{$headerinclude}') . '#',
		'{$headerinclude}{$atujq}{$atujs}'
	);
	
	find_replace_templatesets('showthread',
		'#' . preg_quote('{$posts}') . '#',
		'{$posts}<div id="autorefresh"></div>'
	);
	
	find_replace_templatesets('showthread',
		'#' . preg_quote('{$pollbox}') . '#',
		'{$atu_link}{$pollbox}'
	);	
	
	find_replace_templatesets('forumdisplay',
		'#' . preg_quote('{$rules}') . '#',
		'{$atu_link}{$rules}'
	);	
	
}

function atu_deactivate()
{
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";

	find_replace_templatesets('showthread',
		'#' . preg_quote('{$atujq}{$atujs}') . '#',
		''
	);
	find_replace_templatesets('showthread',
		'#' . preg_quote('<div id="autorefresh"></div>') . '#',
		''
	);
	
	find_replace_templatesets('showthread',
		'#' . preg_quote('{$atu_link}') . '#',
		''
	);
	
	find_replace_templatesets('forumdisplay',
		'#' . preg_quote('{$atu_link}') . '#',
		''
	);
}

function atu_uninstall()
{
	global $db;
	$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name LIKE 'atu_%'");
	$db->query("DELETE FROM ".TABLE_PREFIX."settinggroups WHERE name='atu'");
	rebuild_settings(); 
}


//adds the javascript as well as the atu thread on/off link to the page if we need them
function atu_showthread()
{
	global $mybb, $atujq, $atujs, $atu_link, $tid, $fid;

	if(can_auto_update())
	{
	
		$atujq = '<script src="http://code.jquery.com/jquery-latest.js"></script>
	<script>
	jQuery.noConflict();
	</script>';
		
		$atujs = '<script type="text/javascript">
	var time = '.TIME_NOW.';
	var refreshId = setInterval(function()
	{
		jQuery.get(\'getnewposts.php?tid='.$tid.'}&timestamp=\'+time,
		function(result) {
			jQuery(\'#autorefresh\').append(\'<span style="display: none;" class="new-post" name="post[]">\'+result+\'</span>\');
			jQuery(\'#autorefresh\').find(".new-post:last").fadeIn(\'slow\');
		});

		time =  Math.round((new Date()).getTime() / 1000);

	}, '.intval($mybb->settings['atu_refreshrate']).');
	</script>';
	} else {
		$atujq = '';
		$atujs = '';
	}

	if($mybb->usergroup['cancp'])
	{
		$on = true;
		$display = true;
		
		if($mybb->settings['atu_tf_wlbl'] != 'all')
		{
			$perms = explode("|",$mybb->settings['atu_tf_wlbl']);
			$ids = explode(",",$perms[2]);
			
			if($perms[0] == 'threads')
			{
				$thread_in_list = in_array($tid,$ids);
				
				if($thread_in_list && $perms[1] == 'blacklist')
				{
					$on = false;
				} elseif(!$thread_in_list && $perms[1] == 'whitelist') {
					$on = false;
				}
			} else {
				$display = false;
			}
		}
		if($display)
		{
			if($on)
			{
				$atu_link = '<a href="showthread.php?tid='.$tid.'&amp;toggle_atu=true&amp;my_post_key='.$mybb->post_code.'">Turn off auto thread updating in this thread</a><br />';
			} else {
				$atu_link = '<a href="showthread.php?tid='.$tid.'&amp;toggle_atu=true&amp;my_post_key='.$mybb->post_code.'">Turn on auto thread updating in this thread</a><br />';
			}
		}
	}
}

function atu_forumdisplay()
{
	global $mybb,$fid,$atu_link;
	if($mybb->usergroup['cancp'])
	{
		$on = true;
		$display = true;
		
		if($mybb->settings['atu_tf_wlbl'] != 'all')
		{
			$perms = explode("|",$mybb->settings['atu_tf_wlbl']);
			$ids = explode(",",$perms[2]);
						
			if($perms[0] == 'forums')
			{
				$forum_in_list = in_array($fid,$ids);
				
				if($forum_in_list && $perms[1] == 'blacklist')
				{
					$on = false;
				} elseif(!$forum_in_list && $perms[1] == 'whitelist') {
					$on = false;
				}
			} else {
				$display = false;
			}
		}
		
		if($display)
		{
			if($on)
			{
				$atu_link = '<a href="forumdisplay.php?toggle_atu=true&amp;fid='.$fid.'&amp;my_post_key='.$mybb->post_code.'">Turn off auto thread updating in this forum</a><br />';
			} else {
				$atu_link = '<a href="forumdisplay.php?toggle_atu=true&amp;fid='.$fid.'&amp;my_post_key='.$mybb->post_code.'">Turn on auto thread updating in this forum</a><br />';
			}
		}
	}
}

//this adds/subtracts threads/forums from the whitelist/blacklist.  Enough /s for you?
function atu_change()
{
	global $mybb,$db,$tid,$fid;
	
	if(isset($mybb->input['toggle_atu']) && $mybb->usergroup['cancp'])
	{
		//CSRF?  Nuh uh.
		verify_post_check($mybb->input['my_post_key']);

		$perms = explode("|",$mybb->settings['atu_tf_wlbl']);
		$ids = explode(",",$perms[2]);
		
		$id = '';

		if(THIS_SCRIPT == 'forumdisplay.php' && $perms[0] == 'forums') {$id = (int)$mybb->input['fid']; $foruminfo = get_forum($id); if(!$foruminfo){error("Invalid forum.");}}
		if(THIS_SCRIPT == 'showthread.php' && $perms[0] == 'threads') {$id = $tid;}

		if($id != '')
		{
			if(in_array($id,$ids))
			{
				//it's in there, take it out
				$key = array_search($id,$ids);
				unset($ids[$key]);
			} else {
				//it's not in the array, put it in!
				$ids[] = $id;
			}
			
			//put the IDs back in a CSV
			$ids = implode(",",$ids);
			//then back in the setting
			$perms[2] = $ids;
			$insert['value'] = implode("|",$perms);
			$mybb->settings['atu_tf_wlbl'] = $insert['value'];
			$db->update_query("settings",$insert,"name='atu_tf_wlbl'");
			rebuild_settings();	
			
			//this gets messed up because of the settings rebuild, so let's fix it
			if($mybb->user['classicpostbit'])
			{
				$mybb->settings['postlayout'] = 'classic';
			}
			else
			{
				$mybb->settings['postlayout'] = 'horizontal';
			}
		}		
	}
}

//This function ensures that the post doesn't show up twice on quickreply.
function block_atu()
{
	global $mybb,$pid;
	if(isset($mybb->input['ajax']))
	{
		my_setcookie("ignore_".$pid,TIME_NOW+6);
	}
}

function can_auto_update()
{
	global $mybb,$tid,$fid;
	
	if($mybb->settings['atu_tf_wlbl'] != 'all')
	{
		$perms = explode("|",$mybb->settings['atu_tf_wlbl']);
		$ids = explode(",",$perms[2]);
		
		//check if we're looking at forums or threads, then check the permissions
		if($perms[0] == 'forums')
		{
			$forum_in_list = in_array($fid,$ids);
			
			if($forum_in_list && $perms[1] == 'blacklist')
			{
				return false;
			} elseif(!$forum_in_list && $perms[1] == 'whitelist') {
				return false;
			}
			
		} elseif($perms[0] == 'threads') {
		
			$thread_in_list = in_array($tid,$ids);
			
			if($thread_in_list && $perms[1] == 'blacklist')
			{
				return false;
			} elseif((!$thread_in_list || !is_array($ids)) && $perms[1] == 'whitelist') {
				return false;
			}
		} else {
			//somethings messed up in the settings...
			return false;
		}
	}
	
	if($mybb->settings['atu_usergroups'] != 'all')
	{
		//check usergroup permissions
		$userperms = explode("|",$mybb->settings['atu_usergroups']);
		$usergroups = explode(",",$userperms[0]);
		$listtype = $userperms[1];

		//check primary group
		if(in_array($mybb->user['usergroup'],$usergroups) && $listtype == 'blacklist')
		{
		
			return false;
		}
		
		if(!in_array($mybb->user['usergroup'],$usergroups) && $listtype == 'whitelist')
		{
			return false;
		}
		
		
		//check additional groups
		if($mybb->user['additionalgroups'] != '')
		{
			$addlgroups = explode(",",$mybb->user['additionalgroups']);
			
			foreach($addlgroups as $group)
			{
				if(in_array($group,$usergroups) && $listtype == 'blacklist')
				{
					return false;
				}
				
				if(!in_array($group,$usergroups) && $listtype == 'whitelist')
				{
					return false;
				}
			}
		}
	}
	
	//if we haven't returned false yet, we're good to go
	return true;
}
?>
