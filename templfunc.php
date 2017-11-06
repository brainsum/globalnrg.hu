<?php
if (!class_exists("Template")) {
	if (!defined("TEMPLATE_INCLUDE"))   define("TEMPLATE_INCLUDE", "include");
	if (!defined("TEMPLATE_FLUSH"))     define("TEMPLATE_FLUSH", "flush");

	if (!defined("TEMPLATE_IF"))        define("TEMPLATE_IF", "if");
	if (!defined("TEMPLATE_IFNOT"))     define("TEMPLATE_IFNOT", "ifnot");
	if (!defined("TEMPLATE_ENDIF"))     define("TEMPLATE_ENDIF", "endif");
	if (!defined("TEMPLATE_ELSE"))      define("TEMPLATE_ELSE", "else");

	if (!defined("TEMPLATE_ITERSTART")) define("TEMPLATE_ITERSTART", "iterstart");
	if (!defined("TEMPLATE_ITEREND"))   define("TEMPLATE_ITEREND", "iterend");

	if (!defined("TEMPLATE_NOTFOUND"))  define("TEMPLATE_NOTFOUND", "<!-- template '%s' not found -->");

	/**
	 *
	 * HTML template parser v3.0.1
	 *
	 * 2000-08-07. NAGY Balazs, Written. v1.0.
	 * 2000-08-09. NAGY balazs, RE -> string functions
	 * 2000-08-10. NAGY Balazs, Classify. v1.1.
	 * 2000-08-16. NAGY Balazs, Eliminated directory name inheriting.
	 * 2001-09-07. KARASZI Istvan, don't replace empty [VARS].
	 * 2002-02-23. KARASZI Istvan, recursive parsing! v2.1.
	 * 2002-03-07. KARASZI Istvan, if in iter fix. v2.2.
	 * 2002-03-19. KARASZI Istvan, command in one line only. v2.3.
	 * 2002-05-28. NOLL Janos, Quick hack for "offset not contained in string" v2.3.1
	 * 2002-05-28. NOLL Janos, !== -> != replacement v2.3.2
	 * 2002-05-28. NOLL Janos, $val != false temp fix v2.3.3
	 * 2002-10-22. KARASZI Istvan, reindent and some phpdocu documentation
	 * 2004-04-13. KARASZI Istvan, parseS() function (parsing from string)
	 * 2004-08-25. NOLL Janos, Changed read to file_get_contents (needs PHP > 4.3.0)
	 * 2004-08-25. KARASZI Istvan, some optimizations and drop PHP > 4.3.0 dependency
	 * 2005-09-23. KARASZI Istvan, fully rewrite the code
	 * 2005-09-26. KARASZI Istvan, don't return with variable value on exists check (IF)
	 * 2005-09-26. KARASZI Istvan, parsee() function for AdServer (v3.0)
	 * 2005-10-04. KARASZI Istvan, allow / in INCLUDE tags v3.0.1
	 *
	 * @author	NAGY Balazs <julian7@lsc.hu>
	 * @author	NOLL Janos <jnoll@adverticum.com>
	 * @author	KARASZI Istvan <ikaraszi@adverticum.com>
	 * @copyright	(C)2001-2005 KARASZI Istvan <ikaraszi@adverticum.com>, LGPL
	 * @copyright	(C)2000 NAGY Balazs <julian7@lsc.hu>, LGPL
	 * @version	$Id: templfunc-php.inc,v 1.6 2005/10/04 07:57:47 ikaraszi Exp $
	 */
	class Template {
		/**
		 * variables
		 * @var		array
		 * @access	private
		 */
		var $_variables = array();

		/**
		 * print the text after parse
		 * @var int
		 * @access	private
		 */
		var $_flush = false;

		/**
		 * allowed characters regular expression
		 * @var		string
		 * @access	private
		 */
		var $_allowedChars = "A-Z0-9_";

		/**
		 * replace empty variables too
		 * @var		boolean
		 * @access	private
		 */
		var $replaceEmpty = false;

		/**
		 * Constructor
		 */
		function Template( $variables, $flush=false ) {
			if (is_array($variables)) $this->_variables = $variables;
			$this->_flush = $flush;
		}

		function _searchComments( $str ) {
//			if (!preg_match_all("/<!--\s*([" . $this->_allowedChars . "\/\.-\s]+)\s*-->/i", $str, $m, PREG_OFFSET_CAPTURE)) return null;
			if (!preg_match_all("/<!--\s*([" . $this->_allowedChars . "\/\.\-\s]+)\s*-->/i", $str, $m, PREG_OFFSET_CAPTURE)) return null;

			$c = array();

			for($i = 0; $i < count($m[0]); $i++) {
				$command = $m[1][$i][0];
				if ((list($cmd, $args) = $this->_parseArguments($command)) === null) {
					$cmd = null;
					$args = null;
				}

				$c[] = array(
					0 => $m[0][$i][1],
					1 => $m[0][$i][0],
					2 => $cmd,
					3 => $args
				);
			}

			return $c;
		}

		function _getArrayVar( $var, $counts, $test=false ) {
			$thing = null;

			$bases = explode("-", $var);
			$thing = $this->_getVar($bases[0]);

			if (!is_array($thing)) return null;

			$thing = $thing[$counts[0]];

			$bcount = count($bases);
			$count = count($counts);
			if ($count > 1) {
				for($i = 1; $i < $bcount && $i < $count; $i++) {
					$thing = $thing[$bases[$i]][$counts[$i]];
				}
			}

			$var = $bases[$bcount - 1];
			if (!isset($thing[$var])) return null;

			// variable exists
			if ($test) {
				if (!$thing[$var]) return false;
				return true;
			}

			return $thing[$var];
		}

		function _isVarSet( $var, $varbase, $counts=0 ) {
			if (strlen($varbase)) {
				return $this->_getArrayVar($var, $counts, true);
			}

			if (!isset($this->_variables[$var])) return false;
			if (!$this->_variables[$var]) return false;

			return true;
		}

		function _getVar( $var, $varbase="", $counts=0 ) {
			if ($varbase) $var = $varbase . "-" . $var;

			if (strlen($varbase)) return $this->_getArrayVar($var, $counts);

			if (!isset($this->_variables[$var])) return null;
			else return $this->_variables[$var];
		}

		function _parseArguments( $str ) {
			$str = trim($str);
			$alpha = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";

			if (!($cmdpos = strspn($str, "$alpha"))) return null;
			if ($str[$cmdpos] != " " and $str[$cmdpos] != "-") return null;

			$cmd = strtolower(substr($str, 0, $cmdpos));
			$args = strtolower(substr($str, $cmdpos + 1));

			return array($cmd, $args);
		}

		function _parseVariables( $str, $varbase="", $counts=0 ) {
			if (preg_match_all(sprintf("/\[%s([%s]+)\]/", ($varbase) ? strtoupper($varbase) . "-" : "", $this->_allowedChars), $str, $m)) {
				foreach($m[0] as $id => $match) {
					$var = strtolower($m[1][$id]);
					$val = $this->_getVar($var, $varbase, $counts);

					if ($this->replaceEmpty || $val !== null) {
						$str = str_replace($match, $val, $str);
					}
				}
			}

			return $str;
		}

		function _parseString( $str, $base="", $varbase="", $counts=0 ) {
			$str = $this->_parseVariables($str, $varbase, $counts);
			$prevstart = 0;

			$c = $this->_searchComments($str);

			$i = 0;
			$newstr = $str;
			while(isset($c[$i])) {
				$pos = $c[$i][0];
				$tot = $c[$i][1];
				$cmd = $c[$i][2];
				$arg = $c[$i][3];

				switch($cmd) {
					// handle INCLUDE
					case TEMPLATE_INCLUDE:
						$aend = $pos + strlen($tot);

						if (($substr = $this->_getFileContents($base . "/" . $arg)) !== null) {
							$parsed = $this->_parseString($substr, $base, $varbase, $counts);
						} else $parsed = sprintf(TEMPLATE_NOTFOUND, $arg);

						break;

					// handle IF, IFNOT, ELSE, ENDIF
					case TEMPLATE_IFNOT:
						$invert = true;
					case TEMPLATE_IF:
						for($k = $i; $k < count($c); $k++) {
							if ($c[$k][3] != $arg) continue;
							if ($c[$k][2] == TEMPLATE_ELSE) {
								$ife = $k; // ELSE position
								continue;
							}

							if ($c[$k][2] != TEMPLATE_ENDIF) continue;

							$startpos = $pos + strlen($tot);
							$endpos = $c[$k][0];
							$aend = $endpos + strlen($c[$k][1]);

							if ($ife) {
								$elsepos = $c[$ife][0];

								$branch = substr($str, $startpos, $elsepos - $startpos);

								$elsepos += strlen($c[$ife][1]);
								$elsebranch = substr($str, $elsepos, $endpos - $elsepos);
							} else {
								$branch = substr($str, $startpos, $endpos - $startpos);
								$elsebranch = "";
							}

							if (($ifvar = $this->_isVarSet($arg, $varbase, $counts)) === null) {
								$ifvar = $this->_isVarSet($arg, "", $counts);
							}

							if ($ifvar) {
								if ($invert) $parsed = $elsebranch;
								else $parsed = $branch;
							} else {
								if ($invert) $parsed = $branch;
								else $parsed = $elsebranch;
							}

							unset($ifvar);
							unset($ife);
							unset($invert);
							unset($branch);
							unset($elsebranch);
							unset($elsepos);
							unset($startpos);
							unset($endpos);

							break;
						}
						break;

					// handle iteration
					case TEMPLATE_ITERSTART:
						$iter = $this->_getVar($arg, $varbase, $counts);
						if (!(is_array($iter) && count($iter))) {
							if (!is_array($iter)) {
								$iter = $this->_getVar($arg, "", $counts);
								
								$shift = true;
							}
						}

						for($k = $i; $k < count($c); $k++) {
							if ($c[$k][3] != $arg) continue;
							if ($c[$k][2] != TEMPLATE_ITEREND) continue;

							// here we got ITEREND

							$startpos = $pos + strlen($c[$i][1]);
							$endpos = $c[$k][0];
							$aend = $endpos + strlen($c[$k][1]);

							$itxt = substr($str, $startpos, $endpos - $startpos);

							if (!is_array($counts)) $counts = array();
							if (isset($shift) && $shift) $ccounts = array();
							else $ccounts = $counts;

							$last = count($ccounts);

							$parsed = "";
							if (is_array($iter) && count($iter)) {
								foreach(array_keys($iter) as $key) {
									$ccounts[$last] = $key;
									$parsed .= $this->_parseString($itxt, $base, $arg, $ccounts);
								}
							}

							unset($ccounts);
							unset($iter);
							unset($itxt);
							unset($shift);
							unset($startpos);
							unset($endpos);

							break;
						}
						break;

					case TEMPLATE_FLUSH:
						// TODO: implement flush
				}

				if (is_string($parsed)) {
					$newstr = str_replace(substr($str, $pos, $aend - $pos), $parsed, $newstr);
					//$str = substr_replace($str, $parsed, $pos, $aend - $pos);

					unset($aend);
					unset($parsed);

					//$c = $this->_searchComments($str);
				}
				++$i;
			}

			return $newstr;
		}

		function _getFileContents( $fname ) {
			if (file_exists($fname) && is_readable($fname)) {
				// file_get_contents can be used from PHP 4.3.0
				if (function_exists("file_get_contents"))
					return file_get_contents($fname);
				else
					return join("", file($fname));
			}

			return NULL;
		}

		function parse( $fname ) {
			$base = dirname($fname);

			if (($file = $this->_getFileContents($fname)) !== null) {
				return $this->_parseString($file, $base);
			} else error_log(sprintf("Template is missing/cannot be read: '%s'", $fname));
		}
	}

	/* Own API */
	function parseS( $string, $vars, $flush=1, $replaceEmpty=false ) {
		$template = new Template($vars, $flush);
		$template->replaceEmpty = $replaceEmpty;
		if ($flush) print($template->_parseString($string));
		else return $template->_parseString($string);
	}

	function parse( $fname, $vars=0, $flush=1 ) {
		$template = new Template($vars, $flush);
		if ($flush) print($template->parse($fname));
		else return $template->parse($fname);
	}

	/* Adserver API */
	function parsee( $fname, $vars=0, $flush=1 ) {
		$template = new Template($vars, $flush);
		$template->replaceEmpty = true;
		if ($flush) print($template->parse($fname));
		else return $template->parse($fname);
	}

	/* Compatibility function with Prim's API */
	function template( $fname, $rvars, $itvars, $tofile="" ) {
		$vars = array_merge($rvars, $itvars);
		if ($tofile == "str") $flushp = 0; else $flushp = 1;

		return parse($fname, $vars, $flushp);
	}
}
?>
