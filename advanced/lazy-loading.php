<?php
/**
 * xCSS lazy-loading file
 *
 * Lazy loading for xCSS
 * place this inside of the <head> tag above the <link> tags:
 * <?php include 'path/to/lazy-loading.php' ?>
 */

define('XCSSCONFIG', '../_config.php');
define('XCSSCLASS', '../xcss-class.php');
include XCSSCONFIG;

$config['path_to_css_dir'] = '../'.$config['path_to_css_dir'];

function check_file($file_array, $file_path)
{
	foreach($file_array as $xcss_file => $css_file)
	{
		if(strpos($xcss_file, '*') !== FALSE)
		{
			$xcss_dir = glob($file_path.$xcss_file);
			foreach($xcss_dir as $glob_xcss_file)
			{
				$glob_css_file = dirname($css_file).'/'.basename(str_replace('.xcss', '.css', $glob_xcss_file));
				if(filemtime($glob_xcss_file) > filemtime($glob_css_file))
				{
					return TRUE;
				}
			}
		}
		else
		{
			if(filemtime($file_path.$xcss_file) > filemtime($file_path.$css_file))
			{
				return TRUE;
			}
		}
	}
	return FALSE;
}

if(check_file($config['xCSS_files'], $config['path_to_css_dir']))
{
	include XCSSCLASS;
	
	$xCSS = new xCSS($config);
	
	echo '<script type="text/javascript">'."\n";
	$xCSS->compile();
	unset($xCSS);
	echo '</script>'."\n";
}