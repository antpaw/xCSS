<?php
/**
 * xCSS lazy-loading file
 *
 * Lazy loading for xCSS
 * place this inside of the <head> tag above the <link> tags:
 * <?php include 'path/to/lazy-loading.php' ?>
 */

define('XCSSCONFIG', '../config.php');
define('XCSSCLASS', '../xcss-class.php');
include XCSSCONFIG;

function check_file($file_array, $file_path, $to_master, $master_filename)
{
	foreach($file_array as $xcss_file => $css_file)
	{
		if(strpos($xcss_file, '*') !== FALSE)
		{
			$xcss_dir = glob($file_path.$xcss_file);
			
			foreach($xcss_dir as $glob_xcss_file)
			{
				if($to_master)
				{
					$glob_css_file = $file_path.$master_filename;
				}
				else
				{
					$glob_css_file = $file_path.dirname($css_file).'/'.basename(str_replace('.xcss', '.css', $glob_xcss_file));
				}
				
				if(filemtime($glob_xcss_file) > filemtime($glob_css_file))
				{
					return TRUE;
				}
			}
		}
		else
		{
			if($to_master)
			{
				$path_css_file = $file_path.$master_filename;
			}
			else
			{
				$path_css_file = $file_path.$css_file;
			}

			if(filemtime($file_path.$xcss_file) > filemtime($path_css_file))
			{
				return TRUE;
			}
		}
	}
	return FALSE;
}

if(check_file($config['xCSS_files'], $config['path_to_css_dir'], $config['compress_output_to_master'], $config['master_filename']))
{
	include XCSSCLASS;
	
	$xCSS = new xCSS($config);
	
	echo '<script type="text/javascript">'."\n";
	$xCSS->compile();
	unset($xCSS);
	echo '</script>'."\n";
}