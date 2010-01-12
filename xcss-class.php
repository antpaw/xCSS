<?php defined('XCSSCLASS') OR die('No direct access allowed.');
/**
 * xCSS class
 *
 * @author     Anton Pawlik
 * @version    1.0.1
 * @see        http://xcss.antpaw.org/docs/
 * @copyright  (c) 2010 Anton Pawlik
 * @license    http://xcss.antpaw.org/about/
 */

class xCSS
{
	// config vars
	public $path_css_dir;
	public $master_file;
	public $xcss_files;
	public $reset_files;
	public $hook_files;
	public $css_files;
	public $construct;
	public $compress_output_to_master;
	public $minify_output;
	public $master_content;
	public $debugmode;
	
	// hole content of the xCSS file
	public $filecont;
	
	// an array of keys(selectors) and values(propertys)
	public $parts;
	
	// nodes that will be extended some levels later
	public $levelparts;
	
	// final css nodes as an array
	public $css;
	
	// vars declared in xCSS files
	public $xcss_vars;
	
	// output string for each CSS file
	public $final_file;
	
	// relevant to debugging
	public $debug;
	
	public function __construct(array $cfg)
	{
		if(isset($cfg['disable_xCSS']) && $cfg['disable_xCSS'] === TRUE)
		{
			throw new xCSS_Exception('xcss_disabled');
		}
		
		$this->levelparts = array();
		$this->path_css_dir = isset($cfg['path_to_css_dir']) ? $cfg['path_to_css_dir'] : '../';
		
		if(isset($cfg['xCSS_files']))
		{
			$this->xcss_files = array();
			$this->css_files = array();
			foreach($cfg['xCSS_files'] as $xcss_file => $css_file)
			{
				if(strpos($xcss_file, '*') !== FALSE)
				{
					$xcss_dir = glob($this->path_css_dir . $xcss_file);
					foreach($xcss_dir as $glob_xcss_file)
					{
						$glob_xcss_file = str_replace($this->path_css_dir, NULL, $glob_xcss_file);
						array_push($this->xcss_files, $glob_xcss_file);
						
						$glob_css_file = dirname($css_file).'/'.basename(str_replace('.xcss', '.css', $glob_xcss_file));
						// get rid of the media properties
						$file = explode(':', $glob_css_file);
						array_push($this->css_files, trim($file[0]));
						$cfg['xCSS_files'][$glob_xcss_file] = $glob_css_file;
					}
					unset($cfg['xCSS_files'][$xcss_file]);
				}
				else
				{
					array_push($this->xcss_files, $xcss_file);
					// get rid of the media properties
					$file = explode(':', $css_file);
					array_push($this->css_files, trim($file[0]));
				}
			}
		}
		else
		{
			$this->xcss_files = array('xcss.xcss');
			$this->css_files = array('xcss_generated.css');
		}
		
		// CSS master file
		$this->compress_output_to_master = (isset($cfg['compress_output_to_master']) && $cfg['compress_output_to_master'] === TRUE);
		
		if($this->compress_output_to_master || (isset($cfg['use_master_file']) && $cfg['use_master_file'] === TRUE))
		{
			$this->master_file = isset($cfg['master_filename']) ? $cfg['master_filename'] : 'master.css';
			$this->reset_files = isset($cfg['reset_files']) ? $cfg['reset_files'] : NULL;
			$this->hook_files = isset($cfg['hook_files']) ? $cfg['hook_files'] : NULL;
			
			if( ! $this->compress_output_to_master)
			{
				$xcssf = isset($cfg['xCSS_files']) ? $cfg['xCSS_files'] : NULL;
				$this->create_master_file($this->reset_files, $xcssf, $this->hook_files);
			}
		}
		
		$this->construct = isset($cfg['construct_name']) ? $cfg['construct_name'] : 'self';
		
		$this->minify_output = isset($cfg['minify_output']) ? $cfg['minify_output'] : FALSE;
		
		$this->debugmode = isset($cfg['debugmode']) ? $cfg['debugmode'] : FALSE;
		
		if($this->debugmode)
		{
			$this->debug['xcss_time_start'] = $this->microtime_float();
			$this->debug['xcss_output'] = NULL;
		}
		
		// this is needed to be able to extend selectors across mulitple xCSS files
		$this->xcss_files = array_reverse($this->xcss_files);
		$this->css_files = array_reverse($this->css_files);
		
		$this->xcss_vars = array(
			// unsafe chars will be hidden as vars
			'$__doubleslash'			=> '//',
			'$__bigcopen'				=> '/*',
			'$__bigcclose'				=> '*/',
			'$__doubledot'				=> ':',
			'$__semicolon'				=> ';',
			'$__curlybracketopen'		=> '{',
			'$__curlybracketclosed'		=> '}',
			// shortcuts 
			// it's "a hidden feature" for now
			'bg:'						=> 'background:',
			'bgc:'						=> 'background-color:',
		);
	}
	
	public function create_master_file(array $reset = array(), array $main = array(), array $hook = array())
	{
		$all_files = array_merge($reset, $main, $hook);
		
		$master_file_content = NULL;
		foreach($all_files as $file)
		{
			$file = explode(':', $file);
			$props = isset($file[1]) ? ' '.trim($file[1]) : NULL;
			$master_file_content .= '@import url("'.trim($file[0]).'")'.$props.';'."\n";
		}
		
		$this->create_file($master_file_content, $this->master_file);
	}
	
	public function compile($input_xcss = FALSE)
	{
		if($input_xcss === FALSE)
		{
			$count_xcss_files = count($this->xcss_files);
			for($i = 0; $i < $count_xcss_files; $i++)
			{
				$this->parts = NULL;
				$this->filecont = NULL;
				$this->css = NULL;
				
				$filename = $this->path_css_dir.$this->xcss_files[$i];
				$this->filecont = $this->read_file($filename);
				
				if($this->parse_xcss_string())
				{
					$this->final_parse($this->css_files[$i]);
				}
			}
			
			if( ! empty($this->final_file))
			{
				if($this->compress_output_to_master)
				{
					$master_content = NULL;
					foreach($this->reset_files as $fname)
					{
						$fname = explode(':', $fname);
						$master_content .= $this->read_file($this->path_css_dir.$fname[0])."\n";
					}
					if($this->minify_output && strpos($master_content, '/*') !== FALSE)
					{
						$master_content = preg_replace("/\/\*(.*)?\*\//Usi", NULL, $master_content);				
					}
					$this->final_file = array_reverse($this->final_file);
					foreach($this->final_file as $fcont)
					{
						$master_content .= $this->use_vars($fcont);
					}
					foreach($this->hook_files as $fname)
					{
						$fname = explode(':', $fname);
						$tmp_file = $this->read_file($this->path_css_dir.$fname[0]);
						if($this->minify_output && strpos($tmp_file, '/*') !== FALSE)
						{
							$tmp_file = preg_replace("/\/\*(.*)?\*\//Usi", NULL, $tmp_file);				
						}
						$master_content .= $tmp_file;
					}
					$master_content = $this->do_math($master_content);
					$this->create_file($master_content, $this->master_file);
				}
				else
				{
					foreach($this->final_file as $fname => $fcont)
					{
						$fcont = $this->do_math($this->use_vars($fcont));
						$this->create_file($fcont, $fname);
					}
				}
			}
		}
		else
		{
			$this->filecont = $input_xcss;
			if($this->parse_xcss_string())
			{
				$this->final_parse('string');
				$fcont = $this->use_vars($this->final_file['string']);
				$fcont = $this->do_math($fcont);
				return $this->create_file($fcont, 'string');
			}
		}
	}
	
	public function parse_xcss_string()
	{		
		foreach($this->xcss_vars as $var => $unsafe_char)
		{
			$masked_unsafe_char = str_replace(array('*', '/'), array('\*', '\/'), $unsafe_char);
			$patterns[] = '/content(.*:.*(\'|").*)('.$masked_unsafe_char.')(.*(\'|"))/';
			$replacements[] = 'content$1'.$var.'$4';
		}
		
		$this->filecont = preg_replace($patterns, $replacements, $this->filecont);
		
		if(strlen($this->filecont) > 1)
		{
			$this->split_content();
			
			if( ! empty($this->parts))
			{
				$this->parse_level();
				
				$this->parts = $this->manage_order($this->parts);
				
				if( ! empty($this->levelparts))
				{
					$this->manage_global_extends();
				}
				
				return TRUE;
			}
		}
		
		return FALSE;
	}
	
	public function calc_string($math)
	{
		if(@eval('$result = '.$math.';') === FALSE)
		{
			throw new xCSS_Exception('xcss_math_error', array('math' => $math));
		}
		return $result;
	}
	
	public function do_math($content)
	{
		$units = array('px', '%', 'em', 'pt', 'cm', 'mm');
		$units_count = count($units);
		preg_match_all('/(\[(?:[^\[\]]|(?R))*\])((?:(?: |	)|;)|.+?\S)/', $content, $result);
		
		$count_results = count($result[0]);
		for($i = 0; $i < $count_results; $i++)
		{
			$better_math_str = strtr($result[1][$i], array('[' => '(', ']' => ')'));
			if (strpos($better_math_str, '=') !== FALSE)
			{
				continue;
			}
			if (strpos($better_math_str, '#') !== FALSE)
			{
				preg_match_all('/#(\w{6}|\w{3})/', $better_math_str, $colors);
				for($y = 0; $y < count($colors[1]); $y++)
				{
					$color = $colors[1][$y];
					if(strlen($color) === 6)
					{
						$r = $color[0].$color[1];
						$g = $color[2].$color[3];
						$b = $color[4].$color[5];
					}
					else
					{
						$r = $color[0].$color[0];
						$g = $color[1].$color[1];
						$b = $color[2].$color[2];
					}
					
					if($y === 0)
					{
						$rgb = array(
							str_replace('#'.$color, '0x'.$r, $better_math_str),
							str_replace('#'.$color, '0x'.$g, $better_math_str),
							str_replace('#'.$color, '0x'.$b, $better_math_str),
						);
					}
					else
					{
						$rgb = array(
							str_replace('#'.$color, '0x'.$r, $rgb[0]),
							str_replace('#'.$color, '0x'.$g, $rgb[1]),
							str_replace('#'.$color, '0x'.$b, $rgb[2]),
						);
					}
				}
				$better_math_str = '#';
				$c = $this->calc_string($rgb[0]);
				$better_math_str .= str_pad(dechex($c<0?0:($c>255?255:$c)), 2, 0, STR_PAD_LEFT);
				$c = $this->calc_string($rgb[1]);
				$better_math_str .= str_pad(dechex($c<0?0:($c>255?255:$c)), 2, 0, STR_PAD_LEFT);
				$c = $this->calc_string($rgb[2]);
				$better_math_str .= str_pad(dechex($c<0?0:($c>255?255:$c)), 2, 0, STR_PAD_LEFT);
			}
			else
			{
				$better_math_str = preg_replace("/[^\d\*+-\/\(\)]/", NULL, $better_math_str);
				if ($better_math_str === '()' || $better_math_str === '')
				{
					continue;
				}
				$new_unit = NULL;
				if($result[2][$i] === ';' || $result[2][$i] === ' ' || $result[2][$i] === '	')
				{
					$all_units_count = 0;
					for($x = 0; $x < $units_count; $x++)
					{
						$this_unit_count = count(explode($units[$x], $result[1][$i]))-1;
						if($all_units_count < $this_unit_count)
						{
							$new_unit = $units[$x];
							$all_units_count = $this_unit_count;
						}
					}
					if($all_units_count === 0)
					{
						$new_unit = 'px';
					}
				}
				
				$better_math_str = $this->calc_string($better_math_str) . $new_unit;
			}
			
			$content = str_replace(array('#'.$result[1][$i], $result[1][$i]), $better_math_str, $content);
		}
		
		return $content;
	}
	
	public function read_file($filepath)
	{
		$filecontent = NULL;
		
		if(file_exists($filepath))
		{
			$filecontent = str_replace('ï»¿', NULL, utf8_encode(file_get_contents($filepath)));
		}
		else
		{
			throw new xCSS_Exception('xcss_file_does_not_exist', array('file' => $filepath));
		}
		
		return $filecontent;
	}
	
	public function split_content()
	{
		// removes multiple line comments
		$this->filecont = preg_replace("/\/\*(.*)?\*\//Usi", NULL, $this->filecont);
		// removes inline comments, but not :// (protocol)
		$this->filecont .= "\n";
		$this->filecont = preg_replace("/[^:]\/\/.+/", NULL, $this->filecont);
		$this->filecont = str_replace(array('	extends', 'extends	'), array(' extends', 'extends '), $this->filecont);
		
		$this->filecont = $this->change_braces($this->filecont);
		
		$this->filecont = explode('#c]}', $this->filecont);
		
		foreach($this->filecont as $i => $part)
		{
			$part = trim($part);
			if($part !== '')
			{
				list($keystr, $codestr) = explode('{[o#', $part);
				// adding new line to all (,) in selectors, to be able to find them for 'extends' later
				$keystr = str_replace(',', ",\n", trim($keystr));
				if($keystr === 'vars')
				{
					$this->setup_vars($codestr);
					unset($this->filecont[$i]);
				}
				else if($keystr !== '')
				{
					$this->parts[$keystr] = $codestr;
				}
			}
		}
	}
	
	public function setup_vars($codestr)
	{
		$codes = explode(';', $codestr);
		if( ! empty($codes))
		{
			foreach($codes as $code)
			{
				$code = trim($code);
				if( ! empty($code))
				{
					list($varkey, $varcode) = explode('=', $code);
					$varkey = trim($varkey);
					$varcode = trim($varcode);
					if(strlen($varkey) > 0)
					{
						$this->xcss_vars[$varkey] = $this->use_vars($varcode);
					}
				}
			}
			$this->xcss_vars[': var_rule'] = NULL;
		}
	}
	
	public function use_vars($cont)
	{
		return strtr($cont, $this->xcss_vars);
	}
	
	public function parse_level()
	{
		// this will manage xCSS rule: 'extends'
		$this->parse_extends();

		// this will manage xCSS rule: child objects inside of a node
		$this->parse_children();
	}
	
	public function regex_extend($keystr)
	{
		preg_match_all('/((\S|\s)+?) extends ((\S|\s|\n)[^,]+)/', $keystr, $result);
		return $result;
	}
	
	public function manage_global_extends()
	{
		// helps to find all the extenders of the global extended selector
		foreach($this->levelparts as $keystr => $codestr)
		{
			if(strpos($keystr, 'extends') !== FALSE)
			{
				$result = $this->regex_extend($keystr);
				
				$child = trim($result[1][0]);
				$parent = trim($result[3][0]);
				
				foreach($this->parts as $p_keystr => $p_codestr)
				{
					// to be sure we get all the children we need to find the parent selector
					// this must be the one that has no , after his name
					if(strpos($p_keystr, ",\n".$child) !== FALSE && strpos($p_keystr, $child.',') === FALSE)
					{
						$p_keys = explode(",\n", $p_keystr);
						foreach($p_keys as $p_key)
						{
							$this->levelparts[$p_key.' extends '.$parent] = NULL;
						}
					}
				}
			}
		}
	}
	
	public function manageMultipleExtends()
	{
		// To be able to manage multiple extends, you need to
		// destroy the actual node and creat many nodes that have
		// mono extend. the first one gets all the css rules
		foreach($this->parts as $keystr => $codestr)
		{
			if(strpos($keystr, 'extends') !== FALSE)
			{
				$result = $this->regex_extend($keystr);
				
				$parent = trim($result[3][0]);
				$child = trim($result[1][0]);
				
				if(strpos($parent, '&') !== FALSE)
				{
					$kill_this = $child.' extends '.$parent;
					
					$parents = explode(' & ', $parent);
					$with_this_key = $child.' extends '.$parents[0];
					
					$add_keys = array();
					$count_parents = count($parents);
					for($i = 1; $i < $count_parents; $i++)
					{
						array_push($add_keys, $child.' extends '.$parents[$i]);
					}
					
					$this->parts = $this->add_node_at_order($kill_this, $with_this_key, $codestr, $add_keys);
				}
			}
		}
	}
	
	public function add_node_at_order($kill_this, $with_this_key, $and_this_value, $additional_key = array())
	{
		foreach($this->parts as $keystr => $codestr)
		{
			if($keystr === $kill_this)
			{
				$temp[$with_this_key] = $and_this_value;
				
				if( ! empty($additional_key))
				{
					foreach($additional_key as $empty_key)
					{
						$temp[$empty_key] = NULL;
					}
				}
			}
			else
			{
				$temp[$keystr] = $codestr;
			}
		}
		return $temp;
	}
	
	public function parse_extends()
	{
		// this will manage xCSS rule: 'extends &'
		$this->manageMultipleExtends();
		
		foreach($this->levelparts as $keystr => $codestr)
		{
			if(strpos($keystr, 'extends') !== FALSE)
			{
				$result = $this->regex_extend($keystr);
				
				$parent = trim($result[3][0]);
				$child = trim($result[1][0]);
				
				// TRUE means that the parent node was in the same file
				if($this->search_for_parent($child, $parent))
				{
					// remove extended rule
					unset($this->levelparts[$keystr]);
				}
			}
		}

		foreach($this->parts as $keystr => $codestr)
		{
			if(strpos($keystr, 'extends') !== FALSE)
			{
				$result = $this->regex_extend($keystr);
				if(count($result[3]) > 1)
				{
					unset($this->parts[$keystr]);
					$keystr = str_replace(' extends '.$result[3][0], NULL, $keystr);
					$keystr .= ' extends '.$result[3][0];
					$this->parts[$keystr] = $codestr;
					$this->parse_extends();
					break;
				}
				
				$parent = trim($result[3][0]);
				$child = trim($result[1][0]);
				// TRUE means that the parent node was in the same file
				if($this->search_for_parent($child, $parent))
				{
					// if not empty, create own node with extended code
					$codestr = trim($codestr);
					if($codestr !== '')
					{
						$this->parts[$child] = $codestr;
					}
					
					unset($this->parts[$keystr]);
				}
				else
				{
					$codestr = trim($codestr);
					if($codestr !== '')
					{
						$this->parts[$child] = $codestr;
					}
					unset($this->parts[$keystr]);
					// add this node to levelparts to find it later
					$this->levelparts[$keystr] = $codestr;
				}
			}
		}
	}
	
	public function search_for_parent($child, $parent)
	{
		$parent_found = FALSE;
		foreach ($this->parts as $keystr => $codestr)
		{
			$sep_keys = explode(",\n", $keystr);
			foreach ($sep_keys as $s_key)
			{
				if($parent === $s_key)
				{
					$this->parts = $this->add_node_at_order($keystr, $child.",\n".$keystr, $codestr);
					// finds all the parent selectors with another bind selectors behind
					foreach ($this->parts as $keystr => $codestr)
					{
						$sep_keys = explode(",\n", $keystr);
						foreach ($sep_keys as $s_key)
						{
							if($parent !== $s_key && strpos($s_key, $parent) !== FALSE)
							{
								$childextra = str_replace($parent, $child, $s_key);
								
								if(strpos($childextra, 'extends') === FALSE)
								{
									// get rid off not extended parent node
									$this->parts = $this->add_node_at_order($keystr, $childextra.",\n".$keystr, $codestr);
								}
							}
						}
					}
					$parent_found = TRUE;
				}
			}
		}
		return $parent_found;
	}
	
	public function parse_children()
	{
		$children_left = FALSE;
		foreach($this->parts as $keystr => $codestr)
		{
			if(strpos($codestr, '{') !== FALSE)
			{
				$keystr = trim($keystr);
				unset($this->parts[$keystr]);
				unset($this->levelparts[$keystr]);
				$this->manage_children($keystr, $this->construct."{}\n".$codestr);
				$children_left = TRUE; // maybe
			}
		}
		if($children_left)
		{
			$this->parse_level();
		}
	}
	
	public function manage_children($keystr, $codestr)
	{
		$codestr = $this->change_braces($codestr);
		
		$c_parts = explode('#c]}', $codestr);
		foreach ($c_parts as $c_part)
		{
			$c_part = trim($c_part);
			if($c_part !== '')
			{
				list($c_keystr, $c_codestr) = explode('{[o#', $c_part);
				$c_keystr = trim($c_keystr);
				
				if($c_keystr !== '')
				{
					$better_key = NULL;
					
					$better_strkey = explode(',', $keystr);
					$c_keystr = explode(',', $c_keystr);
					foreach($c_keystr as $child_coma_keystr)
					{
						foreach($better_strkey as $parent_coma_keystr)
						{
							$better_key .= trim($parent_coma_keystr).' '.trim($child_coma_keystr).",\n";
						}
					}
					
					if(strpos($better_key, $this->construct) !== FALSE)
					{
						$better_key = str_replace(' '.$this->construct, NULL, $better_key);
					}
					$this->parts[substr($better_key, 0, -2)] = $c_codestr;
				}
			}
		}
	}
	
	public function change_braces($str)
	{
		/*
			This function was writen by Gumbo
			http://www.tutorials.de/forum/members/gumbo.html
			Thank you very much!
		
			finds the very outer braces and changes them to {[o# code #c]}
		*/
		$buffer = NULL;
		$depth = 0;
		$strlen_str = strlen($str);
		for($i = 0; $i < $strlen_str; $i++)
		{
			$char = $str[$i];
			switch ($char)
			{
				case '{':
					$depth++;
					$buffer .= ($depth === 1) ? '{[o#' : $char;
				break;
				case '}':
					$depth--;
					$buffer .= ($depth === 0) ? '#c]}' : $char;
				break;
				default:
					$buffer .= $char;
			}
		}
		return $buffer;
	}
	
	public function manage_order(array $parts)
	{
		/*
			this function brings the CSS nodes in the right order
			because the last value always wins
		*/
		foreach ($parts as $keystr => $codestr)
		{
			// ok let's find out who has the most 'extends' in his key
			// the more the higher this node will go
			$sep_keys = explode(",\n", $keystr);
			$order[$keystr] = count($sep_keys) * -1;
		}
		asort($order);
		foreach ($order as $keystr => $order_nr)
		{
			// with the sorted order we can now redeclare the values
			$sorted[$keystr] = $parts[$keystr];
		}
		// and give it back
		return $sorted;
	}
	
	public function final_parse($filename)
	{
		foreach($this->parts as $keystr => $codestr)
		{
			$codestr = trim($codestr);
			if($codestr !== '')
			{
				if( ! isset($this->css[$keystr]))
				{
					$this->css[$keystr] = array();
				}
				$codes = explode(';', $codestr);
				foreach($codes as $code)
				{
					$code = trim($code);
					if($code !== '')
					{
						$codeval = explode(':', $code);
						if(isset($codeval[1]))
						{
							$this->css[$keystr][trim($codeval[0])] = trim($codeval[1]);
						}
						else
						{
							$this->css[$keystr][trim($codeval[0])] = 'var_rule';
						}
					}
				}
			}
		}
		$this->final_file[$filename] = $this->create_css();
	}
	
	public function create_css()
	{
		$result = NULL;
		if(is_array($this->css))
		{
			foreach($this->css as $selector => $properties)
			{
				// feel free to modifie the indentations the way you like it
				$result .= "$selector {\n";
				foreach($properties as $property => $value)
				{
					$result .= "	$property: $value;\n";
				}
				$result .= "}\n";
			}
			$result = preg_replace('/\n+/', "\n", $result);
		}
		return $result;
	}
	
	public function create_file($content, $filename)
	{
		if($this->debugmode)
		{
			$this->debug['xcss_output'] .= "/*\nFILENAME:\n".$filename."\nCONTENT:\n".$content."*/\n//------------------------------------\n";
		}
		
		if($this->minify_output)
		{
			$content = str_replace(array("\n ", "\n", "\t", '  ', '   '), NULL, $content);
			$content = str_replace(array(' {', ';}', ': ', ', '), array('{', '}', ':', ','), $content);
		}
		
		if($filename === 'string')
		{
			return $content;
		}
		
		$filepath = $this->path_css_dir.$filename;
		if( ! file_exists($filepath))
		{
			if(is_dir(dirname($filepath)))
			{
				if( ! fopen($filepath, 'w'))
				{
					throw new xCSS_Exception('css_file_unwritable', array('file' => $filepath));
				}
			}
			else
			{
				throw new xCSS_Exception('css_dir_unwritable', array('file' => $filepath));
			}
		}
		else if( ! is_writable($filepath))
		{
			throw new xCSS_Exception('css_file_unwritable', array('file' => $filepath));
		}
		
		file_put_contents($filepath, utf8_decode($content));
	}
	
	public function microtime_float()
	{
		list($usec, $sec) = explode(' ', microtime());
		return ((float) $usec + (float) $sec);
	}
	
	public function __destruct()
	{
		if($this->debugmode)
		{
			$time = $this->microtime_float() - $this->debug['xcss_time_start'];
			echo '// Parsed xCSS in: '.round($time, 6).' seconds'."\n//------------------------------------\n".$this->debug['xcss_output'];
		}
	}
}

class xCSS_Exception extends Exception
{
	public function __construct($message, array $variables = NULL, $code = 0)
	{
		switch ($message)
		{
			case 'xcss_math_error':
				$message = 'xCSS Parse error: unable to solve this math operation: "'.$variables['math'].'"';
			break;
			case 'xcss_file_does_not_exist':
				$message = 'Cannot find "'.$variables['file'].'"';
			break;
			case 'css_file_unwritable':
			case 'css_dir_unwritable':
				$message = 'Cannot write to the output file "'.$variables['file'].'", check CHMOD permissions';
			break;
			case 'xcss_disabled':
				echo '// xCSS was disabled via "config.php"! Remove the xCSS <script> tag from your HMTL <head> tag';
				die();
			break;
			default:
				$message = 'xCSS Parse error: check the syntax of your xCSS files';
			break;
		}
		
		parent::__construct($message, $code);
	}
	
	public function __tostring()
	{
		echo sprintf("// %s\nalert(\"%s\");\n// in %s [ %d ]\n", get_class($this), addslashes($this->getMessage()), $this->getFile(), $this->getLine());
		die();
	}
}