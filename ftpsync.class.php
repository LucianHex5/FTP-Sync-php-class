<?php

/* 
 * The MIT License
 *
 * Copyright 2016 Lucian I. Last
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

class ftpsync  {


	/** @var resource connection var */
	private $conn_id;
	/** @var string host */
	private $ftp_server;
	/** @var string user */
	private $ftp_user_name;
	/** @var string password */
	private $ftp_user_pass;
	/** @var int host server port 
	 * This parameter specifies an alternate port to connect to.
	 * If it is omitted or set to zero, then the default FTP port, 21, will be used. 
	 */
	private $ftp_port;
	
	/** @var array of files or directories to ignore
	 * under the FTP servers file path
	 * uses mb_ereg_match($value, $file)
	 * so you can do stuff like this:
	 * array(
	 *  '/httpdocs.*\/config\.rb',
	 *  '/httpdocs/.*\/translations',
	 * );
	 */
	private $ignore;
					
	function __construct(
					$ftp_server,
					$ftp_user_name, 
					$ftp_user_pass
	) {
		// initailizing properties
		$this->ftp_server			= $ftp_server;
		$this->ftp_user_name	= $ftp_user_name;
		$this->ftp_user_pass	= $ftp_user_pass;
		$this->ftp_port				= 21;
		
		$this->ignore = array();
	}
	
	// getter
	function getConn_id() {
		return $this->conn_id;
	}
	
	//setters
	function setFtp_server(type $ftp_server) {
		$this->ftp_server = $ftp_server;
	}
	function setFtp_user_name(type $ftp_user_name) {
		$this->ftp_user_name = $ftp_user_name;
	}
	function setFtp_user_pass(type $ftp_user_pass) {
		$this->ftp_user_pass = $ftp_user_pass;
	}
	/**
	 * return false if conn_id defined
	 * @param int $ftp_port
	 * @return boolean
	 */
	function setFtp_port($ftp_port) {
		if( !isset($this->conn_id) ) {
			$this->ftp_port = $ftp_port;
			return TRUE;
		} else {
			return FALSE;
		}
	}
	function setIgnore($ignore) {
		$this->ignore = $ignore;
	}
	function clearIgnore() {
		$this->ignore = array();
	}


	/**
	 * Starting and checking connection
	 */
	function setup($isPassive=TRUE) {
		// set up basic connection
		$this->conn_id = ftp_connect($this->ftp_server, $this->ftp_port); 

		// login with username and password
		$this->login_result = ftp_login($this->conn_id, $this->ftp_user_name, $this->ftp_user_pass); 

		// check connection
		if ((!$this->conn_id) || (!$this->login_result)) { 
				echo "FTP connection has failed!\n";
				echo "Attempted to connect to $this->ftp_server for user $this->ftp_user_name\n"; 
				exit; 
		} else {
				echo "Connected to $this->ftp_server,\n\t$this->ftp_user_name\n";
		}

		if($isPassive){
			ftp_pasv($this->getConn_id(), TRUE);
		}
	}
	
	/**
	 * close the FTP stream
	 */
	function close() {
		ftp_close($this->conn_id);
	}
	
	/**
	 * Uploads a Directory recursively to the FTP server
	 * NOTE: echos can be removed for use
	 * 
	 * Thanks to:
	 * lucas@rufy.com
	 * http://ee1.php.net/manual/en/function.ftp-put.php#34688
	 * 
	 * @param string $loc_dir local directory
	 * @param string $rmt_dir remote directory
	 * @param boolean $isRecursive will remove found directories as well
	 * @param boolean $useWhiteList Ignore array will be used as a white list
	 */
	function ftp_put_dir($loc_dir, $rmt_dir, $isRecursive=TRUE, $useWhiteList=FALSE) {
		$loc_dir = rtrim($loc_dir, '/');
		$rmt_dir = rtrim($rmt_dir, '/');
		
		$this->prv_ftp_put_dir($loc_dir, $rmt_dir, $isRecursive, $useWhiteList);
	}
	private function prv_ftp_put_dir($loc_dir, $rmt_dir, $isRecursive=TRUE, $useWhiteList=FALSE) {
		// is $dir a directory
		$dir = dir($loc_dir);
		
		// does $rmt_dir exist
		if ($this->isRmtDir($rmt_dir) === FALSE) {
			echo "\nUnknown remote path specified...\n";
			$this->close();
			exit(1);
		}
		
		 // do this for each file in the directory
		while ($file = $dir->read()) {
			
			if ($file != "." && $file != "..") { // to prevent an infinite loop

				// ignore chosen dirs
				if ($this->isIgnore($this->ignore, $rmt_dir."/".$file) ) {
					continue;
				}

				if (is_dir($loc_dir."/".$file)) { // do the following if it is a directory
					
					if (!@ftp_chdir($this->conn_id, $rmt_dir."/".$file)) {
						// create directories that do not yet exist
						echo "+:/ ".$rmt_dir."/".$file."\n";
						$rtn_mkdir = ftp_mkdir($this->conn_id, $rmt_dir."/".$file);
						
						if ($rtn_mkdir === FALSE) {
							echo "\nThere was an error uploading the directory...\n";
							$this->close();
							exit(1);
						}
					}
					
					// recursive into next directory
					$this->prv_ftp_put_dir($loc_dir."/".$file, $rmt_dir."/".$file);
				
				} else {
					echo '+>> '.$rmt_dir."/".$file;
					$rtn_put = ftp_nb_put(
							$this->conn_id, 
							$rmt_dir."/".$file, 
							$loc_dir."/".$file, 
							$this->ftp_trans_mode($file));
					
				while ($rtn_put == FTP_MOREDATA) {
					// do whatever you want
					echo '.';
					
					// continue downloading
					$rtn_put = ftp_nb_continue($this->conn_id);
				}
				if ($rtn_put != FTP_FINISHED) {
					echo "\nThere was an error uploading the file...\n";
					$this->close();
					exit(1);
				}
					echo "\n";
				}
				
			}
		}
		
	}
	


	/**
	 * Downloads a Directory recursively from the FTP server
	 * NOTE: echos can be removed for use
	 * 
	 * Thanks to:
	 * mroerick@gmx.net
	 * http://php.net/manual/en/function.ftp-get.php#90910
	 * 
	 * @param string $loc_dir local directory
	 * @param string $rmt_dir remote directory
	 * @param boolean $isRecursive will remove found directories as well
	 * @param boolean $useWhiteList Ignore array will be used as a white list
	 * @param boolean $debug var_dump( $contents=	ftp_nlist() )
	 */
	function ftp_get_dir($loc_dir, $rmt_dir, $isRecursive=TRUE, $useWhiteList=FALSE, $debug=FALSE) {
		$loc_dir = rtrim($loc_dir, '/');
		$rmt_dir = rtrim($rmt_dir, '/');
		
		$this->prv_ftp_get_dir($loc_dir, $rmt_dir, $isRecursive, $useWhiteList, $debug);
	}
	private function prv_ftp_get_dir($loc_dir, $rmt_dir, $isRecursive=TRUE, $useWhiteList=FALSE, $debug=FALSE) {
		
		if ($rmt_dir != ".") { // if not itself (`.` means current directory)
			if (ftp_chdir($this->conn_id, $rmt_dir) == false) {
				echo "Change Dir Failed: $rmt_dir\n";
				$this->close();
				exit(1);
			}
			if (!(is_dir($loc_dir))) {
				$rtnmkdir = @mkdir($loc_dir);
				echo '+/. '.$loc_dir."\n";
				if(!$rtnmkdir) {
					echo "\nUnknown local path specified...\n";
					$this->close();
					exit(1);
				}
			}
			chdir($loc_dir);
//			echo 'cd: '.$loc_dir."\n";	
		}

		$contents = @ftp_nlist($this->conn_id, ".");
		if ($debug) {
//			var_dump($contents);
		}
		foreach ($contents as $file) {

			if ($file == '.' || $file == '..')
				continue;

			// ignore chosen dirs
			if (!$useWhiteList == ($this->isIgnore($this->ignore, $rmt_dir."/".$file)) ) {
				continue;
			}
			
			if (@ftp_chdir($this->conn_id, $rmt_dir."/".$file)) {
				ftp_chdir($this->conn_id, "..");
				if($isRecursive) {
					$this->prv_ftp_get_dir($loc_dir."/".$file, $rmt_dir."/".$file, $isRecursive, $useWhiteList, $debug);
				}
			} else {
				
				echo '+<< '.$rmt_dir."/".$file;
				$rtn_get = ftp_nb_get(
						$this->conn_id,
						$loc_dir."/".$file, 
						$rmt_dir."/".$file,
						$this->ftp_trans_mode($file));
				while ($rtn_get == FTP_MOREDATA) {
					// do whatever you want
					echo '.';
					
					// continue downloading
					$rtn_get = ftp_nb_continue($this->conn_id);
				}
				if ($rtn_get != FTP_FINISHED) {
					echo "\nThere was an error downloading the file...\n";
					$this->close();
					exit(1);
				}
				echo "\n";
				if($debug) {
					$this->close(); 
					exit(0);
				}
			}
			
		}

		ftp_chdir($this->conn_id, "..");
		chdir("..");


	}
	
	
	/**
	 * Downloads a Directory recursively from the FTP server
	 * ATTENTION: ignore array is used in the opposite way
	 * NOTE: echos can be removed for use
	 * 
	 * Thanks to:
	 * mroerick@gmx.net
	 * http://php.net/manual/en/function.ftp-get.php#90910
	 * 
	 * @param string $rmt_dir remote directory
	 * @param boolean $isRecursive will remove found directories as well
	 * @param boolean $useWhiteList Ignore array will be used as a white list
	 */
	function ftp_rm_dir($rmt_dir, $isRecursive=false, $useWhiteList=true) {
		$rmt_dir = rtrim($rmt_dir, '/');
		prv_ftp_rm_dir($rmt_dir, $isRecursive, $useWhiteList);
	}
	private function prv_ftp_rm_dir($rmt_dir, $isRecursive=false, $useWhiteList=true) {

		if ($rmt_dir != ".") { // if not itself (`.` means current directory)
			if (ftp_chdir($this->conn_id, $rmt_dir) == false) {
				echo "Change Dir Failed: $rmt_dir\n";
				$this->close();
				exit(1);
			}
		}

		$contents = ftp_nlist($this->conn_id, ".");
		foreach ($contents as $file) {

			if ($file == '.' || $file == '..')
				continue;
			
			// ignore UNCHOSEN dirs and files
			// if 
			if (!$useWhiteList == ($this->isIgnore($this->ignore, $rmt_dir."/".$file) )) {
				continue;
			}

			if (ftp_chdir($this->conn_id, $rmt_dir."/".$file) ) {
				@ftp_chdir($this->conn_id, "..");
				if($isRecursive) {
					$this->prv_ftp_rm_dir($this->conn_id, $rmt_dir."/".$file);
					echo '-/< '.$rmt_dir.$file."\n";
				}
			} else {
				@ftp_delete(
						$this->conn_id, 
						$rmt_dir."/".$file,
						$this->ftp_trans_mode($file));
				echo '-<< '.$rmt_dir.$file."\n";
			}
			
		}

		@ftp_chdir($this->conn_id, "..");


	}
	
	/**
	 * finish if needed
	 * @see ftpsync::isRmtDir()
	 * @param type $rmt_dir remote dir
	 * @return TRUE
	 * @return exit(1) if false
	 */
	function doesRmtDirExist($rmt_dir) {
		if ($this->isRmtDir($rmt_dir) === FALSE) {
			echo "\n"
			. "ERROR 404\n"
			. "Unknown remote path specified...\n";
			$this->close();
			exit(1);
		} else {
			return TRUE;
		}
	}
	/**
	 * does remote directory exits?
	 * @param type $rmt_dir remote dir
	 * @return boolean
	 */
	private function isRmtDir($rmt_dir) {
		$rtn_chdir = ftp_chdir($this->conn_id, $rmt_dir);
		if($rtn_chdir === FALSE){
			return FALSE;
		} else {
			return TRUE;
		}
	}
	
	/**
	 * get transfer mode
	 * 
	 * Thanks to:
	 * Nate from ruggfamily.com
	 * http://php.net/manual/en/function.ftp-get.php#86516
	 * 
	 * @param string $file file name without path
	 * @return FTP_BINARY or FTP_ASCII
	 */
	function ftp_trans_mode($file){   
    $path_parts = pathinfo($file);
		
		if (!isset($path_parts['extension']))
			return FTP_BINARY;
		switch (strtolower($path_parts['extension'])) {
			case 'am':case 'asp':case 'bat':case 'c':case 'cfm':case 'cgi':case 'conf':
			case 'cpp':case 'css':case 'dhtml':case 'diz':case 'h':case 'hpp':case 'htm':
			case 'html':case 'in':case 'inc':case 'js':case 'm4':case 'mak':case 'nfs':
			case 'nsi':case 'pas':case 'patch':case 'php':case 'php3':case 'php4':case 'php5':
			case 'phtml':case 'pl':case 'po':case 'py':case 'qmail':case 'sh':case 'shtml':
			case 'sql':case 'tcl':case 'tpl':case 'txt':case 'vbs':case 'xml':case 'xrc':
				return FTP_ASCII;
		}
		if ($file === '.htaccess') {
			return FTP_ASCII;
		}
		return FTP_BINARY;
	}

	/** @see ftpsync::$ignore
	 * 
	 * @param array $arr 
	 * @param string $file
	 * @return boolean TRUE if $file found in $arr[]
	 */
	private function isIgnore($arr, $file) {
		 
		if(count($arr)===0) {
			return FALSE;
		}
		
		/**
		 * -1 dirskip not found yet
		 * 0+ placement
		 */
		$dirskip = -1;
		
		foreach ($arr as $value) {
			
			// for files and dir under relative path
			if ( !preg_match("/^\//", $value) ) {
				//echo 'relative'."\n";
				if (mb_ereg_match(".*".$value.".*", $file)) {
					return TRUE;
				}
				
			}
			// will look into the path far as $value goes
			else {
				//echo "absolute\n";
				$exregex = explode("/", ltrim($value,'/') );		// ignore regex path
				$expath	 = explode("/", ltrim($file, '/') );		// real file path
				
				$findskip=TRUE;
				$ii=0; // `removed skip // skip '/httpdocs/' in $exregex[0]
				$iireal= 0; // look in $expath
				if($dirskip!==-1) {
					$findskip = FALSE;
					$iireal = $dirskip;
				}
				$lenExregex = count($exregex);
				while($ii < $lenExregex) {
					if($findskip) {
						//find first match 
						if(preg_match("/".$exregex[$ii]."/", $expath[$iireal]) ){
							//found
							$findskip = FALSE;
							$dirskip = $iireal;
						} else { // still looking for skip dir
							$iireal++;
						}
					} else {
						if( isset($expath[$iireal]) || array_key_exists($iireal, $expath) ) {
							if(preg_match("/".$exregex[$ii]."/", $expath[$iireal]) ){
								$ii++;
								$iireal++;
							} else {
								return FALSE;
							}
						} else {
							//echo 'missing';
							$ii = $lenExregex;
						}
						
					}
					
				} // end while
				
				return TRUE;
			}
			
		}
		
		return FALSE;
	}
	
	/** Returns in array form ftp_rawlist()
	 * 
	 * @param type $rmt_dir
	 * @return array [0]files under $rmt [] details per file
	 * @return boolean FALSE if no $ftp_rawlist availible
	 */
	function ftp_list_detailed($rmt_dir = '.') {
		$rmt_dir = rtrim($rmt_dir, '/');
		
		if (is_array($ls_details = @ftp_rawlist($this->conn_id, $rmt_dir))) {
				$items = array();

				foreach ($ls_details as $i=>$ls_detail) {
						$chunks = preg_split("/\s+/", $ls_detail);
						list(
								$item['rights'], 
								$item['number'],
								$item['user'], 
								$item['group'], 
								$item['size'], 
								$item['month'], 
								$item['day'], 
								$item['time']
										) = $chunks;
						$item['type'] = $chunks[0]{0} === 'd' ? 'directory' : 'file';
						preg_match("/\S*$/", $ls_detail, $name);
						$item['name'] = $name[0];
						$items[$i] = $item;
				}

				return $items;
		} else {
			return FALSE;
		}
	}
	
//	private function ftp_check_conn($)
	
} // /ftpsync class







