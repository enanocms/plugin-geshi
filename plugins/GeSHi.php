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
    'abap', 'actionscript3', 'actionscript', 'ada', 'apache', 'applescript', 'apt_sources', 'asm', 'asp', 'autoit', 'avisynth', 'bash', 'basic4gl', 'bf', 'blitzbasic', 'bnf', 'boo', 'caddcl', 'cadlisp', 'cfdg', 'cfm', 'cil', 'c_mac', 'cobol', 'c', 'cpp', 'cpp-qt', 'csharp', 'css', 'delphi', 'diff', 'div', 'dos', 'dot', 'd', 'eiffel', 'email', 'fortran', 'freebasic', 'genero', 'gettext', 'glsl', 'gml', 'gnuplot', 'groovy', 'haskell', 'hq9plus', 'html', 'idl', 'ini', 'inno', 'intercal', 'io', 'java5', 'java', 'javascript', 'kixtart', 'klonec', 'klonecpp', 'latex', 'lisp', 'lolcode', 'lotusformulas', 'lotusscript', 'lscript', 'lua', 'm68k', 'make', 'matlab', 'mirc', 'mpasm', 'mxml', 'mysql', 'nsis', 'objc', 'ocaml-brief', 'ocaml', 'oobas', 'oracle11', 'oracle8', 'pascal', 'perl', 'per', 'php-brief', 'php', 'pic16', 'pixelbender', 'plsql', 'povray', 'powershell', 'progress', 'prolog', 'providex', 'python', 'qbasic', 'rails', 'reg', 'robots', 'ruby', 'sas', 'scala', 'scheme', 'scilab', 'sdlbasic', 'smalltalk', 'smarty', 'sql', 'tcl', 'teraterm', 'text', 'thinbasic', 'tsql', 'typoscript', 'vbnet', 'vb', 'verilog', 'vhdl', 'vim', 'visualfoxpro', 'visualprolog', 'whitespace', 'winbatch', 'xml', 'xorg_conf', 'xpp', 'z80'
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
  // remove nowiki
  $nw = preg_match_all('#<nowiki>(.*?)<\/nowiki>#is', $text, $nowiki);
    
  for ( $i = 0; $i < $nw; $i++ )
  {
    $text = str_replace('<nowiki>'.$nowiki[1][$i].'</nowiki>', '{NOWIKI:'.$random_id.':'.$i.'}', $text);
  }
  
  global $geshi_supported_formats;
  $codeblocks = array();
  $sf = '(' . implode('|', $geshi_supported_formats) . ')';
  $regexp = '/<(code|source) (?:type|lang)="?' . $sf . '"?>(.*?)<\/\\1>/s';
  preg_match_all($regexp, $text, $matches);
  
  // for debug
  /*
  if ( strstr($text, '<code type') )
    die('processing codes: <pre>' . htmlspecialchars(print_r($matches, true)) . '</pre><pre>' . htmlspecialchars($text) . '</pre>' . htmlspecialchars($regexp));
  */
  
  foreach ( $matches[0] as $i => $match )
  {
    $codeblocks[$i] = array(
        'match' => $match,
        'lang' => $matches[2][$i],
        'code' => $matches[3][$i]
      );
    $text = str_replace_once($match, "{GESHI_BLOCK:$i:$random_id}", $text);
  }
  
  // Reinsert <nowiki> sections
  for ( $i = 0; $i < $nw; $i++ )
  {
    $text = str_replace('{NOWIKI:'.$random_id.':'.$i.'}', $nowiki[1][$i], $text);
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
      .geshi_highlighted {
        max-height: 1000000px !important;
        width: 600px !important;
        clip: rect(0px,auto,auto,0px);
        overflow: auto;
      }
      .geshi_highlighted a {
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
    
    $geshi = new GeSHi(trim($code), $lang, null);
    $geshi->set_header_type(GESHI_HEADER_PRE);
    // $geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS);
    $geshi->set_overall_class('geshi_highlighted');
    $parsed = $geshi->parse_code();
    
    $text = str_replace_once("{GESHI_BLOCK:$i:$random_id}", $parsed, $text);
  }
}
