<?php
/*
Plugin Name: GeSHi syntax highlighting
Plugin URI: http://enanocms.org/GeSHi_support
Description: Adds syntax highlighting support using the GeSHi engine.
Author: Dan Fuhry
Version: 0.1
Author URI: http://enanocms.org/
*/

/*
 * GeSHi highlighting plugin for Enano
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

global $db, $session, $paths, $template, $plugins; // Common objects
$GLOBALS['geshi_supported_formats'] = array(
    'abap', 'blitzbasic', 'cpp-qt', 'd', 'idl', 'lua', 'ocaml', 'python', 'smalltalk', 'vhdl', 'actionscript', 'bnf', 'csharp',
    'eiffel', 'ini', 'm68k', 'oobas', 'qbasic', 'smarty', 'visualfoxpro', 'ada', 'caddcl', 'fortran', 'inno', 'matlab',
    'oracle8', 'rails', 'sql', 'winbatch', 'apache', 'cadlisp', 'css', 'freebasic', 'io', 'mirc', 'pascal', 'reg', 'tcl', 'xml',
    'applescript', 'cfdg', 'delphi', 'genero', 'java5', 'mpasm', 'perl', 'robots', 'text', 'xpp', 'asm', 'cfm', 'diff', 'gml', 'java',
    'mysql', 'per', 'ruby', 'thinbasic', 'z80', 'asp', 'c_mac', 'div', 'groovy', 'javascript', 'nsis', 'php-brief', 'sas', 'tsql',
    'autoit', 'c', 'dos', 'haskell', 'latex', 'objc', 'php', 'scheme', 'vbnet', 'bash', 'cpp', 'dot', 'html', 'lisp',
    'ocaml-brief', 'plsql', 'sdlbasic', 'vb'
  );

// Knock out the existing <code> tag support
$plugins->attachHook('text_wiki_construct', 'geshi_disable_tw_code($this);');

function geshi_disable_tw_code($tw)
{
  $tw->disable[] = 'Code';
  foreach ( $tw->rules as $i => $rule )
  {
    if ( $rule == 'Code' )
    {
      unset($tw->rules[$i]);
      return true;
    }
  }
}

// Prevent <code> tags from being stripped or sanitized (the plugin will handle all sanitation)
$plugins->attachHook('render_sanitize_pre', 'geshi_strip_code($text, $geshi_code_blocks, $random_id);');
$plugins->attachHook('render_sanitize_post', 'geshi_restore_code($text, $geshi_code_blocks, $random_id);');

function geshi_strip_code(&$text, &$codeblocks, $random_id)
{
  global $geshi_supported_formats;
  $codeblocks = array();
  $sf = '(' . implode('|', $geshi_supported_formats) . ')';
  preg_match_all('/<code type="?' . $sf . '"?>([\w\W]*?)<\/code>/ms', $text, $matches);
  
  // for debug
  /*
  if ( strstr($text, '<code type') )
    die('processing codes: <pre>' . htmlspecialchars(print_r($matches, true)) . '</pre><pre>' . htmlspecialchars($text) . '</pre>' . htmlspecialchars('/<code type="?' . $sf . '"?>([\w\W]*?)<\/code>/'));
  */
  
  foreach ( $matches[0] as $i => $match )
  {
    $codeblocks[$i] = array(
        'match' => $match,
        'lang' => $matches[1][$i],
        'code' => $matches[2][$i]
      );
    $text = str_replace_once($match, "{GESHI_BLOCK:$i:$random_id}", $text);
  }
}

function geshi_restore_code(&$text, &$codeblocks, $random_id)
{
  foreach ( $codeblocks as $i => $match )
  {
    $text = str_replace_once("{GESHI_BLOCK:$i:$random_id}", $match['match'], $text);
  }
}

// Formatter hook - where the actual highlighting is performed
$plugins->attachHook('render_wikiformat_veryearly', 'geshi_strip_code($text, $codeblocks, $random_id);');
$plugins->attachHook('render_wikiformat_post', 'geshi_perform_highlight($result, $codeblocks, $random_id);');

function geshi_perform_highlight(&$text, &$codeblocks, $random_id)
{
  static $did_header_tweak = false;
  if ( !$did_header_tweak )
  {
    $did_header_tweak = true;
    global $template;
    $template->add_header('<style type="text/css">
      pre.geshi_highlighted {
        max-height: 1000000px !important;
      }
      pre.geshi_highlighted a {
        background-image: none !important;
        padding-right: 0 !important;
      }
      </style>
      ');
  }
  if ( !defined('GESHI_ROOT') )
    define('GESHI_ROOT', ENANO_ROOT . '/plugins/geshi/');
  
  require_once ( GESHI_ROOT . '/base.php' );
  
  foreach ( $codeblocks as $i => $match )
  {
    $lang =& $match['lang'];
    $code =& $match['code'];
    
    $geshi = new GeSHi($code, $lang, null);
    $geshi->set_header_type(GESHI_HEADER_PRE);
    // $geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS);
    $geshi->set_overall_class('geshi_highlighted');
    $parsed = $geshi->parse_code();
    
    $text = str_replace_once("{GESHI_BLOCK:$i:$random_id}", $parsed, $text);
  }
}
