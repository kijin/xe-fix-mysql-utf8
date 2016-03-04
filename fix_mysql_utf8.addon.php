<?php

/**
 * @file fix_mysql_utf8.addon.php
 * @author Kijin Sung <kijin@kijinsung.com>
 * @license GPLv2 or Later <https://www.gnu.org/licenses/gpl-2.0.html>
 * 
 * This addon implements a workaround for UTF-8 emoticons when using XE with
 * MySQL or MariaDB. It automatically converts 4-byte UTF-8 sequences with
 * equivalent HTML entities.
 * 
 * This addon is based on a similar workaround implemented in Rhymix
 * at https://github.com/rhymix/rhymix/pull/116
 */
if (!defined('__XE__')) exit();

/**
 * Encode the title and content of all submissions.
 */
if ($called_position === 'before_module_init' && preg_match('/^proc.+(Insert|Send)/', Context::get('act')))
{
	function utf8mb4_encode_main($str)
	{
		return preg_replace_callback('/[\xF0-\xF7][\x80-\xBF]{3}/', 'utf8mb4_encode_callback', $str);
	}
	
	function utf8mb4_encode_callback($matches)
	{
		$bytes = array(ord($matches[0][0]), ord($matches[0][1]), ord($matches[0][2]), ord($matches[0][3]));
		$codepoint = ((0x07 & $bytes[0]) << 18) + ((0x3F & $bytes[1]) << 12) + ((0x3F & $bytes[2]) << 6) + (0x3F & $bytes[3]);
		return '&#x' . dechex($codepoint) . ';';
	}
	
	$utf8mb4_converted = false;
	
	if ($title = Context::get('title'))
	{
		Context::set('title', $new_title = utf8mb4_encode_main($title), true);
		if ($title !== $new_title)
		{
			$utf8mb4_converted = true;
		}
	}
	
	if ($content = Context::get('content'))
	{
		Context::set('content', $new_content = utf8mb4_encode_main($content), true);
		if ($content !== $new_content)
		{
			$utf8mb4_converted = true;
		}
	}
	
	if ($utf8mb4_converted && FileHandler::exists(_XE_PATH_ . 'classes/security/htmlpurifier/library/HTMLPurifier.php'))
	{
		$hp_source = FileHandler::readFile(_XE_PATH_ . 'classes/security/htmlpurifier/library/HTMLPurifier.php');
		$hp_source = str_replace('return $html;', 'return utf8mb4_encode_main($html);', $hp_source);
		FileHandler::writeFile(_XE_PATH_ . 'files/cache/htmlpurifier/fix_mysql_utf8.patch.php', $hp_source);
		include _XE_PATH_ . 'files/cache/htmlpurifier/fix_mysql_utf8.patch.php';
	}
}
