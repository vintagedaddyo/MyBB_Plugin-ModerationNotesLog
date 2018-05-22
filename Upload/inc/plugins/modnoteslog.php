<?php
/*
 * MyBB: Moderator Log Notes
 *
 * File: modnoteslog.php
 *
 * Authors: Edson Ordaz, Vintagedaddyo
 *
 * MyBB Version: 1.8
 *
 * Plugin Version: 1.1
 *
 */

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("modcp_end", "modnoteslog_log");
$plugins->add_hook("modcp_do_modnotes_start", "modnoteslog_update_note");
$plugins->add_hook("modcp_start", "modnoteslog_delete_note");
$plugins->add_hook("admin_tools_menu_logs", "modnoteslog_admin_nav");
$plugins->add_hook("admin_tools_action_handler", "modnoteslog_action_handler");
$plugins->add_hook("admin_load", "modnoteslog_admin");


function modnoteslog_info()
{
   global $lang;

    $lang->load("modnoteslog");

    $lang->modnoteslog_Desc = '<form action="https://www.paypal.com/cgi-bin/webscr" method="post" style="float:right;">' .
        '<input type="hidden" name="cmd" value="_s-xclick">' .
        '<input type="hidden" name="hosted_button_id" value="AZE6ZNZPBPVUL">' .
        '<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">' .
        '<img alt="" border="0" src="https://www.paypalobjects.com/pl_PL/i/scr/pixel.gif" width="1" height="1">' .
        '</form>' . $lang->modnoteslog_Desc;

    return Array(
        'name' => $lang->modnoteslog_Name,
        'description' => $lang->modnoteslog_Desc,
        'website' => $lang->modnoteslog_Web,
        'author' => $lang->modnoteslog_Auth,
        'authorsite' => $lang->modnoteslog_AuthSite,
        'version' => $lang->modnoteslog_Ver,
        'compatibility' => $lang->modnoteslog_Compat
    );
}

function modnoteslog_activate()
{
	global $db, $lang;

    $lang->load("modnoteslog");

	if(!$db->table_exists("modnotes"))
	{
		$db->query("CREATE TABLE IF NOT EXISTS `".TABLE_PREFIX."modnotes` (
		  `nid` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
		  `uid` varchar(120) NOT NULL DEFAULT '',
		  `text` text NOT NULL,
		  `date` text NOT NULL,
		  PRIMARY KEY (`nid`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;");
	}
	if(!$db->table_exists("modnoteslog"))
	{
		$db->query("CREATE TABLE IF NOT EXISTS `".TABLE_PREFIX."modnoteslog` (
		  `nlid` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
		  `uid` varchar(120) NOT NULL DEFAULT '',
		  `text` text NOT NULL,
		  `date` text NOT NULL,
		  PRIMARY KEY (`nlid`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;");
	}
	$modnotes_groups = array(
		"gid"			=> "0",
		"name"			=> "modnotes",
		"title" 		=> $lang->modnoteslog_setting_0_Title,
		"description"	=> $lang->modnoteslog_setting_0_Description,
		"disporder"		=> "0",
		"isdefault"		=> "0",
	);
	$db->insert_query("settinggroups", $modnotes_groups);
	$gid = $db->insert_id();
	$modnotes = array(
		array(
			"name"			=> "modnotes_pagination",
		    "title" 		=> $lang->modnoteslog_setting_1_Title,
		    "description"	=> $lang->modnoteslog_setting_1_Description,
			"optionscode"	=> "text",
			"value"			=> "5",
			"disporder"		=> 1,
			"gid"			=> $gid,
		),
		array(
			"name"			=> "modnotes_chars",
		    "title" 		=> $lang->modnoteslog_setting_2_Title,
		    "description"	=> $lang->modnoteslog_setting_2_Description,
			"optionscode"	=> "text",
			"value"			=> "10",
			"disporder"		=> 2,
			"gid"			=> $gid,
		)
	);
	foreach($modnotes as $modnotesinstall)
	$db->insert_query("settings", $modnotesinstall);

	rebuild_settings();

	$modnotes_notes = array(
		"title"		=> 'modnotes_notes',
		"template"	=> $db->escape_string('<tr>
<td class="{$trow}" rowspan="2" width="100" style="text-align: center; vertical-align: top;">
<img style="width: 60px;" src="{$avatar}" />
</td>
<td class="{$trow}" >
{$username} <small style="font-size: 10px;"> ({$date} at {$time})</small>
<br />
<a href="modcp.php?action=deletenote&nid={$nid}">Delete</a>
</td>
</tr>
<tr>
<td class="{$trow}" >
{$text}
</td></tr>'),
		"sid"		=> -1,
		"version"	=> 1.0,
		"dateline"	=> time(),
	);

	$modnotes = array(
		"title"		=> 'modnotes',
		"template"	=> $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" align="center" colspan="2"><strong>{$lang->moderator_notes}</strong></td>
</tr>
<tr>
<td class="tcat" colspan="2"><span class="smalltext"><strong>{$lang->notes_public_all}</strong></span>
</td>
{$modnoteslog}
</table>
{$pagination}
<br />'),
		"sid"		=> -1,
		"version"	=> 1.0,
		"dateline"	=> time(),
	);

	$db->insert_query("templates", $modnotes);
	$db->insert_query("templates", $modnotes_notes);
	$db->query("DELETE FROM ".TABLE_PREFIX."datacache WHERE title='modnotes'");
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('modcp', '#<form#', '{$mnl}<form');
}

function modnoteslog_deactivate()
{
	global $db;
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('modcp', '#'.preg_quote('{$mnl}').'#', '', 0);
	$db->drop_table("modnotes");
	$db->drop_table("modnoteslog");
	$db->delete_query("templates","title = 'modnotes_notes'");
	$db->delete_query("templates","title = 'modnotes'");
	$db->query("DELETE FROM ".TABLE_PREFIX."settinggroups WHERE name='modnotes'");
	$db->delete_query("settings","name LIKE 'modnotes_%'");
}

class ModNotesLog {

	/**
	* Log Notes
	*
	*/

	private static $lognotes;

	/*
	* Verify Class
	* @access protected
	*
	*/

	public $verify;

	/*
	* Return Class LogNotes
	*
	*/

	public static function LogNotes()
	{
		if(!is_object($lognotes))
		{
			$lognotes = new self;
		}

		return $lognotes;
	}

	/**
	 * Construct
	 *
	 */

	public function __construct()
	{
		global $mybb;
		$this->verify = new ModNotesLog_Verify();
		$this->Admin = new ModNotesLog_Admin();
	}

	/*
	* Save Note DB
	*
	*/

	public function do_modnotes($note)
	{
		global $db, $lang, $mybb;

		$this->verify->verify_note($note);

		$update = array(
			"uid"	=> $mybb->user['uid'],
			"text"	=> $note,
			"date" 	=> TIME_NOW
		);

		$nid = $db->insert_query("modnotes", $update);
		$nlid = $db->insert_query("modnoteslog", $update);

		redirect("modcp.php", $lang->redirect_modnotes);
	}

	/*
	* delete Note
	*
	*/

	public function do_deletenote()
	{
		global $db, $mybb, $lang;
		if($mybb->input['action'] == "deletenote")
		{
			$db->query("DELETE FROM ".TABLE_PREFIX."modnotes WHERE nid=".$mybb->input['nid']);
			redirect("modcp.php",$lang->redirect_modnotes);
		}
	}

	/*
	* Table Notes
	*
	*/

	public function modnoteslogtable($trow,$avatar,$username,$date,$time,$nid,$text)
	{
		global $templates;
		eval("\$tablenotes = \"".$templates->get("modnotes_notes")."\";");
		return $tablenotes;
	}

	/*
	* Modcp
	*
	*/

	public function logmodcp()
	{
		global $db, $mybb, $templates, $modnoteslog, $mnl, $theme, $lang;

		$query = $db->simple_select('modnotes', 'COUNT(nid) AS notes', '', array('limit' => 1));
		$quantity = $db->fetch_field($query, "notes");
		$page = intval($mybb->input['page']);
		$perpage = $mybb->settings['modnotes_pagination'];

		if($page > 0)
		{
			$start = ($page - 1) * $perpage;
			$pages = $quantity / $perpage;
			$pages = ceil($pages);

			if($page > $pages || $page <= 0)
			{
				$start = 0;
				$page = 1;
			}
		}

		else
		{
			$start = 0;
			$page = 1;
		}

		$modcp_page = "modcp.php";
		$query = $db->query('SELECT * FROM '.TABLE_PREFIX.'modnotes ORDER BY nid DESC LIMIT ' . $start . ', ' . $perpage);

		while($note = $db->fetch_array($query))
		{
			$query_users = $db->simple_select("users", "*", "uid=".$note['uid']);
			while($user = $db->fetch_array($query_users))
			{
				$username = $this->Admin->username_load($user['uid']);
				$avatar = $user['avatar'];
			}
			$date = my_date($mybb->settings['dateformat'], $note['date']);
			$time = my_date($mybb->settings['timeformat'], $note['date']);
			$style = alt_trow();
			$avatar = (!empty($avatar)) ? $avatar : "images/default_avatar.png";
			$note['text'] = htmlspecialchars_uni($note['text']);
			$modnoteslog .= $this->modnoteslogtable($style,$avatar,$username,$date,$time,$note['nid'],$note['text']);
		}

		global $pagination;

		$pagination = multipage($quantity, (int)$perpage, (int)$page, $modcp_page);
		eval("\$mnl = \"".$templates->get("modnotes")."\";");
	}

	/*
	* New Log
	*
	*/

	public function new_table_log()
	{
		$this->Admin->load_Mod_Notes_Log();
	}

	/*
	* New Menu ->Logs Administration <-
	*
	*/

	public function menulogs(&$actions)
	{
     $this->verify->menulogs($actions);
	}
}

class ModNotesLog_Verify {

	/*
	*
	* Redirect ERROR*
	*
	*/

	public function verify_redirect()
	{
      global $lang;

        $lang->load("modnoteslog");

		redirect("modcp.php", $lang->modnoteslog_Redirect);
	}

	/*
	* Verify lenght note
	*
	*/

	public function verify_note($e)
	{
		global $mybb;

		if(my_strlen(trim_blank_chrs($e)) > $mybb->settings['modnotes_chars'])
		{
			return true;
		}
		else
		{
			$this->verify_redirect();
		}
	}

	/*
	* New Action menu
	*
	*/

	public function menulogs(&$action)
	{
	    global $lang;

        $lang->load("modnoteslog");

		$action['modnoteslog'] = array('active' => $lang->modnoteslog_action, 'file' => '');
	}

}


class ModNotesLog_Admin {

	/*
	* Log Notes Administration
	*
	*/

	private static $lognotes_admin;

	/*
	* Return Class ModNotesLog_Admin
	*
	*/

	public static function Admin()
	{
		if(!is_object($lognotes_admin))
		{
			$lognotes_admin = new self;
		}

		return $lognotes_admin;
	}

	/*
	* Load ModNotesLog admin
	*
	*/

	public function load_Mod_Notes_Log()
	{
		global $mybb, $db, $page, $cache, $lang;


        $lang->load("modnoteslog");

		if($page->active_action != "modnoteslog")
		{
			return;
		}

		$page->add_breadcrumb_item($lang->modnoteslog_header);
		$page->output_header($lang->modnoteslog_breadcrumb);

		if($mybb->input['action'] == "dmn")
		{
			$this->emptydmn();
		}

		if($mybb->input['action'] == "dal")
		{
			$this->emptydal();
		}

		$this->admin_load_tables();
		$page->output_footer();
	}

	/*
	* Profile Url
	*
	*/
	public function link_profile($username,$uid)
	{
		global $mybb;

		return "<a href=\"{$mybb->settings['bburl']}/".get_profile_link($uid)."\">{$username}</a>";
	}

	/*
	* Load Username - username format
	*
	*/

	public function username_load($u)
	{
		global $db, $cache, $groupscache;

		$query_users = $db->simple_select("users", "*", "uid=".$u);

		while($user = $db->fetch_array($query_users))
		{
			$groupscache = $cache->read("usergroups");
			$ugroup = $groupscache[$user['usergroup']];
			$format = $ugroup['namestyle'];
			$userin = substr_count($format, "{username}");

			if($userin == 0)
			{
				$format = "{username}";
			}

			$format = stripslashes($format);
			$username = str_replace("{username}", $user['username'], $format);
			$username = $this->link_profile($username, $user['uid']);
		}

		return $username;
	}

	/*
	* Load admin handler Nav
	*
	*/
	public function admin_nav(&$nav)
	{
		global $mybb, $lang;

        $lang->load("modnoteslog");

		end($nav);
		$key = (key($nav))+10;
		if(!$key)
		{
			$key = '110';
		}

		$nav[$key] = array('id' => $lang->modnoteslog_nav_id, 'title' => $lang->modnoteslog_nav_title, 'link' => "index.php?module=tools-modnoteslog");
	}

	/*
	* Load Time AND date
	*
	*/

	public function date_time($dt)
	{
		global $mybb;

		$date = my_date($mybb->settings['dateformat'], $dt);
		$time = my_date($mybb->settings['timeformat'], $dt);

		return $date.",".$time;
	}

	/*
	* Tabs LOgs notes
	*
	*/

	public function tabsload()
	{
		global $page, $lang;

		$lang->load("modnoteslog");

		$tabs["lognotes"] = array(
		'title' => $lang->modnoteslog_tabs_1_Title,
		'link' => "index.php?module=tools-modnoteslog",
		'description' => $lang->modnoteslog_tabs_1_Description
		);
		$tabs["deleteadminlog"] = array(
			'title' => $lang->modnoteslog_tabs_2_Title,
			'link' => "index.php?module=tools-modnoteslog&action=dal\" onclick=\"return confirm('want to delete the log of letters of administration?')",
			'description' => ""
		);
		$tabs["deletemodnotes"] = array(
			'title' => $lang->modnoteslog_tabs_3_Title,
			'link' => "index.php?module=tools-modnoteslog&action=dmn\" onclick=\"return confirm('want to empty the notes of moderation?')",
			'description' => ""
		);

		$page->output_nav_tabs($tabs,"lognotes");
	}

	/*
	* Empty Notes moderation
	*
	*/

	public function emptydmn()
	{
		global $db, $lang;

		$lang->load("modnoteslog");

		$db->query("truncate ".TABLE_PREFIX."modnotes");

		flash_message($lang->modnoteslog_flash_1, 'success');
		admin_redirect("index.php?module=tools-modnoteslog");
	}

	/*
	* Empty Log Notes administration
	*
	*/

	public function emptydal()
	{
		global $db, $lang;

		$lang->load("modnoteslog");

		$db->query("truncate ".TABLE_PREFIX."modnoteslog");

		flash_message($lang->modnoteslog_flash_2, 'success');
		admin_redirect("index.php?module=tools-modnoteslog");
	}

	/*
	* Table Admin Log
	*
	*/

	public function admin_load_tables()
	{
		global $db, $mybb, $lang;

		$lang->load("modnoteslog");

		$query = $db->simple_select('modnoteslog', 'COUNT(nlid) AS text', '', array('limit' => 1));
		$quantity = $db->fetch_field($query, "text");
		$page = intval($mybb->input['page']);
		$perpage = 20;

		if($page > 0)
		{
			$start = ($page - 1) * $perpage;
			$pages = $quantity / $perpage;
			$pages = ceil($pages);

			if($page > $pages || $page <= 0)
			{
				$start = 0;
				$page = 1;
			}
		}

		else
		{
			$start = 0;
			$page = 1;
		}

		$this->tabsload();
		$table = new Table;
		$table->construct_header($lang->modnoteslog_username, array("width" => "15%"));
		$table->construct_header($lang->modnoteslog_note, array("class" => "align_center", "width" => "70%"));
		$table->construct_header($lang->modnoteslog_date, array("class" => "align_center", "width" => "25%"));
		$table->construct_row();

		$query = $db->query('SELECT * FROM '.TABLE_PREFIX.'modnoteslog ORDER BY nlid DESC LIMIT ' . $start . ', ' . $perpage);

		while($note = $db->fetch_array($query))
		{
				$table->construct_cell($this->username_load($note['uid']));
				$note['text'] = htmlspecialchars_uni($note['text']);
				$table->construct_cell($note['text']);
				$table->construct_cell($this->date_time($note['date']), array("class" => "align_center"));
				$table->construct_row();
		}
		$table->output($lang->modnoteslog_header);
		echo multipage($quantity, (int)$perpage, (int)$page, "index.php?module=tools-modnoteslog");
	}
}

function modnoteslog_update_note()
{
	global $mybb;

	return ModNotesLog::LogNotes()->do_modnotes($mybb->input['modnotes']);
}

function modnoteslog_delete_note()
{
	return ModNotesLog::LogNotes()->do_deletenote();
}

function modnoteslog_log()
{
	return ModNotesLog::LogNotes()->logmodcp();
}

function modnoteslog_action_handler(&$action)
{
	ModNotesLog::LogNotes()->menulogs($action);
}

function modnoteslog_admin_nav(&$sub_menu)
{
	ModNotesLog_Admin::Admin()->admin_nav($sub_menu);
}

function modnoteslog_admin()
{
	ModNotesLog::LogNotes()->new_table_log();
}
?>
