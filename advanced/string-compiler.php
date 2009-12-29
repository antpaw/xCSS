<?php
/**
 * xCSS string-compiler file
 *
 */

$xcss_string = '.selector { color: red; }';

define('XCSSCLASS', '../xcss-class.php');
include XCSSCLASS;
define('XCSSCONFIG', '../config.php');
include XCSSCONFIG;

$xCSS = new xCSS($config);

echo '<script type="text/javascript">'."\n";
$css_string = $xCSS->compile($xcss_string);
unset($xCSS);
echo '</script>'."\n";

echo '<style type="text/css">'."\n";
echo $css_string;
echo '</style>'."\n";