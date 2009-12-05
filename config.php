<?php defined('XCSSCONFIG') OR die('No direct access allowed.');
/**
 * xCSS config
 */

$config['path_to_css_dir'] = '../';				//	default: '../'

$config['xCSS_files'] = array
(
//	'source/modules.xcss'		=> 'generated/modules.css',
//	'source/main.xcss'			=> 'generated/main.css',
);

$config['use_master_file'] = true;				//	default: 'true'
$config['compress_output_to_master'] = false;	//	default: 'false'
$config['master_filename'] = 'master.css';		//	default: 'master.css'

$config['reset_files'] = array
(
//	'static/reset.css',
);

$config['hook_files'] = array
(
//	'static/hooks.css: screen',
);

$config['construct_name'] = 'self';				//	default: 'self'

$config['minify_output'] = false;				//	default: 'false'

$config['debugmode'] = false;					//	default: 'false'

$config['disable_xCSS'] = false;				//	default: 'false'