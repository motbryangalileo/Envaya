<?php
	/**
	 * Action for changing a user's name
	 * 
	 * @package Elgg
	 * @subpackage Core
	 * @author Curverider Ltd
	 * @link http://elgg.org/
	 */

	require_once(dirname(dirname(dirname(__FILE__))) . "/engine/start.php");
	global $CONFIG;

	gatekeeper();
	
	$name = get_input('name');
	$user_id = get_input('guid');
	$user = "";
	
	if (!$user_id)
		$user = get_loggedin_user();
	else
		$user = get_entity($user_id);
		
	if (($user) && ($name))
	{
		if (strcmp($name, $user->name)!=0)
		{
			$user->name = $name;
			$user->save();
			system_message(elgg_echo('user:name:success'));			
		}
	}
	else
		register_error(elgg_echo('org:cantedit'));
	
	//forward($_SERVER['HTTP_REFERER']);
	//exit;
?>