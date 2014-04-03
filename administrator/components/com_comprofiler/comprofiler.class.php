<?php
/**
* Joomla/Mambo Community Builder
* @version $Id: comprofiler.class.php 1808 2012-06-19 21:03:12Z beat $
* @package Community Builder
* @subpackage comprofiler.class.php
* @author JoomlaJoe and Beat
* @copyright (C) JoomlaJoe and Beat, www.joomlapolis.com
* @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
*/

// ensure this file is being included by a parent file
if ( ! ( defined( '_VALID_CB' ) || defined( '_JEXEC' ) || defined( '_VALID_MOS' ) ) ) { die( 'Direct Access to this location is not allowed.' ); }

/** @global int $_CB_OneTwoRowsStyleToggle */
/** @global array $_CB_outputedHeads storing already outputed items in head to output only once and avoid double-outputing */
global $_CB_PMS, $_CB_OneTwoRowsStyleToggle, $_CB_outputedHeads;
// $_CB_PMS = new cbPMS();					// moved at end of file
$_CB_OneTwoRowsStyleToggle	=	1;			// toggle for status sectionTableEntry display
$_CB_outputedHeads			=	array();

cbimport( 'cb.tables' );
cbimport( 'phpinputfilter.inputfilter' );
cbimport( 'cb.acl' );

define( "_UE_PREGMATCH_VALID_EMAIL", "/[a-z0-9!#$%&'*+\\/=?^_`{|}~-]+(?:\\.[a-z0-9!#$%&'*+\\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?/i" );
/**
 * Checks if a given string is a valid email address
 *
 * @param	string	$email	String to check for a valid email address
 * @return	boolean
 */
function cbIsValidEmail( $email ) {
	return preg_match( _UE_PREGMATCH_VALID_EMAIL, $email );
}


/**
* Function to create a mail object for futher use (uses phpMailer, smtp or sendmail depending on global config)
*
* @param  string  $from      From e-mail address
* @param  string  $fromname  From name
* @param  string  $subject   E-mail subject
* @param  string  $body      Message body
* @return CBPHPMailer        Mail object
*/
function & comprofilerCreateMail( $from = '', $fromname = '', $subject, $body ) {
	global $_CB_framework;

	cbimport( 'phpmailer.phpmailer');
	$mail					=	new CBPHPMailer();

	$mail->PluginDir		=	$_CB_framework->getCfg('absolute_path') .'/administrator/components/com_comprofiler/library/phpmailer/';
	$mail->SetLanguage( 'en', $_CB_framework->getCfg('absolute_path') . '/administrator/components/com_comprofiler/library/phpmailer/language/' );
	$mail->CharSet 			=	$_CB_framework->outputCharset();
	$mail->IsMail();
	$mail->From				=	$from ? $from : $_CB_framework->getCfg( 'mailfrom' );
	if ( ( $mail->From == '' ) || ( $mail->From == 'registration@whatever' ) ) {
		$mail->From			=	$_CB_framework->getCfg( 'mailfrom' );
	}
	$mail->FromName			=	$fromname ? $fromname : $_CB_framework->getCfg( 'fromname' );
	$mail->Mailer 			=	$_CB_framework->getCfg( 'mailer' );

	if ( $_CB_framework->getCfg( 'mailer' ) == 'smtp' ) {
		// Add smtp values:
		$mail->SMTPAuth		=	$_CB_framework->getCfg( 'smtpauth' );
		$mail->Username		=	$_CB_framework->getCfg( 'smtpuser' );
		$mail->Password		=	$_CB_framework->getCfg( 'smtppass' );
		$mail->Host			=	$_CB_framework->getCfg( 'smtphost' );
		$smtpport			=	(int) trim( $_CB_framework->getCfg( 'smtpport' ) );
		if ( $smtpport ) {
			$mail->Port		=	$smtpport;
		}
		$smtpsecure			=	$_CB_framework->getCfg( 'smtpsecure' );
		if ( ( $smtpsecure === 'ssl' ) || ( $smtpsecure === 'tls' ) ) {
			$mail->SMTPSecure	=	$smtpsecure;
		}
	} elseif ( $_CB_framework->getCfg( 'mailer' ) == 'sendmail' ) {
		// Set sendmail path:
		if ( $_CB_framework->getCfg( 'sendmail' ) ) {
			$mail->Sendmail	=	$_CB_framework->getCfg( 'sendmail' );
		}
	}

	// If email domain matches sub-part of site domain, we can safely set the sender header to lower risk of valid registration mails being flagged as spam:
	$email_parts			=	explode( '@', $mail->From );
	if ( count( $email_parts ) > 1 ) {
		$email_domain		=	array_pop( $email_parts );
		$urlParts			=	parse_url( $_CB_framework->getCfg( 'live_site' ) );

		if ( $email_domain && ( stripos( $urlParts['host'], $email_domain ) !== false ) ) {
			$mail->Sender	=	$mail->From;
		}
	}

	$mail->Subject 			=	$subject;
	$mail->Body 			=	$body;

	return $mail;
}

/**
* Mail function (uses phpMailer or SMTP depending on global settings)
*
* @param  string        $from         From e-mail address
* @param  string        $fromname     From name
* @param  string|array  $recipient    Recipient e-mail address(es)
* @param  string        $subject      E-mail subject
* @param  string        $body         Message body
* @param  boolean       $mode         false = plain text, true = HTML
* @param  string|array  $cc           CC e-mail address(es)
* @param  string|array  $bcc          BCC e-mail address(es)
* @param  string|array  $attachment   Attachment file name(s) (array index is filename, if string)
* @param  string|array  $replyto      ReplyTo e-mail address(es)
* @param  string|array  $replytoname  ReplyTo name(s)
* @return boolean                     True: mail sent, False: mail not sent (error)
*/
function comprofilerMail( $from, $fromname, $recipient, $subject, $body, $mode = 0, $cc = null, $bcc = null, $attachment = null, $replyto = null, $replytoname = null ) {
	$mail					=&	comprofilerCreateMail( $from, $fromname, $subject, $body );

	// activate HTML formatted emails
	if ( $mode ) {
		$mail->IsHTML(true);
	}

	if( is_array( $recipient ) ) {
		foreach ( $recipient as $to ) {
			$mail->AddAddress( $to );
		}
	} else {
		$mail->AddAddress($recipient);
	}
	if ( isset( $cc ) ) {
	    if ( is_array( $cc ) ) {
	        foreach ( $cc as $to ) {
	        	$mail->AddCC( $to );
	        }
		} else {
	        $mail->AddCC( $cc );
	    }
	}
	if ( isset( $bcc ) ) {
	    if ( is_array( $bcc ) ) {
	        foreach ($bcc as $to) $mail->AddBCC($to);
	    } else {
	        $mail->AddBCC($bcc);
	    }
	}
    if ( $attachment ) {
        if ( is_array( $attachment ) ) {
            foreach ( $attachment as $fname => $fpath ) {
            	if ( is_string( $fname ) ) {
	            	$mail->AddAttachment( $fpath, $fname );
            	} else {
            		$mail->AddAttachment( $fpath );
            	}
            }
        } else {
            $mail->AddAttachment( $attachment );
        }
    }
    if ( $replyto ) {
        if ( is_array( $replyto ) ) {
        	reset( $replytoname );
            foreach ( $replyto as $to ) {
            	$toname		=	( ( false !== (list( , $value ) = each( $replytoname ) ) ) ? $value : '' );
            	$mail->AddReplyTo( $to, $toname );
            }
        } else
            $mail->AddReplyTo( $replyto, $replytoname );
    }
	$mailsSent				=	$mail->Send();
/*
	global $_CB_framework;
	if( $_CB_framework->getCfg( 'debug' ) ) {
		//$mosDebug->message( "Mails send: $mailssend");
	}
	if( $mail->error_count > 0 ) {
		//$mosDebug->message( "The mail message $fromname <$from> about $subject to $recipient <b>failed</b><br /><pre>$body</pre>", false );
		//$mosDebug->message( "Mailer Error: " . $mail->ErrorInfo . "" );
	}
*/
	return $mailsSent;
}

/**
* Checks E-Mail address with Regex, MX records and SMTP server function (uses SMTP)
*
* @param  string        $from         From e-mail address
* @param  string|array  $recipient    Recipient e-mail address(es)
* @return int                         Result: -2: invalid email format, -1: couldn't check, 0: invalid email, 1: valid email.
*/
function cbCheckMail( $from, $recipient ) {
	if ( ! cbIsValidEmail( $recipient ) ) {
		return -2;
	}
	$mailparts					=	explode( '@', $recipient, 2 );
	if ( count( $mailparts ) != 2 ) {
		return 0;
	}

	$domain						=	$mailparts[1];
	$mxFound					=	false;
	if ( function_exists( 'getmxrr' ) ) {
		$mxFound				=	false;
		while ( strpos( $domain, '.' ) !== false ) {
			// Validate domain:
			$mxRecords			=	array();
			$mxWeights			=	array();
			if ( @getmxrr( $domain . '.', $mxRecords, $mxWeights ) ) {
				$mxFound	=	true;
				break;
			} else {
				$subDomains		=	explode( '.', $domain, 2 );
				if ( count( $subDomains ) == 2 ) {
					$domain		=	$subDomains[1];
				} else {
					break;
				}
			}
		}
	}
	if ( ! $mxFound ) {
		$ipAddresses			=	gethostbynamel( $mailparts[1] . '.' );		// '.' added so local domain is not added as 2nd trial.
		if ( $ipAddresses === false ) {
			return 0;
		}
		$mxRecords		=	array( $mailparts[1] );
		$mxWeights		=	array( 0 );
	}
	array_multisort( $mxWeights, SORT_ASC, SORT_NUMERIC, $mxRecords );

	cbimport( 'phpmailer.phpmailer' );
	cbimport( 'phpmailer.smtp' );
	$mail					=&	comprofilerCreateMail( $from, '', '', '' );
	$mail->SMTPAuth			=	false;
	// $mail->SMTPDebug		=	2;

	foreach ( $mxRecords as $host ) {
		try {
			$mail->Host			=	$host;
	
			if ( ! $mail->SmtpConnect() ) {
				continue;
			}
			if ( ! $mail->smtp->Mail( $from ) ) {
				$mail->smtp->Reset();
				return -1;
			}
			if ( ! $mail->smtp->Recipient( $recipient ) ) {
				$error			=	$mail->smtp->getError();
				$mail->smtp->Reset();
				if ( isset( $error['smtp_code'] ) && isset( $error['smtp_msg'] ) && ( $error['smtp_code'] == 450 ) && ( substr( $error['smtp_msg'], 0, 5 ) == '4.7.1' ) ) {
					return -1;		// greylisting detected.
				}
				return 0;
			}
			if ( $mail->SMTPKeepAlive == true ) {
				$mail->smtp->Reset();
			} else {
				$mail->SmtpClose();
			}
			return 1;
		} catch ( Exception $e ) {
			return -1;
		}
	}
	if ( function_exists( 'getmxrr' ) && ! $mxFound ) {
		return 0;
	} else {
		return -1;
	}
}

class moscomprofilerHTML {
	/**
	 * Creates an option for a list, a multicheckbox field, or a radio field
	 *
	 * @param  string  $value       value of the selection
	 * @param  string  $text        text of the selection
	 * @param  string  $valueName   name of the value variable
	 * @param  string  $textName    name of the text variable
	 * @return stdClass             selection object
	 */
	static function makeOption( $value, $text = '', $valueName = 'value', $textName = 'text' ) {
		$option					=	new stdClass;
		$option->$valueName		=	$value;
		$option->$textName		=	trim( $text ) ? $text : $value;
		return $option;
	}
	/**
	 * Creates an optgroup for a list
	 *
	 * @param  string  $text        text of the selection (if NULL: end of optgroup)
	 * @param  string  $valueName   name of the value variable
	 * @param  string  $textName    name of the text variable
	 * @return stdClass             selection object
	 */
	static function makeOptGroup( $text = '', $valueName = 'value', $textName = 'text' ) {
		$option					=	new stdClass;
		$option->$valueName		=	( $text !== null ? array( 'optgroup' ) : array( '/optgroup' ) );
		$option->$textName		=	trim( $text );
		return $option;
	}
	static function radioListArr( &$arr, $tag_name, $tag_attribs, $key, $text, $selected, $required=0, $classes = null ) {
		reset( $arr );
		$html = array();
		if ( $classes === null ) {
			$classes			=	array();
		}
		if ( $required ) {
			$classes[]			=	'required';
		}
		if ( count( $classes ) > 0 ) {
			$tag_attribs		.=	' class="' . implode( ' ', $classes ) . '"';
		}
		foreach ( $arr as $i => $option ) {
			$k = /* stripslashes */ ($option->$key);
			$t = /* stripslashes */ ($option->$text);
			if ( isset($option->id) ) {
				$id = $option->id;
			} else {
				$id = str_replace( array( '[', ']'), array( '_', '_' ), $tag_name ) . $i;
			}
			$extra = " id=\"" . $id . "\"";

			if (is_array( $selected )) {
				foreach ($selected as $obj) {
					if ( is_object( $obj ) ) {
						$k2		=	/* stripslashes */ $obj->$key;
					} else {
						$k2		=	$obj;
					}
					if ($k == $k2) {
						$extra .= " checked=\"checked\"";
						break;
					}
				}
			} else {
				$extra .= ($k == /* stripslashes */ ($selected) ? "  checked=\"checked\"" : '');
			}
			if ( $required && ( $i == 0 ) && ! defined( '_CB_VALIDATE_NEW' ) ) {
				$isReq="mosReq=\"".$required."\"";
			} else {
				$isReq="";
			}
			$html[] = "<input type=\"radio\" name=\"$tag_name\" $isReq $tag_attribs value=\"" . htmlspecialchars( $k ) . "\"$extra /> <label for=\"" . $id . "\">" . getLangDefinition($t) . "</label>";
		}
		return $html;
	}
	static function radioList( &$arr, $tag_name, $tag_attribs, $key, $text, $selected, $required = 0, $classes = null ) {
		return "\n\t".implode("\n\t ", moscomprofilerHTML::radioListArr( $arr, $tag_name, $tag_attribs, $key, $text, $selected, $required, $classes ))."\n";
	}
	static function radioListTable( &$arr, $tag_name, $tag_attribs, $key, $text, $selected, $cols=0, $rows=1, $size=0, $required=0, $classes = null ) {
		$cellsHtml = moscomprofilerHTML::radioListArr( $arr, $tag_name, $tag_attribs, $key, $text, $selected, $required, $classes );
		return moscomprofilerHTML::list2Table( $cellsHtml, $cols, $rows, $size );
	}
	static function selectList( &$arr, $tag_name, $tag_attribs, $key, $text, $selected, $required = 0, $htmlspecialcharText = true, $addBlank = null ) {
		reset( $arr );
		$id_name				=	moscomprofilerHTML::htmlId( $tag_name );
		$html	=	"\n"
				.	'<select name="' . htmlspecialchars( $tag_name ) . '" id="' . htmlspecialchars( $id_name ) . '" ' . $tag_attribs . '>';
		if ( $addBlank === null ) {
			$addBlank			=	( ( ( ! $required ) || ( is_array( $selected ) ? ( count( $selected ) == 0 ) : ( $selected == '' ) ) ) && ! ( isset( $arr[0] ) && $arr[0]->$key == '' ) );
		}
		if ( $addBlank ) {
			$html .= "\n\t<option value=\"\"> </option>";
		}

		foreach ( $arr as $option ) {
			$t					=	/* stripslashes */ ($option->$text);
			$id					=	isset($option->id) ? $option->id : null;

			$extra				=	'';
			if ( $id ) {
				$extra			.=	' id="' . $option->id . '"';
			}
			if ( is_array( $option->$key ) ) {
				$a					=	$option->$key;
				if ( $a[0] == 'optgroup' ) {
					$html			.=	"\n  <optgroup label=\"" . htmlspecialchars( $t ) . "\"$extra>";
				} else {
					$html			.=	"\n  </optgroup>";
				}
			} else {
				$k					=	/* stripslashes */ ($option->$key);
				if ( is_array( $selected ) ) {
					foreach ( $selected as $obj ) {
						if ( is_object( $obj ) ) {
							$k2		=	$obj->$key;
						} else {
							$k2		=	$obj;
						}
						if ( $k === $k2 ) {
							$extra	.=	' selected="selected"';
							break;
						}
					}
				} else {
					if ( $k == /* stripslashes */ ( $selected ) ) {
						$extra		.=	' selected="selected"';
					}
				}
				$html				.=	"\n\t<option value=\"" . htmlspecialchars( $k ) . "\"$extra>";
				if ( $htmlspecialcharText ) {
					$html				.=	htmlspecialchars( getLangDefinition( $t ) );
				} else {
					$html				.=	getLangDefinition( $t );
				}
				$html				.=	"</option>";

			}

		}
		$html					.=	"\n</select>\n";
		return $html;
	}
	static function yesnoSelectList( $tag_name, $tag_attribs, $selected, $yes = _UE_YES, $no = _UE_NO ) {
		$arr = array(	moscomprofilerHTML::makeOption( '0', $no ),
						moscomprofilerHTML::makeOption( '1', $yes )
					);
		return moscomprofilerHTML::selectList( $arr, $tag_name, $tag_attribs, 'value', 'text', $selected, 2, true, false );
	}
	static function checkboxListArr( &$arr, $tag_name, $tag_attribs,  $key='value', $text='text',$selected=null, $required = 0, $classes = null ) {
		reset( $arr );
		$html = array();
		if ( $classes === null ) {
			$classes			=	array();
		}
		if ( $required ) {
			$classes[]			=	'required';
		}
		if ( count( $classes ) > 0 ) {
			$tag_attribs		.=	' class="' . implode( ' ', $classes ) . '"';
		}
		foreach ( $arr as $i => $option ) {
			$k = $option->$key;
			$t = $option->$text;
			if ( isset($option->id) ) {
				$id = $option->id;
			} else {
				$id = str_replace( array( '[', ']'), array( '_', '_' ), $tag_name ) . $i;
			}
			$extra = " id=\"" . $id . "\"";

			if (is_array( $selected )) {
				foreach ($selected as $obj) {
					if ( is_object( $obj ) ) {
						$k2		=	$obj->$key;
					} else {
						$k2		=	$obj;
					}
					if ($k === $k2) {
						$extra	.=	" checked=\"checked\"";
						break;
					}
				}
			} else {
				$extra .= ($k == $selected ? " checked=\"checked\"" : '');
			}
			if ( $required && ( $i == 0 ) && ! defined( '_CB_VALIDATE_NEW' ) ) {
				$isReq="mosReq=\"".$required."\"";
			} else {
				$isReq="";
			}
			$html[] = "<input type=\"checkbox\" name=\"$tag_name\" $isReq value=\"".$k."\"$extra $tag_attribs /> <label for=\"" . $id . "\">" . getLangDefinition($t) . "</label>";
		}
		return $html;
	}
	static function checkboxList( &$arr, $tag_name, $tag_attribs,  $key='value', $text='text',$selected=null, $required = 0, $classes = null ) {
		return "\n\t".implode("\n\t", moscomprofilerHTML::checkboxListArr( $arr, $tag_name, $tag_attribs,  $key, $text,$selected, $required, $classes ))."\n";
	}
	static function checkboxListTable( &$arr, $tag_name, $tag_attribs,  $key='value', $text='text',$selected=null, $cols=0, $rows=0, $size=0, $required=0, $classes = null ) {
		$cellsHtml = moscomprofilerHTML::checkboxListArr( $arr, $tag_name, $tag_attribs,  $key, $text,$selected, $required, $classes );
		return moscomprofilerHTML::list2Table( $cellsHtml, $cols, $rows, $size );
	}
	// private methods:
	static function list2Table ( $cellsHtml, $cols, $rows, $size ) {
		$cells					=	count( $cellsHtml );

		$size					=	(int) ( ( $size - ( $size % 3 ) ) / 3  ) * 2;	// int div  3 * 2 width/heigh ratio
		if ( $size == 0 ) {
			$localstyle			=	'';		//" style='width:100%'";
		} else {
			$localstyle			=	' style="width:' . $size . 'em;"';
		}
		$return					=	'';
		if ( $cells ) {
			if ( $rows ) {
				$return			=	"\n\t" . '<table class="cbMulti"' . $localstyle . '>';
				$cols			=	( $cells - ( $cells % $rows ) ) / $rows;	// int div
				if ( $cells % $rows ) {
					$cols++;
				}
				$lineIdx		=	0;
				for ( $lineIdx = 0, $n = min( $rows, $cells ) ; $lineIdx < $n ; $lineIdx++ ) {
					$return		.=	"\n\t\t<tr>";
					for ( $i = $lineIdx ; $i < $cells ; $i += $rows ) {
						$return	.=	'<td>' . $cellsHtml[$i] . '</td>';
					}
					$return 	.=	"</tr>\n";
				}
				$return			.=	"\t</table>\n";
			} elseif ( $cols ) {
				$return			=	"\n\t" . '<table class="cbMulti"' . $localstyle . '>';
				$idx			=	0;
				while ( $cells ) {
					$return		.=	"\n\t\t<tr>";
					for ($i=0, $n=min($cells,$cols); $i < $n; $i++, $cells-- ) {
						$return .=	"<td>".$cellsHtml[$idx++]."</td>";
					}
					$return		.=	"</tr>\n";
				}
				$return			.=	"\t</table>\n";
			} else {
				$return			=	"\n\t" . '<span class="cbSnglCtrlLbl">' . implode( '</span><span class="cbSnglCtrlLbl">', $cellsHtml ) . "</span>\n";
				// $return			=	"\n\t" . implode( "\n\t ", $cellsHtml ) . "\n";
			}
		}
		return $return;
	}
	/**
	 * Returns a validating unique id attribute based on name attribute
	 *
	 * @param  string  $name
	 * @return string
	 */
	static function htmlId( $name ) {
		return str_replace( array( '[', ']' ), array( '__', '' ), $name );
	}
	/**
	* simple Javascript Cloaking
	* email cloacking
 	* by default replaces an email with a mailto link with email cloacked
	*/
	static function emailCloaking( $mail, $mailto=1, $text='', $email=1, $cloaktext=true ) {
		global $_CB_framework;
		static $spanId	=	null;

		if ( $spanId == null ) {
			$spanId		=	rand( 1, 100000 );
		} else {
			$spanId		+=	1;
		}

		// convert text
		$mail 			=	moscomprofilerHTML::encoding_converter( $mail );
		// split email by @ symbol
		$mail			=	explode( '@', $mail );
		if ( count( $mail ) > 1 ) {
			$mail_parts	= explode( '.', $mail[1] );
		} else {
			$mail_parts	=	array( '' );
		}
		// random number
		$rand			=	rand( 1, 100000 );

		$replacement	=	'<span id="cbMa' . $spanId . '" class="cbMailRepl">...</span>';

		$js				=	'	{'
						.	"\n		var prefix='&#109;a'+'i&#108;'+'&#116;o';"
						.	"\n		var path = 'hr'+ 'ef'+'=';"
						.	"\n		var addy". $rand ."= '". @$mail[0] ."'+ '&#64;' +'". implode( "' + '&#46;' + '", $mail_parts ) ."';"
						;
		if ( $mailto ) {
			// special handling when mail text is different from mail addy
			if ( $text ) {
				if ( $cloaktext ) {
					if ( $email ) {
						// convert text
						$text 			=	moscomprofilerHTML::encoding_converter( $text );
						// split email by @ symbol
						$text 			=	explode( '@', $text );
						$text_parts		=	explode( '.', $text[1] );
						$js			 	.=	"\n		var addy_text". $rand ." = '". @$text[0] ."' + '&#64;' + '". implode( "' + '&#46;' + '", @$text_parts ) ."';";
					} else {
						$text 	= moscomprofilerHTML::encoding_converter( $text );
						$js				.=	"\n		var addy_text". $rand ." = '". $text ."';";
					}
				} else {
					$js					.=	"\n		var addy_text". $rand ." = '". $text ."';";
				}
				$js				.=	"\n		$('#cbMa" . $spanId . "').html("
								.				"'<a ' + path + '\\'' + prefix + ':' + addy". $rand ." + '\\'>'"
								.				" + addy_text". $rand
								.				" + '</a>'"
								.			");"
								;
			} else {
				$js				.=	"\n		$('#cbMa" . $spanId . "').html("
								.				"'<a ' + path + '\\'' + prefix + ':' + addy". $rand ." + '\\'>'"
								.				" + addy". $rand
								.				" + '</a>'"
								.			");"
								;
			}
		} else {
				$js				.=	"\n		$('#cbMa" . $spanId . "').html(addy". $rand . ");";
		}
		$js						.=	"\n	}";
		$replacement 	.= "<noscript> \n";
		$replacement 	.= _UE_CLOAKED;
		$replacement 	.= "\n</noscript> \n";

		$_CB_framework->outputCbJQuery( $js );
		return $replacement;
	}
/*
	/**
	* simple Javascript Cloaking
	* email cloacking
 	* by default replaces an email with a mailto link with email cloacked
	*
	static function emailCloaking( $mail, $mailto=1, $text='', $email=1, $cloaktext=true ) {
		// convert text
		$mail 		= moscomprofilerHTML::encoding_converter( $mail );
		// split email by @ symbol
		$mail		= explode( '@', $mail );
		if ( count( $mail ) > 1 ) {
			$mail_parts	= explode( '.', $mail[1] );
		} else {
			$mail_parts	=	array( '' );
		}
		// random number
		$rand	= rand( 1, 100000 );

		$replacement 	= "\n<script language='JavaScript' type='text/javascript'> \n";
		$replacement 	.= "<!-- \n";
		$replacement 	.= "var prefix = '&#109;a' + 'i&#108;' + '&#116;o'; \n";
		$replacement 	.= "var path = 'hr' + 'ef' + '='; \n";
		$replacement 	.= "var addy". $rand ." = '". @$mail[0] ."' + '&#64;' + '". implode( "' + '&#46;' + '", $mail_parts ) ."'; \n";
		if ( $mailto ) {
			// special handling when mail text is different from mail addy
			if ( $text ) {
				if ( $cloaktext ) {
					if ( $email ) {
						// convert text
						$text 	= moscomprofilerHTML::encoding_converter( $text );
						// split email by @ symbol
						$text 	= explode( '@', $text );
						$text_parts	= explode( '.', $text[1] );
						$replacement 	.= "var addy_text". $rand ." = '". @$text[0] ."' + '&#64;' + '". implode( "' + '&#46;' + '", @$text_parts ) ."'; \n";
					} else {
						$text 	= moscomprofilerHTML::encoding_converter( $text );
						$replacement 	.= "var addy_text". $rand ." = '". $text ."';\n";
					}
				} else {
					$replacement	.=	"var addy_text". $rand ." = '". $text ."'; \n";
				}
				$replacement 	.= "document.write( '<a ' + path + '\\'' + prefix + ':' + addy". $rand ." + '\\'>' ); \n";
				$replacement 	.= "document.write( addy_text". $rand ." ); \n";
				$replacement 	.= "document.write( '<\\/a>' ); \n";
			} else {
				$replacement 	.= "document.write( '<a ' + path + '\\'' + prefix + ':' + addy". $rand ." + '\\'>' ); \n";
				$replacement 	.= "document.write( addy". $rand ." ); \n";
				$replacement 	.= "document.write( '<\\/a>' ); \n";
			}
		} else {
			$replacement 	.= "document.write( addy". $rand ." ); \n";
		}
		$replacement 	.= "//--> \n";
		$replacement 	.= "</script> \n";
		$replacement 	.= "<noscript> \n";
		$replacement 	.= _UE_CLOAKED;
		$replacement 	.= "\n</noscript> \n";

		return $replacement;
	}
*/
	static function encoding_converter( $text ) {
		// replace vowels with character encoding
		$text 	= str_replace( 'a', '&#97;', $text );
		$text 	= str_replace( 'e', '&#101;', $text );
		$text 	= str_replace( 'i', '&#105;', $text );
		$text 	= str_replace( 'o', '&#111;', $text );
		$text	= str_replace( 'u', '&#117;', $text );
		return addslashes( $text );
	}
} // end class moscomprofilerHTML


/**
 * Deletes all user views from that user and for that user (called on user delete). Temporary function !!
 *
 * @param int $userId
 * @return boolean true if ok, false with warning on sql error
 */
function _cbdeleteUserViews( $userId ) {
	global $_CB_database;
	$sql='DELETE FROM #__comprofiler_views WHERE viewer_id = '.(int) $userId.' OR profile_id = '.(int) $userId;
	$_CB_database->SetQuery($sql);
	if (!$_CB_database->query()) {
		$this->_setErrorMSG('SQL error' . $_CB_database->stderr(true));
		return false;
	}
	return true;
}

//FUNCTIONS

function deleteAvatar( $avatar ){
	global $_CB_framework;
// 	if(preg_match('/gallery\//i',$avatar)==false && is_file($_CB_framework->getCfg('absolute_path').'/images/comprofiler/'.$avatar)) {
   	if( ( strpos( $avatar, '/' ) === false ) && is_file($_CB_framework->getCfg('absolute_path').'/images/comprofiler/'.$avatar)) {
   		@unlink($_CB_framework->getCfg('absolute_path').'/images/comprofiler/'.$avatar);
		if(is_file($_CB_framework->getCfg('absolute_path').'/images/comprofiler/tn'.$avatar)) @unlink($_CB_framework->getCfg('absolute_path').'/images/comprofiler/tn'.$avatar);
	}
}

function getActivationMessage( &$user, $cause ) {
	global $ueConfig;
	if ( ! isset( $ueConfig['emailpass'] ) ) {
		$ueConfig['emailpass']	=	'0';
	}

	$messagesToUser = null;
	if ( in_array( $cause, array( 'UserRegistration', 'SameUserRegistrationAgain' ) ) ) {
		if 		 ( $ueConfig['emailpass'] == '1' && $user->approved != 1 && $user->confirmed == 1 ){
			$messagesToUser = _UE_REG_COMPLETE_NOPASS_NOAPPR;
		} elseif ( $ueConfig['emailpass'] == '1' && $user->approved != 1 && $user->confirmed == 0 ) {
			$messagesToUser = _UE_REG_COMPLETE_NOPASS_NOAPPR_CONF;
		} elseif ( $ueConfig['emailpass'] == '1' && $user->approved == 1 && $user->confirmed == 1 ) {
			$messagesToUser = _UE_REG_COMPLETE_NOPASS;
		} elseif ( $ueConfig['emailpass'] == '1' && $user->approved == 1 && $user->confirmed == 0 ) {
			$messagesToUser = _UE_REG_COMPLETE_NOPASS_CONF;
		} elseif ( $ueConfig['emailpass'] == '0' && $user->approved != 1 && $user->confirmed == 1 ) {
			$messagesToUser = _UE_REG_COMPLETE_NOAPPR;
		} elseif ( $ueConfig['emailpass'] == '0' && $user->approved != 1 && $user->confirmed == 0 ) {
			$messagesToUser = _UE_REG_COMPLETE_NOAPPR_CONF;
		} elseif ( $ueConfig['emailpass'] == '0' && $user->approved == 1 && $user->confirmed == 0 ) {
			$messagesToUser = _UE_REG_COMPLETE_CONF;
		} else {
			$messagesToUser = _UE_REG_COMPLETE;
		}
	} elseif ( $cause == 'UserConfirmation' ) {
		if ($user->approved != 1) {
			$messagesToUser = _UE_USER_CONFIRMED_NEEDAPPR;
		} else {
			if ( $ueConfig['emailpass'] == '1' ) {
				$messagesToUser = _UE_REG_COMPLETE_NOPASS;
			} else {
				$messagesToUser = _UE_USER_CONFIRMED;
			}
		}
	}

	if ( $messagesToUser ) {
		$messagesToUser = array( 'sys' => $messagesToUser );
		if ( $cause == 'SameUserRegistrationAgain' ) {
			array_unshift( $messagesToUser, _UE_YOU_ARE_ALREADY_REGISTERED );
		}
	}
	return $messagesToUser;
}
/**
 * Activates a user
 * user plugins must have been loaded
 *
 * @param  moscomprofilerUser  $user
 * @param  int      $ui               1=frontend, 2=backend, 0=no UI: machine-machine UI
 * @param  string   $cause            (one of: 'UserRegistration', 'UserConfirmation', 'UserApproval', 'NewUser', 'UpdateUser')
 * @param  boolean  $mailToAdmins     true if the standard new-user email should be sent to admins if moderator emails are enabled
 * @param  boolean  $mailToUser       true if the welcome new user email (from CB config) should be sent to the new user
 * @param  boolean  $triggerBeforeActivate
 * @return array of string          texts to display
 */
function activateUser( &$user, $ui, $cause, $mailToAdmins = true, $mailToUser = true, $triggerBeforeActivate = true ) {
	global $_CB_database, $ueConfig, $_PLUGINS;

	static $notificationsSent	=	array();

	$activate = ( $user->confirmed && ( $user->approved == 1 ) );
	$showSysMessage = true;
	$messagesToUser = getActivationMessage( $user, $cause );

	if ( $cause == 'UserConfirmation' && $user->approved == 0) {
		$activate = false;
		$msg = array(
			'emailAdminSubject'	=> array( 'sys' => _UE_REG_ADMIN_PA_SUB ),
			'emailAdminMessage'	=> array( 'sys' => _UE_REG_ADMIN_PA_MSG ),
			'emailUserSubject'	=> array( ),
			'emailUserMessage'	=> array( )
		);
	} elseif ( $user->confirmed == 0 ) {
		$msg = array(
			'emailAdminSubject'	=> array( ),
			'emailAdminMessage'	=> array( ),
			'emailUserSubject'	=> array( 'sys' => getLangDefinition( stripslashes( $ueConfig['reg_pend_appr_sub'] ) ) ),
			'emailUserMessage'	=> array( 'sys' => getLangDefinition( stripslashes( $ueConfig['reg_pend_appr_msg'] ) ) )
		);
	} elseif ( $cause == 'SameUserRegistrationAgain' ) {
		$activate = false;
		$msg = array(
			'emailAdminSubject'	=> array( ),
			'emailAdminMessage'	=> array( ),
			'emailUserSubject'	=> array( ),
			'emailUserMessage'	=> array( )
		);
	} elseif ( $user->confirmed && ! ( $user->approved == 1 ) ) {
		$msg = array(
			'emailAdminSubject'	=> array( 'sys' => _UE_REG_ADMIN_PA_SUB ),
			'emailAdminMessage'	=> array( 'sys' => _UE_REG_ADMIN_PA_MSG ),
			'emailUserSubject'	=> array( 'sys' => getLangDefinition( stripslashes( $ueConfig['reg_pend_appr_sub'] ) ) ),
			'emailUserMessage'	=> array( 'sys' => getLangDefinition( stripslashes( $ueConfig['reg_pend_appr_msg'] ) ) )
		);
	} elseif  ( $user->confirmed && ( $user->approved == 1 ) ) {
		$msg = array(
			'emailAdminSubject'	=> array( 'sys' => _UE_REG_ADMIN_SUB ),
			'emailAdminMessage'	=> array( 'sys' => _UE_REG_ADMIN_MSG ),
			'emailUserSubject'	=> array( 'sys' => getLangDefinition( stripslashes( $ueConfig['reg_welcome_sub'] ) ) ),
			'emailUserMessage'	=> array( 'sys' => getLangDefinition( stripslashes( $ueConfig['reg_welcome_msg'] ) ) )
		);
	}
	$msg['messagesToUser']		=	$messagesToUser;

	if ( $triggerBeforeActivate ) {
		$results = $_PLUGINS->trigger( 'onBeforeUserActive', array( &$user, $ui, $cause, $mailToAdmins, $mailToUser ));
		if( $_PLUGINS->is_errors() && ( $ui != 0 ) ) {
			echo $_PLUGINS->getErrorMSG( '<br />' );
		}

		foreach ( $results as $res ) {
			if ( is_array( $res ) ) {
				$activate		=	$activate			&& $res['activate'];
				$mailToAdmins	=	$mailToAdmins		&& $res['mailToAdmins'];
				$mailToUser		=	$mailToUser		&& $res['mailToUser'];
				$showSysMessage	=	$showSysMessage	&& $res['showSysMessage'];
				foreach ( array_keys( $msg ) as $key ) {
					if ( isset( $res[$key] ) && $res[$key] ) {
						array_push( $msg[$key], $res[$key] );
					}
				}
			}
		}
		if ( ! ( $mailToAdmins && ( $ueConfig['moderatorEmail'] == 1 ) ) ) {
			unset( $msg['emailAdminSubject']['sys'] );
			unset( $msg['emailAdminMessage']['sys'] );
		}
		if ( ! $mailToUser ) {
			unset( $msg['emailUserSubject']['sys'] );
			unset( $msg['emailUserMessage']['sys'] );
		}
		if ( ! $showSysMessage ) {
			unset( $msg['messagesToUser']['sys'] );
		}
	}

	if ( $activate ) {
		$user->block				=	0;
		$user->storeBlock();
		$user->removeActivationCode();
	}

	if ( $activate ) {
		$_PLUGINS->trigger( 'onUserActive', array( &$user, $ui, $cause, $mailToAdmins, $mailToUser ) );
		if( $_PLUGINS->is_errors() && ( $ui != 0 ) ) {
			 $msg['messagesToUser']	=	$_PLUGINS->getErrorMSG( '<br />' )
			 						.	$msg['messagesToUser'];
		}
	}
	if ( ! isset( $notificationsSent[$user->id][$user->confirmed][$user->approved][$user->block] ) ) {		// in case done several times (e.g. plugins), avoid resending messages.
		$cbNotification				=	new cbNotification();

		if ( $ueConfig['moderatorEmail'] && count( $msg['emailAdminMessage'] ) ) {
			$pwd					=	$user->password;
			$user->password			=	null;
			$cbNotification->sendToModerators( implode( ', ', $msg['emailAdminSubject'] ),
											   $cbNotification->_replaceVariables( implode( '\n\n', $msg['emailAdminMessage'] ), $user ) );
			$user->password			=	$pwd;
		}

		if ( count( $msg['emailUserMessage'] ) ) {
			$cbNotification->sendFromSystem( $user, implode( ', ', $msg['emailUserSubject'] ), implode( '\n\n', $msg['emailUserMessage'] ) );
		}
		$notificationsSent[$user->id][$user->confirmed][$user->approved][$user->block]	=	true;
	}
	return $msg['messagesToUser'];
}

/**
* Page navigation support functions
*/

/**
* Writes the html links for pages, eg, previous 1 2 3 ... x next
* @param int The record number to start dislpaying from
* @param int Number of rows to display per page
* @param int Total number of rows
* @param string base url (without SEF): cbSef done inside this function
* @param mixed string/array : string: search parameter added as &$prefix.search=... if NOT NULL ; array: each added as $prefix.&key=$val
* @param string prefix on the &limitstart and &search URL items
*/

function writePagesLinks($limitstart, $limit, $total,$ue_base_url,$search=null,$prefix='') {
	$limitstart = max( (int) $limitstart, 0 );
	$limit		= max( (int) $limit, 1 );
	$total		= (int) $total;
	$ret='';
	if (is_array($search)) {
		$search_str = '';
		foreach ($search as $k => $v) {
			if ($v && $k!='limitstart')  $search_str .= '&amp;'.urlencode($prefix.$k).'='.urlencode($v);
		}
	} else {
		$search_str = (($search) ? '&amp;'.urlencode($prefix).'search='.urlencode($search) : '');
	}
	// limit is not used in CB but helps sh404sef according to feature #1707:
	$limstart_str	=	'&amp;' . urlencode($prefix) . 'limit=' . (int) $limit . '&amp;'.urlencode($prefix) . 'limitstart=';
	$pages_in_list = 6;                // set how many pages you want displayed in the menu (not including first&last, and ev. ... repl by single page number.
	$displayed_pages = $pages_in_list;
	$total_pages = ceil( $total / $limit );
	$this_page = ceil( ($limitstart+1) / $limit );
	// $start_loop = (floor(($this_page-1)/$displayed_pages))*$displayed_pages+1;
	$start_loop = $this_page-floor($displayed_pages/2); if ($start_loop < 1) $start_loop = 1; if ($start_loop == 3) $start_loop = 2;		//BB
	if ($start_loop + $displayed_pages - 1 < $total_pages-2) {
		$stop_loop = $start_loop + $displayed_pages - 1;
	} else {
		$stop_loop = $total_pages;
	}

	if ($this_page > 1) {
		$page = ($this_page - 2) * $limit;
		$ret .= "\n<a class=\"pagenav\" href=\"".cbSef($ue_base_url.$limstart_str.'0'.$search_str)."\" title=\"" . _UE_FIRST_PAGE . "\">&lt;&lt; " . _UE_FIRST_PAGE . "</a>";
		$ret .= "\n<a class=\"pagenav\" href=\"".cbSef($ue_base_url.$limstart_str.$page.$search_str)."\" title=\"" . _UE_PREV_PAGE . "\">&lt; " . _UE_PREV_PAGE . "</a>";
		if ($start_loop > 1) $ret .= "\n<a class=\"pagenav\" href=\"".cbSef($ue_base_url.$limstart_str."0".$search_str)."\" title=\"" . _UE_FIRST_PAGE . "\"><strong>1</strong></a>";
		if ($start_loop > 2) $ret .= "\n<span class=\"pagenav\"> <strong>...</strong> </span>";
	} else {
		$ret .= '<span class="pagenav">&lt;&lt; '. _UE_FIRST_PAGE .'</span> ';
		$ret .= '<span class="pagenav">&lt; '. _UE_PREV_PAGE .'</span> ';
	}

	for ($i=$start_loop; $i <= $stop_loop; $i++) {
		$page = ($i - 1) * $limit;
		if ($i == $this_page) {
			$ret .= "\n <span class=\"pagenav\">[".$i."]</span> ";
		} else {
			$ret .= "\n<a class=\"pagenav\" href=\"".cbSef($ue_base_url.$limstart_str.$page.$search_str)."\"><strong>$i</strong></a>";
		}
	}

	if ($this_page < $total_pages) {
		$page = $this_page * $limit;
		$end_page = ($total_pages-1) * $limit;
		if ($stop_loop < $total_pages-1) $ret .= "\n<span class=\"pagenav\"> <strong>...</strong> </span>";
		if ($stop_loop < $total_pages) $ret .= "\n<a class=\"pagenav\" href=\"".cbSef($ue_base_url.$limstart_str.$end_page.$search_str)."\" title=\"" . _UE_END_PAGE . "\"><strong>".$total_pages."</strong></a>";
		$ret .= "\n<a class=\"pagenav\" href=\"".cbSef($ue_base_url.$limstart_str.$page.$search_str)."\" title=\"" . _UE_NEXT_PAGE . "\">" . _UE_NEXT_PAGE . " &gt;</a>";
		$ret .= "\n<a class=\"pagenav\" href=\"".cbSef($ue_base_url.$limstart_str.$end_page.$search_str)."\" title=\"" . _UE_END_PAGE . "\">" . _UE_END_PAGE . " &gt;&gt;</a>";
	} else {
		$ret .= '<span class="pagenav">'. _UE_NEXT_PAGE .' &gt;</span> ';
		$ret .= '<span class="pagenav">'. _UE_END_PAGE .' &gt;&gt;</span>';
	}
	return $ret;
}

/**
* Writes the html for the pages counter, eg, Results 1-10 of x
*
* @param int The record number to start dislpaying from
* @param int Number of rows to display per page
* @param int Total number of rows
*/
function writePagesCounter($limitstart, $limit, $total) {
	$from_result = $limitstart+1;
	if ($limitstart + $limit < $total) {
		$to_result = $limitstart + $limit;
	} else {
		$to_result = $total;
	}
	if ($total > 0) {
		echo _UE_RESULTS . " <b>" . $from_result . " - " . $to_result . "</b> " . _UE_OF_TOTAL . " <b>" . $total . "</b>";
	} else {
		echo _UE_NO_RESULTS . ".";
	}
}
function isOdd($x){
if($x & 1) return TRUE;
else return FALSE;
}
function check_filesize($file,$maxSize) {

   $size = filesize($file);

   if($size <= $maxSize) {
      return true;
   }
   return false;
}

function check_image_type($type)
{
   switch( $type )
   {
      case 'jpeg':
      case 'pjpeg':
      case 'jpg':
         return '.jpg';
         break;
      case 'png':
         return '.png';
         break;
   }

   return false;
}

function display_avatar_gallery($avatar_gallery_path)
{
   $dir = @opendir($avatar_gallery_path);
   $avatar_images = array();
   $avatar_col_count = 0;
   while( true == ( $file = @readdir($dir) ) ) {

      if( $file != '.' && $file != '..' && is_file($avatar_gallery_path . '/' . $file) && !is_link($avatar_gallery_path. '/' . $file) )
      {
            if( preg_match('/(\.gif$|\.png$|\.jpg|\.jpeg)$/is', $file) )
            {
               $avatar_images[$avatar_col_count] = $file;
               // $avatar_name[$avatar_col_count] = ucfirst(str_replace("_", " ", preg_replace('/^(.*)\..*$/', '\1', $file)));
               $avatar_col_count++;
            }
       }
   }

   @closedir($dir);

   @ksort($avatar_images);
   @reset($avatar_images);

   return $avatar_images;
}

function fmodReplace($x,$y)
{ //function provided for older PHP versions which do not have an fmod function yet
   $i = floor($x/$y);
   return $x - $i*$y;
}

function dateConverter($oDate,$oFromFormat,$oToFormat) {
	if($oDate=="" || $oDate == null || !isset($oDate)) {
		return "";
	} else {
		$specChar = array(".","/");
		$oDate = str_replace($specChar,"-",$oDate);
		$oFromFormat = str_replace($specChar,"-",$oFromFormat);
		$oDate=explode(" ",$oDate);
		if (!ISSET($oDate[1])) $oDate[1]="";
		$dateParts=explode ("-", $oDate[0] );
		$fromParts=explode( "-", $oFromFormat );

		$dateArray = array();
		$dateArray[$fromParts[0]] = $dateParts[0];
		$dateArray[$fromParts[1]] = $dateParts[1];
		if ( isset( $dateParts[2] ) ) {
			$dateArray[$fromParts[2]] = $dateParts[2];
		}
		if (strpos($oToFormat,"/")!=false) $char = "/";
		elseif (strpos($oToFormat,".")!=false) $char = ".";
		else $char = "-";

		$toParts=explode( $char, $oToFormat );

		$returnDate					=	$oToFormat;
		foreach ( $toParts as $toPart ) {
			if ( ( $toPart == 'Y' ) || ( $toPart == 'y' ) ) {
				if ( array_key_exists( $toPart, $dateArray ) ) {
					$returnDate		=	str_replace($toPart,$dateArray[$toPart],$returnDate);
				} elseif ( $toPart == 'y' ) {
					$returnDate		=	str_replace($toPart,substr($dateArray['Y'],-2),$returnDate);
				} else {
					$returnDate		=	str_replace($toPart,$dateArray['y'],$returnDate);
				}
			}else {
				$returnDate			=	str_replace($toPart,substr($dateArray[$toPart],0,2),$returnDate);
			}
		}
		return $returnDate. ( ( $oDate[1] != "" ) ? " ". $oDate[1] : "" );
	}

}

/**
 * offsets date-time if time is present and $serverTimeOffset 1, then formats to CB's configured date-time format.
 *
 * @param mixed		string $date in "Y-m-d H:i:s" format, or 	int : unix timestamp
 * @param int $serverTimeOffset : 0: don't offset, 1: offset if time also in $date
 * @param boolean $showtime : false don't show time even if time is present in string
 * @return string date formatted
 */
function cbFormatDate( $date, $serverTimeOffset = 1, $showtime = true ) {
	global $ueConfig;

	if ( is_int( $date ) ) {
		$date = date( ($showtime) ? "Y-m-d H:i:s" : "Y-m-d", $date );
	}
	if ( ( $date!='' ) && ( $date != null ) && ( $date != '0000-00-00 00:00:00' ) && ( $date != '0000-00-00' ) ) {
		if ( strlen( $date ) > 10 ) {
			if ( ( $serverTimeOffset == 1 ) ) {
				$date = _old_cbFormatDate( $date, (($showtime) ? "%Y-%m-%d %H:%M:%S" : "%Y-%m-%d" ) );		// offsets datetime with server offset setting
			} else {
				$date = substr( $date, 0, 10 );
			}
		}
		$ret = dateConverter( $date, 'Y-m-d', $ueConfig['date_format'] );
	} else {
		$ret = "";
	}
	return $ret;
}
/**
* Returns formated date according to current local and adds time offset
*
* @param  string  $date    In datetime format
* @param  string  $format  Optional format for strftime
* @param  int     $offset  Time offset if different than global one
* @return string           Formated date
* @access private
*/
function _old_cbFormatDate( $date, $format = "", $offset = null ) {
	global $_CB_framework;

	if ( $format == '' ) {
		// %Y-%m-%d %H:%M:%S
		$format		=	defined( '_DATE_FORMAT_LC' ) ? _DATE_FORMAT_LC : ( defined( 'DATE_FORMAT_LC' ) ? DATE_FORMAT_LC : '%Y-%m-%d %H:%M:%S' );
	}
	if ( is_null( $offset ) ) {
		$offset		=	$_CB_framework->getCfg( 'offset' );
	}
	$regs			=	null;
	if ( $date && preg_match( "/([0-9]{4})-([0-9]{2})-([0-9]{2})[ ]([0-9]{2}):([0-9]{2}):([0-9]{2})/", $date, $regs ) ) {
		$date		=	mktime( $regs[4], $regs[5], $regs[6], $regs[2], $regs[3], $regs[1] );
		$date		=	$date > -1 ? strftime( $format, $date + ( $offset * 3600 ) ) : '-';
	}
	return $date;
}
/**
 * returns htmlspecialchar( formatted name of user as specified in format )
 *
 * @param  string  $name    Name            $user->name
 * @param  string  $uname   Username        $user->username
 * @param  string  $format  Format from CB  $ueConfig['name_format']
 * @return unknown
 */
function getNameFormat($name,$uname,$format) {
	if ( $name || $uname ) {
		switch ($format) {
			case 1 :
				$returnName = cbUnHtmlspecialchars($name);	//TBD: unhtml is kept for backwards database compatibility until CB 2.0
				break;
			case 2 :
				$returnName = cbUnHtmlspecialchars($name)." (".$uname.")";	//TBD: unhtml is kept for backwards database compatibility until CB 2.0
				break;
			case 4 :
				$returnName = $uname." (".cbUnHtmlspecialchars($name).")";	//TBD: unhtml is kept for backwards database compatibility until CB 2.0
				break;
			case 3 :
			default:
				$returnName = $uname;
				break;
		}
	} else {
		$returnName = _UE_UNNAMED_USER;
	}
	return htmlspecialchars($returnName);
}
/**
 * Splits $user->name into $user->firstname, $user->middlename, $user->lastname
 *
 * @param moscomprofilerUser $user
 */
function cbSplitSingleName( &$user ) {
	global $ueConfig;

	switch ( $ueConfig['name_style'] ) {
		case 2:
			// firstname + lastname:
			$posLname					=	strrpos( $user->name, ' ' );
			if ( $posLname !== false ) {
				$user->firstname		=	substr( $user->name, 0, $posLname );
				$user->lastname			=	substr( $user->name, $posLname + 1 );
			} else {
				$user->firstname		=	'';
				$user->lastname			=	$user->name;
			}
			// Equivalent to:
			// $sql = "INSERT IGNORE INTO #__comprofiler(id,user_id,lastname,firstname) "
 			//	  ." SELECT id,id, SUBSTRING_INDEX(name,' ',-1), "
 			//					 ."SUBSTRING( name, 1, length( name ) - length( SUBSTRING_INDEX( name, ' ', -1 ) ) -1 ) "
 			//	  ." FROM #__users";
		break;
		case 3:
			// firstname + middlename + lastname:
			$posMname					=	strpos( $user->name, ' ' );
			$posLname					=	strrpos( $user->name, ' ' );
			if ( $posLname !== false ) {
				$user->lastname			=	substr( $user->name, $posLname + 1 );
				$user->firstname		=	substr( $user->name, 0, $posMname );
				if ( $posMname !== $posLname ) {
					$user->middlename	=	substr( $user->name, $posMname + 1, $posLname - $posMname -1 );
				} else {
					$user->middlename	=	'';
				}
			} else {
				$user->firstname		=	'';
				$user->lastname			=	$user->name;
			}
			// Equivalent to:
			// $sql = "INSERT IGNORE INTO #__comprofiler(id,user_id,middlename,lastname,firstname) "
			//	 . " SELECT id,id,SUBSTRING( name, INSTR( name, ' ' ) +1,"
			//	 						  ." length( name ) - INSTR( name, ' ' ) - length( SUBSTRING_INDEX( name, ' ', -1 ) ) -1 ),"
			//	 		 ." SUBSTRING_INDEX(name,' ',-1),"
			//	 		 ." IF(INSTR(name,' '),SUBSTRING_INDEX( name, ' ', 1 ),'') "
			//	 . " FROM #__users";
    		break;
    	default:
 			// name only: nothing to do !
   			break;
    }
}
/**
 * CB 1.0 COMPATIBILITY FUNCTION: DO NOT USE FOR NEW DEVELOPMENT !
 * replaced by cbGetField temporarly
 * @access private
 *
 * @param unknown_type $oType
 * @param unknown_type $oValue
 * @param unknown_type $user
 * @param unknown_type $prefix
 * @param unknown_type $imgMode
 * @param unknown_type $linkURL
 * @param unknown_type $field
 * @return unknown
 */
function getFieldValue( $oType, $oValue=null, $user=null, $prefix=null, $imgMode=0, $linkURL=null, $field=null) {
	global $ueConfig, $_CB_database, $_CB_framework, $_PLUGINS;
	$oReturn="";
	switch ($oType){
		CASE 'checkbox':
			if($oValue!='' && $oValue!=null) {
				if($oValue==1) { $oReturn=_UE_YES;
				} elseif($oValue==0) { $oReturn=_UE_NO;
				} else { $oReturn=null; }
			}
		break;
		CASE 'select':
		CASE 'radio':
			$oReturn			=	htmlspecialchars( getLangDefinition( $oValue ) );
		break;
		CASE 'multiselect':
		CASE 'multicheckbox':
			$oReturn			=	array();
			$oReturn			=	explode("|*|",htmlspecialchars( $oValue ));
			for( $i = 0; $i < count($oReturn); $i++ ) {
   				$oReturn[$i]	=	htmlspecialchars( getLangDefinition( $oReturn[$i] ) );
			}
			$oReturn			=	implode( ', ', $oReturn );
		break;
		CASE 'date':
			if($oValue!='' || $oValue!=null) {
				if ($oValue!='0000-00-00 00:00:00' && $oValue!='0000-00-00') {
					$oReturn = cbFormatDate( htmlspecialchars( $oValue ) );
				} else {
					$oReturn = "";
				}
			}
		break;
		CASE 'primaryemailaddress':
			if ($ueConfig['allow_email_display']==3 || $imgMode != 0) {
				$oValueText = _UE_SENDEMAIL;
			} else {
				$oValueText = htmlspecialchars( $oValue );
			}
			$emailIMG = '<img src="' . $_CB_framework->getCfg( 'live_site' ) . '/components/com_comprofiler/images/email.gif" border="0" alt="' . _UE_SENDEMAIL. '" title="' . _UE_SENDEMAIL. '" />';
			switch ( $imgMode ) {
				case 0:
					$linkItemImg = null;
					$linkItemSep = null;
					$linkItemTxt = $oValueText;
				break;
				case 1:
					$linkItemImg = $emailIMG;
					$linkItemSep = null;
					$linkItemTxt = null;
				break;
				case 2:
					$linkItemImg = $emailIMG;
					$linkItemSep = ' ';
					$linkItemTxt = $oValueText;
				break;
			}
			//if no email or 4 (do not display email) then return empty string
			if ( $oValue==null || $ueConfig['allow_email_display']==4 || ($imgMode!=0 && $ueConfig['allow_email_display']==1) ) {
				$oReturn="";
			} else {
				switch ( $ueConfig['allow_email_display'] ) {
					case 1: //display email only
						$oReturn = moscomprofilerHTML::emailCloaking( htmlspecialchars( $oValue ), 0 );
						break;
					case 2: //mailTo link
						// cloacking doesn't cloack the text of the hyperlink, if that text does contain email addresses		//TODO: fix it.
						if ( ! $linkItemImg && $linkItemTxt == htmlspecialchars( $oValue ) ) {
							$oReturn  = moscomprofilerHTML::emailCloaking( htmlspecialchars( $oValue ), 1, '', 0 );
						} elseif ( $linkItemImg && $linkItemTxt != htmlspecialchars( $oValue ) ) {
							$oReturn  = moscomprofilerHTML::emailCloaking( htmlspecialchars( $oValue ), 1, $linkItemImg . $linkItemSep . $linkItemTxt, 0, false );
						} elseif ( $linkItemImg && $linkItemTxt == htmlspecialchars( $oValue ) ) {
							$oReturn  = moscomprofilerHTML::emailCloaking( htmlspecialchars( $oValue ), 1, $linkItemImg, 0, false ) . $linkItemSep;
							$oReturn .= moscomprofilerHTML::emailCloaking( htmlspecialchars( $oValue ), 1, '', 0 );
						} elseif ( ! $linkItemImg && $linkItemTxt != htmlspecialchars( $oValue ) ) {
							$oReturn  = moscomprofilerHTML::emailCloaking( htmlspecialchars( $oValue ), 1, $linkItemTxt, 0 );
						}
						break;
					case 3: //email Form (with cloacked email address if visible)
						$oReturn = "<a href=\""
						. cbSef("index.php?option=com_comprofiler&amp;task=emailUser&amp;uid=" . $user->id . getCBprofileItemid(true))
						. "\" title=\"" . _UE_MENU_SENDUSEREMAIL_DESC . "\">" . $linkItemImg . $linkItemSep;
						if ( $linkItemTxt && ( $linkItemTxt != _UE_SENDEMAIL ) ) {
							$oReturn .= moscomprofilerHTML::emailCloaking( $linkItemTxt, 0 );
						} else {
							$oReturn .= $linkItemTxt;
						}
						$oReturn .=  "</a>";
						break;
				}
			}
		break;
		CASE 'pm':
			$pmIMG				=	'<img src="' . $_CB_framework->getCfg( 'live_site' ) . '/components/com_comprofiler/images/pm.gif" border="0" alt="' . _UE_PM_USER . '" title="' . _UE_PM_USER . '" />';
			$oReturn="";
			global $_CB_PMS;
			$resultArray = $_CB_PMS->getPMSlinks($user->id, $_CB_framework->myId(), "", "", 1);	// toid,fromid,subject,message,1: link to compose new PMS message for $toid user.
			if (count($resultArray) > 0) {
				foreach ($resultArray as $res) {
				 	if (is_array($res)) {
						switch ($imgMode) {
							case 0:
								$linkItem=getLangDefinition($res["caption"]);
							break;
							case 1:
								$linkItem=$pmIMG;
							break;
							case 2:
								$linkItem=$pmIMG.' '.getLangDefinition($res["caption"]);
							break;
						}
						$oReturn .= "<a href=\"".cbSef($res["url"])."\" title=\"".getLangDefinition($res["tooltip"])."\">".$linkItem."</a>";
				 	}
				}
			}
		break;
		CASE 'emailaddress':
			if ( $oValue == null ) {
				$oReturn		=	'';
			} else {
				if ( $ueConfig['allow_email'] == 1 ) {
					$oReturn	=	moscomprofilerHTML::emailCloaking( htmlspecialchars( $oValue ), 1, "", 0 );
				} else {
					$oReturn	=	moscomprofilerHTML::emailCloaking( htmlspecialchars( $oValue ), 0 );
				}
			}
		break;
		CASE 'webaddress':
			IF($oValue==null) $oReturn="";
			ELSEIF($ueConfig['allow_website']==1) {
				$oReturn=array();
				$oReturn = explode("|*|",$oValue);
				if (count($oReturn) < 2) $oReturn[1]=$oReturn[0];
				$oReturn="<a href=\"http://".htmlspecialchars($oReturn[0])."\" target=\"_blank\" rel=\"nofollow\">".htmlspecialchars($oReturn[1])."</a>";
			} ELSE {
				$oReturn=$oValue;
			}
		break;
		CASE 'image':
			$cbUser		=&	CBuser::getInstance( null );
			$cbUser->loadCbRow( $user );
			$oValue		=	$cbUser->avatarFilePath( );
/*
			if(is_dir($_CB_framework->getCfg('absolute_path')."/components/com_comprofiler/plugin/language/".$_CB_framework->getCfg( 'lang' )."/images")) $fileLang=$_CB_framework->getCfg( 'lang' );
			else $fileLang="default_language";

			if($user->avatarapproved==0) $oValue="components/com_comprofiler/plugin/language/".$fileLang."/images/tnpendphoto.jpg";
			elseif(($user->avatar=='' || $user->avatar==null) && $user->avatarapproved==1) $oValue = "components/com_comprofiler/plugin/language/".$fileLang."/images/tnnophoto.jpg";
			elseif(strpos($user->avatar,"gallery/")===false) $oValue="images/comprofiler/tn".$oValue;
			else $oValue="images/comprofiler/".$oValue;

			if(!is_file($_CB_framework->getCfg('absolute_path')."/".$oValue)) $oValue = "components/com_comprofiler/plugin/language/".$fileLang."/images/tnnophoto.jpg";
			if(is_file($_CB_framework->getCfg('absolute_path')."/".$oValue)) {
*/
				$onclick = null;
				$aTag = null;
				if($ueConfig['allow_profilelink']==1) {
					$profileURL = cbSef("index.php?option=com_comprofiler&amp;task=userProfile&amp;user=".$user->id.getCBprofileItemid(true));
					// $onclick = "onclick=\"javascript:window.location='".$profileURL."'\"";
					$aTag = "<a href=\"".$profileURL."\">";
				}
				$oReturn = $aTag."<img src=\"".$oValue. "\" ".$onclick." alt=\"\" style=\"border-style: none;\" />".($aTag ? "</a>" : "");
/*
			}
*/
		break;
		CASE 'status':
			if ( $ueConfig['allow_onlinestatus'] == 1 ) {
				if ( isset( $user ) ) {
					$_CB_database->setQuery("SELECT COUNT(*) FROM #__session WHERE userid = " . (int) $user->id . " AND guest = 0");
					$isonline = $_CB_database->loadResult();
				} else {
					$isonline = $oValue;
				}
				if($isonline > 0) {
					$oValue = _UE_ISONLINE;
					// $img			=	'online.png';
					$class			=	'cb_online';

					// $onlineIMG= "<img src=\"components/com_comprofiler/images/online.gif\" border=\"0\" alt=\"".$oValue."\" title=\"".$oValue."\" />";
				} else {
					$oValue = _UE_ISOFFLINE;
					// $img			=	'offline.png';
					$class			=	'cb_offline';
					// $onlineIMG= "<img src=\"components/com_comprofiler/images/offline.gif\" border=\"0\" alt=\"".$oValue."\" title=\"".$oValue."\" />";
				}
				SWITCH($imgMode) {
					CASE 0:
						$oReturn=$oValue;
					break;
					CASE 1:
						//$oReturn=$onlineIMG;
						$oReturn	=	'<span class="' . $class . '" title="' . htmlspecialchars( $oValue ). '">&nbsp;</span>';
					break;
					CASE 2:
						//$oReturn=$onlineIMG.' '.$oValue;
						$oReturn	=	'<span class="' . $class . '"><span>' . htmlspecialchars( $oValue ) . '</span></span>';
					break;
				}
			}
		break;
		CASE 'formatname':
			if ($linkURL && $ueConfig['allow_profilelink']==1) $oReturn = "<a href=\"".$linkURL."\">";
			$oReturn .= getNameFormat($user->name,$user->username,$ueConfig['name_format']);
			if ($linkURL && $ueConfig['allow_profilelink']==1) $oReturn .= "</a>";
		break;
		CASE 'textarea':
			$oReturn = nl2br(htmlspecialchars($oValue));
		break;
		CASE 'delimiter':
			if ( ( $field !== null ) && ( $user !== null ) ) {
				$oReturn = cbReplaceVars( getLangDefinition( cbUnHtmlspecialchars( $field->description ) ), $user );	//TBD: unhtml is kept for backwards database compatibility until CB 2.0
			} else  {
				$oReturn = cbReplaceVars( getLangDefinition( $oValue ), $user );
			}
		break;
		CASE 'editorta':
			$cbFielfs		=	new cbFields();
			$badHtmlFilter = & $cbFielfs->getInputFilter( array (), array (), 1, 1 );
			if ( isset( $ueConfig['html_filter_allowed_tags'] ) && $ueConfig['html_filter_allowed_tags'] ) {
				$badHtmlFilter->tagBlacklist = array_diff( $badHtmlFilter->tagBlacklist, explode(" ", $ueConfig['html_filter_allowed_tags']) );
			}
			$oReturn = $cbFielfs->clean( $badHtmlFilter, $oValue );
			unset( $cbFielfs );
		break;
		CASE 'predefined':
			if ($linkURL && $ueConfig['allow_profilelink']==1) $oReturn = "<a href=\"".$linkURL."\">";
			$oReturn .= htmlspecialchars(cbUnHtmlspecialchars($oValue));		// needed for #__users:name (has &039; instead of ' in it...)		//TBD: unhtml is kept for backwards database compatibility until CB 2.0
			if ($linkURL && $ueConfig['allow_profilelink']==1) $oReturn .= "</a>";
		break;
		CASE 'text':
		DEFAULT:
			if ( $field != null ) {
				$args		=	array( &$field, &$user, 'html', 'profile' );
				$oReturn	=	$_PLUGINS->callField( $oType, 'getField', $args, $field );
			} else {
				$oReturn	=	htmlspecialchars( $oValue );
			}
			break;
	}
	if ($prefix != null && ($oReturn != null || $oReturn != '')) {
		$oReturn = $prefix.$oReturn;
	}
	return $oReturn;
}


/** CORRECTION FOR OLD-STYLE TEMPLATES:
*/
/**
* DEPRECIATED: DO NOT USE.
* Use: addHeadStyleSheet, addHeadScriptUrl, and other $_CB_framework->document->addHead functions.
*
* Outputs once an arbitrary html text into head tags if possible and configured, otherwise echo it.
* @param int     $old_ui  pass it 1 : Don't care (was $ui)
* @param string  $text    html text for header
*/
function addCbHeadTag($old_ui,$text) {
	global $_CB_framework, $_CB_outputedHeads;
	if ( $_CB_framework->getCfg( 'debug' ) == 1 ) {
		$bt		=	@debug_backtrace();
		trigger_error( sprintf('addCbHeadTag CALLED FROM: %s line %s (function %s). This is old depreciated old CB 1.2 RC API. (Use: addHeadStyleSheet, addHeadScriptUrl, and other $_CB_framework->document->addHead functions).' . "\n", @$bt[0]['file'], @$bt[0]['line'], @$bt[1]['class'] . ':' . @$bt[1]['function'] ), E_USER_NOTICE );
	}
	if ( ! in_array( $text, $_CB_outputedHeads) ) {
		$_CB_framework->document->addHeadCustomHtml( $text );
		$_CB_outputedHeads[] = $text;
	}
}
/**
* Outputs an arbitrary html text into head tags if possible and configured, otherwise echo it.
* Adds RTL overrides in 'rtl.css' on 'template.css' file if in RTL output mode from template's rtl.css file if existing, otherwise from default template.
*
* @param  int     $obsoleteUi    (was int $ui user interface : 1: frontend, 2: backend)
* @param  string  $templatefile
* @param  string  $media         e.g. "screen"
*/
function outputCbTemplate( $obsoleteUi = 0, $templateFile = 'template.css', $media = null ) {
	global $_CB_framework;

	$_CB_framework->document->addHeadStyleSheet( selectTemplate() . $templateFile, false, $media );
	// add RTL overrides if in RTL output mode from template's rtl.css file if existing, otherwise from default template:
	if ( ( $templateFile === 'template.css' ) && ( $_CB_framework->document->getDirection() == 'rtl' ) ) {
		if ( file_exists( selectTemplate( 'absolute_path' ) ) ) {
			$rtlPath		=	selectTemplate() . 'rtl.css';
		} else {
			$rtlPath		=	selectTemplate( 'live_site', 'default' );
		}
		$_CB_framework->document->addHeadStyleSheet( $rtlPath . '/rtl.css', false, $media );
	}
	$useragent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "unknown";
	if ( strstr( $useragent, 'MSIE' ) ) {
		$matches	=	null;
		if ( preg_match( '/MSIE\s(\d+\.\d+)/i', $useragent, $matches ) ) {
			if ( $matches[1] < 9 ) {
				// Internet Explorer before version 9 doesn't understand curvy corners: help it:
				$_CB_framework->document->addHeadScriptUrl( '/components/com_comprofiler/js/curvycorners.js', true );
			}
		}
	}
}
/**
* Outputs an arbitrary html text into head tags if possible and configured, otherwise echo it.
* @param int $obsoleteUi  (was int user interface : 1: frontend, 2: backend)
*/
function outputCbJs( ) {
	static $needOut	=	1;
	if ( $needOut-- ) {
		global $_CB_framework;
		$_CB_framework->document->addHeadScriptUrl( '/components/com_comprofiler/js/cb12.js', true );
	}
}

function utf8RawUrlDecode ($source, $charset = null ) {
	if ( $charset === null ) {
		global $_CB_framework;
		$charset	=	$_CB_framework->outputCharset();
	}
	$decodedStr = '';
	$pos = 0;
	$len = strlen ($source);
	while ($pos < $len) {
		$charAt = substr ($source, $pos, 1);
		if ($charAt=='%') {
			$pos++;
			$charAt = substr ($source, $pos, 1);
			if ($charAt=='u') { // we got a unicode character
				$pos++;
				$unicodeHexVal = substr ($source, $pos, 4);
				$unicode = hexdec ($unicodeHexVal);
				$entity = "&#". $unicode . ';';
				$decodedStr .= html_entity_decode($entity, ENT_QUOTES, $charset );
				$pos += 4;
			} else { // we have an escaped ascii character
				$hexVal = substr ($source, $pos, 2);
				if ( $charset == 'UTF-8' ) {
					$decodedStr .= utf8_encode( chr( hexdec( $hexVal ) ) );
				} else {
					$decodedStr .= chr( hexdec( $hexVal ) );
				}
				$pos += 2;
			}
		} else {
			$decodedStr .= $charAt;
			$pos++;
		}
	}
	return $decodedStr;
}

/** Escapes with real database escaping algorythm (stripslashes first if magic_quotes_gpc are set to take care of MB charsets) */
function cbGetEscaped( $string ) {
	global $_CB_database;
	if (get_magic_quotes_gpc()==1) {
		return ( $_CB_database->getEscaped( stripslashes( $string ) ) );
	} else {
		return ( $_CB_database->getEscaped( $string ) );
	}
}

/** Unescapes from PHP escaping algorythm if magic_quotes are set */
function cbGetUnEscaped( $string ) {
	if (get_magic_quotes_gpc()==1) {
		// if (ini_get('magic_quotes_sybase')) return str_replace("''","'",$string);
		return ( stripslashes( $string ));			// this does not handle it correctly if magic_quotes_sybase is ON.
	} else {
		return ( $string );
	}
}

/** Unescapes SQL string except % and _ . So it's reverse of $_CB_database->getEscaped... */
function cbUnEscapeSQL($string) {
	return str_replace(array("\\0","\\n","\\r","\\\\","\\'","\\\"","\\Z"),array("\x00","\n","\r","\\","'","\"","\x1a"),$string);
}

/** Escapes SQL search strings. To be used only on escaped strings
 *	@deprecated cb 1.2.3  use: $_CB_database->getEscaped( $string, true ) on non-escaped string
 */
function cbEscapeSQLsearch( $string ) {
	return str_replace(array("%","_"),array("\\%","\\_"), $string );
}

/** Unescapes SQL search strings
 *	@deprecated cb 1.2.3
 */
function cbUnEscapeSQLsearch( $string ) {
	return str_replace(array("\\%","\\_"),array("%","_"), $string );
}
/**
 * @deprecated CB 1.2.2 (not used at all in CB and CB Team plugins, so can be removed)
 */
function unAmpersand($text) {
	return str_replace( '&amp;','&', $text);
}
/**
 * @deprecated CB 1.2.2
 * Legacy function: use cbUnHtmlspecialchars instead !
 */
if ( ! is_callable( 'unHtmlspecialchars' ) ) {
	function unHtmlspecialchars( $text ) {
		return cbUnHtmlspecialchars( $text );
	}
}
/**
 * @deprecated CB 1.2.2  (not used at all in CB and CB Team plugins after CB 1.2.2, so can be removed)
 * Legacy function: use CBTxt::html_entity_decode( $string ) instead !
 */
function unhtmlentities( $string, $quotes = ENT_COMPAT, $charset = "ISO-8859-1" ) {
	return CBTxt::_unhtmlentities( $string, $quotes, $charset );
}

/**
 * Convert HTML entities to plaintext
 * Rewritten in CB to use CB's own version of html_entity_decode where innexistant or buggy in < joomla 1.5
 *
 * @access	protected
 * @param	string	$source
 * @return	string	Plaintext string
 */
function cb_html_entity_decode_all( $source ) {
	global $_CB_framework;

	$charset = $_CB_framework->outputCharset();
	// entity decode : use own version of html_entity_decode :
	$source = CBTxt::_unhtmlentities( $source, ENT_QUOTES, $charset );
	if ( $charset == 'UTF-8' ) {
		// convert decimal
		$source = preg_replace('/&#(\d+);/me', "utf8_encode(chr(\\1))", $source);	 		// decimal notation
		// convert hex
		$source = preg_replace('/&#x([a-f0-9]+);/mei',"utf8_encode(chr(0x\\1))", $source);	// hex notation
	} else {
		// convert decimal
		$source = preg_replace('/&#(\d+);/me', "chr(\\1)", $source); // decimal notation
		// convert hex
		$source = preg_replace('/&#x([a-f0-9]+);/mei', "chr(0x\\1)", $source); // hex notation
	}
	return $source;
}
/**
 * @deprecated CB 1.2.2 : use CBTxt::utf8ToISO( $string )
 * @param unknown_type $string
 */
function utf8ToISO( $string ) {
	return CBTxt::utf8ToISO( $string );
}
function ISOtoUtf8( $string ) {
	global $_CB_framework;

	$iso	=	$_CB_framework->outputCharset();
	if ($iso == "UTF-8") {
		return $string;
	} else {
		return CBTxt::charsetConv( $string, $iso, 'UTF-8' );
	}
}
/**
 * Checks if begin of $subject matches a $search string
 *
 * @param string|array of string $subject
 * @param string|array of string $search
 * @return boolean true if a match is found
 */
function cbStartOfStringMatch( $subject, $search ) {
	if ( is_array( $search)) {
		foreach ($search as $s ) {
			if ( substr( $subject, 0, strlen( $s ) ) == $s ) {
				return true;
			}
		}
		return false;
	}
	return( substr( $subject, 0, strlen( $search ) ) == $search );
}

/**
 * UTF8 helper functions
 * @license    LGPL (http://www.gnu.org/copyleft/lesser.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
/**
 * Unicode aware replacement for strlen()
 *
 * utf8_decode() converts characters that are not in ISO-8859-1
 * to '?', which, for the purpose of counting, is alright - It's
 * even faster than mb_strlen.
 *
 * @author <chernyshevsky at hotmail dot com>
 * @see    strlen()
 * @see    utf8_decode()
 */
function cbutf8_strlen($string) {
	return strlen(utf8_decode($string));
}
function cbIsoUtf_strlen( $string ) {
	global $_CB_framework;

	if ( $_CB_framework->outputCharset() == 'UTF-8' ) {
		return cbutf8_strlen( $string );
	} else {
		return strlen( $string );
	}
}
/**
 * Unicode aware replacement for substr()
 *
 * @author lmak at NOSPAM dot iti dot gr
 * @link   http://www.php.net/manual/en/function.substr.php
 * @see    substr()
 */
function cbutf8_substr( $str, $start, $length = null ) {
	if ( function_exists( 'mb_substr' ) ) {
		return mb_substr( $str, $start, $length, 'UTF-8' );
	} else {
		$ar = null;
		preg_match_all("/./u", $str, $ar);

		if($length != null) {
			return join("",array_slice($ar[0],$start,$length));
		} else {
			return join("",array_slice($ar[0],$start));
		}
	}
}
function cbIsoUtf_substr( $str, $start, $length = null ) {
	global $_CB_framework;

	if ( $_CB_framework->outputCharset() == 'UTF-8' ) {
		return cbutf8_substr(  $str, $start, $length );
	} else {
		if ( $length === null ) {
			return substr( $str, $start );
		} else {
			return substr(  $str, $start, $length );
		}
	}
}
/**
* Makes a variable safe to display in forms
*
* Object parameters that are non-string, array, object or start with underscore
* will be converted
*
* @param  stdClass      $mixed         An object to be parsed
* @param  int           $quote_style   The optional quote style for the htmlspecialchars function
* @param  string|array  $exclude_keys  An optional single field name or array of field names not to be parsed (eg, for a textarea)
* @access private
*/
function _cbMakeHtmlSafe( &$mixed, $quote_style = ENT_QUOTES, $exclude_keys = '' ) {
	if ( is_object( $mixed ) ) {
		foreach ( get_object_vars( $mixed ) as $k => $v ) {
			if ( is_array( $v ) || is_object( $v ) || $v == NULL || substr( $k, 1, 1 ) == '_' ) {
				continue;
			}
			if ( is_string( $exclude_keys ) && ( $k == $exclude_keys ) ) {
				continue;
			} else if ( is_array( $exclude_keys ) && in_array( $k, $exclude_keys ) ) {
				continue;
			}
			$mixed->$k		=	htmlspecialchars( $v, $quote_style );
		}
	}
}
/**
 * Reads the files and directories in a directory
 * Backend-only
 *
 * @param  string   $path      The file system path
 * @param  string   $filter    A filter for the names
 * @param  boolean  $recurse   Recurse search into sub-directories
 * @param  boolean  $fullpath  True if to prepend the full path to the file name
 * @access private
 */
function cbReadDirectory( $path, $filter='.', $recurse=false, $fullpath=false  ) {
	$arr						=	array();
	if ( ! @is_dir( $path ) ) {
		return $arr;
	}
	$handle						=	opendir( $path );

	while ( true == ( $file = readdir( $handle ) ) ) {
		if ( ! in_array( $file, array( '.', '..', '.svn', '.git', '.gitignore', '.gitattributes', '__MACOSX' ) ) ) {
			$dir				=	_cbPathName( $path . '/' . $file, false );
			if ( preg_match( "/$filter/", $file ) ) {
				if ( $fullpath ) {
					$arr[]		=	trim( _cbPathName( $path . '/' . $file, false ) );
				} else {
					$arr[]		=	trim( $file );
				}
			}
			if ( $recurse && is_dir( $dir ) ) {
				$arr2			=	cbReadDirectory( $dir, $filter, $recurse, $fullpath );
				if ( ! $fullpath ) {
					foreach ( $arr2 as $k => $n ) {
						$arr2[$k]	=	$file . '/' . $n;
					}
				}
				$arr			=	array_merge( $arr, $arr2 );
			}
		}
	}
	closedir( $handle );
	asort( $arr );
	return $arr;
}

/**
* Function to strip additional / or \ in a path name
* Backend-only
*
* @param  string   $p_path              The path
* @param  boolean  $p_addtrailingslash  Add trailing slash
* @access private
*/
function _cbPathName( $p_path, $p_addtrailingslash = true ) {
	if ( substr( PHP_OS, 0, 3 ) == 'WIN' )	{
		$f				=	'/';
		$t				=	'\\';
	} else {
		$f				=	'\\';
		$t				=	'/';
	}

	$retval				=	str_replace( $f, $t, $p_path );						// fix /
	if ( $p_addtrailingslash ) {
		if ( substr( $retval, -1 ) != $t ) {
			$retval		.=	$t;
		}
	}
	$prepend			=	( substr( $retval, 0, 2 ) == $t . $t ) ? $t : '';	// check for UNC path //
	$retval				=	$prepend . str_replace( $t . $t, $t, $retval );		// Remove double // while keeping UNC if needed
	return $retval;
}

/**
* Utility function to include ToolTips and set defaults for CB
* @param int   $obsoleteUi    Obsolete (was $ui  user interface : 1: frontend, 2: backend NOT USED ANYMORE !)
* @param string width of tooltips
* @return void
*/
function initToolTip( $obsoleteUi = 0, $width='250' ) {
	static $outputed	=	false;

	if ( ! $outputed ) {
		global $_CB_framework;

		$postScript		=	'overlib_pagedefaults(WIDTH,'.$width.',VAUTO,RIGHT,AUTOSTATUSCAP, CSSCLASS,'
						.	'TEXTFONTCLASS,\'cb-tips-font\',FGCLASS,\'cb-tips-fg\',BGCLASS,\'cb-tips-bg\''
						.	',CAPTIONFONTCLASS,\'cb-tips-capfont\', CLOSEFONTCLASS, \'cb-tips-closefont\');'
						;
		$_CB_framework->document->addHeadScriptUrl( '/components/com_comprofiler/js/overlib_all_mini.js', false, null, $postScript );
		$outputed		=	true;
	}
	return null;
}
	// This function is partly "repeated" here for mambo 4.5 backwards compatibility.
	// Corrected: VAUTO, AUTOSTATUS, <img> tag xhtml 1.0 trans compatibility.

	/**
* Utility function to provide ToolTips
* @param int user interface : 1: frontend, 2: backend (not used anymore
* @param string ToolTip text
* @param string Box title
* @returns HTML code for ToolTip
*/
function CB45_mosToolTip( $ui, $tooltip, $title='', $width='', $image='tooltip.png', $htmltext='', $href='', $style='', $olparams='',$click=false, $altText=' ' ) {
	if ( $altText == ' ' ) {
		$altText	=	' alt="' . strip_tags( sprintf( _UE_INFORMATION_FOR_FIELD, htmlspecialchars( stripslashes( $title ) ), htmlspecialchars( stripslashes( $tooltip ) ) ) ) . '"';
	}
	if ( $width ) {
		$width = ', WIDTH, \''. addslashes( $width ) .'\'';
	}
	if ( $title ){
		$title = ', CAPTION, \''. addslashes( stripslashes( $title ) ) .'\'';
	}
	if ( $olparams ) {
		$olparams = ', '.$olparams;
	}
	if ($click) {
		$tooltipcode = " onclick=\"return overlib('" . addslashes( str_replace( array( "\n", "\r" ), ' ', stripslashes( $tooltip ) ) ) . "'" . $title . $width . $olparams . ");\"";
	} else {
		$tooltipcode = " onmouseover=\"return overlib('" . addslashes( str_replace( array( "\n", "\r" ), ' ', stripslashes( $tooltip ) ) ) . "'" . $title . $width . $olparams . ");\" onmouseout=\"return nd();\"";
	}
	if ( !$htmltext ) {
		if ($image == 'tooltip.png') {
			$style = 'style="border:0" width="16" height="16" title=""';
		}
		$image 	= selectTemplate($ui). $image;
		if ($href) {
			$htmltext = '<img src="'. $image .'" '. $style . $altText . ' />';
		}
	}
	if ( $href ) {
		$tip 	= "<a href=\"". $href . '"' . $tooltipcode . ' ' . $style .">". $htmltext ."</a>";
	} else {
		if ($htmltext) {
			$tip 	= "<span" . $tooltipcode . ' ' . $style . ">" . $htmltext ."</span>";
		} else {
			$tip 	= '<img src="'. $image .'" ' . $style . $altText . $tooltipcode . " />";
		}
	}
	return $tip;
}
// $ui is not used anymore:
function cbFieldTip($ui, $fieldTip, $tipTitle='', $width='', $image='images/mini-icons/icon-16-info.png', $htmltext='', $href='', $style='', $olparams='',$click=false) {
	$altText	=	' alt="' . strip_tags( sprintf( _UE_INFORMATION_FOR_FIELD, htmlspecialchars( $tipTitle ), htmlspecialchars( $fieldTip ) ) ) . '" title=""';
	// overlib_mini does not support newlines:
	if (strpos($fieldTip, "&lt;") === false) {
		$fieldTip = str_replace("\r\n", "&lt;br /&gt;", $fieldTip);
		$fieldTip = str_replace("\n", "&lt;br /&gt;", $fieldTip);
	} else {
		$fieldTip = str_replace("\r\n", " ", $fieldTip);
		$fieldTip = str_replace("\n", " ", $fieldTip);
	}
	$fieldTip = str_replace(array('"','<','>',"\\"), array("&quot;","&lt;","&gt;","\\\\"), $fieldTip);
	$tipTitle = str_replace(array('"','<','>',"\\"), array("&quot;","&lt;","&gt;","\\\\"), $tipTitle);
	$fieldTip = str_replace(array("'","&#039;","&#39;"), "\\'", $fieldTip);
	$tipTitle = str_replace(array("'","&#039;","&#39;"), "\\'", $tipTitle);
	return CB45_mosToolTip( $ui, $fieldTip, $tipTitle, $width, $image, $htmltext, $href, $style, $olparams, $click, $altText );
}

/**
 * Shows tooltip icons or explanation line for fields
 *
 * @param int         $ui            =1 front-end, =2 back-end
 * @param boolean|int $oReq          =true|1: field required
 * @param boolean|int $oProfile      =true|1: on profile, =false|0: not on profile, =null: icon not shown at all
 * @param string      $oDescription  description to show in tooltip ove a i (if any)
 * @param string      $oTitle        Title of description to show in tooltip
 * @param boolean     $showLabels    Description to show in tooltip : TRUE: show info of labels, 2: show info but not about the 'i';
 * @return string                    HTML code.
 */
function getFieldIcons($ui, $oReq, $oProfile, $oDescription="", $oTitle="", $showLabels=false) {
	global $ueConfig;

	if ( isset( $ueConfig['icons_display'] ) ) {
		$display						 =	$ueConfig['icons_display'];
	} else {
		$display						 =  3;
	}
	$templatePath						 =	selectTemplate($ui);
	$oReturn							 =	"";
	if ( $display & 1 ) {
		if ($oReq)				$oReturn .= " <img src='".$templatePath."images/mini-icons/icon-16-required.png' width='16' height='16' alt='* "._UE_FIELDREQUIRED."' title='"._UE_FIELDREQUIRED."' />";
		if ($showLabels)		$oReturn .= " " . _UE_FIELDREQUIRED_SHORT . ( ( $display > 1 ) && ( ( $oProfile !== null ) && ($showLabels !== 2 ) ) ? ' | ' : '' );
	}
	if ( $display & 2 ) {
		if ( $oProfile !== null ) {
			if ($oProfile)		$oReturn .= " <img src='".$templatePath."images/mini-icons/icon-16-profile-yes.png' width='16' height='16' alt='"._UE_FIELDONPROFILE."' title='"._UE_FIELDONPROFILE."' />";
			if ($showLabels)	$oReturn .= " " . _UE_FIELDONPROFILE_SHORT . " | ";
			if ((!$oProfile) || $showLabels) {
								$oReturn .= " <img src='".$templatePath."images/mini-icons/icon-16-profile-no.png' width='16' height='16' alt='"._UE_FIELDNOPROFILE."' title='"._UE_FIELDNOPROFILE."' />";
			}
			if ($showLabels)	$oReturn .= " " . _UE_FIELDNOPROFILE_SHORT . ( $display > 3 ? ' | ' : '' );
		}
	}
	if ( $display & 4 ) {
		if ($oDescription)		$oReturn .= " " . cbFieldTip( $ui, getLangDefinition( $oDescription ), getLangDefinition( $oTitle ) );
		if ($showLabels===true)	$oReturn .= " " . cbFieldTip( $ui, _UE_FIELDDESCRIPTION, "?" ) . " " . _UE_FIELDDESCRIPTION_SHORT;
	}
	return "<span class='cbFieldIcons".(($showLabels) ? "Labels" : "")."'>".$oReturn."</span>";
}

/**
 * Replaces [fieldname] by the content of the user row (except for [password])
 *
 * @param  string         $msg
 * @param  stdClass       $row
 * @param  boolean|array  $htmlspecialchars  on replaced values only: FALSE : no htmlspecialchars, TRUE: do htmlspecialchars, ARRAY: callback method
 * @param  boolean        $menuStats
 * @param  array          $extraStrings
 * @param  boolean        $translateLanguage  on $msg only
 * @return string
 */
function cbReplaceVars( $msg, &$row, $htmlspecialchars = true, $menuStats = true, $extraStrings = null, $translateLanguage = true ){
	if ( $extraStrings === null ) {
		$extraStrings	=	array();
	}
	if ( isset( $row->id ) && is_object( $row ) && ( strtolower( get_class( $row ) ) == 'moscomprofileruser' ) ) {
		$cbUser			=&	CBuser::getInstance( $row->id );
	} else {
		$cbUser			=	new CBuser();
		$cbUser->loadCbRow( $row );
	}
	return $cbUser->replaceUserVars( $msg, $htmlspecialchars, $menuStats, $extraStrings, $translateLanguage );
}

/**
* Random string of a-z,A-Z,0-9 generator
*
* @param  int       $stringLength  number of chars
* @return password
*/
function cbMakeRandomString( $stringLength = 8, $noCaps = false ) {
	global $_CB_framework;

	if ( $noCaps ) {
		$chars		=	'abchefghjkmnpqrstuvwxyz0123456789';
	} else {
		$chars		=	'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	}
	$len			=	strlen( $chars );
	$rndString		=	'';

	$stat			=	@stat( __FILE__ );
	if ( ! is_array( $stat ) ) {
		$stat		=	array();
	}
	$stat[]			=	@php_uname();
	$stat[]			=	uniqid( '', true );
	$stat[]			=	microtime();
	$stat[]			=	$_CB_framework->getCfg( 'secret' );
	$stat[]			=	mt_rand( 0, mt_getrandmax() );
	mt_srand( crc32( implode( ' ', $stat ) ) );

	for ( $i = 0; $i < $stringLength; $i++ ) {
		$rndString	.=	$chars[mt_rand( 0, $len - 1 )];
	}
	return $rndString;
}
/**
* @deprecated CB 1.2.2 (backwards compatibility: obsolete):
* use $user->verifyPassword() and $user->hashAndSaltPassword()
*
* Generate the hashed/salted/encoded password for the database
* and to check the password at login:
* if $row provided, it is checking the existing password (and update if needed)
* if not provided, it will generate a new hashed password
*
* @param  string              $passwd  cleartext
* @param  moscomprofilerUser  $row
* @return string|boolean      salted/hashed password if $row not provided, otherwise TRUE/FALSE on password check
*/
function cbHashPassword( $passwd, $row = null ) {
	if ( $row ) {
		return $row->verifyPassword( $passwd );
	} else {
		global $_CB_database;
		cbimport( 'cb.tables' );
		$row					=	new moscomprofilerUser( $_CB_database );
		return $row->hashAndSaltPassword( $passwd );
	}
}
// CB 1.1 backwards compatibility: obsolete: use cbSef
function cbSefRelToAbs( $string, $htmlSpecials = true ) {
	return cbSef( $string, $htmlSpecials );
}

/**
 * CB registration spam protections:
 */
function cbGetRegAntiSpams( $decrement = 0, $salt0 = null, $salt1 = null ) {
	global $_CB_framework;
	if ( ( $salt0 === null ) || ( $salt1 === null ) ) {
		static $formSalt	=	null;
		if ( $formSalt === null ) {
			$formSalt		=	cbMakeRandomString( 16 );
		}
		$salt0				=	$formSalt;
		$salt1				=	$formSalt;
	}
	$time				 	=	time();
	$valtime				=	( (int) ( $time / 10800 )) - $decrement;
	// no IP addresses here, since on AOL it changes all the time.... $hostIPs = cbGetIParray();
	if ( ( strlen( $salt0 ) == 16 ) && ( strlen( $salt1 ) == 16 ) ) {
		$validate = array();
		$validate[0]		=	'cbrv1_' . md5( $salt0 . $_CB_framework->getCfg('secret') . $valtime ) . '_' . $salt0;
		$validate[1]		=	'cbrv1_' . md5( $salt1 . $_CB_framework->getCfg( 'db' )   . $valtime ) . '_' . $salt1;
		return $validate;
	} else {
		_cbExpiredSessionJSterminate();
		exit;
	}
}
function cbGetRegAntiSpamFieldName() {
	return 'cbrasitway';
}
function cbGetRegAntiSpamCookieName( $fieldValue ) {
	return 'cbrvs';
/*
	$md5Part					=	substr( $fieldValue, 6, 32 );
	if ( ! preg_match( '/[0-9a-z]{32}/i', $md5Part ) ) {
		return false;
	}
	return 'cbrvs_' . $md5Part;
*/
}
function cbGetRegAntiSpamInputTag( $cbGetRegAntiSpams = null ) {
	if ( $cbGetRegAntiSpams === null ) {
		$cbGetRegAntiSpams		=	cbGetRegAntiSpams();
	}
	cbimport( 'cb.session' );
	CBCookie::setcookie( cbGetRegAntiSpamCookieName( $cbGetRegAntiSpams[0] ), $cbGetRegAntiSpams[1], false );
	return "<input type=\"hidden\" name=\"" . cbGetRegAntiSpamFieldName() ."\" value=\"" .  $cbGetRegAntiSpams[0] . "\" />\n";
}

function cbRegAntiSpamCheck( $mode = 1 ) {
	global $_POST;

	$validateValuePost	 		=	cbGetParam( $_POST, cbGetRegAntiSpamFieldName() );
	$validateCookieName			=	cbGetRegAntiSpamCookieName( $validateValuePost );
	if ( $validateCookieName === false ) {
		$i						=	2;
	} else {
		cbimport( 'cb.session' );
		$validateValueCookie	=	CBCookie::getcookie( $validateCookieName );
		$parts0					=	explode( '_', $validateValuePost );
		$parts1					=	explode( '_', $validateValueCookie );
		if ( ( count( $parts0 ) == 3 ) && ( count( $parts1 ) == 3 ) ) {
			for($i = 0; $i < 2; $i++) {
				$validate		=	cbGetRegAntiSpams( $i, $parts0[2], $parts1[2] );
				if ( ( $validateValuePost == $validate[0] ) && ( $validateValueCookie == $validate[1] ) ) {
					break;
				}
			}
		} else {
			$i					=	2;
		}
	}
	if ( $i == 2 ) {
		if ( $mode == 2 ) {
			return false;
		}
		_cbExpiredSessionJSterminate( 200 );
		exit;
	}
	return true;
}

/**
 * CB email to user spam protections:
 */
function cbGetAntiSpams( $salt0 = null, $salt1 = null ) {
	global $_CB_framework, $_CB_database;

	if ( ( $salt0 === null ) || ( $salt1 === null ) ) {
		$salt0					=	cbMakeRandomString( 32 );
		$salt1					=	$salt0;
	}
	$query						=	"SELECT message_number_sent, message_last_sent FROM #__comprofiler WHERE id = " . (int) $_CB_framework->myId();
	$_CB_database->setQuery( $query );
	$users						=	$_CB_database->loadObjectList();
	if ( ( ! $_CB_database->getErrorNum() ) && ( strlen( $salt0 ) == 32 ) && ( strlen( $salt1 ) == 32 ) && ( count( $users ) == 1 ) ) {
		$message_number_sent	=	$users[0]->message_number_sent;
		$message_last_sent		=	$users[0]->message_last_sent;
		$validate				=	array();
		$validate[0]			=	'cbsv1_' . md5( $salt0 . $_CB_framework->getCfg('secret') .  $_CB_framework->getCfg( 'db' ) . $message_number_sent . $message_last_sent . $_CB_framework->myId() )       . '_' . $salt0;
		$validate[1]			=	'cbsv1_' . md5( $salt1 . $_CB_framework->getCfg('secret') .  $_CB_framework->getCfg( 'db' ) . $message_number_sent . $message_last_sent . $_CB_framework->myUsername() ) . '_' . $salt1;
		return $validate;
	} else {
		_cbExpiredSessionJSterminate();
		exit;
	}
}
function cbGetAntiSpamInputTag() {
	$validate					=	cbGetAntiSpams();
	cbimport( 'cb.session' );
	CBCookie::setcookie( 'cbvs', $validate[1], false );
	return "<input type=\"hidden\" name=\"cbvssps\" value=\"" .  $validate[0] . "\" />\n";
}
function cbAntiSpamCheck( $autoBack = true ) {
	global $_POST;

	$validateValuePost	 	=	cbGetParam( $_POST, 'cbvssps', '' );
	cbimport( 'cb.session' );
	$validateValueCookie	=	CBCookie::getcookie( 'cbvs' );
	$parts0					=	explode( '_', $validateValuePost );
	$parts1					=	explode( '_', $validateValueCookie );
	if ( ( count( $parts0 ) == 3 ) && ( count( $parts1 ) == 3 ) ) {
		$validate			=	cbGetAntiSpams( $parts0[2], $parts1[2] );
	}
	if ( ( count( $parts0 ) != 3 ) || ( count( $parts1 ) != 3 ) || ( $validateValuePost !== $validate[0] ) || ( $validateValueCookie !== $validate[1] ) ) {
		if ( $autoBack ) {
			_cbExpiredSessionJSterminate();
		} else {
			return _UE_SESSION_EXPIRED . ' ' . _UE_PLEASE_REFRESH;
		}
	}
	return null;
}
function cbSpamProtect( $userid, $count ) {
	global $_CB_database;

	$maxmails		= 10;		// mails per
	$maxinterval	= 24*3600;	// hours (expressed in seconds) limit

	$time = time();

	$query = "SELECT message_number_sent, message_last_sent FROM #__comprofiler WHERE id = " . (int) $userid;
	$_CB_database->setQuery($query);
	$users = $_CB_database->loadObjectList();
	if ( ( ! $_CB_database->getErrorNum() ) && ( count($users) == 1 ) ) {
		$message_number_sent	= $users[0]->message_number_sent;
		$message_last_sent		= $users[0]->message_last_sent;
		if ( $message_last_sent != '0000-00-00 00:00:00' ) {
			list( $y, $c, $d, $h, $m, $s ) = sscanf( $message_last_sent, "%4d-%2d-%2d\t%2d:%2d:%2d" );
			$expiryTime = mktime($h, $m, $s, $c, $d, $y) + $maxinterval;
			if ( $time < $expiryTime ) {
				if ( $message_number_sent >= $maxmails ) {
					if (!defined('_UE_MAXEMAILSLIMIT')) DEFINE('_UE_MAXEMAILSLIMIT','You exceeded the maximum limit of %d emails per %d hours. Please try again later.');
					return sprintf( _UE_MAXEMAILSLIMIT, $maxmails, $maxinterval/3600 );
				} else {
					if ( $count ) {
						$query = "UPDATE #__comprofiler SET message_number_sent = message_number_sent + 1 WHERE id = " . (int) $userid;
						$_CB_database->setQuery($query);
						$users = $_CB_database->query();
					}
				}
			} else {
				if ( $count ) {
					$query = "UPDATE #__comprofiler SET message_number_sent = 1, message_last_sent = NOW() WHERE id = " . (int) $userid;
					$_CB_database->setQuery($query);
					$users = $_CB_database->query();
				}
			}
		} else {
			if ( $count ) {
				$query = "UPDATE #__comprofiler SET message_number_sent = 1, message_last_sent = NOW() WHERE id = " . (int) $userid;
				$_CB_database->setQuery($query);
				$users = $_CB_database->query();
			}
		}
		return null;
	} else {
		return "Not Authorized";
	}
}

	// here for backwards compatibility only:

	function getFieldEntry($ui,$oldCalendars,$oType,$oName,$oDescription,$oTitle,$oValue,$oReq,$oLabel,$oID,$oSize, $oMaxLen, $oCols, $oRows,$oProfile, $rowFieldValues=null,$oReadOnly=0,$field=null) {
		global $_CB_framework, $_PLUGINS;

		if ( $oSize > 0 ) {
			$pSize		=	" size='".$oSize."' ";
		} else {
			$pSize		=	"";
		}
		if ( $oMaxLen > 0 ) {
			$pMax		=	" maxlength='".$oMaxLen."' ";
		} else {
			$pMax		=	"";
		}
		if ( $oCols > 0 ) {
			$pCols		=	" cols='".$oCols."' ";
		} else {
			$pCols		=	"";
		}
		if ( $oRows > 0 ) {
			$pRows		=	" rows='".$oRows."' ";
		} else {
			$pRows		=	"";
		}
		if ( $oReadOnly > 0 ) {
			$pReadOnly	=	" disabled=\"disabled\" ";
			$oReq		=	0;
		} else {
			$pReadOnly	=	"";
		}
		$mosReq			=	"mosReq=\"".$oReq."\"";
		$displayFieldIcons	=	true;
		SWITCH ($oType){
	//		CASE 'text':
	//			$oReturn = "<input class=\"inputbox\" $pReadOnly $mosReq mosLabel=\"". htmlspecialchars( getLangDefinition($oLabel) ) ."\" $pSize $pMax type=\"text\" name=\"".$oName."\" id=\"".$oName."\" value=\"".htmlspecialchars($oValue)."\" />";
	//		break;
			CASE 'textarea':
				$oReturn = "<textarea class=\"inputbox\" $pReadOnly $mosReq mosLabel=\"". htmlspecialchars( getLangDefinition($oLabel) ) ."\" $pCols $pRows  name=\"".$oName."\" id=\"".$oName."\">".htmlspecialchars($oValue)."</textarea>";		//TBD: limit by pmax using JS
			break;
			CASE 'editorta':
				if(!($oReadOnly > 0)) {
					$oReturn	=	$_CB_framework->displayCmsEditor( $oName, $oValue, 600, 350, $oCols, $oRows );
					$_CB_framework->outputCbJQuery( 'document.adminForm.'.$oName.".setAttribute('mosReq',".$oReq."); document.adminForm.".$oName.".setAttribute('mosLabel','". addslashes( getLangDefinition($oLabel) ) ."');" );
				} else {
					$oReturn = $oValue;
				}
			break;
			CASE 'select':
			CASE 'multiselect':
			CASE 'radio':
			CASE 'multicheckbox':
				$oReturn = $rowFieldValues;
			break;
			CASE 'checkbox':
				$checked='';
				if($oValue!='' && $oValue != null && $oValue==1) $checked=" checked=\"checked\"";
				$oReturn = "<input $pReadOnly $mosReq mosLabel=\"". htmlspecialchars( getLangDefinition($oLabel) ) ."\" type=\"checkbox\" $checked name=\"".$oName."\" id=\"".$oName."\" value=\"1\" />";
			break;
			CASE 'hidden':
				$oReturn = "<input $pReadOnly mosLabel=\"". htmlspecialchars( getLangDefinition($oLabel) ) ."\" type=\"hidden\" name=\"".$oName."\" id=\"".$oName."\" value=\"".htmlspecialchars($oValue)."\" />";
			break;
			CASE 'password':
				$oReturn = "<input class=\"inputbox\" $pReadOnly $mosReq mosLabel=\"". htmlspecialchars( getLangDefinition($oLabel) ) ."\" type=\"password\" name=\"".$oName."\" id=\"".$oName."\" value=\"".htmlspecialchars($oValue)."\" />";
			break;
			CASE 'date':
				$calendars	=	new cbCalendars( $_CB_framework->getUi() );
				$oReturn = $calendars->cbAddCalendar($oName,$oLabel,$oReq,$oValue,$oReadOnly);
			break;
			CASE 'emailaddress':
				$oReturn = "<input class=\"inputbox\" $pReadOnly $pMax $mosReq $pSize mosLabel=\"". htmlspecialchars( getLangDefinition($oLabel) ) ."\" type=\"text\" name=\"".$oName."\" id=\"".$oName."\" value=\"".htmlspecialchars($oValue)."\" />";
			break;
			CASE 'webaddress':
				if ($oRows!=2) {
					$oReturn = "<input class=\"inputbox\" $pReadOnly $pMax $pSize $mosReq mosLabel=\"". htmlspecialchars( getLangDefinition($oLabel) ) ."\" type=\"text\" name=\"".$oName."\" id=\"".$oName."\" value=\"".htmlspecialchars($oValue)."\" />";
				} else {
					$oValuesArr=array();
					$oValuesArr = explode("|*|",$oValue);
					if (count($oValuesArr) < 2) $oValuesArr[1]="";
					$oReturn = "<span class=\"webUrlSpan\">";
					$oReturn .= "<span class=\"subTitleSpan\">"._UE_WEBURL.":</span>";
					$oReturn .= "<span class=\"subFieldSpan\"><input class=\"inputbox\" $pReadOnly $pMax $pSize $mosReq mosLabel=\"". htmlspecialchars( getLangDefinition($oLabel) ) ."\" type=\"text\" name=\"".$oName."\" id=\"".$oName."\" value=\"".htmlspecialchars($oValuesArr[0])."\" />";
					$oReturn .= getFieldIcons($ui, $oReq, $oProfile, $oDescription, $oTitle)."</span>";
					$displayFieldIcons = false;

					$oReturn .= "</span><span class=\"webTextSpan\">";
					$oReturn .= "<span class=\"subTitleSpan\">"._UE_WEBTEXT.":</span>";
					$oReturn .= "<span class=\"subFieldSpan\"><input class=\"inputbox\" $pReadOnly $pMax $pSize $mosReq mosLabel=\"". htmlspecialchars( getLangDefinition($oLabel) ) ."\" type=\"text\" name=\"".$oName."Text\" id=\"".$oName."Text\" value=\"".htmlspecialchars($oValuesArr[1])."\" /></span>";
					$oReturn .= "</span>";
				}
				break;
			CASE 'delimiter':
				$oReturn = '';
			break;
			CASE 'text':
			default:
				if ( $field != null ) {
					$fieldReq			=	$field->required;
					$field->required	=	$oReq;
					$args				=	array( &$field, null, 'htmledit', 'edit' );
					$oReturn			=	$_PLUGINS->callField( $oType, 'getField', $args, $field );
					$field->required	=	$fieldReq;
				} else {
					$oReturn	=	'FIELD TYPE NOT IMPLEMENTED FOR INPUT';
				}

		}
		if ( $oReturn && $displayFieldIcons ) {
			$oReturn .= getFieldIcons( $ui, $oReq, $oProfile, $oDescription, $oTitle );
		}
		return $oReturn;
	}

	/**
	 * Deletes a user without any check or warning
	 *
	 * @param int $id userid
	 * @param string $condition php condition string on $user e.g. "return (\$user->block == 1);"
	 * @param string $inComprofilerOnly deletes user only in CB, not in Mambo/Joomla
	 * @return mixed : "" if user deleted and found ok, null if user not found, false if condition was not met, string error in case of error raised by plugin
	 */
	function cbDeleteUser ( $id, $condition = null, $inComprofilerOnly = false ) {
		global $_CB_framework, $_CB_database, $_PLUGINS;
		$config1 =& JFactory::getConfig();
		$msg = null;
		$obj2 = new moscomprofiler( $_CB_database );

		$query = "UPDATE `#__adsmanager_ads` SET `published` = '0' WHERE `#__adsmanager_ads`.`userid` =" . (int) $id;
		$_CB_database->setQuery($query);																	// ubivaem vse obiavlenia
		$_CB_database->query ();
		$query = "DELETE FROM `#__tranz_accept` WHERE `#__tranz_accept`.`touserid` =" .(int)$id." or `#__tranz_accept`.`fromuserid` =".(int)$id;
		$_CB_database->setQuery($query);																	// ubivaem vse tranzakcii
		$_CB_database->query ();
		$query = "SELECT * FROM `#__tranz_gen` where id=1";
		$_CB_database->setQuery($query);																//poluchaem nastroiki
		$gen = $_CB_database->loadObjectList();
		foreach ($gen as $genn);
		$query = "SELECT * FROM `#__users` where id=".(int) $id;
		$_CB_database->setQuery($query);											//poluchaem informaciu o udalaemom polzovatele
		$us1 = $_CB_database->loadObjectList();
		foreach ($us1 as $us2);
		$tim1=(time()+32400);
		$today1=date("Y-m-d H:i:s",$tim1);
		$query = "INSERT INTO `#__users_del` (
`id` ,
`name` ,
`delete_date`
)
VALUES (
".(int) $id." , '".$us2->name."', '".$today1."'
)";
		$_CB_database->setQuery($query);																	// Архивируем пользователя
		$_CB_database->query ();
		$query = "SELECT * FROM `#__users` where (block=0) and (id <>". (int) $id.")";
		$_CB_database->setQuery($query);
		$uss = $_CB_database->loadObjectList();
		if ($us2->points !=0) {
		if ($genn->raspred==1) {
		$rezult=round ($us2->points / count ($uss),2);
		foreach ($uss as $nex) {
		$query = "UPDATE `#__users` SET `points` = '".($nex->points+$rezult)."', `dolg` = '".($nex->dolg+($rezult*-1))."', `message` = if ((LOCATE('u',message) !=0), message, CONCAT (message, 'uu'))  WHERE `#__users`.`id` =".$nex->id;
		$_CB_database->setQuery($query);
		$_CB_database->query ();
		$query = "INSERT INTO `#__tranz_data` (
`id` ,
`touserid` ,
`fromuserid` ,
`fromuserapproved` ,
`points` ,
`rank` ,
`insert_date` ,
`accept_date` ,
`rule` ,
`keyreference` ,
`namereference` ,
`datareference` 
)
VALUES (
NULL , '".$nex->id."', '".(int) $id."', '1', '".$rezult."', '0', '".$today1."', '".$today1."', '0', '0', 'Компенсация за удаление пользователя №".(int) $id."', ''
)";
		$_CB_database->setQuery($query);
		$_CB_database->query ();
			$_CB_database->setQuery("SELECT * FROM `#__users` where id=".$nex->id);
			$usern1=$_CB_database->loadObjectList();
			foreach($usern1 as $us);
			if ($us->sett6 == 1) {
			$_CB_database->setQuery("SELECT DISTINCT a.userid FROM #__session AS a, #__users AS u WHERE (a.userid = u.id) AND (a.guest = 0) AND (a.client_id = 0) AND (u.id = ".$us->id.")");
			$usern6=$_CB_database->loadresult();
			if ($usern6 !=$us->id)
			{
			JUtility::sendMail($config1->getValue( 'config.mailfrom' ),$config1->getValue( 'config.fromname' ),$us->email, 'Уведомление', 'Уважаемый пользователь, на сайте: <a href= "http://'.$_SERVER['HTTP_HOST'].'/otchjot-o-platezhakh">'.$_SERVER['HTTP_HOST'].'</a> произошло начисление или снятие вам часов. Для просмотра зайдите на сайт и посмотрите отчёт о платежах.<p><font size=2>Отписаться от этого уведомления можно <a href="http://'.$_SERVER['HTTP_HOST'].'/index.php/settings">тут</a></font></p>',1);
			}}
			if ($us->sett3==1) {
			$nach = strpos ($us->message, "n");
			if ($nach === false) {
			$query = "UPDATE `#__users` SET `message` = '".$us->message."n1n' WHERE `#__users`.`id` =".$us->id;
			$_CB_database->setQuery($query);
			$_CB_database->query();
			} else {
			$kon = strrpos ($us->message, "n");																		//Блок посылки поп-апа
			$len = strlen ($us->message);
			$re = substr ($us->message, $nach+1, ((($len-$nach)-($len-$kon))-1));
			$re = $re+1;
			$rf = (substr ($us->message, 0, $nach+1)).$re.(substr ($us->message, $kon, ($len-$kon)));
			$query = "UPDATE `#__users` SET `message` = '".$rf."' WHERE `#__users`.`id` =".$us->id;
			$_CB_database->setQuery($query);
			$_CB_database->query();
			}}
		}
		} else {
		$query = "select points from `#__users` WHERE `id` =".$us2->idref;
		$_CB_database->setQuery($query);				//poluchaem colichestvo ochkov refa
		$ref = $_CB_database->loadObjectList();
		foreach ($ref as $ref1);
		$query = "UPDATE `#__users` SET `points` = '".($ref1->points+$us2->points)."' WHERE `#__users`.`id` =".$us2->idref;
		$_CB_database->setQuery($query);			//otnimaem u refa ochki
		$_CB_database->query ();
		$query = "INSERT INTO `#__tranz_data` (
`id` ,
`touserid` ,
`fromuserid` ,
`fromuserapproved` ,
`points` ,
`rank` ,
`insert_date` ,
`accept_date` ,
`rule` ,
`keyreference` ,
`namereference` ,
`datareference` 
)
VALUES (
NULL , '".$us2->idref."', '".(int) $id."', '1', '".$us2->points."', '0', '".$today1."', '".$today1."', '0', '0', 'Компенсация за удаление пользователя №".(int) $id."', ''
)";
		$_CB_database->setQuery($query);
		$_CB_database->query ();
			$_CB_database->setQuery("SELECT * FROM `#__users` where id=".$trn->touserid);
			$usern1=$_CB_database->loadObjectList();
			foreach($usern1 as $us);
			if ($us->sett3==1) {
			$nach = strpos ($us->message, "n");
			if ($nach === false) {
			$query = "UPDATE `#__users` SET `message` = '".$us->message."n1n' WHERE `#__users`.`id` =".$us->id;
			$_CB_database->setQuery($query);
			$_CB_database->query();
			} else {
			$kon = strrpos ($us->message, "n");																		//Блок посылки поп-апа
			$len = strlen ($us->message);
			$re = substr ($us->message, $nach+1, ((($len-$nach)-($len-$kon))-1));
			$re = $re+1;
			$rf = (substr ($us->message, 0, $nach+1)).$re.(substr ($us->message, $kon, ($len-$kon)));
			$query = "UPDATE `#__users` SET `message` = '".$rf."' WHERE `#__users`.`id` =".$us->id;
			$_CB_database->setQuery($query);
			$_CB_database->query();
			}}}}
		 //procedura pereschota max min
		$min1=100*count ($uss)*-1;
		$max1=1000*count ($uss);
		$_CB_database->setQuery("UPDATE `#__tranz_gen` SET `min_points` = '".$min1."',`max_points` = '".$max1."' WHERE `#__tranz_gen`.`id` =1");
		$_CB_database->query();
		$_CB_database->setQuery("ALTER TABLE `#__users` CHANGE `max_points` `max_points` FLOAT( 10, 2 ) NOT NULL DEFAULT '".$max1."'");
		$_CB_database->query();
		foreach ($uss as $nex1) {							//obrezaem min i max
		if ($nex1->max_points > $max1) {
		$_CB_database->setQuery("UPDATE `#__users` SET `max_points` = '".$max1."' WHERE `#__users`.`id` =".$nex1->id);
		$_CB_database->query();
		};
		if ($nex1->min_points < $min1) {
		$_CB_database->setQuery("UPDATE `#__users` SET `min_points` = '".$min1."' WHERE `#__users`.`id` =".$nex1->id);
		$_CB_database->query();
		};
		};
		$query = "SELECT * FROM #__comprofiler c LEFT JOIN #__users u ON c.id = u.id WHERE c.id = " . (int) $id;
		$_CB_database->setQuery($query);
		$user = $_CB_database->loadObjectList();

		if ( ( $_CB_database->getErrorNum() ) || ( count( $user ) == 0 ) ) {
			$query = "SELECT * FROM #__users u LEFT JOIN #__comprofiler c ON c.id = u.id WHERE u.id = " . (int) $id;
			$_CB_database->setQuery($query);
			$user = $_CB_database->loadObjectList();
		}
		if ( ( ! $_CB_database->getErrorNum() ) && ( count( $user ) > 0 ) ) {
			$user = $user[0];

			if ( ( $condition == null ) || eval( $condition ) ) {
				$_PLUGINS->loadPluginGroup( 'user' );
				$_PLUGINS->trigger( 'onBeforeDeleteUser', array( $user ) );
				if ( $_PLUGINS->is_errors() ) {
					$msg = $_PLUGINS->getErrorMSG();
				} else {
					deleteAvatar( $user->avatar );
					$reports	=	new moscomprofilerUserReport( $_CB_database );
					$reports->deleteUserReports ( $user->id );
					_cbdeleteUserViews( $user->id );
					if ( ! $inComprofilerOnly ) {
						$obj	=&	$_CB_framework->_getCmsUserObject( $id );
						$obj->delete( $id );
						$msg	.=	$obj->getError();
					}
					$obj2->delete( $id );
					$msg .= $obj2->getError();

					// delete user acounts active sessions
					$query = "DELETE FROM #__session"
				 	. "\n WHERE userid = " . (int) $id
				 	;
					$_CB_database->setQuery( $query );
					$_CB_database->query();

					$_PLUGINS->trigger( 'onAfterDeleteUser', array( $user, true ) );
				}
			} else {
				$msg = false;
			}
		}
		return $msg;
	}
	/**
	 * Computes page title, sets page title and pathway
	 *
	 * @param  moscomprofilerUser  $user
	 * @param  string              $thisUserTitle    Title if it's the user displaying
	 * @param  string              $otherUserTitle   Title if it's another user displayed
	 * @return string    title (plaintext, without htmlspecialchars or slashes)
	 */
	function cbSetTitlePath( $user, $thisUserTitle, $otherUserTitle ) {
		global $ueConfig, $_CB_framework;

		if ( $_CB_framework->myId() == $user->id ) {
			$title	=	$thisUserTitle;
		} else {
			$name	=	getNameFormat( $user->name, $user->username, $ueConfig['name_format'] );
			$title	=	sprintf( $otherUserTitle, $name );
		}
		$_CB_framework->setPageTitle( $title );
		$_CB_framework->appendPathWay( htmlspecialchars( $title ) );
		return $title;
	}
	/**
	 * Redirects user to a/his profile or a given task.
	 *
	 * @param unknown_type $uid
	 * @param unknown_type $message
	 * @param unknown_type $task
	 */
	function cbRedirectToProfile( $uid, $message, $task = null ) {
		global $_CB_framework;

		$redirectURL		=	"index.php?option=com_comprofiler";
		if ( $_CB_framework->myId() != $uid ) {
			$redirectURL	.=	"&amp;user=" . $uid;
		}
		if ( $task ) {
			$redirectURL	.=	"&amp;task=" . $task;
		}
		$redirectURL		.=	getCBprofileItemid();
		cbRedirect( cbSef( $redirectURL, false ), $message );
	}

	function teamCreditsReplacer( $input ) {
		static $index = 0;
		$l = array( '/community-builder', '/social-networking', '/joomla', '/membership-management',
					'/cb-solutions/directory', 'http://extensions.joomla.org/extensions/clients-a-communities/communities/210',
					'/joomla-templates',
					'/cb-solutions/add-ons', '/cb-solutions/cbsubs', '/cb-solutions/cbsubs', '/cb-solutions/incubator',
					'/online-social-network', '/hosting' );
		return '<a href="' . ( isset( $l[$index] ) && $l[$index][0] == '/' ? 'http://www.joomlapolis.com' : '' ) . ( isset( $l[$index] ) ? $l[$index++] : '/' ) . '">';
	}
	/**	Gives credits display for frontend and backend
	*	@param int	1=frontend, 2=backend
	*/
	function teamCredits( $ui ) {
		global $_CB_framework, $ueConfig;

		outputCbTemplate( $ui );
		outputCbJs( $ui );

?>
		<table style="width:100%;border0;align:center;padding:8px;" cellpadding="0" cellspacing="0" class="cbborderlesstable">
		<tr>
			<td>
				<table width="100%" border="0" align="center" cellpadding="2" cellspacing="0">
				<tr>
					<td style="text-align:center" colspan="2">
					<?php
					if ($ui == 2) {
						echo '<a href="http://www.joomlapolis.com" target="_blank" style="border:0px;display:block;width:304px;margin:auto;background:white solid;"><img src="' . $_CB_framework->getCfg( 'live_site' ) . '/components/com_comprofiler/images/smcblogo.gif" border="0" /></a><br />';
?><div style="width:95%;text-align:center;margin-bottom:15px;">
	<div style="width:auto;margin:0px;text-align:left;">
<?php update_checker(); ?>
	</div>
</div>
<?php
					} else {
						echo "<b>"._UE_SITE_POWEREDBY."</b><br />";
						echo '<a href="http://www.joomlapolis.com" target="_blank" style="border:0px;display:block;width:304px;margin:auto;background-color:#fff;"><img src="' . $_CB_framework->getCfg( 'live_site' ) . '/components/com_comprofiler/images/smcblogo.gif" border="0"/></a><br />';
					}
					?>
					</td>
				</tr>
				<tr>
					<td style="text-align:center" colspan="2">
						<?php
						$w = "<p><strong>Community Builder</strong>&trade; (CB) is the complete <strong>Social Networking software</strong> solution for <strong>Joomla</strong>&trade; that is used by this website to support its <strong>membership management</strong>.</p>
						<p>This <strong>Joomla extension</strong> is the <strong>most popular Joomla component on the Joomla Extensions Directory</strong>.</p> 
						<p>It comes with 6 built-in free CB templates, but more cool and fast <strong>Joomla templates</strong> are available.</p>
						<p>Community Builder has <strong>over 200 CB solutions add-ons</strong>, both free and commercial that can extend the functionality of any Joomla website. One of these is the <strong>paid memberships software</strong> solution, CBSubs&trade;, that can manage <strong>paid subscriptions</strong> to access your website content. Many more exciting CB plugins are in our <strong>CB incubator</strong>.</p> 
						<p>Finally, for those wanting a turnkey <strong>Online Social Network</strong>, Joomlapolis.com offers business-class <strong>Joomla hosting</strong>, including a one-click social networking website installer.</p>";
						$w = preg_replace_callback( '/<strong>/', 'teamCreditsReplacer', $w );
						$w = str_replace( '</strong>', '</a>', $w );
						?>
						<div style="text-align:left;">
							<?php echo $w; ?>
							<br/>
							<p><b>Software: Copyright 2004-2012 joomlapolis.com, MamboJoe/JoomlaJoe, Beat and CB team. This component is released under the GNU/GPL version 2 License. All copyright statements must be kept. Derivate work must prominently duly acknowledge original work and include visible online links. Official site:</b></p>
						</div>
					<b><a href="http://www.joomlapolis.com">www.joomlapolis.com</a></b>
<?php
		if ( $ui == 1 ) {
?>
					<br /><br /><b>Please note that the authors and distributors of this software are not affiliated nor related in any way with the site owners using this free software here, and declines any warranty regarding the content and functions of this site.
<?php
		}
?>
					<br /><br />
					Credits:
					</b>
					<br />
					</td>
				</tr>
				<tr>
					<td style="text-align:center" colspan="2">
					<script type="text/javascript">//<!--
					/*
					Fading Scroller- By DynamicDrive.com
					For full source code, and usage terms, visit http://www.dynamicdrive.com
					This notice MUST stay intact for use
					fcontent[4]="<h3>damian caynes<br />inspired digital<br /></h3>Logo Design";
					*/
					var delay=1000; //set delay between message change (in miliseconds)
					var fcontent=new Array();
					begintag=''; //set opening tag, such as font declarations
					fcontent[0]="<h3>CBJoe/JoomlaJoe/MamboJoe<br /></h3>Founder &amp; First Developer";
					fcontent[1]="<h3>DJTrail<br /></h3>Co-Founder &amp; Lead Tester";
					fcontent[2]="<h3>Nick A.<br /></h3>Documentation and Public Relations";
					fcontent[3]="<h3>Beat B.<br /></h3>Lead Developer";
					fcontent[4]="<h3>Kyle L.<br /></h3>Developer and Support";
					fcontent[5]="<h3>Lou Griffith<br />Spottsfield Entertainment<br /></h3>Logo Design";
					closetag='';

					var fwidth='100%';	//'250px' //set scroller width
					var fheight='80px'; //set scroller height

					var fadescheme=<?php echo ( ( $ui == 2 ) || ($ueConfig['templatedir'] != 'dark') ? 0 : 1 ); ?>; //set 0 to fade text color from (white to black), 1 for (black to white)
					var fadelinks=1; //should links inside scroller content also fade like text? 0 for no, 1 for yes.

					///No need to edit below this line/////////////////

					var hex=(fadescheme==0)? 255 : 0;
					var startcolor=(fadescheme==0)? "rgb(255,255,255)" : "rgb(0,0,0)";
					var endcolor=(fadescheme==0)? "rgb(0,0,0)" : "rgb(255,255,255)";

					var ie4=document.all&&!document.getElementById;
					var ns4=document.layers;
					var DOM2=document.getElementById;
					var faderdelay=0;
					var index=0;

					if (DOM2)
					faderdelay=2000;

					//function to change content
					function changecontent(){
						if (index>=fcontent.length)
							index=0;
							if (DOM2){
								document.getElementById("fscroller").style.color=startcolor;
								document.getElementById("fscroller").innerHTML=begintag+fcontent[index]+closetag;
								linksobj=document.getElementById("fscroller").getElementsByTagName("A");
								if (fadelinks)
									linkcolorchange(linksobj);
									colorfade();
								} else if (ie4)
									document.all.fscroller.innerHTML=begintag+fcontent[index]+closetag;
								else if (ns4){
								document.fscrollerns.document.fscrollerns_sub.document.write(begintag+fcontent[index]+closetag);
								document.fscrollerns.document.fscrollerns_sub.document.close();
							}
						index++;
						setTimeout("changecontent()",delay+faderdelay);
					}

					// colorfade() partially by Marcio Galli for Netscape Communications.  ////////////
					// Modified by Dynamicdrive.com

					frame=20;

					function linkcolorchange(obj){
						if (obj.length>0){
							for (i=0;i<obj.length;i++)
								obj[i].style.color="rgb("+hex+","+hex+","+hex+")";
							}
						}

					function colorfade() {
					// 20 frames fading process
					if(frame>0) {
						hex=(fadescheme==0)? hex-12 : hex+12; // increase or decrease color value depd on fadescheme
						document.getElementById("fscroller").style.color="rgb("+hex+","+hex+","+hex+")"; // Set color value.
						if (fadelinks)
							linkcolorchange(linksobj);
							frame--;
							setTimeout("colorfade()",20);
						} else {
							document.getElementById("fscroller").style.color=endcolor;
							frame=20;
							hex=(fadescheme==0)? 255 : 0;
						}
					}

					if (ie4||DOM2)
						document.write('<div id="fscroller" style="border:0px solid black;width:'+fwidth+';height:'+fheight+';padding:2px"></div>');
						window.onload=changecontent;
					//-->
					</script>
					<ilayer id="fscrollerns" width="&{fwidth};" height="&{fheight};">
						<layer id="fscrollerns_sub" width="&{fwidth};" height="&{fheight};" left=0 top=0></layer>
					</ilayer>
					</td>
				</tr>
			<?php
			if ($ui==2) {
			?><tr>
					<td style="text-align:center" colspan="2"><strong>Please note there is a free installation document, as well as a full documentation subscription for this free component available at <a href="http://www.joomlapolis.com/">www.joomlapolis.com</a></strong><br />&nbsp;</td>
				</tr>
				<tr>
					<td style="text-align:center" colspan="2">If you like the services provided by this free component, <a href="http://www.joomlapolis.com/">please consider making a small donation to support the team behind it</a><br />&nbsp;</td>
				</tr>
			<?php
			} elseif ( $_CB_framework->myId() ) {
			?><tr>
					<td style="text-align:center" colspan="2"><a href="<?php echo cbSef( 'index.php?option=com_comprofiler' .getCBprofileItemid( true ) ); ?>"><?php echo _UE_BACK_TO_YOUR_PROFILE; ?></a><br />&nbsp;</td>
				</tr>
			<?php
			}
			?></table>
		<br />Community Builder includes following components:<br />
		<table class="adminform" cellpadding="0" cellspacing="0" style="border:0; width:100%; text-align:left;">
		<tr>
			<th>
			Application
			</th>
			<?php
			if ($ui==2) {
			?><th>
			Version
			</th>
			<?php
			}
			?><th>
			License
			</th>
		</tr>
		<tr>
			<td>
			<a href="http://www.foood.net" target="_blank">Icons (old icons)</a>
			</td>
			<?php
			if ($ui==2) {
			?><td>
			N/A
			</td>
			<?php
			}
			?><td>
			<a href="http://www.foood.net/agreement.htm" target="_blank">
			http://www.foood.net/agreement.htm
			</a>
			</td>
		</tr>
		<tr>
			<td>
			<a href="http://nuovext.pwsp.net/" target="_blank">Icons</a>
			</td>
			<?php
			if ($ui==2) {
			?><td>
			2.2
			</td>
			<?php
			}
			?><td>
			<a href="http://www.gnu.org/licenses/lgpl.html" target="_blank">
			GNU Lesser General Public License
			</a>
			</td>
		</tr>
		<tr>
			<td>
			<a href="http://webfx.eae.net" target="_blank">Tabs</a>
			</td>
			<?php
			if ($ui==2) {
			?><td>
			1.02
			</td>
			<?php
			}
			?><td>
			<a href="http://www.apache.org/licenses/LICENSE-2.0" target="_blank">
			Apache License, Version 2.0
			</a>
			</td>
		</tr>
		<tr>
			<td>
			<a href="http://www.dynarch.com/projects/calendar" target="_blank">Calendar</a>
			</td>
			<?php
			if ($ui==2) {
			?><td>
			1.1
			</td>
			<?php
			}
			?><td>
			<a href="http://www.gnu.org/licenses/lgpl.html" target="_blank">
			GNU Lesser General Public License
			</a>
			</td>
		</tr>
		<tr>
			<td>
			<a href="http://www.dynamicdrive.com/dynamicindex7/jasoncalendar.htm" target="_blank">Jason&#039;s Calendar</a>
			</td>
			<?php
			if ($ui==2) {
			?><td>
			2005-09-05
			</td>
			<?php
			}
			?><td>
			<a href="http://dynamicdrive.com/notice.htm" target="_blank">
			Dynamic Drive terms of use License
			</a>
			</td>
		</tr>
		<tr>
			<td>
			<a href="http://www.bosrup.com/web/overlib/" target="_blank">overLib</a>
			</td>
			<?php
			if ($ui==2) {
			?><td>
			4.17
			</td>
			<?php
			}
			?><td>
			<a href="http://www.bosrup.com/web/overlib/?License" target="_blank">
			http://www.bosrup.com/web/overlib/?License
			</a>
			</td>
		</tr>
		<tr>
			<td>
			<a href="http://snoopy.sourceforge.net/" target="_blank">Snoopy</a>
			</td>
			<?php
			if ($ui==2) {
			?><td>
			1.2.3
			</td>
			<?php
			}
			?><td>
			<a href="http://www.gnu.org/licenses/lgpl.html" target="_blank">
			GNU Lesser General Public License
			</a>
			</td>
		</tr>
		<tr>
			<td>
			<a href="http://www.phpclasses.org/browse/package/2189.html" target="_blank">PHPMailer</a>
			</td>
			<?php
			if ($ui==2) {
			?><td>
			2.0.0
			</td>
			<?php
			}
			?><td>
			<a href="http://www.gnu.org/licenses/lgpl.html" target="_blank">
			GNU Lesser General Public License
			</a>
			</td>
		</tr>
		<tr>
			<td>
			<a href="http://www.phpclasses.org/browse/package/2189.html" target="_blank">PHP Input Filter</a>
			<a href="http://freshmeat.net/projects/inputfilter/" target="_blank">(forge)</a>
			</td>
			<?php
			if ($ui==2) {
			?><td>
			1.2.2+
			</td>
			<?php
			}
			?><td>
			<a href="http://www.gnu.org/licenses/old-licenses/gpl-2.0.html" target="_blank">
			GNU General Public License
			</a>
			</td>
		</tr>
		<tr>
			<td>
			<a href="http://www.joomlapolis.com/" target="_blank">BestMenus</a>
			</td>
			<?php
			if ($ui==2) {
			?><td>
			1.0
			</td>
			<?php
			}
			?><td>
			<a href="http://www.joomlapolis.com/" target="_blank">
			Open Source GPL (GNU General Public License) v2
			</a>
			</td>
		</tr>
		<tr>
			<td>
			<a href="http://jquery.com/" target="_blank">jQuery</a>
			</td>
			<?php
			if ($ui==2) {
			?><td>
			<?php echo _CB_JQUERY_VERSION; ?>
			</td>
			<?php
			}
			?><td>
			<a href="http://docs.jquery.com/" target="_blank">
			MIT license
			</a>
			</td>
		</tr>
		</table>
			</td>
		</tr>
</table>
<?php
	}

/**
 * Gets an array of IP addresses taking in account the proxys on the way.
 * An array is needed because FORWARDED_FOR can be facked as well.
 *
 * @return array of IP addresses, first one being host, and last one last proxy (except fackings)
 */
function cbGetIParray() {
	global $_SERVER;

	if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ip_adr_array		=	explode(',',$_SERVER['HTTP_X_FORWARDED_FOR']);
	} else {
		$ip_adr_array		=	array();
	}
	$ip_adr_array[]			=	$_SERVER['REMOTE_ADDR'];
	return $ip_adr_array;
}
/**
 * Gets a comma-separated list of IP addresses taking in account the proxys on the way.
 * An array is needed because FORWARDED_FOR can be facked as well.
 *
 * @return string of IP addresses, first one being host, and last one last proxy (except fackings)
 */
function cbGetIPlist() {
	return addslashes(implode(",",cbGetIParray()));
}
/**
* records the hit to a user profile
* @access private
* @param int viewed user id
*/
function _incHits($profileId) {
	global $_CB_database;
	$_CB_database->setQuery("UPDATE #__comprofiler SET hits=(hits+1) WHERE id=" . (int) $profileId);
	if (!$_CB_database->query()) {
		echo "<script type=\"text/javascript\"> alert('UpdateHits: ".$_CB_database->getErrorMsg()."');</script>\n";
		// exit();
	}
}
/**
* records a visit and the hit with timed protection similar to voting protections
* @param int viewing user id
* @param int viewed user id
* @param IP address of viewing user
*/
function recordViewHit( $viewerId, $profileId, $currip ) {
	global $_CB_framework, $_CB_database, $ueConfig;

	$query					=	'SELECT ' . $_CB_database->NameQuote( 'lastview' ) . ', ' . $_CB_database->NameQuote( 'lastip' )
							.	"\n FROM " . $_CB_database->NameQuote( '#__comprofiler_views' )
							.	"\n WHERE " . $_CB_database->NameQuote( 'viewer_id' ) . " = " . (int) $viewerId
							.	"\n AND " . $_CB_database->NameQuote( 'profile_id' ) . " = " . (int) $profileId
							.	( $viewerId == 0 ? "\n AND " . $_CB_database->NameQuote( 'lastip' ) . " = " . $_CB_database->Quote( $currip ) : null )
							.	"\n ORDER BY " . $_CB_database->NameQuote( 'lastview' ) . " DESC";
	$_CB_database->setQuery( $query );
	$views					=	$_CB_database->loadObjectList();

	if ( count( $views ) == 0 ) {
		// no views yet: insert the view record:
		$query				=	'INSERT INTO ' . $_CB_database->NameQuote( '#__comprofiler_views' )
							.	"\n ( " . $_CB_database->NameQuote( 'viewer_id' )
							.	', ' . $_CB_database->NameQuote( 'profile_id' )
							.	', ' . $_CB_database->NameQuote( 'lastip' )
							.	', ' . $_CB_database->NameQuote( 'lastview' )
							.	', ' . $_CB_database->NameQuote( 'viewscount' ) . ' )'
							.	"\n VALUES ( "
							.	(int) $viewerId
							.	', ' . (int) $profileId
							.	', ' . $_CB_database->Quote( $currip )
							.	', NOW()'
							.	', 1 )';
		$_CB_database->setQuery( $query );
		if ( ! $_CB_database->query() ) {
			echo "<script type=\"text/javascript\">alert( 'InsertViews: " . addslashes( $_CB_database->getErrorMsg() ) . "' );</script>\n";
		}

		_incHits( $profileId );

	} else {
		// we already have view(s):
		$count				=	count( $views );

		$lastview			=	strtotime( $views[0]->lastview );

		if ( $count > 1 ) {
			// huston, we have a database problem: we have more than one entry for the pair viewer-viewed OR the tripplet (anonymous viewer=0 - viewed - IP address):
			// updating would generate key conflicts: cleanupt that mess please:
			$query		=	'DELETE FROM ' . $_CB_database->NameQuote( '#__comprofiler_views' )
						.	"\n WHERE " . $_CB_database->NameQuote( 'viewer_id' ) . " = " . (int) $viewerId
						.	"\n AND " . $_CB_database->NameQuote( 'profile_id' ) . " = " . (int) $profileId
						.	( $viewerId == 0 ? "\n AND " . $_CB_database->NameQuote( 'lastip' ) . " = " . $_CB_database->Quote( $currip ) : null )
						.	"\n AND " . $_CB_database->NameQuote( 'lastview' ) . " <> " . $_CB_database->Quote( $views[0]->lastview );
		$_CB_database->setQuery( $query );
			if ( ! $_CB_database->query() ) {
				echo "<script type=\"text/javascript\">alert( 'DeleteViews: " . addslashes( $_CB_database->getErrorMsg() ) . "' );</script>\n";
			}
		}

		// ok there was a view, we will count it only if lastview time is greater than the minimum interval configured,
		$needsUpdate	=	( ( $_CB_framework->now() - $lastview ) > ( $ueConfig['minHitsInterval'] * 60 ) );
		// but we will update any IP address changes in case of a logged-in user (for guests, the SELECT above is by IP address, so that entry and IP is already same:
		if ( ( $currip != $views[0]->lastip ) || $needsUpdate ) {
			$query	=	'UPDATE ' . $_CB_database->NameQuote( '#__comprofiler_views' )
					.	"\n SET " . $_CB_database->NameQuote( 'lastview' ) . " = NOW()"
					.	', ' . $_CB_database->NameQuote( 'lastip' ) . " = " . $_CB_database->Quote( $currip )
					.	( $needsUpdate ? ', ' . $_CB_database->NameQuote( 'viewscount' ) . " = (" . $_CB_database->NameQuote( 'viewscount' ) . "+1)" : '' )
					.	"\n WHERE " . $_CB_database->NameQuote( 'viewer_id' ) . " = " . (int) $viewerId
					.	"\n AND " . $_CB_database->NameQuote( 'profile_id' ) . " = " . (int) $profileId
					.	( $viewerId == 0 ? "\n AND " . $_CB_database->NameQuote( 'lastip' ) . " = " . $_CB_database->Quote( $currip ) : null );
			$_CB_database->setQuery( $query );
			if ( ! $_CB_database->query() ) {
				echo "<script type=\"text/javascript\">alert( 'UpdateViews: " . addslashes( $_CB_database->getErrorMsg() ) . "' );</script>\n";
			}

			if ( $needsUpdate ) {
				_incHits( $profileId );
			}
		}
	}
}

/**
* Calendars for date fields handler
* @package Community Builder
* @author Beat
*/
class cbCalendars {
	/**	@var int 1=Front End 2=Admin */
	var $ui=0;
	/** @var string Date Format */
    var $dateFormat;
    /** @var int 1=popup 2=jason's */
	var $calendarType;
	/**
	* Constructor
	* Includes files needed for displaying calendar for date fields
	*
	* @param int $ui            user interface: 1=Front End 2=Admin
	* @param int $calendarType  calendar type: 1 = popup only  2=drop downs, null=according to config
	*/
	function cbCalendars($ui, $calendarType = null ) {
		global $ueConfig;

		$this->ui				=	$ui;
		if ( $calendarType === null ) {
			if ( isset( $ueConfig['calendar_type'] ) ) {
				$calendarType	=	$ueConfig['calendar_type'];
			} else {
				$calendarType	=	2;
			}
		}
		$this->calendarType		=	$calendarType;

		if ( $this->calendarType == 1 ) {

			$dFind				=	array("d","m","y","Y");
			$dReplace			=	array("%d","%m","%Y","%Y");				// array("%d","%m","%y","%Y"); keep always 4 digits for year
			$this->dateFormat	=	str_replace($dFind, $dReplace, $ueConfig['date_format']);

		} else {

			$dFind				=	array("d","m","Y","y");
			$dReplace			=	array("DD","MM","YYYY","YYYY");			// array("DD","MM","YYYY","YY"); keep always 4 digits for year
			$this->dateFormat	=	str_replace($dFind, $dReplace, $ueConfig['date_format']);

		}

		if ( ! ( isset($ueConfig['xhtmlComply']) && $ueConfig['xhtmlComply'] ) ) {
			$this->_addHeadTag();
		}
	}
	/**
	* Echos a calendar field
	*
	* @param string   $oName    the field name
	* @param string   $oLabel   the field label
	* @param boolean  $oReq     true if field is required
	* @param string the existing value (in Y-mm-dd SQL format)
	* @param boolean read-only field
	*/
	function cbAddCalendar( $oName, $oLabel, $oReq, $oValue = "", $oReadOnly = false, $showTime = false, $yMin = null, $yMax = null ) {
		global $_CB_framework, $ueConfig;

		$this->_addHeadTag();

		if ($oValue=='0000-00-00 00:00:00' || $oValue=='0000-00-00') {
			$oValue			=	"";
		} else {
			$fieldForm		=	str_replace( 'y', 'Y', $ueConfig['date_format'] );
			$oValue			=	dateConverter( $oValue, 'Y-m-d', $fieldForm );			// 'date' type of field
		}

		if ( $this->calendarType == 1 ) {
			$vardisabled	=	($oReadOnly) ? ' disabled="disabled"' : '';

			$return = '<input class="inputbox' . ( $oReq ? ' required' : '' ) . '"'.$vardisabled.' mosReq="'.$oReq.'" mosLabel="'. htmlspecialchars( getLangDefinition($oLabel) ) .'"'
					. ' type="text" name="' . $oName .'" readonly="readonly" id="' . $oName . '" value="' . $oValue . '" />'
					. "\n";
			if (!$oReadOnly) {
				$js			=
				'Calendar.setup({'
				. 'inputField : "'.$oName.'",'	// id of the input field
				. 'ifFormat   : "'.$this->dateFormat.($showTime ? ' %H:%M:00' : '').'",'	// format of the input field
				. 'showsTime  : '.($showTime ? 'true' : 'false').','				// will display a time selector
				. 'singleClick: true'				// single-click mode
				. '});'
				;
				$_CB_framework->outputCbJQuery( $js );
			}
		} else {
			if ( $oReadOnly ) {
				$return			=	htmlspecialchars( $oValue );
			} else {
				$jsReqBoolean	=	'false';		// in fact this only sets today's date, making our JS for required not working ( $oReq ? 'true' : 'false' );
				// format: "<script>DateInput('orderdate', true, 'DD-MON-YYYY')</script>"
				$oIdName		=	str_replace( array( '[', ']' ), '', $oName );

				if ( ( $yMin !== null ) && ( $yMax !== null ) ) {
					$years		=	',1,' . (int) $yMin . ',' . (int) $yMax;
				} else {
					$years		=	'';
				}
				$vardisabled	=	($oReadOnly) ? ' disabled="disabled"' : '';
				$return			=	'<input class="inputbox' . ( $oReq ? ' required' : '' ) . '"'.$vardisabled.' mosReq="'.$oReq.'" mosLabel="'. htmlspecialchars( getLangDefinition($oLabel) ) .'"'
								.	' type="text" name="' . $oName .'" id="' . $oIdName . '" value="' . $oValue . '" />'
								.	"\n";
								// we do substitute both: <input .... type="text" ...> and without any type (which is IE7 behavior default text type attribute is ommitted ! + IE writes <INPUT:
				$js				=	'$("#' . $oIdName . '").parent().each( function() { $(this).html( $(this).html().replace(/^(?:([^<]*<input [^>]*)(type="?text"?)([^>]*)>)|(?:([^<]*<input [^>]*)([^>]*)>)/i,\'$1$4 type="hidden" $3$5>\') ); })'
								.	'.children("#' . $oIdName . '").after( cbcalDateHtml(\'' . $oIdName . "', " . $jsReqBoolean . ", '" . $this->dateFormat . "', '" . $oValue . "', '" . $oName . "', ''" . $years . ")"
								.	');'
								;
				$_CB_framework->outputCbJQuery( $js, 'cb_calendarinput' );
			}
		}
		return $return;
	}
	/**
	 * Adds a html head tag once for the calendar if needed.
	 * @access private
	 *
	 */
	function _addHeadTag() {
		global $_CB_framework;

		static $added				=	array( 1 => false, 2 => false );

		if ( ! $added[$this->calendarType] ) {
			$UElanguagePath			=	$_CB_framework->getCfg( 'absolute_path' ).'/components/com_comprofiler/plugin/language';
			if ( file_exists( $UElanguagePath.'/'.$_CB_framework->getCfg( 'lang' ).'/calendar-locals.js' ) ) {
				$calendarLangFile	=	'/components/com_comprofiler/plugin/language/'.$_CB_framework->getCfg( 'lang' ).'/calendar-locals.js';
			} elseif ( file_exists( $UElanguagePath.'/' . strtolower( $_CB_framework->getCfg( 'lang_tag' ) ) . '/calendar-locals.js' ) ) {
				$calendarLangFile	=	'/components/com_comprofiler/plugin/language/' . strtolower( $_CB_framework->getCfg( 'lang_tag' ) ) . '/calendar-locals.js';
			} else {
				$calendarLangFile	=	'/components/com_comprofiler/plugin/language/default_language/calendar-locals.js';
			}

			if ( $this->calendarType == 1 ) {
				$_CB_framework->document->addHeadStyleSheet( selectTemplate( $this->ui ).'calendar.css', false, null, array( 'title' => 'win2k-cold-1' ) );
				$_CB_framework->document->addHeadScriptUrl( '/components/com_comprofiler/js/calendar.js', true );
				$_CB_framework->document->addHeadScriptUrl( $calendarLangFile, false, null, null, null, null, array( 'type' => 'text/javascript', 'charset' => 'utf-8' ) );
				$_CB_framework->document->addHeadScriptUrl( '/components/com_comprofiler/js/calendar-setup.js', true );
			} else {
				$_CB_framework->document->addHeadScriptUrl( $calendarLangFile, false, 'var cbTemplateDir="' . selectTemplate( $this->ui ) . '"; Calendar = function () { };', null, null, null, array( 'type' => 'text/javascript', 'charset' => 'utf-8' ) );
				$_CB_framework->addJQueryPlugin( 'cb_calendarinput', '/components/com_comprofiler/js/calendardateinput.js' );
			}

			$added[$this->calendarType]		=	true;
		}
	}
}	// end Class cbCalendars


/**
* Tab Creation handler
* @package Mambo
* @author Phil Taylor
* @author Extended by MamboJoe
*/
class cbTabs extends cbTabHandler {
	/** @var int Use cookies */
	var $useCookies				=	0;
	/** @var int 1=Front End 2=Admin */
	var $ui						=	0;
	/**	@var int 1=Display 2=Edit */
	var $action					=	0;
	/** @var string adds additional validation javascript for edit tabs */
	var $fieldJS				=	'';
	/**	@var array by position of tab objects for displaying */
	var $tabsToDisplay			=	array();
	/**	@var array by position of positions already rendered */
	var $renderedPositions		=	array();
	/**	@var array by tabid of tab contents for displaying */
	var $tabsContents			=	array();
	/** @var array to step down html output formatting */
	var $_stepDownFormatting	=	array( 'table' => 'tr', 'tabletrs' => 'tr', 'tr' => 'td', 'td' => 'div', 'divs' => 'div', 'div' => 'span', 'span' => 'span', 'uls' => 'ul', 'ul' => 'li', 'ols' => 'ol', 'ol' => 'li', 'li' => 'div', 'none' => 'none' );
	/** @var array to reorder javascript */
	var $_tabPanesJs			=	array();
	/** @var array to reorder javascript */
	var $_outputScripts;
	/**
	* Constructor
	* Includes files needed for displaying tabs and sets cookie options
	* @param int  $useCookies  If set to 1 cookie will hold last used tab between page refreshes
	* @param int  $ui          user interface: 1: frontend, 2: backend
	* @param int  $mode        Reserved for future use, short-term workaround for to early script output (was cbCalendar object reference)
	* @param boolean $outputTabpaneScript  TRUE (DEFAULT): output scripts for tabpanes, FALSE: silent, no echo output
	*/
	function cbTabs( $useCookies, $ui, $mode = null, $outputTabpaneScript = true ) {
		global $_CB_framework;

		static $scriptOut		=	false;

		$this->cbTabHandler();
		$this->ui				=	$ui;
		$this->useCookies		=	$useCookies;
		$this->_outputScripts	=	$outputTabpaneScript;
		if ( $outputTabpaneScript && ! $scriptOut ) {
			$head				=
				"var cbshowtabsArray = new Array();\n"
			.	"function showCBTab( sName ) {\n"
			.	"	if ( typeof(sName) == 'string' ) {\n"
			.	"		sName = sName.toLowerCase();\n"
			.	"	}\n"
			.	"	for (var i=0;i<cbshowtabsArray.length;i++) {\n"
			.	"		for (var j=0;j<cbshowtabsArray[i][0].length;j++) {\n"
			.	"			if (cbshowtabsArray[i][0][j] == sName) {\n"
			.	"				eval(cbshowtabsArray[i][1]);\n"
			.	"				return;\n"
			.	"			}\n"
			.	"		}\n"
			.	"	}\n"
			.	"}\n"
			;
			// $_CB_framework->document->addHeadScriptUrl( '/components/com_comprofiler/js/tabpane.js', true, null, $head );
			$_CB_framework->addJQueryPlugin( 'cb_webfxtabs', '/components/com_comprofiler/js/tabpane.js' );
			$_CB_framework->outputCbJQuery( $head, 'cb_webfxtabs' );
			$scriptOut			=	true;
		}
	}

	/**
	* creates a tab pane and creates JS obj
	* @param string The Tab Pane Name
	*/
	function startPane( $id ) {
		global $_CB_framework;

		if ( $this->_outputScripts ) {
			$js							=	'var tabPane' . $id . ' = new WebFXTabPane(document.getElementById("' . $id . '"),' . ( $this->useCookies ? 'true' : 'false' ) . ');';
			if ( isset( $this->_tabPanesJs[$id] ) && is_array( $this->_tabPanesJs[$id] ) ) {
				$js						.=	"\n" . implode( "\n", $this->_tabPanesJs[$id] );
			}
			$_CB_framework->outputCbJQuery( $js, 'cb_webfxtabs' );
			$this->_tabPanesJs[$id]	=	true;
		}
		return "<div class=\"tab-pane\" id=\"".$id."\">";
	}

	/**
	* Ends Tab Pane
	*/
	function endPane( ) {
		return '</div>';
	}

	/**
	* Creates a tab with title text and starts that tabs page
	* @param pID - This is the pane unique identifier
	* @param tabText - This is what is displayed on the tab
	* @param paneid - This is the parent pane to build this tab on
	*/
	function startTab( $pID, $tabText, $paneid ) {
		// not needed anymore since DOM is ready when we start:
		//	$js		=	'tabPane' . $pID . '.addTabPage(document.getElementById("cbtab' . $paneid . '"));';
		//	$this->_outputJs( $pID, $js );
		return '<div class="tab-page" id="cbtab' . $paneid . '">'
		.	'<h2 class="tab">' . $tabText . '</h2>';
	}

	/**
	* Ends a tab page
	*/
	function endTab( ) {
		return '</div>';
	}
	/**
	* Loads tabs list from database (if not already loaded)
	* @access private
	* @param object cb user object to display
	* @param string name of position if only one position to display (default: null)
	* @return array of object tabs from comprofiler tabs database (ordered by position, ordering)
	*/
	function _loadTabsList( &$user, $position = '' ) {
		global $_CB_database, $_CB_framework;

		if ( ! isset( $this->tabsToDisplay[$position] ) ) {
			$_CB_database->setQuery( "SELECT * FROM #__comprofiler_tabs t"
			. "\n WHERE t.enabled=1"
			. ( $position == '' ? "" : "\n AND t.position = " . $_CB_database->Quote( $position ) )
			. ( ! ( ( $_CB_framework->getUi() == 2 ) && $_CB_framework->acl->amIaSuperAdmin() ) ?
					"\n AND t.viewaccesslevel IN ("   . implode( ',', CBuser::getMyInstance()->getAuthorisedViewLevelsIds( false ) ) . ')'
				.	"\n AND t.useraccessgroupid IN (" . implode( ',', $_CB_framework->acl->get_groups_below_me( null, true ) ) .')'
				: '' )
			. "\n ORDER BY t.position, t.ordering" );
			$this->tabsToDisplay[$position]		=	$_CB_database->loadObjectList( 'tabid', 'moscomprofilerTabs', array( &$_CB_database ) );

			// THIS is VERY experimental, and not yet part of CB API !!! :
			global $_PLUGINS;
			$_PLUGINS->loadPluginGroup( 'user' );
			$_PLUGINS->trigger( 'onAfterTabsFetch', array( &$this->tabsToDisplay[$position], &$user, 'profile' ) );
		}
	}
	/**
	* Gets html code for all cb tabs, sorted by position (default: all, no position name in db means "cb_tabmain")
	* @param moscomprofilerUser  $user  object to display
	* @param string              $position  name of position if only one position to display (default: null)
	* @param int                 $tabid   Only a specific tab
	*/
	function generateViewTabsContent( $user, $position = '', $tabid = null, $output = 'html', $formatting = null /* 'table' or 'divs' */, $reason = 'profile' ) {
		global $_CB_OneTwoRowsStyleToggle, $ueConfig, $_PLUGINS;

		if ( $formatting === null ) {
			$formatting								=	( isset( $ueConfig['use_divs'] ) && $ueConfig['use_divs'] ? 'divs' : 'table' );
		}

		$tabOneTwoRowsStyleToggle					=	array();
		$this->action								=	1;

		$this->_loadTabsList( $user );

		static $menusPrepared						=	false;
		if ( ! $menusPrepared ) {
			$_PLUGINS->trigger( 'onPrepareMenus', array( &$user ) );
			$menusPrepared							=	true;
		}

		// optimize rendering only for position if tab rendering required (needed because of the $_CB_OneTwoRowsStyleToggle
		if ( $tabid && ! $position ) {
			if ( isset( $this->tabsToDisplay[''][$tabid] ) ) {
				$position	=	( $this->tabsToDisplay[''][$tabid]->position == '' ? 'cb_tabmain' : $this->tabsToDisplay[''][$tabid]->position );

			}
		}

		if ( isset( $this->renderedPositions[$position] ) ) {
			// all tabs are already rendered:
			return;
		}

		//Pass 1: gets all menu and status content + initializes tabsToDisplay[$position] with list of tabs if needed:
		foreach( $this->tabsToDisplay[''] AS $k => $oTab ) {
			if ( ( ! isset( $oTab->position ) ) || ( $oTab->position == '' ) ) {
				$oTab->position						=	'cb_tabmain';
			}
			if( $oTab->pluginclass != null ) {
				$this->_callTabPlugin( $oTab, $user, $oTab->pluginclass, 'getMenuAndStatus', $oTab->pluginid );
			}
			if ( ( $position == '' ) || ( $oTab->position == $position ) ) {
				$this->tabsToDisplay[$oTab->position][$k]	=	$oTab;
			}
		}

		$this->renderedPositions[$position]		=	true;

		if ( ! isset( $this->tabsToDisplay[$position] ) ) {
			return;
		}

		//Pass 2: generate content
		foreach( $this->tabsToDisplay[$position] AS $k => $oTab ) {
			$pos									=	$oTab->position;
			if ( ! isset( $tabOneTwoRowsStyleToggle[$pos] ) ) {
				$tabOneTwoRowsStyleToggle[$pos]	=	1;
			}

			$this->tabsContents[$k]				=	'';
			if( $oTab->pluginclass != null ) {
				$_CB_OneTwoRowsStyleToggle			=	$tabOneTwoRowsStyleToggle[$pos];
				$pluginTabContent					=	$this->_callTabPlugin($oTab, $user, $oTab->pluginclass, 'getDisplayTab', $oTab->pluginid);
				if ( is_array( $pluginTabContent ) ) {
					$this->tabsContents[$k]			.=	$this->_renderFields( $pluginTabContent, $user, $output, $formatting, $reason, array() );
				} else {
					$this->tabsContents[$k]			.=	$pluginTabContent;
				}
				$tabOneTwoRowsStyleToggle[$pos]	=	$_CB_OneTwoRowsStyleToggle;
			}
		}
		foreach( $this->tabsToDisplay[$position] AS $k => $oTab ) {
			$pos									=	$oTab->position;
			if ( $oTab->fields ) {
				$_CB_OneTwoRowsStyleToggle			=	$tabOneTwoRowsStyleToggle[$pos];
				$this->tabsToDisplay[$position][$k]->_fieldsCount				=	0;
				$this->tabsContents[$k]			.=	$this->_getTabContents( $oTab->tabid, $user, $this->tabsToDisplay[$position][$k]->_fieldsCount, $output, $formatting, $reason );
				$tabOneTwoRowsStyleToggle[$pos]	=	$_CB_OneTwoRowsStyleToggle;
			}
		}
		$_PLUGINS->trigger( 'onAfterPrepareViewTabs', array( &$this->tabsContents, &$this->tabsToDisplay[$position], &$user, $position, $tabid ) );
	}
	function getProfileTabHtml( $tabid, $default = null ) {
		if ( isset( $this->tabsContents[$tabid] ) ) {
			return $this->tabsContents[$tabid];
		}
		return $default;
	}
	/**
	* Gets html code for all cb tabs, sorted by position (default: all, no position name in db means "cb_tabmain")
	* @param object cb user object to display
	* @param string name of position if only one position to display (default: null)
	* @return array of string with html to display at each position, key = position name, or NULL if position is empty.
	*/
	function getViewTabs( $user, $position = '' ) {
		global $ueConfig;

		// returns cached rendering if needed:
		static $renderedCache					=	array();
		if ( isset( $renderedCache[$user->id] ) ) {
			if ( $position == '' ) {
				return $renderedCache[$user->id];
			}
			if ( isset( $renderedCache[$user->id][$position] ) ) {
				return array( $position => $renderedCache[$user->id][$position] );
			}
		}

		// detects recursion loops (e.g. trying to render a position within a position !):
		static $callCounter						=	0;
		if ( $callCounter++ > 10 ) {
			echo 'Rendering recursion for CB position: ' . $position;
			trigger_error( 'Rendering recursion for CB position: ' . $position, E_USER_ERROR );
			exit( 1 );
		}

		// loads the tabs and generate the inside content of the tab:
		$this->generateViewTabsContent( $user, $position );

		// recursion counter decrement:
		$callCounter--;

		if ( ! isset( $this->tabsToDisplay[$position] ) ) {
			return null;
		}

	//	$output									=	'html';
		$html									=	array();
		$results								=	array();
		$oNest									=	array();
		$i										=	0;
		$tabNavJS								=	array();

		//Pass 3: generate formatted output for each position by display type (keeping tabs together in each position)
		foreach( $this->tabsToDisplay[$position] AS $k => $oTab ) {
			$pos								=	$oTab->position;
			if( ! isset($html[$pos] ) ) {
				$html[$pos]						=	'';
				$results[$pos]					=	'';
				$oNest[$pos]					=	'';
				$tabNavJS[$pos]					=	array();
			}
			// handles content of tab:
			$tabContent							=	$this->tabsContents[$k];

			if ( ( $tabContent != '' ) || ( $oTab->fields && ( $oTab->_fieldsCount > 0 ) && isset( $ueConfig['showEmptyTabs'] ) && ( $ueConfig['showEmptyTabs'] == 1 ) ) ) {
				$overlaysWidth 					=	'400';			//BB later this could be one more tab parameter...
				$tabTitle						=	cbReplaceVars( getLangDefinition( $oTab->title ), $user );
				switch ($oTab->displaytype) {
				//	case "template":
				//		$cbTemplate	=	HTML_comprofiler::_cbTemplateLoad();
				//		$html[$pos] .=	HTML_comprofiler::_cbTemplateRender( $cbTemplate, $user, 'Profile', 'drawTab', array( &$user, $oTab, $tabTitle, $tabContent, 'cb_tabid_' . $oTab->tabid ), $output );
				//		break;
					case "html":
						$html[$pos] .=	"\n\t\t\t<div class=\"cb_tab_html cb_tab_content\" id=\"cb_tabid_" . $oTab->tabid . "\">"
									.	$tabContent
									.  "\n\t\t\t</div>\n";
						break;
					case "div":
						$html[$pos] .= "\n\t\t\t<div class=\"cb_tab_container cb_tab_content cb_tab_div\" id=\"cb_tabid_" . $oTab->tabid . "\">"
									.  "\n\t\t\t\t<div class=\"contentheading\">" . $tabTitle . "</div>"
									.  "\n\t\t\t\t<div class=\"contentpaneopen\">".$tabContent."</div>"
									.  "\n\t\t\t</div>\n";
						break;
					case "rounddiv":
						$html[$pos] .=	'<div class="cbtmpldialog cb_tab_rounddiv"><div class="cbtmplhd"><div class="cbtmplc"></div></div><div class="cbtmplbd"><div class="cbtmplc"><div class="cbtmpls">'
									. '<div class="cb_tab_container cb_tab_content" id="cb_tabid_' . $oTab->tabid . '">'
									.  '<div class="contentheading">' . $tabTitle . '</div>'
									.  '<div class="contentpaneopen">' . $tabContent . '</div>'
									.  '</div>'
									.	'</div></div></div><div class="cbtmplft"><div class="cbtmplc"></div></div></div>';
						break;
					case "overlib":
						$tipTitle	= $htmltext = $tabTitle;
						$fieldTip	= "&lt;div class=\"contentpaneopen cb_tab_content cb_tab_overlib\" id=\"cb_tabid_" . $oTab->tabid . "\" style=\"width:100%\"&gt;".$tabContent."&lt;/div&gt;";
						$style		= "class=\"cb-tips-hover\"";
						$olparams	= '';
						$html[$pos]	.= cbFieldTip($this->ui, $fieldTip, $tipTitle, $overlaysWidth, '', $htmltext, "", $style, $olparams,false);
						break;
					case "overlibfix":
						$tipTitle	= $htmltext = $tabTitle;
						$fieldTip	= "&lt;div class=\"contentpaneopen cb_tab_content cb_tab_overlib_fix\" id=\"cb_tabid_" . $oTab->tabid . "\" style=\"width:100%\"&gt;".$tabContent."&lt;/div&gt;";
						$style		= "class=\"cb-tips-hover\"";
						$olparams	= "STICKY,NOCLOSE,CLOSETEXT,'"._UE_CLOSE_OVERLIB."'";
						$html[$pos]	.= cbFieldTip($this->ui, $fieldTip, $tipTitle, $overlaysWidth, '', $htmltext, "", $style, $olparams,false);
						break;
					case "overlibsticky":
						$tipTitle	=	$tabTitle;
						$htmltext	=	$tabTitle;
						$href		=	'javascript:void(0)';
						$fieldTip	=	"&lt;div class=\"contentpaneopen cb_tab_content cb_tab_overlib_sticky\" id=\"cb_tabid_" . $oTab->tabid . "\" style=\"width:100%\"&gt;".$tabContent."&lt;/div&gt;";
						$style		=	"class=\"cb-tips-button\" title=\""._UE_CLICKTOVIEW." ".$tipTitle."\"";
						$olparams	=	"STICKY,CLOSECLICK,CLOSETEXT,'"._UE_CLOSE_OVERLIB."'";
						$html[$pos]	.=	cbFieldTip($this->ui, $fieldTip, $tipTitle, $overlaysWidth, '', $htmltext, $href, $style, $olparams,true);
						break;
					case "tab":
					default:
						//$results .= $this->startPane($pos);	done at the end below
						if ( $ueConfig['nesttabs'] && $oTab->fields && ( ( $oTab->pluginclass == null ) || ( $oTab->sys == 2 ) ) ) {
							$oNest[$pos] .= $this->startTab("CBNest".$pos, $tabTitle,$oTab->tabid)
									. "\n\t\t\t<div class=\"tab-content cb_tab_content cb_tab_tab_nested\" id=\"cb_tabid_" . $oTab->tabid . "\">"
									. $tabContent
									. "</div>\n"
									. $this->endTab();
							$tabNavJS[$pos][$i]->nested = true;
						} else {
							$results[$pos] .= $this->startTab($pos, $tabTitle,$oTab->tabid)
									. "\n\t\t\t<div class=\"tab-content cb_tab_content cb_tab_tab_main\" id=\"cb_tabid_" . $oTab->tabid . "\">"
									. $tabContent
									. "</div>\n"
									. $this->endTab();
							$tabNavJS[$pos][$i]->nested = false;
						}
						$tabNavJS[$pos][$i]->name			=	$tabTitle;
						$tabNavJS[$pos][$i]->id				=	$oTab->tabid;
						$tabNavJS[$pos][$i]->pluginclass	=	$oTab->pluginclass;
						$i++;
						break;
				}
			}
		}	//foreach tab
		// Pass 4: concat different types, generating tabs preambles/postambles:
		foreach ( $html as $pos => $val ) {
			if ( $ueConfig['nesttabs'] && $oNest[$pos] ) {
				$oNestPre = $this->startTab($pos,_UE_PROFILETAB,$pos . 0)
						  . "<div class=\"cb_tab_contains_tab\" id=\"cb_position_" . $pos . "\">"
						  . $this->startPane("CBNest".$pos);
				$oNest[$pos] .= $this->endPane()
							 . "</div>"
							 . $this->endTab();
				$results[$pos] = $oNestPre.$oNest[$pos].$results[$pos];

				// reorder tabs to regroup nested ones:
				$newNavJS						=	array();
				$i								=	0;
				foreach ( $tabNavJS[$pos] as $k => $v ) {
					if ( $v->nested ) {
						$newNavJS[$i++]			=	$v;
					}
				}
				if ( count( $newNavJS ) > 0 ) {
					$newNavJS[$i]->name			=	_UE_PROFILETAB;
					$newNavJS[$i]->id			=	32000;
					$newNavJS[$i]->pluginclass	=	'profiletab';
					$newNavJS[$i]->nested		=	false;
					$i++;
				}
				foreach ( $tabNavJS[$pos] as $k => $v ) {
					if ( ! $v->nested ) {
						$newNavJS[$i++]			=	$v;
					}
				}
				$tabNavJS[$pos]					=	$newNavJS;

			}
			if ( $results[$pos] ) {
				if ($val) {
					$html[$pos] .= "<br />";
				}
				$html[$pos]		.= $this->_getTabNavJS($pos, $tabNavJS[$pos])
								. $this->startPane($pos)
								. $results[$pos]
								. $this->endPane();
			}
		}

		// cache rendering if it's the complete rendering:
		if ( $position == '' ) {
			$renderedCache[$user->id]		=	$html;
		}

		return $html;
	}

	function getEditTabs( &$user, $postdata = null, $output = 'htmledit', $formatting = 'table', $reason = 'edit', $tabbed = true ) {
		global $ueConfig, $_PLUGINS;

		$i									=	0;
		$tabNavJS							=	array();
		$this->action						=	2;
		$this->fieldJS						=	'';
		$results							=	'';
		$nestResults						=	'';

		$oTabs								=	$this->_getTabsDb( $user, $reason );

		$initFieldsToDefault				=	( ( $reason == 'register' ) && ( $postdata === null ) )
												|| ( ( $reason == 'edit' ) && ( $user->id == null ) && ( $postdata === null ) );
		// if tab does not display CB fields by CB, and we are registering or creating a new user, we still need to init fields to default value:
		if ( $initFieldsToDefault ) {
			$fields							=	$this->_getTabFieldsDb( null, $user, $reason, null, true );
			if ( is_array( $fields ) ) {
				foreach ( $fields as $oField ) {
					$this->_initFieldToDefault( $oField, $user, $reason );
				}
			}
		}
		$oContent							=	'';
		foreach( $oTabs AS $oTab ) {
			// get Content from super-tabs:	// experimental event:
			$_PLUGINS->trigger( 'onBeforeEditATab', array( &$oContent, &$oTab, &$user, &$postdata, $output, $formatting, $reason, $tabbed ) );

			// get Content from plugin tabs:
			if ( $oTab->pluginclass != null ) {
				if ( $reason == 'register' ) {
					$userNull				=	null;
					$pluginTabContent		=	$this->_callTabPlugin( $oTab, $userNull, $oTab->pluginclass, 'getDisplayRegistration', $oTab->pluginid, $postdata );
				} else {
					$pluginTabContent		=	$this->_callTabPlugin( $oTab, $user, $oTab->pluginclass, 'getEditTab', $oTab->pluginid );
				}
				if ( is_array( $pluginTabContent ) ) {
					$oContent				.=	$this->_renderFields( $pluginTabContent, $user, $output, $formatting, $reason, array() );
				} else {
					$oContent				.=	$pluginTabContent;
				}
				$this->fieldJS				.=	$this->_getVarPlugin( $oTab, $oTab->pluginclass, 'fieldJS', $oTab->pluginid );
			}
			// get Content from fields:
			if ( $oTab->fields ) {
				if ( ( $oTab->pluginclass != null ) || ( $reason == 'register' ) ) {
					$oTab->description		=	null;
				}
				$oContent					.=	$this->_getEditTabContents( $oTab, $user, $output, $formatting, $reason, true );
			}

			// get Content from super-tabs:	// experimental event:
			$_PLUGINS->trigger( 'onAfterEditATab', array( &$oContent, &$oTab, &$user, &$postdata, $output, $formatting, $reason, $tabbed ) );

			if ( $tabbed && ( $oContent != '' ) ) {
				$tabTitle					=	cbReplaceVars( getLangDefinition( $oTab->title ), $user );
				$tabNavJS[$i]				=	new stdClass();
				if ( $ueConfig['nesttabs'] && $oTab->fields && ( ( $oTab->pluginclass == null ) || ( $oTab->sys == 2 ) ) ) {
					if ( ! $nestResults ) {
						$nestResults = $this->startTab("CB",_UE_PROFILETAB, 0)
						. "<div class=\"cb_tab_contains_tab\" id=\"cb_position_" . $oTab->position . "\">"
						. $this->startPane( "CBNest" . "CB" );
					}
					$nestResults			.=	$this->startTab( "CBNest" . "CB", $tabTitle, $oTab->tabid )
											.	$oContent
											.	$this->endTab()
											;
					$tabNavJS[$i]->nested	=	true;
				} else {
					$results				.=	$this->startTab( "CB", $tabTitle, $oTab->tabid )
											.	$oContent
											.	$this->endTab()
											;
					$tabNavJS[$i]->nested	=	false;
				}
				$tabNavJS[$i]->name			=	getLangDefinition($oTab->title);
				$tabNavJS[$i]->id			=	$oTab->tabid;
				$tabNavJS[$i]->pluginclass	=	$oTab->pluginclass;
				$i++;
				$oContent					=	'';
			}
		}
		if ( $nestResults ) {
			// reorder tabs to regroup nested ones:
			$newNavJS						=	array();
			$i								=	0;
			foreach ( $tabNavJS as $k => $v ) {
				if ( $v->nested ) {
					$newNavJS[$i++]			=	$tabNavJS[$k];
				}
			}
			if ( count( $newNavJS ) > 0 ) {
				$newNavJS[$i]				=	new stdClass();
				$newNavJS[$i]->name			=	_UE_PROFILETAB;
				$newNavJS[$i]->id			=	32000;
				$newNavJS[$i]->pluginclass	=	'profiletab';
				$newNavJS[$i]->nested		=	false;
				$i++;
			}
			foreach ( $tabNavJS as $k => $v ) {
				if ( ! $v->nested ) {
					$newNavJS[$i++]			=	$tabNavJS[$k];
				}
			}
			$tabNavJS						=	$newNavJS;

			$nestResults					.=	$this->endPane()
											.	"</div>"
											.	$this->endTab();
			$results						=	$nestResults . $results;
			$nestResults					=	"";
		}

		if ( $tabbed ) {
			$return							=	$this->_getTabNavJS("CB", $tabNavJS)
											.	$this->startPane("CB")
											.	$results
											.	$this->endPane()
											;
			return $return;
		} else {
			return $oContent;
		}
	}

	function _getTabNavJS( $pID, &$tabs ) {
		global $ueConfig;

		$i					=	0;
		$i_nest				=	0;
		if( ! ( count( $tabs ) > 0 ) ) {
			return '';
		}
		$js					=	'var tabPane'.$pID.";\n";
		if ($ueConfig['nesttabs']) {
			$js				.=	'var tabPaneCBNest'.$pID.";\n";
		}

		foreach( $tabs AS $tab ) {
			$evals			=	'tabPane'.$pID.'.setSelectedIndex( '.$i.' );';
			if ( $tab->nested ) {
				$evals		.=	'tabPaneCBNest'.$pID.'.setSelectedIndex( '.$i_nest.' );';
			}

			$values			=	array();
			$values[]		=	addslashes( strtolower( $tab->name ) );
			$values[]		=	$tab->id;
			if ( $tab->pluginclass != null ) {
				$values[]	=	addslashes( strtolower($tab->pluginclass) );
			}

			$js			.=	"cbshowtabsArray.push( [['" . implode( "','", $values ) . "'],'" . $evals . "'] );\n";

			if ( $tab->nested ) {
				$i_nest++;
			}
			else {
				$i++;
			}
		}
		$this->_outputJs( $pID, $js );
		return null;
	}
	function _outputJs( $pID, $js ) {
		global $_CB_framework;

		if ( $this->_outputScripts ) {
			if ( isset( $this->_tabPanesJs[$pID] ) && ( $this->_tabPanesJs[$pID] === true ) ) {
				$_CB_framework->outputCbJQuery( $js, 'cb_webfxtabs' );
			} else {
				// in case startTab or _getTabNavJS gets called bafore corresponding startPane:
				$this->_tabPanesJs[$pID][]		=	$js;
			}
		}
	}
	/**
	 * Gets tabs for $reason (WARNING: here we have 'editsave' as additional reason !)
	 *
	 * @param  moscomprofilerUser  $user
	 * @param  string              $reason ( 'profile', 'register', 'list', 'edit', 'editsave' )
	 * @return array of moscomprofilerTabs
	 */
	function & _getTabsDb( &$user, $reason ) {
		global $_CB_framework, $_CB_database;

		static $tabsCache	=	null;

		if ( $tabsCache === null ) {
			$sql			=	'SELECT * FROM #__comprofiler_tabs t'
							.	"\n WHERE t.enabled = 1";
			if ( ! ( ( $_CB_framework->getUi() == 2 ) && $_CB_framework->acl->amIaSuperAdmin() ) ) {
				if ( $reason != 'register' ) {
					$sql	.=	"\n AND t.viewaccesslevel IN ("   . implode( ',', CBuser::getMyInstance()->getAuthorisedViewLevelsIds( false ) ) . ')'
							.	"\n AND t.useraccessgroupid IN (" . implode(',', $_CB_framework->acl->get_groups_below_me( null, true ) ) . ")";
				}
			}
			$sql			.=	"\n ORDER BY ";
			if ( $reason == 'register' ) {
				$sql		.=	't.ordering_register, ';
			}
			$sql			.=	't.position, t.ordering';
			$_CB_database->setQuery( $sql );
			$tabsCache		=	$_CB_database->loadObjectList( 'tabid', 'moscomprofilerTabs', array( &$_CB_database ) );

			// THIS is VERY experimental, and not yet part of CB API !!! :
			global $_PLUGINS;
			$_PLUGINS->loadPluginGroup( 'user' );
			$_PLUGINS->trigger( 'onAfterTabsFetch', array( &$tabsCache, &$user, $reason ) );
		}
		return $tabsCache;
	}
	function _getTabFieldsDb( $tabid, &$user, $reason, $fieldIdOrName = null, $prefetchFields = true, $fullAccess = false ) {
		static $prefetched		=	array();
		static $fieldsByName	=	null;

		$preIdx					=	$fullAccess ? 'full' : $reason;

		if ( ( ! $prefetchFields ) || ! isset( $prefetched[$preIdx] ) ) {

			global $_CB_framework, $_CB_database, $ueConfig;

			$where				=	array();
			$ordering			=	array();

			if ( $fieldIdOrName && ! $prefetchFields ) {
				if ( is_int( $fieldIdOrName ) ) {
					$where[]	=	'f.fieldid = ' . (int) $fieldIdOrName;
				} else {
					$where[]	=	'f.name = ' . $_CB_database->Quote( $fieldIdOrName );
				}
			}
			if ( ( $reason == 'list' ) && ( in_array( $ueConfig['name_format'], array( 1, 2, 4 ) ) ) ) {
				$where[]		=	"( f.published = 1 OR f.name = 'name' )";
			} elseif ( $reason != 'adminfulllist' ) {
				$where[]		=	'f.published = 1';
			}
			if ( ! $fullAccess ) {
				switch ( $reason ) {
					case 'profile':
						$where[]	=	'f.profile != 0';
						break;
					case 'list':
						$where[]	=	"( f.profile != 0 OR f.name = 'username'" . ( in_array( $ueConfig['name_format'], array( 1, 2, 4 ) ) ? " OR f.name = 'name'" : '' ) . ')';
						break;
					case 'register':
						$where[]	=	'f.registration = 1';
						break;
					case 'adminfulllist':
					default:
						break;
				}

				if ( $tabid && ! $prefetchFields ) {
					$where[]		=	'f.tabid = ' . (int) $tabid;
				} else {
					if ( $reason != 'adminfulllist' ) {
						$where[]	=	't.enabled = 1';
					}
					if ( ( $reason != 'register' ) && ! ( ( $_CB_framework->getUi() == 2 ) && $_CB_framework->acl->amIaSuperAdmin() ) ) {
						$where[]	=	't.viewaccesslevel IN ('   . implode( ',', CBuser::getMyInstance()->getAuthorisedViewLevelsIds( false ) ) . ')';
						$where[]	=	't.useraccessgroupid IN (' . implode(',', $_CB_framework->acl->get_groups_below_me( null, true ) ) . ')';
					}
				}
				if ( ( ( $reason == 'profile' ) || ( $reason == 'list' ) ) && ( $ueConfig['allow_email_display'] == 0 ) && ( $reason != 'adminfulllist' ) ) {
					$where[]		=	'f.type != ' . $_CB_database->Quote( 'emailaddress' );
				}
			}
			if ( ( ! $tabid ) || $prefetchFields ) {
				if ( $reason == 'register' ) {
					$ordering[]	=	't.ordering_register';
				}
				$ordering[]		=	't.position';
				$ordering[]		=	't.ordering';
			}
			$ordering[]			=	'f.ordering';

			$sql				=	'SELECT f.*';
			if ( $reason == 'register' ) {
				$sql			.=	', t.ordering_register AS tab_ordering_register, t.position AS tab_position, t.ordering AS tab_ordering';
			}
			$sql				.=	' FROM #__comprofiler_fields f';
			if ( ( ! $tabid ) || $prefetchFields ) {
				// don't get fields which are not assigned to tabs:
				$sql			.=	"\n INNER JOIN #__comprofiler_tabs AS t ON (f.tabid = t.tabid)";
			}
			$sql				.=	( $where ? "\n WHERE " . implode( ' AND ', $where ) : '' )
								.	"\n ORDER BY " . implode( ', ', $ordering );
								;
			$_CB_database->setQuery( $sql );
			if ( $prefetchFields ) {
				$fieldsByName	=	$_CB_database->loadObjectList( 'name', 'moscomprofilerFields', array( &$_CB_database ), true );		// true means strtolower array indexes of name
				if ( ! $_CB_database->getErrorNum() ) {
					foreach ( array_keys( $fieldsByName ) as $i ) {
						$fieldsByName[$i]->params								=	new cbParamsBase( $fieldsByName[$i]->params );
						$prefetched[$preIdx][(int) $fieldsByName[$i]->tabid][$fieldsByName[$i]->fieldid]	=	$fieldsByName[$i];
					}
				}
			} else {
				$fields			=	$_CB_database->loadObjectList( null, 'moscomprofilerFields', array( &$_CB_database ) );
				if ( ! $_CB_database->getErrorNum() ) {
					for ( $i = 0, $n = count( $fields ); $i < $n; $i++ ) {
						$fields[$i]->params										=	new cbParamsBase( $fields[$i]->params );
					}
				}
			}
		}
		if ( isset( $prefetched[$preIdx] ) ) {
			if ( $tabid ) {
				if (isset( $prefetched[$preIdx][(int) $tabid] ) ) {
					$fields		=	$prefetched[$preIdx][(int) $tabid];
				} else {
					$fields		=	array();
				}
			} elseif ( $fieldIdOrName ) {
				if ( is_int( $fieldIdOrName ) ) {
					$fields		=	array();
					foreach ( array_keys( $prefetched[$preIdx] ) as $k ) {
						if ( isset( $prefetched[$preIdx][$k][$fieldIdOrName] ) ) {
							$fields[]	=	$prefetched[$preIdx][$k][$fieldIdOrName];
							break;
						}
					}
				} elseif (isset( $fieldsByName[strtolower( $fieldIdOrName )] ) ) {
					$fields		=	array( $fieldsByName[strtolower( $fieldIdOrName )] );
				} else {
					$fields		=	array();
				}
			} else {
				$fields			=	array();
				foreach ( $prefetched[$preIdx] as /* $tid => */ $flds ) {
				//	$fields		=	array_merge( $fields, $flds );
					foreach ( $flds as $fl ) {
						$fields[$fl->fieldid]	=	$fl;
					}
				}
			}
		}

		// THIS is VERY experimental, and not yet part of CB API !!! :
		global $_PLUGINS;
		$_PLUGINS->loadPluginGroup( 'user' );
		$_PLUGINS->trigger( 'onAfterFieldsFetch', array( &$fields, &$user, $reason, $tabid, $fieldIdOrName, $fullAccess ) );

		return $fields;
	}

	// old version for backwards compatibility in case a plugin uses it: DEPRECIATED:
	function _getViewTabContents( $tabid, &$user ) {
		$oFields				=	$this->_getTabFieldsDb( $tabid, $user, 'profile' );
		return $this->_getFieldsContents( $oFields, $user, (int) $tabid );
	}

	function _getTabContents( $tabid, &$user, &$fieldsCount, $output = 'html', $formatting = 'table' /* 'divs' */, $reason = 'profile' ) {
		$oFields				=	$this->_getTabFieldsDb( $tabid, $user, $reason );
		$fieldsCount			=	count( $oFields );
		return $this->_getFieldsContents( $oFields, $user, (int) $tabid, $output, $formatting, $reason );
	}

	function _getEditTabContents( &$tab, &$user, $output = 'htmledit', $formatting = 'table', $reason = 'edit', $prefetchFields = true ) {
		$results				=	'';

		if ( is_object( $tab ) ) {
			$tabid				=	(int) $tab->tabid;
		} else {
			$tabid				=	null;
		}
		$fields					=	$this->_getTabFieldsDb( $tabid, $user, $reason, null, $prefetchFields );
		if ( count( $fields ) > 0 ) {
			if ( $reason == 'edit' ) {
				$results		.=	$this->_writeTabDescription( $tab, $user );
			}
			$results			.=	$this->_getFieldsContents( $fields, $user, $tabid, $output, $formatting, $reason );
		}
		return $results;
	}
	function getSearchablesContents( &$searchableFields, &$userMe, &$searchVals, $list_compare_types, $output = 'htmledit', $formatting = 'divs', $reason = 'search' ) {
		global $_CB_database;

		$results				=	null;

		if ( count( $searchableFields ) > 0 ) {
			$user				=	new moscomprofilerUser( $_CB_database );
			$fields				=	$this->_getTabFieldsDb( null, $userMe, $reason, null, true );
			if ( is_array( $fields ) ) {
				foreach ( $fields as $oField ) {
					$this->_initFieldToDefault( $oField, $user, $reason );
				}
			}
			if ( is_object( $searchVals ) ) {
				foreach ( get_object_vars( $searchVals ) as $k => $v ) {
					$user->$k	=	$v;
				}
			}
/*
			if ( $postdata !== null ) {
				$user->bindSafely( $postdata, $_CB_framework->getUi(), $reason, $user );
			}
*/
			$results			=	$this->_getFieldsContents( $searchableFields, $user, 'listsearch', $output, $formatting, $reason, $list_compare_types );
		}
		return $results;
	}
	function applySearchableContents( &$searchableFields, &$searchVals, &$postdata, $list_compare_types, $reason = 'search' ) {
		global $_PLUGINS;

		$searches				=	new cbSqlQueryPart();
		$searches->tag			=	'where';
		$searches->type			=	'sql:operator';
		$searches->operator		=	'AND';

		$searchVals				=	new stdClass();
		foreach ( $searchableFields as $field ) {
			$fieldSearches		=	$_PLUGINS->callField( $field->type, 'bindSearchCriteria', array( &$field, &$searchVals, &$postdata, $list_compare_types, $reason ), $field );
			if ( count( $fieldSearches ) > 0 ) {
				$searches->addChildren( $fieldSearches );
			}
		}
		return $searches;
	}
	/**
	 * Saves all fields from all the visible tabs $postdata to $user
	 *
	 * @param  moscomprofilerUser  $user      RETURNED populated: touch only variables related to saving this field (also when not validating for showing re-edit)
	 * @param  array               $postdata  Typically $_POST (but not necessarily), filtering required.
	 * @param  string              $reason    'edit' for save user edit, 'register' for save registration
	 */
	function saveTabsContents( &$user, &$postdata, $reason ) {
		global $_CB_framework, $_PLUGINS;

		$fields					=	$this->_getTabFieldsDb( null, $user, $reason, null, false );
		$result					=	true;
		foreach ( $fields as $field ) {
			if ( ( ! ( ( $field->readonly > 0 ) && $_CB_framework->getUi() == 1 ) ) || ( $reason == 'register' ) || ( $reason == 'search' ) ) {
				$_PLUGINS->callField( $field->type, 'prepareFieldDataSave', array( &$field, &$user, &$postdata, $reason ), $field );
			} else {
				$_PLUGINS->callField( $field->type, 'prepareFieldDataNotSaved', array( &$field, &$user, &$postdata, $reason ), $field );
			}
		}
		return $result;
	}
	function commitTabsContents( &$user, &$postdata, $reason ) {
		global $_CB_framework, $_PLUGINS;

		$fields					=	$this->_getTabFieldsDb( null, $user, $reason, null, false );
		$result					=	true;
		foreach ( $fields as $field ) {
			if ( ( ! ( ( $field->readonly > 0 ) && $_CB_framework->getUi() == 1 ) ) || ( $reason == 'register' ) || ( $reason == 'search' ) ) {
				if ( ! $_PLUGINS->is_errors() ) {
					$_PLUGINS->callField( $field->type, 'commitFieldDataSave', array( &$field, &$user, &$postdata, $reason ), $field );
				}
			}
		}
		return $result;
	}
	function rollbackTabsContents( &$user, &$postdata, $reason ) {
		global $_CB_framework, $_PLUGINS;

		$fields					=	$this->_getTabFieldsDb( null, $user, $reason, null, false );
		$result					=	true;
		foreach ( $fields as $field ) {
			if ( ( ! ( ( $field->readonly > 0 ) && $_CB_framework->getUi() == 1 ) ) || ( $reason == 'register' ) || ( $reason == 'search' ) ) {
				if ( $_PLUGINS->is_errors() ) {
					$_PLUGINS->callField( $field->type, 'rollbackFieldDataSave', array( &$field, &$user, &$postdata, $reason ), $field );
				}
			}
		}
		return $result;
	}
	function savePluginTabs( &$user, &$postdata ) {
		global $_PLUGINS;

		$oTabs					=	$this->_getTabsDb( $user, 'editsave' );

		foreach ( $oTabs AS $oTab ) {
			if ( $oTab->pluginclass != null ) {
				$this->_callTabPlugin( $oTab, $user, $oTab->pluginclass, 'saveEditTab', $oTab->pluginid, $postdata );
				if ( $_PLUGINS->is_errors() ) {
					break;
				}
			}
		}
		return 1;
	}

	function getRegistrationPluginTabs( &$postdata ) {
		$results				=	array();
		$userNull				=	null;
		$oTabs					=	$this->_getTabsDb( $userNull, 'register' );
		foreach( $oTabs AS $oTab ) {
			if ( $oTab->pluginclass != null ) {
				if ( ! isset( $results[(int) $oTab->ordering_register][$oTab->position][(int) $oTab->ordering] ) ) {
					$results[(int) $oTab->ordering_register][$oTab->position][(int) $oTab->ordering]	=	'';
				}
				$results[(int) $oTab->ordering_register][$oTab->position][(int) $oTab->ordering]		.=	$this->_callTabPlugin( $oTab, $userNull, $oTab->pluginclass, 'getDisplayRegistration', $oTab->pluginid, $postdata );
				$this->fieldJS	.=	$this->_getVarPlugin($oTab, $oTab->pluginclass, 'fieldJS', $oTab->pluginid);
			}
		}
		return $results;
	}


	function saveRegistrationPluginTabs( &$user, &$postdata ) {
		$results				=	array();
		$userNull				=	null;
		$oTabs					=	$this->_getTabsDb( $userNull, 'register' );
		foreach( $oTabs AS $oTab ) {
			if( $oTab->pluginclass != null ) {
				$results[]		=	$this->_callTabPlugin($oTab, $user, $oTab->pluginclass, 'saveRegistrationTab', $oTab->pluginid, $postdata);
			}
		}
		return $results;
	}

	function _getFieldsContents( &$oFields, &$user, $tabid, $output = 'html', $formatting = 'table', $reason = 'profile', $list_compare_types = 0 ) {
		global $_CB_OneTwoRowsStyleToggle;

		$results										=	null;
		if ( is_array( $oFields ) ) {

			if ( cbStartOfStringMatch( $output, 'html' ) ) {

				$formattingFields						=	$this->_stepDownFormatting[$formatting];
				foreach( $oFields AS $oField ) {
					$results							.=	$this->_getSingleFieldContent( $oField, $user, $output, $formattingFields, $reason, $list_compare_types );
				}

				if ( $results != null ) {
					switch ( $formatting ) {
						case 'table':
							// only displayed at Profile Edit: $return .= $this->_writeTabDescription( $tab, $user );
							return "\n\t\t\t" . '<table class="cbFieldsContentsTab cbFields" id="cbtf_' . $tabid . '">' . $results . "\n\t\t\t</table>";
							break;

						case 'tr':
							$class 						=	'sectiontableentry' . $_CB_OneTwoRowsStyleToggle;
							$_CB_OneTwoRowsStyleToggle	=	( $_CB_OneTwoRowsStyleToggle == 1 ? 2 : 1 );
							return "\n\t\t\t\t<tr class=\"cbFieldsContentsTab " . $class . '" id="cbtf_' . $tabid . '">' . $results . "\n\t\t\t\t</tr>";

						case 'td':
							return "\n\t\t\t\t\t" . '<td class="cbFieldsContentsTab" id="cbtf_' . $tabid . '">' . $results . "\n\t\t\t\t\t</td>";

						case 'div':
						case 'divs':
							return '<div class="cbFieldsContentsTab" id="cbtf_' . $tabid . '">' . $results . '</div>';

						case 'span':
							return '<span class="cbFieldsContentsTab" id="cbtf_' . $tabid . '">' . $results . '</span>';

						case 'ul':
							return '<ul class="cbFieldsContentsList" id="cbtf_' . $tabid . '">' . $results . '</ul>';

						case 'ol':
							return '<ol class="cbFieldsContentsList" id="cbtf_' . $tabid . '">' . $results . '</ol>';

						case 'li':
							return '<li class="cbFieldsContentsList" id="cbtf_' . $tabid . '">' . $results . '</li>';

						case 'tabletrs':
						case 'none':
							return $results;

						default:
							return '*' . $results . '*';
							break;
					}
				}

			} else {

				foreach( $oFields AS $k => $oField ) {
					$results[$k]						=	$this->_getSingleFieldContent( $oField, $user, $output, $formatting, $reason );
				}

			}
		}
		return $results;
	}

	function _initFieldToDefault( &$field, &$user, $reason ) {
		global $_PLUGINS;
		$_PLUGINS->callField( $field->type, 'initFieldToDefault', array( &$field, &$user, $reason ), $field );
	}

	function _getSingleFieldContent( &$oField, &$user, $output = 'html', $formatting = 'tr', $reason = 'profile', $list_compare_types = 0 ) {
		global $_PLUGINS;
		return $_PLUGINS->callField( $oField->type, 'getFieldRow', array( &$oField, &$user, $output, $formatting, $reason, $list_compare_types ), $oField );
	}

	function _callTabPlugin( &$tab, &$user, $pluginclass, $method, $pluginid = null, $postdata = null ) {
		global $_PLUGINS;

		$results				=	null;
        if ( $pluginid ) {
	        if( $_PLUGINS->loadPluginGroup( 'user', array( (int) $pluginid ) ) ) {
	        	$args			=	array( &$tab , &$user, $this->ui, &$postdata );
	        	$results		=	$_PLUGINS->call( $pluginid, $method, $pluginclass, $args, ( is_object( $tab ) ? $tab->params : null ) );
	        }
        }
	    return $results;
	}
	function _renderFields( $pluginTabContent, $user, $output, $formatting, $reason, $rowClasses ) {
		global $_PLUGINS, $_CB_OneTwoRowsStyleToggle;

		$rendered				=	null;
		$formattingFields		=	$this->_stepDownFormatting[$formatting];
		foreach ($pluginTabContent as $field ) {
			$saveToggle			=	$_CB_OneTwoRowsStyleToggle;
			$rendered			.=	$_PLUGINS->callField( $field->type, 'renderFieldHtml', array( &$field, &$user, $field->_value, $output, $formattingFields, $reason, $rowClasses ), $field );
			if ( isset( $field->_rowNoToggle ) ) {
				$_CB_OneTwoRowsStyleToggle	=	$saveToggle;
			}
		}
		return $rendered;
	}
	/**
	 * builds a pseudo-moscomprofilerFields to return in array in getDisplayRegistration and getEditTab methods (in $output == 'htmledit' mode)
	 * @static
	 * @param  moscomprofilerTabs  $tab
	 * @param  string              $value
	 * @param  string              $title
	 * @param  string              $description
	 * @param  string              $uniqueId           unique id system-wide ( a-z,A-Z, _ )
	 * @param  boolean             $displayOnTwoLines
	 * @param  string              $name               if the $title refers to an input then this should be the name of the input for focus and tooltips to work properly
	 * @param  boolean             $rowstyletoggle     change row style toggle
	 * @return moscomprofilerFields
	 */
	function _createPseudoField( $tab, $title, $value, $description, $uniqueId, $displayOnTwoLines = false  , $name = null, $rowstyletoggle = true ) {
		global $_CB_database;

		$pseudoField					=	new moscomprofilerFields( $_CB_database );
		$pseudoField->_value			=	$value;
		if ( ! $rowstyletoggle ) {
			$pseudoField->_rowNoToggle	=	true;
		}
		$pseudoField->name				=	$name;
		$pseudoField->type				=	'delimiter';
		$pseudoField->title				=	$title;
		$pseudoField->description		=	$description;
		$pseudoField->fieldid			=	preg_replace( '/^W/', '_' , $uniqueId );
		$pseudoField->profile			=	( $displayOnTwoLines ? 2 : 1 );
		$pseudoField->displaytitle		=	1;
		return $pseudoField;
	}
	/**
	 * Returns Javascript code for the plugin
	 *
	 * @param  moscomprofilerTabs  $tab
	 * @param  string              $pluginclass
	 * @param  string              $variable      'fieldJs'
	 * @param  int                 $pluginid
	 * @return string
	 */
	function _getVarPlugin( &$tab, $pluginclass, $variable, $pluginid = null ) {
		global $_PLUGINS;
		return $_PLUGINS->getVar( $pluginid, $pluginclass, $variable );
	}


	/**
	* Loads plugin corresponding to tab from database and calls a method of it.
	* @param object cb user object to display
	* @param array $_POST data
	* @param string name of class to search for and to call
	* @param string name of method to call
	* @return mixed returned result of call (null if call not performed)
	*/
	function tabClassPluginTabs( &$user, &$postdata, $pluginName, $tabClassName, $method ) {
		global $_CB_database, $_PLUGINS;

		$result = null;

		if ($pluginName) {
			$query	=	"SELECT * FROM #__comprofiler_plugin p"
					.	"\n WHERE p.published = 1 AND p.element = " . $_CB_database->Quote( strtolower( $pluginName ) );
			$_CB_database->setQuery( $query );
			$pluginsList				=	$_CB_database->loadObjectList( null, 'moscomprofilerPlugin', array( &$_CB_database ) );
			if ( count( $pluginsList ) == 1 ) {
				$plugin					=	$pluginsList[0];
				if ( $_PLUGINS->loadPluginGroup( 'user', array( (int) $plugin->id ) ) ) {
					if ( class_exists( $tabClassName ) ) {
						$null			=	null;
						$result			=	$this->_callTabPlugin( $null, $user, $tabClassName, $method, $plugin->id, $postdata );
					}
				}
			}
		} else {
			$query	=	"SELECT * FROM #__comprofiler_tabs t"
					.	"\n WHERE t.enabled=1 AND t.pluginclass is not null AND LOWER(t.pluginclass) = "
					.	$_CB_database->Quote( strtolower( $tabClassName ) );
			// no ACL check here on purpose
			$_CB_database->setQuery( $query );
			$oTabs						=	$_CB_database->loadObjectList( null, 'moscomprofilerTabs', array( &$_CB_database ) );
			if ( count( $oTabs ) == 1 ) {
				$oTab					=	$oTabs[0];
				if( $oTab->pluginid && ( $oTab->pluginclass != null ) ) {
					if ( $_PLUGINS->loadPluginGroup( 'user', array( (int) $oTab->pluginid ) ) ) {
						// plugin exists and is published:
						$result			=	$this->_callTabPlugin( $oTab, $user, $oTab->pluginclass, $method, $oTab->pluginid, $postdata );
					}
				}
			}
		}
		return $result;
	}

	function fieldCall( $fieldIdOrName, &$user, &$postdata, $reason ) {
		global $_PLUGINS;

		$fields							=	$this->_getTabFieldsDb( null, $user, $reason, $fieldIdOrName, false );
		if ( is_array( $fields ) && ( count( $fields ) == 1 ) ) {
			foreach ( $fields as $field ) {
				$_PLUGINS->loadPluginGroup( 'user', array( (int) $field->pluginid ) );
				$fieldRes				=	$_PLUGINS->callField( $field->type, 'fieldClass', array( &$field, &$user, &$postdata, $reason ), $field );
				return $fieldRes;
			}
		}
		return false;
	}
}	// end Class cbTabs

/**
* PMS handler
* @package Community Builder
* @author Beat
*/
class cbPMS extends cbPMSHandler {
	var $PMSpluginsList;
	/**
	* Constructor
	*/
	function cbPMS() {
		$this->cbPMSHandler();
		$this->PMSpluginsList = null;
	}

	function _callPlugin($plug, $args, $pluginclass, $method) {
		global $_PLUGINS;
		$results=null;
		if ( $plug->id ) {
			if($_PLUGINS->loadPluginGroup('user', array( (int) $plug->id))) {
				$results=$_PLUGINS->call($plug->id, $method, $pluginclass, $args, $plug->params);
			}
		}
		return $results;
	}
	function _callPluginTypeMethod($type, $methodName, $args) {
		global $_CB_database;
		$results = array();
		if ($this->PMSpluginsList === null) {
			$_CB_database->setQuery( "SELECT * FROM #__comprofiler_plugin p"
			. "\n WHERE p.published=1 "
			. "\n AND p.element LIKE '%" . cbEscapeSQLsearch( trim( strtolower( $_CB_database->getEscaped($type) ) ) ) . ".%' "
			. "\n ORDER BY p.ordering" );
			$this->PMSpluginsList = $_CB_database->loadObjectList();
			if ( $_CB_database->getErrorNum() ) {
				return $results;
			}
		}
		foreach($this->PMSpluginsList AS $plug) {
			$className = 'get'.substr($plug->element, strlen($type)+1).'Tab';
			$results[] = $this->_callPlugin($plug, $args, $className, $methodName);
		}
		return $results;
	}
	/**
	* Sends a PMS message on the enabled "pms.*" plugins
	* @param int userId of receiver
	* @param int userId of sender
	* @param string subject of PMS message (UNESCAPED)
	* @param string body of PMS message (UNESCAPED)
	* @param boolean false: real user-to-user message; true: system-Generated by an action from user $fromid (if non-null)
	* @param boolean false: subject and message body UNESCAPED = default; true: ESCAPED
	* @return boolean : true for OK, or false if ErrorMSG generated. Special error: _UE_PMS_TYPE_UNSUPPORTED : if anonym fromid>=0 sysgenerated unsupported
	*/
	function sendPMSMSG($toid, $fromid, $subject, $message, $systemGenerated=false, $escaped=false) {
		$args = array($toid, $fromid, $subject, $message, $systemGenerated, $escaped);
		return $this->_callPluginTypeMethod("pms", "sendUserPMS", $args);
	}
	/**
	* returns all the parameters needed for a hyperlink or a menu entry to do a pms action
	* @param int userId of receiver
	* @param int userId of sender
	* @param string subject of PMS message
	* @param string body of PMS message
	* @param int kind of link: 1: link to compose new PMS message for $toid user. 2: link to inbox of $fromid user; 3: outbox, 4: trashbox,
	  5: link to edit pms options
	* @return mixed array of string {"caption" => menu-text ,"url" => NON-cbSef relative url-link, "tooltip" => description} or false and errorMSG
	*/
	function getPMSlinks($toid=0, $fromid=0, $subject="", $message="", $kind) {
		$args = array($toid, $fromid, $subject, $message, $kind);
		return $this->_callPluginTypeMethod("pms", "getPMSlink", $args);
	}
	/**
	* gets PMS system capabilities
	* @return mixed array of string {"subject" => boolean ,"body" => boolean} or false if ErrorMSG generated
	*/
	function getPMScapabilites() {
		$args = array();
		return $this->_callPluginTypeMethod("pms", "getPMScapabilites", $args);
	}
	/**
	* gets PMS unread messages count
	* @param	int user id
	* @return	mixed number of messages unread by user $userid or false if ErrorMSG generated
	*/
	function getPMSunreadCount($userid) {
		$args = array($userid);
		return $this->_callPluginTypeMethod("pms", "getPMSunreadCount", $args);
	}
}	// end Class cbPMS

/**
* Connections Class for handeling CB connections
* @package Community Builder
* @author MamboJoe
*/
class cbConnection {
	/**	@var errorMSG should be used to store the error message when an error is encountered*/
	var $errorMSG;
	/**	@var errorNum should be used to store the error number when an error is encountered*/
	var $errorNum;
	/**	@var referenceid should be used to store the userid related to base user of the connection action*/
	var $referenceid;
	/**	@var connectionid should be used to store the userid related to target user of the connection action*/
	var $connectionid;
	/**	@var degreeOfSep should be used to store the numeric value related to distance between referenceid and connectionid*/
	var $degreeOfSep;
	/**	@var userMSG should be used to store the message that needs to be returned to the user*/
	var $userMSG;

	function cbConnection($referenceid) {
		$this->referenceid=$referenceid;
		return;
	}
	function addConnection($connectionid,$umsg=null) {
		global $ueConfig, $_PLUGINS;

		$existingConnection = $this->getConnectionDetails( $this->referenceid , $connectionid );
		if ( $existingConnection === false ) {

			$_PLUGINS->loadPluginGroup('user');
			$_PLUGINS->trigger( 'onBeforeAddConnection', array($this->referenceid,$connectionid,$ueConfig['useMutualConnections'],$ueConfig['autoAddConnections'],&$umsg));
			if($_PLUGINS->is_errors()) {
				$this->_setUserMSG($_PLUGINS->getErrorMSG());
				return false;
			}

			if(!$this->_insertConnection($this->referenceid,$connectionid,$umsg)) {
				$this->_setUserMSG($this->getErrorMSG());
				return false;
			}
			if($ueConfig['useMutualConnections']) {
				$msg = _UE_CONNECTIONPENDINGACCEPTANCE;
				$subject= _UE_CONNECTIONPENDSUB;
				$messageHTML = _UE_CONNECTIONPENDMSG;
			} else {
				$msg = _UE_CONNECTIONADDSUCCESSFULL;
				$subject= _UE_CONNECTIONMADESUB;
				$messageHTML = _UE_CONNECTIONMADEMSG;
			}
			$messageText = $messageHTML;

			$result = $this->_notifyConnectionChange($this->referenceid,$connectionid,$msg,$subject,$messageHTML,$messageText,$umsg);
			$_PLUGINS->trigger( 'onAfterAddConnection', array($this->referenceid,$connectionid,$ueConfig['useMutualConnections'],$ueConfig['autoAddConnections']));

		} else {
			$result		=	false;
		}
		return $result;
	}
	function _notifyConnectionChange($userid,$connectionid,$msg,$subject,$messageHTML,$messageText,$userMessage=null) {
		global $_CB_framework, $_CB_database, $ueConfig;

		$rowFrom = new moscomprofilerUser( $_CB_database );
		$rowFrom->load( (int) $userid );

		$fromname=getNameFormat($rowFrom->name,$rowFrom->username,$ueConfig['name_format']);
		$fromURL="index.php?option=com_comprofiler&amp;task=userProfile&amp;user=".$userid."&amp;tab=1".getCBprofileItemid(true);
		$fromURL = cbSef( $fromURL );

		if (strncasecmp("http", $fromURL, 4) != 0) $fromURL = $_CB_framework->getCfg( 'live_site' ) . "/" . $fromURL;
		$subject= sprintf($subject,$fromname);

		if($userMessage!=null) {
			$messageHTML .= sprintf(str_replace("\n", "\n<br />", _UE_CONNECTIONMSGPREFIX),$fromname,"<strong>".htmlspecialchars($userMessage)."</strong>");
			$messageText .= sprintf(str_replace("\n", "\r\n", _UE_CONNECTIONMSGPREFIX),$fromname,$userMessage);
		}

		$nmsgHTML= sprintf($messageHTML,'<strong><a href="'.$fromURL.'">'.$fromname.'</a></strong>');
		$nmsgText = sprintf($messageText,$fromname);

		$manageURL = 'index.php?option=com_comprofiler&amp;task=manageConnections'.getCBprofileItemid(true);
		$manageURL = cbSef( $manageURL );

		if (strncasecmp("http", $manageURL, 4) != 0) $manageURL = $_CB_framework->getCfg( 'live_site' ) . "/" . $manageURL;
		$nmsgHTML = $nmsgHTML . "\n<br /><br /><a href=\"".$manageURL."\">"._UE_MANAGECONNECTIONS."</a>\n";
		$nmsgText = $nmsgText . "\r\n\r\n\r\n".$fromname." "._UE_PROFILE.": ". cbUnHtmlspecialchars( $fromURL );
		$nmsgText = $nmsgText . "\r\n\r\n"._UE_MANAGECONNECTIONS.": ". cbUnHtmlspecialchars( $manageURL ) . "\r\n";
		$nmsgHTML = '<div style="padding: 4px; margin: 4px 3px 6px 0px; background: #C44; font-weight: bold;" class="cbNotice">'
										._UE_SENDPMSNOTICE . "</div>\n\n" . $nmsgHTML;

		$cbNotification= new cbNotification();
		$cbNotification->sendFromUser($connectionid,$userid,$subject,$nmsgHTML,$nmsgText);

		$this->_setUserMSG($msg);
		return true;

	}
	function _insertConnection($refid, $connid, $userMessage) {
		global $_CB_database,$ueConfig;
		$accepted=1;
		$pending=0;
		if($ueConfig['useMutualConnections']) {
			$accepted=1;
			$pending=1;
		}
		$sql="INSERT INTO #__comprofiler_members (referenceid,memberid,accepted,pending,membersince,reason) VALUES (" . (int) $refid . "," . (int) $connid . "," . (int) $accepted . "," . (int) $pending.",CURDATE(),'" . $_CB_database->getEscaped($userMessage) . "')";
		$_CB_database->SetQuery($sql);
		if (!$_CB_database->query()) {
			$this->_setErrorMSG("SQL error insCon1 " . $_CB_database->stderr(true));
			return false;
		}
		if($ueConfig['autoAddConnections']) {
			$accepted=1;
			$pending=0;
			if($ueConfig['useMutualConnections']) {
				$accepted=0;
				$pending=0;
			}
			$sql="INSERT INTO #__comprofiler_members (referenceid,memberid,accepted,pending,membersince,reason) VALUES (" . (int) $connid . "," . (int) $refid . "," . (int) $accepted . "," . (int) $pending . ",CURDATE(),'" . $_CB_database->getEscaped($userMessage) . "')";
			$_CB_database->SetQuery($sql);
			if (!$_CB_database->query()) {
				$this->_setErrorMSG("SQL error insCon2 " . $_CB_database->stderr(true));
				return false;
			}
		}
		return true;
	}
	function removeConnection($userid,$connectionid) {
		global $ueConfig, $_PLUGINS;

		if ($this->getConnectionDetails($userid,$connectionid) === false) {
			$this->_setErrorMSG(_UE_NODIRECTCONNECTION);
			return false;
		}
		$_PLUGINS->loadPluginGroup('user');
		$_PLUGINS->trigger( 'onBeforeRemoveConnection', array($userid,$connectionid,$ueConfig['useMutualConnections'],$ueConfig['autoAddConnections']));
		if($_PLUGINS->is_errors()) {
			$this->_setUserMSG($_PLUGINS->getErrorMSG());
			return false;
		}

		$result		=	$this->_deleteConnection($userid,$connectionid);

		$msg = _UE_CONNECTIONREMOVESUCCESSFULL;
		/* You can uncomment this and comment the _setUserMSG line after this comment if you really want bad news to flow...:
			$subject = _UE_CONNECTIONREMOVED_SUB;
			$messageHTML = _UE_CONNECTIONREMOVED_MSG;
			$messageText = $messageHTML;
			$result = $this->_notifyConnectionChange($userid,$connectionid,$msg,$subject,$messageHTML,$messageText);
		*/
		$this->_setUserMSG($msg);

		$_PLUGINS->trigger( 'onAfterRemoveConnection', array($userid,$connectionid,$ueConfig['useMutualConnections'],$ueConfig['autoAddConnections']));
		return $result;
	}

	function denyConnection($userid,$connectionid) {			//BB needs to be called+do different then remove (one way if ...?)
		global $ueConfig, $_PLUGINS;

		if ($this->getConnectionDetails( $connectionid, $userid ) === false) {
			$this->_setErrorMSG(_UE_NODIRECTCONNECTION);
			return false;
		}
		$_PLUGINS->loadPluginGroup('user');
		$_PLUGINS->trigger( 'onBeforeDenyConnection', array($userid,$connectionid,$ueConfig['useMutualConnections'],$ueConfig['autoAddConnections']));
		if($_PLUGINS->is_errors()) {
			$this->_setUserMSG($_PLUGINS->getErrorMSG());
			return false;
		}

		$result		=	$this->_deleteConnection( $connectionid, $userid );

		$msg = _UE_CONNECTIONDENYSUCCESSFULL;
		/* You can uncomment this and comment the _setUserMSG line after this comment if you really want bad news to flow...:
			$subject = _UE_CONNECTIONDENIED_SUB;
			$messageHTML = _UE_CONNECTIONDENIED_MSG;
			$messageText = $messageHTML;
			$result = $this->_notifyConnectionChange($userid,$connectionid,$msg,$subject,$messageHTML,$messageText);
		*/
		$this->_setUserMSG($msg);
		$_PLUGINS->trigger( 'onAfterDenyConnection', array($userid,$connectionid,$ueConfig['useMutualConnections'],$ueConfig['autoAddConnections']));
		return $result;
	}
	function _deleteConnection($refid,$connid) {
		global $_CB_database,$ueConfig;
		$sql="DELETE FROM #__comprofiler_members WHERE referenceid=".(int) $refid." AND memberid=".(int) $connid;
		$_CB_database->SetQuery($sql);
		if (!$_CB_database->query()) {
			$this->_setErrorMSG("SQL error" . $_CB_database->stderr(true));
			return false;
		}

		if($ueConfig['autoAddConnections']) {
			$sql="DELETE FROM #__comprofiler_members WHERE referenceid=".(int) $connid." AND memberid=".(int) $refid;
			$_CB_database->SetQuery($sql);
			if (!$_CB_database->query()) {
				$this->_setErrorMSG("SQL error" . $_CB_database->stderr(true));
				return false;
			}
		}
		return true;
	}

	function acceptConnection($userid,$connectionid) {
		global $ueConfig, $_PLUGINS;

		if ($this->getConnectionDetails( $connectionid, $userid ) === false) {
			$this->_setErrorMSG(_UE_NODIRECTCONNECTION);
			return false;
		}
		$_PLUGINS->loadPluginGroup('user');
		$_PLUGINS->trigger( 'onBeforeAcceptConnection', array($userid,$connectionid,$ueConfig['useMutualConnections'],$ueConfig['autoAddConnections']));
		if($_PLUGINS->is_errors()) {
			$this->_setUserMSG($_PLUGINS->getErrorMSG());
			return false;
		}

		$this->_activateConnection($userid,$connectionid);

		$msg = _UE_CONNECTIONACCEPTSUCCESSFULL;
		$subject = _UE_CONNECTIONACCEPTED_SUB;
		$messageHTML = _UE_CONNECTIONACCEPTED_MSG;
		$messageText = $messageHTML;
		$result = $this->_notifyConnectionChange($userid,$connectionid,$msg,$subject,$messageHTML,$messageText);
		$_PLUGINS->trigger( 'onAfterAcceptConnection', array($userid,$connectionid,$ueConfig['useMutualConnections'],$ueConfig['autoAddConnections']));
		return $result;
	}
	function _activateConnection($userid,$connectionid) {
		global $_CB_database,$ueConfig;
		$sql="UPDATE #__comprofiler_members SET accepted=1, pending=0, membersince=CURDATE() WHERE referenceid=".(int) $connectionid." AND memberid=".(int) $userid;
		$_CB_database->SetQuery($sql);
		//echo $_CB_database->getQuery();
		if (!$_CB_database->query()) {
			$this->_setErrorMSG("SQL error" . $_CB_database->stderr(true));
			return 0;
		}

		if($ueConfig['autoAddConnections']) {
			$sql="UPDATE #__comprofiler_members SET accepted=1, pending=0, membersince=CURDATE() WHERE referenceid=".(int) $userid." AND memberid=".(int) $connectionid;
			$_CB_database->SetQuery($sql);
			//echo $_CB_database->getQuery();
			if (!$_CB_database->query()) {
				$this->_setErrorMSG("SQL error" . $_CB_database->stderr(true));
				return 0;
			}
		}
		return 1;

	}
	function getConnectionsCount( $userid, $countPendingsToo = false )  {
		global $_CB_database;

		static $cache			=	array();
		$userid					=	(int) $userid;
		if ( ! isset( $cache[$userid] ) ) {
			//select a count of all applicable entries
			$query				=	"SELECT COUNT(*)"
				. "\n FROM #__comprofiler_members AS m"
				. "\n LEFT JOIN #__comprofiler AS c ON m.memberid = c.id"
				. "\n LEFT JOIN #__users AS u ON m.memberid = u.id"
				. "\n WHERE m.referenceid = " . (int) $userid
				. "\n AND c.approved = 1 AND c.confirmed = 1 AND c.banned = 0 AND u.block = 0"
				. ( $countPendingsToo ? '' : "\n AND m.pending = 0" )
				. " AND m.accepted = 1"
				;
			$_CB_database->setQuery( $query );
			$cache[$userid]		=	(int) $_CB_database->loadResult();
		}
		return $cache[$userid];
	}
	function getPendingConnections( $userid, $offset = 0, $limit = 200 ) {
		global $_CB_database;
		$query = "SELECT DISTINCT m.*,u.name,u.email,u.username,c.avatar,c.avatarapproved, u.id, IF(s.session_id=null,0,1) AS 'isOnline' "
		. "\n FROM #__comprofiler_members AS m"
		. "\n LEFT JOIN #__comprofiler AS c ON m.referenceid=c.id"
		. "\n LEFT JOIN #__users AS u ON m.referenceid=u.id"
		. "\n LEFT JOIN #__session AS s ON s.userid=u.id"
		. "\n WHERE m.memberid=". (int) $userid ." AND m.pending=1"
		. "\n AND c.approved=1 AND c.confirmed=1 AND c.banned=0 AND u.block=0"
		;
		$_CB_database->setQuery( $query, $offset, $limit );
		$objects = $_CB_database->loadObjectList();
		return $objects;
	}
	function getActiveConnections( $userid, $offset = 0, $limit = 200 ) {
		global $_CB_database;
		$query = "SELECT DISTINCT m.*,u.name,u.email,u.username,c.avatar,c.avatarapproved, u.id, IF(s.session_id=null,0,1) AS 'isOnline' "
		. "\n FROM #__comprofiler_members AS m"
		. "\n LEFT JOIN #__comprofiler AS c ON m.memberid=c.id"
		. "\n LEFT JOIN #__users AS u ON m.memberid=u.id"
		. "\n LEFT JOIN #__session AS s ON s.userid=u.id"
		. "\n WHERE m.referenceid=". (int) $userid .""
		. "\n AND c.approved=1 AND c.confirmed=1 AND c.banned=0 AND u.block=0 AND m.accepted=1"
		. "\n ORDER BY m.accepted "
		;
		$_CB_database->setQuery( $query, $offset, $limit );
		$objects = $_CB_database->loadObjectList();
		return $objects;
	}
	function getConnectedToMe( $userid, $offset = 0, $limit = 200 ) {
		global $_CB_database;
		$query = "SELECT DISTINCT m.*,u.name,u.email,u.username,c.avatar,c.avatarapproved, u.id, IF(s.session_id=null,0,1) AS 'isOnline' "
		. "\n FROM #__comprofiler_members AS m"
		. "\n LEFT JOIN #__comprofiler AS c ON m.referenceid=c.id"
		. "\n LEFT JOIN #__users AS u ON m.referenceid=u.id"
		. "\n LEFT JOIN #__session AS s ON s.userid=u.id"
		. "\n WHERE m.memberid=". (int) $userid ." AND m.pending=0"
		. "\n AND c.approved=1 AND c.confirmed=1 AND c.banned=0 AND u.block=0"
		;
		$_CB_database->setQuery( $query, $offset, $limit );
		$objects = $_CB_database->loadObjectList();
		return $objects;
	}

	function saveConnection($connectionid,$desc=null,$contype=null) {
		global $_CB_database;

		$sql="UPDATE #__comprofiler_members SET description='".htmlspecialchars(cbGetEscaped($desc))."', type='".htmlspecialchars(cbGetEscaped($contype))."' WHERE referenceid=".(int) $this->referenceid." AND memberid=".(int) $connectionid;
		$_CB_database->SetQuery($sql);
		if (!$_CB_database->query()) {
			$this->_setErrorMSG("SQL error" . $_CB_database->stderr(true));
			return 0;
		}
		return 1;
	}

	function getDegreeOfSepPathArray( $fromid, $toid, $limit = 10, $degree = 6 ) {
		global $_CB_database;

		$fromid	= (int) $fromid;
		$toid	= (int) $toid;
		$limit	= (int) $limit;

		if ( $degree >= 1 ) {
$sql="SELECT a.referenceid, a.memberid AS d1 "
."\n FROM `#__comprofiler_members` AS a FORCE INDEX (aprm)"
."\n WHERE a.referenceid = " . $fromid . " AND a.accepted=1 AND a.pending=0 AND a.memberid = " . $toid;
		$_CB_database->setQuery( $sql );
		$congroups = $_CB_database->loadRowList();
		}
		if ( empty( $congroups ) && $degree >= 2 ) {
$sql="SELECT a.referenceid, a.memberid AS d1,  b.memberid AS d2 "
."\n FROM `#__comprofiler_members` AS a FORCE INDEX (aprm)"
."\n LEFT JOIN  #__comprofiler_members AS b FORCE INDEX (pamr) ON a.memberid=b.referenceid AND b.accepted=1 AND b.pending=0 "
."\n WHERE a.referenceid = " . $fromid . " AND a.accepted=1 AND a.pending=0 AND b.memberid = " . $toid
."\n AND b.memberid NOT IN ( " . $fromid . ",a.memberid ) "
// ."\n ORDER BY a.memberid,b.memberid "
;
		$_CB_database->setQuery( $sql, 0, $limit );
		$congroups = $_CB_database->loadRowList();
		}
		if ( empty( $congroups ) && $degree >= 3 ) {
$sql="SELECT a.referenceid, a.memberid AS d1,  b.memberid AS d2,  c.memberid AS d3 "
."\n FROM `#__comprofiler_members` AS a FORCE INDEX (aprm)"
."\n LEFT JOIN  #__comprofiler_members AS b FORCE INDEX (pamr) ON a.memberid=b.referenceid AND b.accepted=1 AND b.pending=0 "
."\n LEFT JOIN  #__comprofiler_members AS c FORCE INDEX (pamr) ON b.memberid=c.referenceid AND c.accepted=1 AND c.pending=0 "
."\n WHERE a.referenceid = " . $fromid . " AND a.accepted=1 AND a.pending=0 AND c.memberid = " . $toid
."\n AND b.memberid NOT IN ( " . $fromid . ",a.memberid) "
."\n AND c.memberid NOT IN ( " . $fromid . ",a.memberid,b.memberid) "
// ."\n ORDER BY a.memberid,b.memberid,c.memberid "
;
		$_CB_database->setQuery( $sql, 0, $limit );
		$congroups = $_CB_database->loadRowList();
		}
		if ( empty( $congroups ) && $degree >= 4 ) {
$sql="SELECT a.referenceid, a.memberid AS d1,  b.memberid AS d2,  c.memberid AS d3,  d.memberid AS d4 "
."\n FROM `#__comprofiler_members` AS a FORCE INDEX (aprm)"
."\n LEFT JOIN  #__comprofiler_members AS b FORCE INDEX (aprm) ON a.memberid=b.referenceid AND b.accepted=1 AND b.pending=0 "
."\n LEFT JOIN  #__comprofiler_members AS c FORCE INDEX (pamr) ON b.memberid=c.referenceid AND c.accepted=1 AND c.pending=0 "
."\n LEFT JOIN  #__comprofiler_members AS d FORCE INDEX (pamr) ON c.memberid=d.referenceid AND d.accepted=1 AND d.pending=0 "
."\n WHERE a.referenceid = " . $fromid . " AND a.accepted=1 AND a.pending=0 AND d.memberid = " . $toid
."\n AND b.memberid NOT IN ( " . $fromid . ",a.memberid) "
."\n AND c.memberid NOT IN ( " . $fromid . ",a.memberid,b.memberid) "
."\n AND d.memberid NOT IN ( " . $fromid . ",a.memberid,b.memberid,c.memberid) "
// ."\n ORDER BY a.memberid,b.memberid,c.memberid,d.memberid "
;
		$_CB_database->setQuery( $sql, 0 ,$limit );
		$congroups = $_CB_database->loadRowList();
		}
		if ( empty( $congroups ) && $degree >= 5 ) {
$sql="SELECT a.referenceid, a.memberid AS d1,  b.memberid AS d2,  c.memberid AS d3,  d.memberid AS d4,  e.memberid AS d5 "
."\n FROM `#__comprofiler_members` AS a FORCE INDEX (aprm)"
."\n LEFT JOIN  #__comprofiler_members AS b FORCE INDEX (aprm) ON a.memberid=b.referenceid AND b.accepted=1 AND b.pending=0 "
."\n LEFT JOIN  #__comprofiler_members AS c FORCE INDEX (aprm) ON b.memberid=c.referenceid AND c.accepted=1 AND c.pending=0 "
."\n LEFT JOIN  #__comprofiler_members AS d FORCE INDEX (pamr) ON c.memberid=d.referenceid AND d.accepted=1 AND d.pending=0 "
."\n LEFT JOIN  #__comprofiler_members AS e FORCE INDEX (pamr) ON d.memberid=e.referenceid AND e.accepted=1 AND e.pending=0 "
."\n WHERE a.referenceid = " . $fromid . " AND a.accepted=1 AND a.pending=0 AND e.memberid = " . $toid
."\n AND b.memberid NOT IN ( " . $fromid . ",a.memberid) "
."\n AND c.memberid NOT IN ( " . $fromid . ",a.memberid,b.memberid) "
."\n AND d.memberid NOT IN ( " . $fromid . ",a.memberid,b.memberid,c.memberid) "
."\n AND e.memberid NOT IN ( " . $fromid . ",a.memberid,b.memberid,c.memberid,d.memberid) "
// ."\n ORDER BY a.memberid,b.memberid,c.memberid,d.memberid,e.memberid "
;
		$_CB_database->setQuery( $sql, 0, $limit );
		$congroups = $_CB_database->loadRowList();
		}
		if ( empty( $congroups ) && $degree >= 6 ) {
$sql="SELECT a.referenceid, a.memberid AS d1,  b.memberid AS d2,  c.memberid AS d3,  d.memberid AS d4,  e.memberid AS d5,  f.memberid AS d6 "
."\n FROM `#__comprofiler_members` AS a FORCE INDEX (aprm)"
."\n LEFT JOIN  #__comprofiler_members AS b FORCE INDEX (aprm) ON a.memberid=b.referenceid AND b.accepted=1 AND b.pending=0 "
."\n LEFT JOIN  #__comprofiler_members AS c FORCE INDEX (aprm) ON b.memberid=c.referenceid AND c.accepted=1 AND c.pending=0 "
."\n LEFT JOIN  #__comprofiler_members AS d FORCE INDEX (pamr) ON c.memberid=d.referenceid AND d.accepted=1 AND d.pending=0 "
."\n LEFT JOIN  #__comprofiler_members AS e FORCE INDEX (pamr) ON d.memberid=e.referenceid AND e.accepted=1 AND e.pending=0 "
."\n LEFT JOIN  #__comprofiler_members AS f FORCE INDEX (pamr) ON e.memberid=f.referenceid AND f.accepted=1 AND f.pending=0 "
."\n WHERE a.referenceid = " . $fromid . " AND a.accepted=1 AND a.pending=0 AND f.memberid = " . $toid
."\n AND b.memberid NOT IN ( " . $fromid . ",a.memberid) "
."\n AND c.memberid NOT IN ( " . $fromid . ",a.memberid,b.memberid) "
."\n AND d.memberid NOT IN ( " . $fromid . ",a.memberid,b.memberid,c.memberid) "
."\n AND e.memberid NOT IN ( " . $fromid . ",a.memberid,b.memberid,c.memberid,d.memberid) "
."\n AND f.memberid NOT IN ( " . $fromid . ",a.memberid,b.memberid,c.memberid,d.memberid,e.memberid) "
// ."\n ORDER BY a.memberid,b.memberid,c.memberid,d.memberid,e.memberid,f.memberid "
;
		$_CB_database->setQuery( $sql, 0 , $limit );
		$congroups = $_CB_database->loadRowList();
		}
		return $congroups;
	}
	function getDegreeOfSepPath( $fromid, $toid ) {
		$congroups = $this->getDegreeOfSepPathArray( $fromid, $toid, 1 );
		if ( is_array( $congroups ) && ( count( $congroups ) > 0 ) ) {
			$this->_setDegreeOfSep( count( $congroups[0] ) - 1 );
			return $congroups[0];
		} else {
			return null;
		}
	}
	/**
	 * Gets connection details
	 *
	 * @param  int  $fromid
	 * @param  int  $toid
	 * @return moscomprofilerMember
	 */
	function getConnectionDetails($fromid,$toid) {
		global $_CB_database;
		$query			=	"SELECT * "
						.	"\n FROM #__comprofiler_members AS m"
						.	"\n WHERE m.referenceid=".(int) $fromid." AND m.memberid=".(int) $toid
						;

		$_CB_database->setQuery( $query );
		$connections	=	$_CB_database->loadObjectList( null, 'moscomprofilerMember', array( &$_CB_database ) );
		if ( ( ! $_CB_database->getErrorNum() ) && ( count( $connections ) > 0 ) ) {
			return $connections[0];
		} else {
			return false;
		}
	}
	function getDegreeOfSep() {
		return $this->degreeOfSep;
	}
	function _setDegreeOfSep( $deg ) {
		$this->degreeOfSep = $deg;
		return;
	}
	function getUserMSG() {
		return $this->userMSG;
	}
	function _setUserMSG($msg) {
		$this->userMSG=$msg;
		return;
	}
	function getErrorMSG() {
		return $this->errorMSG;
	}
	function _setErrorMSG($msg) {
		$this->errorMSG=$msg;
		return;
	}
}	// end class cbConnection

/**
 * Translates connection types list |*|-separated to be listed, ', '-separated
 *
 * @param  string $types
 * @return string
 */
function getConnectionTypes( $types ) {
	$typelist	=	null;
	$types		=	explode( "|*|", $types );
	foreach( $types AS $type ) {
		if( $typelist == null ) {
			$typelist	=	getLangDefinition( $type );
		} else {
			$typelist	.=	", " . getLangDefinition( $type );
		}
	}
	return $typelist;
}

/**
* Notification Class for handeling CB notifications
* @package Community Builder
* @author MamboJoe
*/
class cbNotification {
	/**	@var errorMSG should be used to store the error message when an error is encountered*/
	var $errorMSG;
	/**	@var errorNum should be used to store the error number when an error is encountered*/
	var $errorNum;

	function cbNotification() {
	}
	function sendFromUser($toid,$fromid,$subject,$message, $messageEmail=null) {		//BB: add html email notifications processing later
		global $ueConfig;

		if ($messageEmail === null) $messageEmail = $message;
		SWITCH($ueConfig['conNotifyType']) {
			case 1:
				return $this->sendUserEmail($toid,$fromid,$subject,$messageEmail);
			break;
			case 2:
				return $this->sendUserPMSmsg($toid,$fromid,$subject,$message, true);
			break;
			case 3:
				$resultPMS	 = $this->sendUserPMSmsg($toid,$fromid,$subject,$message, true);
				$resultEmail = $this->sendUserEmail($toid,$fromid,$subject,$messageEmail);
				return $resultPMS && $resultEmail;
			break;
			default:
				return false;
			break;
		}
	}
	function sendUserPMSmsg($toid,$fromid,$subject,$message, $systemGenerated=false) {
		global $_CB_PMS;
		$resultArray = $_CB_PMS->sendPMSMSG($toid,$fromid,$subject,$message,$systemGenerated);
		if (count($resultArray) > 0) return $resultArray[0];
		else return false;
	}
	function sendUserEmail($toid,$fromid,$subject,$message,$revealEmail=false) {
		global $_CB_framework, $_CB_database, $ueConfig, $_SERVER;

		if ( ( ! $subject ) && ( ! $message ) ) {
			return true;
		}
		$rowFrom = new moscomprofilerUser( $_CB_database );
		$rowFrom->load( (int) $fromid );

		$rowTo = new moscomprofilerUser( $_CB_database );
		$rowTo->load( (int) $toid );
		$uname=getNameFormat($rowFrom->name,$rowFrom->username,$ueConfig['name_format']);
		if ($revealEmail) {
			if (isset($ueConfig['allow_email_replyto']) && $ueConfig['allow_email_replyto'] == 2) {
				$rowFrom->replytoEmail = $rowFrom->email;
				$rowFrom->replytoName  = $uname;
				$rowFrom->email = $ueConfig['reg_email_from'];
			} else {	// if (!isset($ueConfig['allow_email_replyto']) || $ueConfig['allow_email_replyto'] == 1)
				$rowFrom->replytoEmail = null;
				$rowFrom->replytoName  = null;
				$rowFrom->email = $rowFrom->email;
			}
		} else {
			$rowFrom->replytoEmail = null;
			$rowFrom->replytoName  = null;
			$rowFrom->name = _UE_NOTIFICATIONSAT." ".cb_html_entity_decode_all($_CB_framework->getCfg( 'sitename' ));
			$rowFrom->email = $ueConfig['reg_email_from'];
			$message.="\n\n".sprintf(_UE_EMAILFOOTER,cb_html_entity_decode_all($_CB_framework->getCfg( 'sitename' )),$_CB_framework->getCfg( 'live_site' ))."\n";
		}
		return $this->_sendEmailMSG( $rowTo, $rowFrom, $subject, $message, $revealEmail );
	}
	function sendFromSystem( $toid, $sub, $message, $replaceVariables = true, $mode = 0, $cc = null, $bcc = null, $attachment = null, $extraStrings = null, $footer = true ) {
		global $_CB_framework, $_CB_database, $ueConfig;

		if ( ( ! $sub ) && ( ! $message ) ) {
			return true;
		}
		if ( $extraStrings === null ) {
			$extraStrings		=	array();
		}
		$rowFrom				=	new stdClass();
		$rowFrom->email			=	$ueConfig['reg_email_from'];
		$rowFrom->name			=	stripslashes( $ueConfig['reg_email_name'] );
		$rowFrom->replytoEmail	=	$ueConfig['reg_email_replyto'];
		$rowFrom->replytoName	=	stripslashes( $ueConfig['reg_email_name'] );

		if ( ! is_object( $toid ) ) {
			$rowTo				=	CBuser::getUserDataInstance( $toid );
		} else {
			$rowTo = $toid;
		}

		if ($replaceVariables) {
			$sub				=	$this->_replaceVariables( $sub, $rowTo, $mode, $extraStrings );
			$message			=	$this->_replaceVariables( $message, $rowTo, $mode, $extraStrings );
		}
		if ( $footer ) {
			$message			.=	( $mode ? "\n<br />\n<br />" : "\n\n" )
								.	sprintf(_UE_EMAILFOOTER,cb_html_entity_decode_all($_CB_framework->getCfg( 'sitename' )),$_CB_framework->getCfg( 'live_site' ));
		}
		// $message = str_replace(array("\\","\"","\$"), array("\\\\","\\\"","\\\$"), $message);
		// eval ("\$message = \"$message\";");
		$message = str_replace( array( '\n' ), array( "\n" ), $message ); // compensate for wrong language definitions (using '\n' instaed of "\n")

		return $this->_sendEmailMSG( $rowTo, $rowFrom, cb_html_entity_decode_all($_CB_framework->getCfg( 'sitename' )).' - '.$sub, $message, false, $mode, $cc, $bcc, $attachment );

	}
	function sendToModerators( $sub, $message, $replaceVariables = false, $mode = 0, $cc = null, $bcc = null, $attachment = null  ) {
		global $_CB_database, $ueConfig;

		$moderators				=	implode( ',', getParentGIDS( $ueConfig['imageApproverGid'] ) );
		if ( $moderators ) {
			$query				=	'SELECT u.id'
								.	"\n FROM #__users u"
								.	"\n INNER JOIN #__comprofiler c"
								.	' ON u.id = c.id';

			if ( checkJversion() == 2 ) {
				$query			.=	"\n INNER JOIN #__user_usergroup_map g"
								.	' ON c.id = g.user_id'
								.	"\n WHERE g.group_id IN ( $moderators )";
			} else {
				$query			.=	"\n WHERE u.gid IN ( $moderators )";
			}

			$query				.=	"\n AND u.block = 0"
								.	"\n AND c.confirmed = 1"
								.	"\n AND c.approved = 1"
								.	"\n AND u.sendEmail = 1";

			$_CB_database->setQuery( $query );
			$mods				=	$_CB_database->loadObjectList();
			foreach ( $mods AS $mod ) {
				$this->sendFromSystem( $mod->id, $sub, $message, $replaceVariables, $mode, $cc, $bcc, $attachment );
			}
		}
	}

	function _sendEmailMSG( $to, $from, $sub, $msg, $addPrefix = false, $mode = 0, $cc = null, $bcc = null, $attachment = null ) {			//BB: add html
		global $_CB_framework, $ueConfig, $_SERVER;

		if ( $addPrefix) {
			$uname			=	getNameFormat($from->name,$from->username,$ueConfig['name_format']);
			$premessage 	=	sprintf(_UE_SENDEMAILNOTICE, $uname, cb_html_entity_decode_all($_CB_framework->getCfg( 'sitename' )), $_CB_framework->getCfg( 'live_site' ));
			if ( isset( $ueConfig['allow_email_replyto'] ) && ( $ueConfig['allow_email_replyto'] == 2 ) ) {
				$premessage	.=	sprintf(_UE_SENDEMAILNOTICE_REPLYTO, $uname, $from->email);
			}
			$postmessage	=	sprintf(_UE_SENDEMAILNOTICE_DISCLAIMER, cb_html_entity_decode_all($_CB_framework->getCfg( 'sitename' )));
			// $premessage .=	sprintf(_UE_SENDEMAILNOTICE_MESSAGEHEADER, $uname);
			$tim1=(time()+32400);
			$today1="
Отправлено: ".date("Y-m-d H:i:s",$tim1);
			$msg			=	$premessage . $msg . $postmessage.$today1;
			$from->name		=	$uname . " @ ". cb_html_entity_decode_all($_CB_framework->getCfg( 'sitename' ));		// $ueConfig['reg_email_name']
		}
//		if (class_exists("mosPHPMailer")) {
			$res			=	comprofilerMail( $from->email, $from->name, $to->email, $sub, $msg, $mode,  $cc, $bcc, $attachment, $from->replytoEmail, $from->replytoName );
/*		} else if (function_exists( 'mosMail' )) {
			$res = mosMail($from->email, $from->name, $to->email, $sub, $msg);
		} else { //TODO drop this once we are dedicated to >= 4.5.2
			$EOL			=	defined( 'PHP_EOL' ) ? PHP_EOL : '\n';	// assume Linux for old systems.
			$header  = "MIME-Version: 1.0" . $EOL;
			$header .= "Content-type: text/plain; charset=" . $_CB_framework->outputCharset() . $EOL;
			$header .= "Content-Transfer-encoding: 8bit" . $EOL;
			$fromTag  = $from->name." <" . $from->email . ">";
			$header .= "From: ".$fromTag. $EOL;
			$replyTag = $from->replytoName." <" . $from->replytoEmail . ">";
			$header .= "Reply-To: ".$replyTag. $EOL;
			$header .= "Organization: ".cb_html_entity_decode_all($_CB_framework->getCfg( 'sitename' )). $EOL;
			$header .= "Message-ID: <".md5(uniqid(time()))."@{$_SERVER['SERVER_NAME']}>" . $EOL;
			$header .= "Return-Path: ".$from->email. $EOL;
			$header .= "X-Priority: 3" . $EOL;
			$header .= "X-MSmail-Priority: Low" . $EOL;
			$header .= "X-Mailer: PHP\r\n"; //hotmail and others dont like PHP mailer. --Microsoft Office Outlook, Build 11.0.5510
			$header .= "X-Sender: ".$from->email. $EOL . $EOL;
			$res =  mail($to->email, $sub, $msg, $header);
		}
*/
		return $res;
	}
	function _getUserDetails( $row, $includePWD ) {
		$uDetails		=	_UE_EMAIL." : ".$row->email;
		$uDetails		.=	"\n"._UE_UNAME." : ".$row->username."\n";
		if ( ( $includePWD == 1 ) && ( $row->confirmed == 1 ) && ( $row->approved == 1 ) ) {
			$uDetails	.=	_UE_PASS." : ".$row->password."\n";
		}
	 	return $uDetails;
	}

	function _replaceVariables( $msg, $row, $mode = 0, $extraStrings = null ){
		global $_CB_framework, $ueConfig;

		if ( $extraStrings === null ) {
			$extraStrings		=	array();
		}
		if( $ueConfig['reg_confirmation'] == 1 ) {
			if ( $row->confirmed ) {
				$confirmLink	=	"\n" . _UE_USER_EMAIL_CONFIRMED . ".\n";
			} else {
				if ( $row->cbactivation ) {
					$confirmCode = $row->cbactivation;
				} else {
					$confirmCode = '';
				}
				// no sef here !  space added after link for dumb emailers (Ms Entourage)
				$confirmLink = " \n".$_CB_framework->getCfg( 'live_site' )."/index.php?option=com_comprofiler&task=confirm&confirmcode=" . $confirmCode . getCBprofileItemid( false, 'confirm' ) . " \n";
				// not implemented in viewUrl yet: $confirmLink = " \n". $_CB_framework->viewUrl( 'confirm', array( 'confirmcode' => $confirmCode ) ) ." \n";
			}
		} else {
			$confirmLink = ' ';
		}

		$msg = str_replace( array( '\n' ), array( "\n" ), $msg );	// was eval ("\$msg = \"$msg\";"); // compensate for wrong language definitions (using '\n' instaed of "\n")
		$msg = cbstr_ireplace("[EMAILADDRESS]", $row->email, $msg);
		$msg = cbstr_ireplace("[SITEURL]", $_CB_framework->getCfg( 'live_site' ), $msg);
		$msg = cbstr_ireplace("[DETAILS]", $this->_getUserDetails( $row, ( isset( $ueConfig['emailpass'] ) ? $ueConfig['emailpass'] : 0 ) ), $msg);
		$msg = cbstr_ireplace("[CONFIRM]", $confirmLink, $msg );
		$msg = cbReplaceVars( $msg, $row, $mode, true, $extraStrings );		// this is for plaintext emails, no htmlspecialchars needed here.
		return $msg;
	}
}	// end class cbNotification

class cbFields {
	/**	@var errorMSG should be used to store the error message when an error is encountered*/
	var $errorMSG;
	/**	@var errorNum should be used to store the error number when an error is encountered*/
	var $errorNum;

	function cbFields() {
	}

	/**
	 * Returns a reference to an input filter object
	 *
	 * @param  array  $tagsArray	list of user-defined tags
	 * @param  array  $attrArray	list of user-defined attributes
	 * @param  int    $tagsMethod	WhiteList method = 0, BlackList method = 1
	 * @param  int    $attrMethod	WhiteList method = 0, BlackList method = 1
	 * @param  int    $xssAuto	Only auto clean essentials = 0, Allow clean blacklisted tags/attr = 1
	 * @return CBInputFilter
	 */
	function & getInputFilter( $tagsArray = null, $attrArray = null, $tagsMethod = 0, $attrMethod = 0, $xssAuto = 1 ) {
		if ( $tagsArray === null ) {
			$tagsArray	=	array();
		}
		if ( $attrArray === null ) {
			$attrArray	=	array();
		}
		$filter			=	new CBInputFilter( $tagsArray, $attrArray, $tagsMethod, $attrMethod, $xssAuto );
		return $filter;
	}
	/**
	 * Try to convert to plaintext
	 * Rewritten in CB to use CB's own version of html_entity_decode where innexistant or buggy in < joomla 1.5
	 *
	 * @access	private
	 * @param	string	$source
	 * @return	string	Plaintext string
	 * @since	1.5
	 */
	function _decode($source)
	{
		// entity decode : use own version of html_entity_decode, including dec & hex:
		return cb_html_entity_decode_all( $source );
	}
	/**
	 * Processes for XSS and specified bad code.
	 *
	 * @access private
	 * @param  CBInputFilter  $filter
	 * @param  mixed	      $source   Input string/array-of-string to be 'cleaned'
	 * @return mixed                    'cleaned' version of input parameter
	 */
	function _process( &$filter, $source )
	{
		if ( is_array( $source ) ) {
			foreach ( $source as $key => $value ) {
				if ( is_array( $value ) ) {
					$source[$key]	=	$this->_process( $filter, $value );
				} elseif ( is_string( $value ) ) {
					$source[$key]	=	$filter->remove( $this->_decode( $value ) );
				}
			}
		} elseif ( is_string( $source ) && ! empty( $source ) ) {
			$source					=	$filter->remove($this->_decode($source));
		}
		return $source;
	}
	/**
	 * Method to be called by another php script. Processes for XSS and
	 * specified bad code.
	 *
	 * @param  CBInputFilter  $filter
	 * @param  mixed          $source  Input string/array-of-string to be 'cleaned'
	 * @param  string         $type    Return type for the variable (INT, FLOAT, WORD, BOOLEAN, STRING)
	 * @return mixed	               'Cleaned' version of input parameter
	 */
	function clean( &$filter, $source, $type = 'string' ) {
		// Handle the type constraint
		switch (strtoupper($type))
		{
			case 'INT' :
			case 'INTEGER' :
				// Only use the first integer value
				$matches = null;
				@preg_match('/-?[0-9]+/', $source, $matches);
				$result = @ (int) $matches[0];
				break;

			case 'FLOAT' :
			case 'DOUBLE' :
				// Only use the first floating point value
				$matches = null;
				@preg_match('/-?[0-9]+(\.[0-9]+)?/', $source, $matches);
				$result = @ (float) $matches[0];
				break;

			case 'BOOL' :
			case 'BOOLEAN' :
				$result = (bool) $source;
				break;

			case 'WORD' :
				$result = (string) preg_replace( '#\W#', '', $source );
				break;

			default :
				$result = $this->_process( $filter, $source );
				break;
		}
		return $result;
	}

	function prepareFieldDataView() {

	}
	/**
	 * OBSOLETE METHOD: Left for backwards compatibility if plugins use even this...
	 * DO NOT USE ANYMORE: will be removed in next CB version.
	 */
	function prepareFieldDataSave( $fieldId, $fieldType, $fieldName, $value=null, $registration=0,$field=null ) {
		global $ueConfig, $_POST, $_CB_database, $_PLUGINS;

		switch($fieldType) {
		CASE 'date':
			$sqlFormat	=	"Y-m-d";
			$fieldForm	=	str_replace( 'y', 'Y', $ueConfig['date_format'] );
			$value		=	dateConverter( cbGetUnEscaped( $value ), $fieldForm, $sqlFormat );
		break;
		CASE 'webaddress':
			if (isset($_POST[$fieldName."Text"]) && ($_POST[$fieldName."Text"])) {
				$oValuesArr=array();
				$oValuesArr[0]=str_replace(array('mailto:','http://','https://'),'',
								cbGetUnEscaped($value));
				$oValuesArr[1]=str_replace(array('mailto:','http://','https://'),'',
								cbGetUnEscaped((isset($_POST[$fieldName."Text"]) ? stripslashes( cbGetParam( $_POST, $fieldName."Text", '' ) ) : "")));
				$value = implode("|*|",$oValuesArr);
			} else {
				$value= str_replace(array('mailto:','http://','https://'),'',cbGetUnEscaped($value));
			}
		break;
		CASE 'emailaddress':
			$value=str_replace(array('mailto:','http://','https://'),'',cbGetUnEscaped($value));
		break;
		CASE 'editorta':
			$value = cbGetUnEscaped( $value );
			$badHtmlFilter = & $this->getInputFilter( array (), array (), 1, 1 );
			if ( isset( $ueConfig['html_filter_allowed_tags'] ) && $ueConfig['html_filter_allowed_tags'] ) {
				$badHtmlFilter->tagBlacklist = array_diff( $badHtmlFilter->tagBlacklist, explode(" ", $ueConfig['html_filter_allowed_tags']) );
			}
			$value = $this->clean( $badHtmlFilter, $value );
		break;
		case 'radio':
			$value = array( $value );
		// intentionally no break: fall through:
		case 'multiselect':
		case 'multicheckbox':
		case 'select':
			if ($value === null) {
				$value = array();
			}
			$_CB_database->setQuery( "SELECT fieldtitle AS id FROM #__comprofiler_field_values"
			. "\n WHERE fieldid = " . (int) $fieldId
			. "\n ORDER BY ordering" );
			$Values = $_CB_database->loadResultArray();
			if (! is_array( $Values ) ) {
				$Values = array();
			}
			foreach ( $value as $k => $v ) {
				if ( ! in_array( cbGetUnEscaped( $v ), $Values ) ) {
					unset( $value[$k] );
				}
			}

			$value=cbGetUnEscaped(implode("|*|",$value));

		break;
		case 'checkbox':
			if ( ( $value === null ) || ( ! in_array( $value, array( "0", "1" ) ) ) ) $value = "";
			$value=cbGetUnEscaped($value);
		break;
		case 'delimiter':
		break;

		CASE 'textarea':
		CASE 'primaryemailaddress':
		CASE 'pm':
		CASE 'image':
		CASE 'status':
		CASE 'formatname':
		CASE 'predefined':
		default:
			$value		=	cbGetUnEscaped( $value );

			if ( $field != null ) {
				$args	=	array( &$field, null, &$_POST, 'edit' );
				$value	=	$_PLUGINS->callField( $fieldType, 'prepareFieldDataSave', $args, $field );
			}
			break;
		}
		return $value;
	}

	function getErrorMSG() {
		return $this->errorMSG;
	}
	function _setErrorMSG($msg) {
		$this->errorMSG=$msg;
		return;
	}
}	// end class cbFields

global $_CB_PMS;
/** @global cbPMS $_CB_PMS */
$_CB_PMS		=	new cbPMS();

?>