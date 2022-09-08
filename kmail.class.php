<?php
	/*
	 *	KMail class	ver 1.3
	 */

	/*
	 * Copyright (C) <2011>  <Eper Kalman>
	 *
	 * This program is free software: you can redistribute it and/or modify
	 * it under the terms of the GNU General Public License as published by
	 * the Free Software Foundation, either version 3 of the License, or
	 * (at your option) any later version.
	 *
	 * This program is distributed in the hope that it will be useful,
	 * but WITHOUT ANY WARRANTY; without even the implied warranty of
	 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	 * GNU General Public License for more details.
	 *
	 * You should have received a copy of the GNU General Public License
	 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
	 */

	/*
	 *	KMail class for sending mail to smpt server directly with utf-8 and
	 *	multiple attachments support.
	 */
	 
	/*
	 *	KMail requirements & limitations:	
	 *	-	PHP 4
	 *	Mimetype Functions (from www.php.net)
	 *	Note to Windows Users: In order to use this module on a Windows environment, 
	 *	you must set the path to the bundled magic.mime file in your php.ini. 
	 *	Example 1. Setting the path to magic.mime mime_magic.magicfile = "$PHP_INSTALL_DIR\magic.mime"
	 *
	 *	KMail will send exactly what will get, if magic quotes is enabled, the string must be 
	 *  stripped (stripslashes, see in the sendmail demo file) before sending.
	 *	
	 */
 
	/*
	 *	KMail history:
	 *	-	Ver 1.3		-	added supressing error message if not finfo not found the file
						Changed attach() method to exept comma separated list
	 *					Added embedd_pics() method to send inline pictures
	 *					Added mesage_from_file() method to read message from file
	 *					Fixed if server not responding no error message bug
	 *	-	Ver 1.2.1	-	Added mime-types for matroska audio and video files
	 *					Fixed improperly disconnect bug in a loop bug
	 *					Fixed separatelly defined checkdnsrr() for windows loop bug
	 *	-	Ver 1.2		-	Added public functions cc(), bcc(), mail_list(), list_id(), unsubscribe();
	 *				-	Added possibility to define recepients in comma separated list, with limit that
	 *					after comma must to be whitespace because comma can be valid part of mail adresses
	 *				-	Removed 100 recipients limit
	 *				-	Improved list of mime-types for detection
	 *				-	Fixed subject line length check & break apart the subject
	 *				-	Fixed message length
	 *				-	Fixed date header
	 *	-	Ver 1.1.1	-	Fixed bug for showing attachment sign in mail clients
	 *	-	Ver 1.1		-	Changed mime-type detection if mime_content_type returns empty string
	 *				-	Added sending empty subject header if subject is not defined
	 *				-	Added deleting empty space from mail adresses if there is by mistake
	 *				-	Fixed notice if the attachment file not have extension
	 *				-	Fixed tls connection
	 *	-	Ver 1.0.1	-	Changed PHP_OS to safer php_uname('s')
	 * 	-	Ver 1.0 	-	First release
	 */

	/*
	 *	KMail public functions
	 *	new KMail()		-> 	initialize the class
	 *	host(str)		->	define smtp host
	 *	port(int)		->	define port to connect (default: 25);
	 *	secure(str)		->	define secure connection type (default: none);
	 *	limit($int)		->	smtp maximum recipients in one session, defaul 50
	 *
	 *	user(str)		->	define username to connect
	 *	password(str)		->	define password to connect
	 *
	 *	mail_list()		->	add header send mail To: Undisclosed recipients <senders@mail> and add list_id and list unsubscribe headers
	 *	list_id(str)		->	add list id header
	 *	unsubscribe()		->	sends unsubscribe header, usage: unsubscribe($mailaddress, optionally $url);
	 *
	 *	from(str)		->	define senders mail
	 *	sender_name(str)	->	define senders name to show
	 *	reply($mail)		->	define reply to (default: no-reply), if $mail is not defined, reply to sender
	 *	to()			->	define recipents (one or more in array or comma separated list)
	 *	cc()			->	define recipents of carbon copies (one or more in array or comma separated list)
	 *	bcc()			->	define recipents of blind carbon copies (one or more in array or comma separated list)
	 *	subject($str)		->	define mails subject
	 *	message($str)		->	define message
	 *	message_from_file($file)->	define the file contains the message (if message is defined from file the message(str) will be ignored)
	 *	txt()			->	send mail as text/plain (default: html)
	 *	attach()		->	define attachment (one or more in array or comma separated list) whith
	 *					limitation that file and directory names cannot contains comma
	 *	embedd_pics()		->	define inline pictures for html files (one or more in array or comma separated list) 
	 *					the filename in html img src="" part must appear in embedd_pics list or will be ignored (send without)
	 *					LIMITATIONS:
	 *					1.	file and directory names cannot contains comma
	 *					2.	cannot use same filename with different path more times (will send only first picture for all)
	 *
	 *	send()			->	mail send (returns TRUE if mail is sended for one recipient)
	 *	report()		->	report warnings and errors
	 *	debug()			->	show sended commands and responses for debug
	 *
	 */
	 
	/*
	 *	Special thanks
	 *	-	for the comments and feedbacks to everyone who gived
	 */

	if (! class_exists('KMail', false))	{
		
		// change in ver 1.0.1 PHP_OS->php_uname('s')
		if ((! function_exists('checkdnsrr')) && 
			(strpos(strtolower(php_uname('s')), 'win') !== FALSE))	{
			
			// change in ver1.2.1 added var found to fix the loop bug
			function checkdnsrr($host, $type)	{
				$found=FALSE;
				$dns_recs=array('ANY', 'SOA', 'A', 'AAAA', 'A6', 'NS', 'MX', 'CNAME', 'PTR', 'TXT', 'SPF');
				if (! in_array(strtoupper($type), $dns_recs))	{
					trigger_error('Unkown DNS record type defined.', E_WARNING);
					return $found;
				}
				@exec('nslookup -type='.$type.' '.escapeshellcmd($host), $result);
				// change in ver1.2.1 return TRUE to break;
				foreach($result as $line)	{
					if (preg_match('/^'.$host.'/',$line)) {
						$found=TRUE;
						break;
					}
				}
				return $found;
			}
		}
		
		class KMail	{
		
			// smtp parameters
			var $host		=	'';
			var $port		=	25;
			var $secure		=	'';
			var $username		=	'';
			var $password		=	'';
			var $session_limit	=	50;
			
			// mail
			var $from		=	'postmaster@kmail.com';
			var $to			=	array();
			var $cc			=	array();
			var $bcc		=	array();
			var $to_sum		=	array();
			var $primary_to		=	'';
			var $subject		=	'';
			var $message		=	'';
			var $message_file	=	'';
			var $text		=	FALSE;
			var $files		=	array();
			var $sender		=	'';
			var $noreply		=	TRUE;
			var $reply_to		=	FALSE;
			var $mail_message	=	'';
			var $mail_list_mode	=	FALSE;
			var $unsub_mail		=	FALSE;
			var $unsub_url		=	FALSE;	
			var $list_id_txt	=	FALSE;
			var $embedd		=	FALSE;	// added in ver.1.3 to embedd pictures
			var $inlines		=	FALSE;
			
			// debug
			var $debug		=	FALSE;
			
			// report & error & auth
			var $report_str		=	'';
			var $server_auth	=	FALSE;
			var $error		=	FALSE;
			var $time_limit		=	0;
			var $rcpdelay		=	0;
			var $condelay		=	1;
			
 			// constants
			var $EOL		=	"\r\n";
			var $WSP		=	' ';
		
			public function host($host)	{
				$this->host=$host;
			}
			
			public function	port($port)	{
				if ((integer)$port<=0)	{
					$this->rep("Invalid port number: $port.");
					$this->error=TRUE;
					return;
				}
				$this->port=(integer)$port;
			}

			public function secure($type)	{

				// changed in ver1.1 fixed tls connection
				if (strpos(strtolower($type), 'ssl') !== FALSE)	{
					$this->secure='ssl://';
				}	elseif (strpos(strtolower($type), 'tls') !== FALSE)	{
					$this->secure='tls://';
				}	else	{
					$this->rep("Invalid secure connection type defined: $type.");
				}
			}
		
			public function	limit($int)	{
				if (! $int)	return;
				$this->session_limit=(integer)$int;
			}
			
			public function debug()	{
				$this->debug=TRUE;
			}
			
			public function user($user)	{
				$this->username=$user;
			}
			
			public function password($pass)	{
				$this->password=$pass;
			}
			
			public function from($mail)	{

				// added in ver1.1 delete empty space from mail addresses if there is
				$mail=@preg_replace('/\s/i', '', $mail);
				// basic check for validity
				// changed in ver1.2 to separate function
				if (! $this->is_valid_adress($mail, FALSE))	{
					$this->rep("Invalid senders mail defined: $mail. Switched to default $this->from.");
					return;
				}
				$this->from=$mail;
			}
			
			public function reply($mail=FALSE)	{
				$this->noreply=FALSE;
				if ($mail)	{
					$mail=preg_replace('/\r|\n|\a|\e|f\v/is', '', $mail);
					if ($this->is_valid_adress($mail, TRUE)) $this->reply_to=$mail;	
				}
			}
			
			public function to($mails)	{

				if (! isset($mails))	return;
				if (! is_array($mails))	{
					// added in ver1.2 check for comma (with space after) separated list
					if (! strlen($mails)) return;
					$mails=$this->comma_sep_explode($mails);
				}
				// changed in ver 1.2 because of bcc and cc methods
				$this->primary_to=$mails[0];
				$this->to=$mails;
			}
			
			// added in ver1.2 method blind carbon copy 
			public function bcc($mails)	{
				if (! isset($mails))	return;
				if (! is_array($mails))	{
					if (! strlen($mails)) return;
					$mails=$this->comma_sep_explode($mails);
				}
				$this->bcc=$mails;
			}
			
			// added in ver1.2 method carbon copy
			public function cc($mails)	{
				if (! isset($mails))	return;
				if (! is_array($mails))	{
					if (! strlen($mails)) return;
					$mails=$this->comma_sep_explode($mails);
				}
				$this->cc=$mails;
			}
			
			// added in ver1.2 to show only To: Undisclosed recipients
			public function mail_list()	{
				$this->mail_list_mode=TRUE;
			}
			
			// added in ver1.2 list_id header
			public function list_id($text)	{
				if ((! isset($text)) or (! strlen($text)))	return;
				$this->list_id_txt=$text;
			}
			
			// added in ver1.2 send list-unsubscribe header
			public function	unsubscribe($mail=FALSE, $url=FALSE)	{
				if (($mail) && (strlen($mail))) $this->unsub_mail=$mail;
				if (($url) && (strlen($url))) $this->unsub_url=$url;
			}
			
			public function sender_name($name)	{
				if (strlen($name)) $this->sender=$this->utf8($name);
			}
			
			public function txt()	{
				$this->text=TRUE;
			}
			
			public function subject($subject)	{
				if (! strlen($subject)) return;
				// strip invalid chars
				$this->subject=preg_replace('/\r|\n|\a|\e|f\v/is', '', $this->utf8($subject));
			}
			
			public function message($message)	{
				$this->message=$this->utf8($message);
			}

			// added in ver1.3 to read message from file
			public function message_from_file($file)	{
				$this->message_file=$file;
			}


			// changed in ver 1.3 to exept comma separated list
			// now the filenames cannot contains commas 
			public function attach($files)	{
				if (! isset($files))	return;
				if (! is_array($files))	{
					if (! strlen($files)) return;
					$files=$this->comma_sep_explode($files);
				}
				
				$this->files=$files;
			}

			// added in ver1.3 for embedding pictures into html
			// the filenames cannot contains commas
			public function embedd_pics($pictures)	{
				if (! isset($pictures))	return;
				if (! is_array($pictures))	{
					if (! strlen($pictures)) return;
					$pictures=$this->comma_sep_explode($pictures);
				}
				$this->embedd=$pictures;
			}
			
			public function report()	{
				if (! $this->report_str) $this->report_str="\n<br>Mail succesfuly has been send without any warning or error.";
				return $this->report_str;
			}
			
			// function added in ver1.2 explode and sanitize mail adresses
			private function comma_sep_explode($string)	{

				// strip spaces will do later
				if (function_exists('mb_split'))	{
					$array=mb_split(', ', $string);
				}	else	{
					$array=explode(', ', $string);
				}
				foreach ($array as $key => $val)	{
					$array[$key]=@preg_replace('/\r|\n|\t|\a|\e|f\v|\s/i', '', $val);
				}
				return $array;
			}
			
			// function added in ver1.2 implode mail adresses into comma separated list
			private function comma_sep_implode($mails)	{

				if ((! isset($mails)) or (! is_array($mails)))	return '';
				$comma_sep_list='';
				$count=count($mails);
				$inc=1;
				foreach ($mails as $mail)	{
					$comma_sep_list.=$mail;
					if ($inc!=$count)	{
						$comma_sep_list.=', ';
						$inc++;
					}
				}
				// wrap the list to not be to long, shorter because of firts line
				return wordwrap($comma_sep_list, 68, $this->EOL.$this->WSP);
			}
			
			private function rep($line)	{

				$this->report_str.=$line."<br>\n";
			}
			
			private function utf8($string)	{

				if (! function_exists('mb_detect_encoding'))	return $string;
				$in_charset=mb_detect_encoding($string);
				if (strtolower($in_charset)=='utf-8') return $string;
				return mb_convert_encoding($string, 'utf-8', $in_charset); 
			}
			
			private function get_mime_type($file)	{

				// changed in ver1.1 if mime_content_type returns empty string
				// changed in ver1.3 supress error message if not found
				if (function_exists('finfo_open')) {
					if ($finfo = @finfo_open(FILEINFO_MIME))	{
						$mime_type = finfo_file($finfo, $file);
						finfo_close($finfo);
					}
				}	
				if ((! isset($mime_type)) && 
				    (function_exists('mime_content_type')))	{
					$mime_type = @mime_content_type($file);
				}
				if (! isset($mime_type) or (! $mime_type))	{
					// changed list in version 1.2 to improve mime-type detection
					// it will be easyest to handle the source if is not in separated file
					// if you have properly set the mime-type functions before include it
					$mime_types	=	array(	'323'		=>	'text/h323',
									'*'		=>	'application/octet-stream',
									'acx'		=>	'application/internet-property-stream',
									'ai'		=>	'application/postscript',
									'aif'		=>	'audio/x-aiff',
									'aifc'		=>	'audio/x-aiff',
									'aiff'		=>	'audio/x-aiff',
									'asf'		=>	'video/x-ms-asf',
									'asr'		=>	'video/x-ms-asf',
									'asx'		=>	'video/x-ms-asf',
									'au'		=>	'audio/basic',
									'avi'		=>	'video/x-msvideo',
									'axs'		=>	'application/olescript',
									'bas'		=>	'text/plain',
									'bcpio'		=>	'application/x-bcpio',
									'bin'		=>	'application/octet-stream',
									'bmp'		=>	'image/bmp',
									'c'		=>	'text/plain',
									'cat'		=>	'application/vnd.ms-pkiseccat',
									'cdf'		=>	'application/x-cdf',
									'cdf'		=>	'application/x-netcdf',
									'cer'		=>	'application/x-x509-ca-cert',
									'class'		=>	'application/octet-stream',
									'clp'		=>	'application/x-msclip',
									'cmx'		=>	'image/x-cmx',
									'cod'		=>	'image/cis-cod',
									'cpio'		=>	'application/x-cpio',
									'crd'		=>	'application/x-mscardfile',
									'crl'		=>	'application/pkix-crl',
									'crt'		=>	'application/x-x509-ca-cert',
									'csh'		=>	'application/x-csh',
									'css'		=>	'text/css',
									'dcr'		=>	'application/x-director',
									'der'		=>	'application/x-x509-ca-cert',
									'dir'		=>	'application/x-director',
									'dll'		=>	'application/x-msdownload',
									'dms'		=>	'application/octet-stream',
									'doc'		=>	'application/msword',
									'dot'		=>	'application/msword',
									'dvi'		=>	'application/x-dvi',
									'dxr'		=>	'application/x-director',
									'eps'		=>	'application/postscript',
									'etx'		=>	'text/x-setext',
									'evy'		=>	'application/envoy',
									'exe'		=>	'application/octet-stream',
									'fif'		=>	'application/fractals',
									'flr'		=>	'x-world/x-vrml',
									'gif'		=>	'image/gif',
									'gtar'		=>	'application/x-gtar',
									'gz'		=>	'application/x-gzip',
									'h'		=>	'text/plain',
									'hdf'		=>	'application/x-hdf',
									'hlp'		=>	'application/winhlp',
									'hqx'		=>	'application/mac-binhex40',
									'hta'		=>	'application/hta',
									'htc'		=>	'text/x-component',
									'htm'		=>	'text/html',
									'html'		=>	'text/html',
									'htt'		=>	'text/webviewhtml',
									'ico'		=>	'image/x-icon',
									'ief'		=>	'image/ief',
									'iii'		=>	'application/x-iphone',
									'ins'		=>	'application/x-internet-signup',
									'isp'		=>	'application/x-internet-signup',
									'jfif'		=>	'image/pipeg',
									'jpe'		=>	'image/jpeg',
									'jpeg'		=>	'image/jpeg',
									'jpg'		=>	'image/jpeg',
									'js'		=>	'application/x-javascript',
									'latex'		=>	'application/x-latex',
									'lha'		=>	'application/octet-stream',
									'lsf'		=>	'video/x-la-asf',
									'lsx'		=>	'video/x-la-asf',
									'lzh'		=>	'application/octet-stream',
									'm13'		=>	'application/x-msmediaview',
									'm14'		=>	'application/x-msmediaview',
									'm3u'		=>	'audio/x-mpegurl',
									'man'		=>	'application/x-troff-man',
									'mdb'		=>	'application/x-msaccess',
									'me'		=>	'application/x-troff-me',
									'mht'		=>	'message/rfc822',
									'mhtml'		=>	'message/rfc822',
									'mka' 		=>	'audio/x-matroska',
									'mkv'		=>	'video/x-matroska',
									'mk3d' 		=> 	'video/x-matroska-3d',
									'mid'		=>	'audio/mid',
									'mny'		=>	'application/x-msmoney',
									'mov'		=>	'video/quicktime',
									'movie'		=>	'video/x-sgi-movie',
									'mp2'		=>	'video/mpeg',
									'mp3'		=>	'audio/mpeg',
									'mpa'		=>	'video/mpeg',
									'mpe'		=>	'video/mpeg',
									'mpeg'		=>	'video/mpeg',
									'mpg'		=>	'video/mpeg',
									'mpp'		=>	'application/vnd.ms-project',
									'mpv2'		=>	'video/mpeg',
									'ms'		=>	'application/x-troff-ms',
									'msg'		=>	'application/vnd.ms-outlook',
									'mvb'		=>	'application/x-msmediaview',
									'nc'		=>	'application/x-netcdf',
									'nws'		=>	'message/rfc822',
									'oda'		=>	'application/oda',
									'p10'		=>	'application/pkcs10',
									'p12'		=>	'application/x-pkcs12',
									'p7b'		=>	'application/x-pkcs7-certificates',
									'p7c'		=>	'application/x-pkcs7-mime',
									'p7m'		=>	'application/x-pkcs7-mime',
									'p7r'		=>	'application/x-pkcs7-certreqresp',
									'p7s'		=>	'application/x-pkcs7-signature',
									'pbm'		=>	'image/x-portable-bitmap',
									'pdf'		=>	'application/pdf',
									'pfx'		=>	'application/x-pkcs12',
									'pgm'		=>	'image/x-portable-graymap',
									'pko'		=>	'application/ynd.ms-pkipko',
									'pma'		=>	'application/x-perfmon',
									'pmc'		=>	'application/x-perfmon',
									'pml'		=>	'application/x-perfmon',
									'pmr'		=>	'application/x-perfmon',
									'pmw'		=>	'application/x-perfmon',
									'pnm'		=>	'image/x-portable-anymap',
									'pot'		=>	'application/vnd.ms-powerpoint',
									'ppm'		=>	'image/x-portable-pixmap',
									'pps'		=>	'application/vnd.ms-powerpoint',
									'ppt'		=>	'application/vnd.ms-powerpoint',
									'prf'		=>	'application/pics-rules',
									'ps'		=>	'application/postscript',
									'pub'		=>	'application/x-mspublisher',
									'qt'		=>	'video/quicktime',
									'ra'		=>	'audio/x-pn-realaudio',
									'ram'		=>	'audio/x-pn-realaudio',
									'ras'		=>	'image/x-cmu-raster',
									'rgb'		=>	'image/x-rgb',
									'rmi'		=>	'audio/mid',
									'roff'		=>	'application/x-troff',
									'rtf'		=>	'application/rtf',
									'rtx'		=>	'text/richtext',
									'scd'		=>	'application/x-msschedule',
									'sct'		=>	'text/scriptlet',
									'setpay'	=>	'application/set-payment-initiation',
									'setreg'	=>	'application/set-registration-initiation',
									'sh'		=>	'application/x-sh',
									'shar'		=>	'application/x-shar',
									'sit'		=>	'application/x-stuffit',
									'snd'		=>	'audio/basic',
									'spc'		=>	'application/x-pkcs7-certificates',
									'spl'		=>	'application/futuresplash',
									'src'		=>	'application/x-wais-source',
									'sst'		=>	'application/vnd.ms-pkicertstore',
									'stl'		=>	'application/vnd.ms-pkistl',
									'stm'		=>	'text/html',
									'sv4cpio'	=>	'application/x-sv4cpio',
									'sv4crc'	=>	'application/x-sv4crc',
									'svg'		=>	'image/svg+xml',
									'swf'		=>	'application/x-shockwave-flash',
									't'		=>	'application/x-troff',
									'tar'		=>	'application/x-tar',
									'tcl'		=>	'application/x-tcl',
									'tex'		=>	'application/x-tex',
									'texi'		=>	'application/x-texinfo',
									'texinfo'	=>	'application/x-texinfo',
									'tgz'		=>	'application/x-compressed',
									'tif'		=>	'image/tiff',
									'tiff'		=>	'image/tiff',
									'tr'		=>	'application/x-troff',
									'trm'		=>	'application/x-msterminal',
									'tsv'		=>	'text/tab-separated-values',
									'txt'		=>	'text/plain',
									'uls'		=>	'text/iuls',
									'ustar'		=>	'application/x-ustar',
									'vcf'		=>	'text/x-vcard',
									'vrml'		=>	'x-world/x-vrml',
									'wav'		=>	'audio/x-wav',
									'wcm'		=>	'application/vnd.ms-works',
									'wdb'		=>	'application/vnd.ms-works',
									'wks'		=>	'application/vnd.ms-works',
									'wmf'		=>	'application/x-msmetafile',
									'wps'		=>	'application/vnd.ms-works',
									'wri'		=>	'application/x-mswrite',
									'wrl'		=>	'x-world/x-vrml',
									'wrz'		=>	'x-world/x-vrml',
									'xaf'		=>	'x-world/x-vrml',
									'xbm'		=>	'image/x-xbitmap',
									'xla'		=>	'application/vnd.ms-excel',
									'xlc'		=>	'application/vnd.ms-excel',
									'xlm'		=>	'application/vnd.ms-excel',
									'xls'		=>	'application/vnd.ms-excel',
									'xlt'		=>	'application/vnd.ms-excel',
									'xlw'		=>	'application/vnd.ms-excel',
									'xof'		=>	'x-world/x-vrml',
									'xpm'		=>	'image/x-xpixmap',
									'xwd'		=>	'image/x-xwindowdump',
									'z'		=>	'application/x-compress',
									'zip'		=>	'application/zip');

					@preg_match("/\.([^\.]+)$/s", $file, $ext);
					// added in ver 1.1 to fix notice if file not have extension
					if (! isset($ext[1]))	$ext[1]='';
					if (array_key_exists(strtolower($ext[1]), $mime_types))	{
						$mime_type=$mime_types[$ext[1]];
					}	else	{
						$mime_type='unknown/'.$ext[1];
					}
				}

				return @preg_replace('/\;(.*?)$/','', $mime_type);
			}
			
			// added in ver1.2 separate function for syntax check
			private function is_valid_adress($mail, $report=TRUE)	{

				if (function_exists('filter_var'))	{
					$valid=filter_var($mail, FILTER_VALIDATE_EMAIL);
				}	else	{
					$valid=@preg_match("/^[^@]*@[^@]*\.[^@]*$/", $mail);
				}
				if ((! $valid) && ($report))	$this->rep("Mail address $mail syntax is incorrect by KMail.");

				return $valid;
			}
			
			private function check_recipients($mails)	{

				// changed in ver1.2 $this->to -> $mails to use with cc and bcc
				$checked=array();
				foreach ($mails as $mail)	{
					// added in ver1.2 check adress syntax for any case
					if ($this->is_valid_adress($mail))	{
						list($adress, $domain)=explode('@', $mail);
						if (! checkdnsrr($domain, 'A'))	{
							if (! checkdnsrr($domain, 'ANY'))	{
								// added in ver1.2 report
								$this->rep("Mail address $mail have invalid domain by KMail.");
 								continue;
							}
						}	
						if (! in_array($mail, $checked))	array_push($checked, $mail);
					}
				}
				if (count($checked))	{
					sort($checked);
					return $checked;
				}
				return FALSE;
			}
			
			// added in ver1.2 base64 encode and split the subject
			private function b64_encode_subject($subject)	{

				if (! strlen($subject))	return '';
				$subject_c	=	'';
				$part	 	=	'';
				$chars 		= 	preg_split('//', $subject, -1);
				$len		=	count($chars);
				$str_len	=	1;
				$allowed	=	21;	// first line
				$step_over	=	FALSE;
				
				for ($i=0; $i<$len; $i++)	{
					$part.=$chars[$i];
					if (($str_len > $allowed) or 
						($i==$len-1))	{
						// wait for the first space
						if (($i==($len-1)) or 
							($chars[$i]===' ') or 
							($str_len>50))	{	// hard way break the word apart
								$subject_c.="=?UTF-8?B?".base64_encode($part)."?=".$this->EOL;
								if ($i!=($len-1)) $subject_c.=$this->WSP;
								$part='';
								$str_len=1;
								$allowed=37;	// after first line
								$step_over=TRUE;
						}
					}
					if (! $step_over) {$str_len++;} else {$step_over=FALSE;}
				}
				return $subject_c;
			}

			// added in ver1.3 read html from file
			private function read_html()	{

				$this->message=@file_get_contents($this->message_file);
				if ($this->message===FALSE)	{
					$this->rep("Message file $this->message_file not found or is not readable.");
					return FALSE;
				}
				$this->message=$this->utf8($this->message);

				return TRUE;
			}

			// added in ver1.3 to get images from html messages
			// and replace source in img tags with cid-s
			private function parse_images()	{
				
				$inline_list=array();
				$cid_inc=1;

				// first delete </img> tags if there id
				$this->message=preg_replace('/\<\s*\/\s*img.\s*\/\>/is', '', $this->message);
				// the get the list if img tags
				$count=preg_match_all('/\<\s*img\s.*?\>/is', $this->message, $img_tags);
				
				if ((! $count) && ($this->embedd))	{
					$this->rep('Images defined for embedding not exists in message.');
					return FALSE;
				}

				// LIMITATION: 	cannot use different pictures with same filename and different 
				//  		path the routine will use only the first picture for all

				$now=time();

				for ($inc=0; $inc<$count; $inc++)	{
					
					$tag=$img_tags[0][$inc];
					preg_match('/src\s*=\s*\"(.*?)\"/is', $tag, $src);

					$founded=FALSE;
					foreach ($this->embedd as $inline)	{

						// delete from message if not found
						if (! is_readable($inline)) continue;

						// get the filename
						$parts=mb_split('/', $inline);
						$pic_count=count($parts);
						$pic=$parts[$pic_count-1];
						if ((isset($pic)) && (mb_strpos($src[0], $pic)!==FALSE))	{

							// create new src with cid
							$cid='cid:img'.$cid_inc.$now;

							// replace in html						
							$this->message=preg_replace('/'.preg_quote($src[1], '/').'/s', $cid, $this->message);

							// collect into array $cid=>$embedd
							$inline_list=array_merge($inline_list, array($cid=>$inline));

							$cid_inc++;
							$founded=TRUE;
						}
					}
					if (! $founded) {
						$this->rep('Image file defined in '.htmlspecialchars($tag).' tag not found.');
						// delete not founded picture from message
						$this->message=preg_replace('/'.preg_quote($tag, '/').'/s', '<br>', $this->message);
					}
				}

				if (count($inline_list))	{return $inline_list;}	else	{return FALSE;}
			}
			
			private function unsubscribe_list()	{
			
				$unsubscribe	=	'List-Unsubscribe: ';
				if ($this->unsub_mail)	$unsubscribe.='<mailto:'.preg_replace('/\r|\n|\t|\a|\e|f\v|\s/i', '', $this->unsub_mail).'>';
				if (($this->unsub_url) && ($this->unsub_mail))	$unsubscribe.=','.$this->EOL.$this->WSP;
				if ($this->unsub_url) $unsubscribe.='<'.preg_replace('/\r|\n|\t|\a|\e|f\v|\s/i', '', $this->unsub_url).'>';
				return	$unsubscribe;
			}


			// changed in ver1.3 compose mail in separate functions;
			private function header()	{
				
				$header='';
				$EOL=$this->EOL;

				// fixed and added in ver1.2 to determine only once
				if (! $KMAIL_host=$_SERVER['HTTP_HOST'])	{
					if (! $KMAIL_host=$_SERVER['SERVER_NAME']) $KMAIL_host='localhost';
				}
				// basic header
				if (! $this->noreply)	{
					// reply-to
					if (! $this->sender)	$this->sender=preg_replace('/@(.*?)$/is','', $this->from);
					if ($this->reply_to)	{$replyto=$this->reply_to;} else {$replyto=$this->from;}
					$header		= 	 "From: =?UTF-8?B?".base64_encode($this->sender)."?= <$this->from>".$EOL
								."Reply-To: =?UTF-8?B?".base64_encode($this->sender)."?= <$replyto>".$EOL
								."Return-Path: =?UTF-8?B?".base64_encode($this->sender)." <$this->from>".$EOL;
				}	else	{
					// noreply
					if ($this->sender)	{
						$send_name='=?UTF-8?B?'.base64_encode($this->sender).'?=';
					}	else	{
						$send_name=$KMAIL_host;
					}
					$noreply	=	$send_name.' <no-reply@'.str_replace('www.','', strtolower($KMAIL_host)).'>';
					$header		= 	 "From:  $noreply".$EOL
								."Return-Path: $noreply".$EOL;
				}
				// changed in ver1.2 added carbon copy and blind carbon copy
				if ((count($this->to_sum) > 1) && (! $this->mail_list_mode))	{


					if (! isset($this->cc)) $this->cc=FALSE;
					if (! isset($this->bcc)) $this->bcc=FALSE;
					
					$header		.=	"To: ".($this->comma_sep_implode($this->to)).$EOL;
					
					if ($this->bcc)	{
						$header	.=	"Bcc: ".$EOL;
					}
					if ($this->cc)	{
						// if cc is send check for address in to not appear twice
						foreach (array_keys($this->cc) as $key)	{
							if (in_array($this->cc[$key], $this->to))	unset($this->cc[$key]);
						}
						$header	.=	"Cc: ".($this->comma_sep_implode($this->cc)).$EOL;
					}
					
				}	else	{
					if ($this->mail_list_mode)	{
						$header	.=	"To: Undisclosed Recipients <$this->from>".$EOL;
					}	else	{
						$header	.=	"To: <$this->primary_to>".$EOL;
					}
				}
				
				if ($this->subject)	{
					// added in ver1.2 check for subject line length
					$subject_c	=	"Subject: =?UTF-8?B?".base64_encode($this->subject).'?='.$EOL;
					if (strlen($subject_c) > 74)	{
						//	in most cases this will step over - wrap the subject
						$subject_c = 'Subject: '.$this->b64_encode_subject($this->subject);
					}
					$header 	.=	 $subject_c;
				}	else	{
					// added in ver1.1 send empty subject header if subject is not defined
					$header		.=	 'Subject: '.$EOL;
				}
				
				/*
				$header 		.=	 "Date: ".@date("D, j M m Y H:i:s O").$EOL
								."Message-ID: <".time()." KMail@".str_replace('www.','', strtolower($KMAIL_host)).">".$EOL;
				*/
				$header 		.=	 "Date: ".@date("j M Y H:i:s O").$EOL
								."Message-ID: <".time()." KMail@".str_replace('www.','', strtolower($KMAIL_host)).">".$EOL;

								
				// added in ver1.2 optionally List_id & Unsubscribe header
				if ($this->mail_list_mode)	{
					$header		.=	'Precedence: bulk'.$EOL;
					if ($this->list_id_txt)	{
						$header	.=	'List-Id: =?UTF-8?B?'.base64_encode($this->list_id_txt).'?='.$EOL.$this->WSP.'<'.$KMAIL_host.'>'.$EOL;
					}
					if (($this->unsub_url) or ($this->unsub_mail))	{
						$header	.=	$this->unsubscribe_list().$EOL;
					}
				}
				
				$header			.=	 'X-Mailer: KMail PHP v'.phpversion().$EOL
								.'MIME-Version: 1.0'.$EOL;

				$this->header=$header;
			}

			private function html2txt($html)	{

				// replace the images with alt or filename
				$this->message=preg_replace('/\<\s*\/\s*img.\s*\>/is', '', $html);
				$count=preg_match_all('/\<\s*img\s.*?\>/is', $html, $img_tags);

				for ($inc=0; $inc<$count; $inc++)	{
					
					$part=$img_tags[0][$inc];
					preg_match('/alt\s*=\s*\"(.*?)\"/is', $part, $alt);
					if ((isset($alt[1])) && (strlen($alt[1])))	{
						$replacement=$alt[1];
					}	else	{
						preg_match('/src\s*=\s*\"(.*?)\"/is', $part, $src);
						// get the filename
						$parts=mb_split('/', $src[1]);
						$src_count=count($parts);
						$replacement=$parts[$src_count-1];
					}
					$html=preg_replace('/'.preg_quote($part, '/').'/s', $replacement, $html);
				}
				
				$html=strip_tags($html, '<br>');  //for strip_tags "<br/>" & "<br>" is the same
				$html=preg_replace('/\r|\n|\t|\a|\e|\f|\v|\s/is', '', $html); // strip unvanted
				$html=preg_replace('/\<\s*br\s*\/*\>/is', $this->EOL, $html); // change to eol

				return $html;
			}

			// changed in ver1.3 compose mail in separate functions;
			private function plain_text()	{

				$EOL=$this->EOL;

				$this->body		= "Content-Type: text/plain; charset=\"utf-8\"".$EOL
						  	  ."Content-Transfer-Encoding: base64".$EOL.$EOL
							// changed in ver1.2 chunk the message to			  
							  .(chunk_split(base64_encode($this->html2txt($this->message)))).$EOL.$EOL;
			}

			private function multipart_alternative()	{

				$EOL=$this->EOL;
				$WSP=$this->WSP;

				$ALTSEP=md5(time()+10);

				$this->body	 =  'Content-Type: multipart/alternative;'.$EOL.$WSP."boundary=\"$ALTSEP\"".$EOL.$EOL
						   ."--".$ALTSEP.$EOL
						   // text part
						   ."Content-Type: text/plain; charset=\"utf-8\"".$EOL
						   ."Content-Transfer-Encoding: base64".$EOL.$EOL
						   // changed in ver1.2 chunk the message to
						   .(chunk_split(base64_encode($this->html2txt($this->message)))).$EOL.$EOL  // strip html tags alt stands
						   ."--".$ALTSEP.$EOL
						   // html & relative part
						   ."Content-Type: text/html; charset=\"utf-8\"".$EOL
						   ."Content-Transfer-Encoding: base64".$EOL.$EOL
						   // changed in ver1.2 chunk the message to
						   .(chunk_split(base64_encode($this->message))).$EOL.$EOL
						   ."--".$ALTSEP."--".$EOL.$EOL; //END
			}

			// changed in ver1.3 compose mail in separate functions;
			private function multipart_related()	{

				$EOL=$this->EOL;
				$WSP=$this->WSP;

				$RELSEP=md5(time()+100);

				// added in ver1.3 inline pictures (do not send if the message is not html)

				if ($this->embedd)	{

					foreach ($this->inlines as $cid=>$inline)	{
						if (! is_readable($inline))	{
							$this->rep("File not found or file is not readable: $inline .");
							unset($this->inlines[$cid]);
						}
					}
					if (! count($this->inlines)) $inlines=FALSE;
				}

				if (! $this->inlines)	return;

				$this->body ='Content-Type: multipart/related;'.$EOL.$WSP.
					     "boundary=\"$RELSEP\";".$EOL.$EOL
					    ."--".$RELSEP.$EOL
					    .$this->body;

				// add inline pictures
				foreach ($this->inlines as $cid=>$inline)	{

					$filename=basename($inline);
					$mimetype=$this->get_mime_type($inline);
					$contentID=str_replace('cid:','',$cid);

					if (! $inline_data=@file_get_contents($inline))	continue;
				
					$this->body .=	 "--".$RELSEP.$EOL
							."Content-Type: $mimetype;".$EOL.$WSP."name=\"$filename\"".$EOL
							."Content-Transfer-Encoding: base64".$EOL
							."Content-ID: <$contentID>".$EOL
							."X-Attachment-Id: $contentID".$EOL
							."Content-Disposition: INLINE".$EOL.$EOL

							//."Content-Disposition: INLINE;".$EOL.$WSP."filename=\"$filename\"".$EOL.$EOL
							.(chunk_split(base64_encode($inline_data))).$EOL.$EOL;
				}
			
				$this->body  .=  "--".$RELSEP."--".$EOL.$EOL;
			}

			// changed in ver1.3 compose mail in separate functions;
			private function multipart_mixed()	{

				$EOL=$this->EOL;
				$WSP=$this->WSP;

				$MIXSEP=md5(time()+200);

				// changed in ver1.3 less code
				// attachment check if not found step over
				if (count($this->files))	{
					foreach (array_keys($this->files) as $key)	{
						if (! is_readable($this->files[$key]))	{
							$this->rep('File not found or file is not readable: '.$this->files[$key].' .');
							unset($this->files[$key]);
						}
					}
				}

				if (! $f_sum=count($this->files)) return;

				// additional header
				// fixed in ver 1.1.1. multipart/related -> multipart/mixed, thanks to Stefan for notice
				// to show attachment sign in email client
				$this->body	  = 	'Content-Type: multipart/mixed;'.$EOL.$WSP."boundary=\"$MIXSEP\"".$EOL.$EOL
							."--".$MIXSEP.$EOL
							.$this->body;

				foreach ($this->files as $file)		{

					$filename=basename($file);
					$mimetype=$this->get_mime_type($file);

					if (! $file_content=@file_get_contents($file)) continue;

					$this->body.=  "--".$MIXSEP.$EOL
						      ."Content-Type: $mimetype;".$EOL.$WSP."name=\"$filename\"".$EOL
						      ."Content-Transfer-Encoding: base64".$EOL
						      ."Content-Disposition: attachment;".$EOL.$WSP."filename=\"$filename\"".$EOL.$EOL
						      .(chunk_split(base64_encode($file_content))).$EOL.$EOL;
				}
					
				$this->body	.=	"--".$MIXSEP."--".$EOL.$EOL; // END
			}
			
			// compose mail;
			private function mail_create()	{

				// create headers				
				$this->header();
				
				// content-type
				if (($this->text) or
				    (! count($this->files) && (! $this->embedd) && (! strlen($this->message))))	{
					// send as simple text & empty message
					$this->plain_text();
				}	else	{
					// first see the inlines
					$this->inlines=$this->parse_images();
					// compose alternetive
					$this->multipart_alternative();
					// compose related => add inline pictures
					if ($this->inlines) $this->multipart_related();
					// compose mixes => add attachments
					$this->multipart_mixed();
				}

				// changed in ver1.2 return $header.$body; => $this->mail_message=$header.$body; to avoid 100 recipient limit
				$this->mail_message=$this->header.$this->body;
				unset($this->header, $this->body);
			}
			
			private function command_send($command, $expected_code)	{

				if ((! $command) or (! $expected_code) or (! $this->smtp))	return FALSE;
				if (! @fputs($this->smtp, $command)) return FALSE;
				$response=$this->get_response();
				$response_ok=FALSE;
				// debug
				// added in ver1.2 htmlspecialchars to show the whole command
				if ($this->debug) echo "<br>Command:<br>".nl2br(htmlspecialchars($command))."<br>Response:".nl2br($response)."\n";
				if ($response)	{
					// check for auth
					if (strpos($command, 'EHLO') !== FALSE)	{
						if (strpos(strtoupper($response), 'AUTH') !== FALSE)	$this->server_auth=TRUE;
					}
					// response code
					$code=substr($response, 0, 3);
					if (! is_array($expected_code))	$expected_code=array($expected_code);
					foreach($expected_code as $expected)	{
						if ($code==$expected)	$response_ok=TRUE;
					}
				}
				if (! $response_ok)	{
					if (! $response)	{
						$this->rep("SMTP Server not responding.");
					}	else	{
						$this->rep("Invalid response code: <br>$response");
					}
					return FALSE;
				}

				return TRUE;
			}
			
			private function get_response()	{

				if (! $this->smtp)	return FALSE;
				if (! $response=@fread($this->smtp, 1)) return FALSE;
				if (! $state = socket_get_status($this->smtp)) return FALSE;
				if ($state['timed_out']) {
					$this->rep('Connection response timeout.');
					return FALSE;
				}
				if (! $left=@fread($this->smtp, $state['unread_bytes'])) return FALSE;
				return $response.=$left;
			}
			
			private function connect()	{

				// changed in ver1.2 timeout 10->30
				if (! $this->smtp=@fsockopen($this->secure.$this->host, $this->port, $errno, $errstr, 60)) {
					$this->rep("Can not connect to host: $this->secure$this->host: $this->port. ($errstr)");
					// echo "ERROR: $errno - $errstr";

					return FALSE;

				}
				// if connection is established get and set time limit
				$this->time_limit=ini_get('max_execution_time');
				set_time_limit(0);
				
				return TRUE;
			}
			
			private function disconnect($err=FALSE)	{
				if ($err)	{
					@fputs($this->smtp, 'RSET'.$this->EOL);
					@fputs($this->smtp, 'QUIT'.$this->EOL);
				}
				@fclose($this->smtp);
				if ($this->debug)	echo "\n<br>Disconnected.";
				// restore time limit
				set_time_limit($this->time_limit);
			}
			
			public function send()	{
				
				// basic checks
				if ((! $this->host) or (! $this->port))	{
					$this->rep('Invalid smtp host or port defined.');
					return FALSE;
				}
				
				if (((! strlen($this->username)) &&	(strlen($this->password)))	or
					 ((strlen($this->username))	&& (! strlen($this->password))))	{
						$this->rep('Invalid user name or password defined.');
						return FALSE;
				}

				// added in ver 1.3 read the message file if is defined
				if ($this->message_file)	{
					if (! $this->read_html()) return FALSE;
				}

				
				if ((! strlen($this->subject))	&& 
					(! strlen($this->message)) && 
					(! count($this->files)))	{
						$this->rep('Nothing to send.');
						return FALSE;
				}
				
				// changed in ver1.2 add carbon and blind carbon copy
				$this->to_sum=$this->check_recipients($this->to);
				
				if (! $this->to_sum)	{
					$this->rep('One recipient to(\'recipient@mail\') must to be defined.');
					return FALSE;
				}
				if (count($this->bcc))	{
					$this->bcc=$this->check_recipients($this->bcc);
					if ($this->bcc)	$this->to_sum=array_merge($this->to_sum, $this->bcc);
				}
				if (count($this->cc))	{
					$this->cc=$this->check_recipients($this->cc);
					if ($this->cc)	$this->to_sum=array_merge($this->to_sum, $this->cc);
				}
				// remove duplicates
				$this->to_sum=array_unique($this->to_sum);
				
				// write the message
				// changed in ver1.2 if (! $mail=$this->mail_create()) return FALSE; to avoid 100 recipients limit 
				$this->mail_create();
				if (! $this->mail_message)	return FALSE;

				$EOL=$this->EOL;
				
				// added in ver1.2 send the mails in chunks of defult 99 recipients
				// -> array_chunk and foreach $this->to iteration
				$this->to_sum=array_chunk($this->to_sum, $this->session_limit);
				
				// added in ver1.2 if debug is on 
				ob_start();				
				
				// change in ver1.2.1 return FALSE to break & break2
				// and changed  $this->disconnect(TRUE); to variable $server_err 
				// to fix improperly disconnect bug in loop
	
				$server_err=FALSE;

				foreach ($this->to_sum as $this->to)	{
					// smtp connect
					if (! $this->connect())	{
						$server_err='con'; // to make difference if cannot make the connection
						break;
					}
					// empty the first response
					if (! $this->get_response())	{
						$server_err=TRUE;
						break;
					}
					
					// say hello	
					if (! $this->command_send('EHLO '.$_SERVER['SERVER_NAME'].$EOL, 250))	{
						if (! $this->command_send('HELO '.$_SERVER['SERVER_NAME'].$EOL, 250)) {
							$server_err=TRUE;
							break;
						}
					}
					// auth
					if (($this->server_auth) && (! strlen($this->username) or (! strlen($this->password))))	{
						$this->rep('Server require authentication.');
						$server_err=TRUE;
						break;
					}
					if (strlen($this->username))	{
						// fixed in ver1.3 incorrect disconnection when auth
						if (! $this->command_send("AUTH LOGIN".$EOL, 334))	{
							$server_err=TRUE;
							break;
						}
						if (! $this->command_send(base64_encode($this->username).$EOL, 334))	{
							$server_err=TRUE;
							break;
						}
						if (! $this->command_send(base64_encode($this->password).$EOL, 235))	{
							$server_err=TRUE;
							break;
						}
					}
					// from
					if (! $this->command_send("MAIL FROM:<$this->from>".$EOL, 250))	{
						$server_err=TRUE;
						break;
					}
					// to
					foreach ($this->to as $send_to)	{
						if (! $this->command_send("RCPT TO:<$send_to>".$EOL, array(250, 251, 450, 550, 551, 553)))	{
							$server_err=TRUE;
							break 2;
						}
						usleep((integer)($this->rcpdelay*1000000));
					}
					// message
					if (! $this->command_send("DATA".$EOL, 354))	{
						$server_err=TRUE;
						break;
					}
					// changed in ver1.2 $mail -> $this->mail_message to avoid 100 recipients limit
					if (! $this->command_send($this->mail_message.$EOL.'.'.$EOL, 250))	{
						$server_err=TRUE;
						break;
					}
					// quit
					if (! $this->command_send("QUIT".$EOL, 221)) {
						$server_err=TRUE;
						break;
					}
					
					// smtp diconnect
					$this->disconnect();
					sleep($this->condelay);
				}
				
				ob_end_flush();				

				if ($server_err)	{
					// try to send reset to server and quit properly
					if ($server_err!='con')	$this->disconnect(TRUE);
					return FALSE;
				}
				$this->rep('Mail has been send.');
				return TRUE;
			}
		}
	}

?>
