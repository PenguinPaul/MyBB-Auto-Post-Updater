<?php

//ONLY UPDATES ON LAST PAGE.


//get MyBB
define("IN_MYBB",1);
include_once("global.php");

//get the thread
$tid = (int)$mybb->input['tid'];
$thread = get_thread($tid);

//get the forum
$fid = $thread['fid'];
$forum = get_forum($fid);
//valid forum?
if(!$forum || $forum['type'] != "f")
{
  die("Forum error.");
}

//can this user auto update this thread?
if(!can_auto_update())
{
	die("Auto updating not enabled for this thread.<br />");
}



// Is the currently logged in user a moderator of this forum?
if(is_moderator($fid))
{
	$visibl = "AND (p.visible='1' OR p.visible='0')";
	$ismod = true;
}
else
{
	$visible = "AND p.visible='1'";
	$ismod = false;
}

// Make sure we are looking at a real thread here.
if(!$thread['tid'] || ($thread['visible'] == 0 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
{
	die("Thread error.<br />");
}

$forumpermissions = forum_permissions($thread['fid']);

// Does the user have permission to view this thread?
if($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
{
	die("Permissions error.<br />");
}

// Check if this forum is password protected and we have a valid password
check_forum_password($forum['fid']);


//this user can view this thread... let's auto update any posts he can see!

$since = (int)$mybb->input['timestamp'];

$posts = '';
$query = $db->query("
	SELECT u.*, u.username AS userusername, p.*, f.*, eu.username AS editusername
	FROM ".TABLE_PREFIX."posts p
	LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
	LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
	LEFT JOIN ".TABLE_PREFIX."users eu ON (eu.uid=p.edituid)
	WHERE p.dateline>={$since} {$visible}
	ORDER BY p.dateline
");

require_once("inc/functions_post.php");
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

while($post = $db->fetch_array($query))
{
	if(!isset($mybb->cookies['ignore_'.$post['pid']]))
	{
		$posts .= build_postbit($post);
	}
	
	$post = '';
}
		
echo $posts;
exit;
?>
