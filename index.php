<?php
/**
 * xCSS index file
 *
 * @author     Anton Pawlik
 * @see        http://xcss.antpaw.org/docs/
 * @copyright  (c) 2009 Anton Pawlik
 * @license    http://xcss.antpaw.org/about/
 */


define('XCSSCLASS', 'xcss-class.php');
file_exists(XCSSCLASS) ? include XCSSCLASS : die('alert("xCSS Parse error: Can\'t find the xCSS class file: \''.XCSSCLASS.'\'.");');

define('XCSSCONFIG', 'config.php');
file_exists(XCSSCONFIG) ? include XCSSCONFIG : die('alert("xCSS Parse error: Can\'t find the xCSS config file: \''.XCSSCONFIG.'\'.");');

$xCSS = new xCSS($config);

$xCSS->compile();