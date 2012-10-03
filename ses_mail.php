<?php
/**
 * Emulate the PHP mail() function using the AWS SES mail library in ses.php
 * :%s/ mail(/ ses_mail(/g
 * @dependency ses.php  http://www.orderingdisorder.com/aws/ses/ 
 * @date 20121002
 * @version 0.5.0
 * @author Aaron Abelard <aaron@steamcube.com>
 */
if( !function_exists( 'ses_mail' ))
{
function ses_mail( $to, $subject, $message, $headers = '', $parameters = '' )
{
	$aws_key              = '***AWSKEY***';
	$aws_secret           = '***AWSSECRET***';
	$default_email        = '***DEFAULTEMAIL***';
	$convert_html_to_text = false; // If we specify an HTML message type, automatically down convert a text only version
	// This conversion is not terribly advanced, and will result in poorly formatted text messages if not taken into 
	// consideration.  The only "smart" transform is to turn <br> into new lines

	require_once('ses.php');
	$ses = new SimpleEmailService( $aws_key, $aws_secret );

	// We're emulating the PHP mail() function, which takes a variety of parameters as raw headers
	// We need to locate that those, extract the value, and call the relevant SES.php function.
	$translate = array(
	                    'From'         => 'setFrom',
	                    'Reply-To'     => 'addReplyTo',
	                    'Cc'           => 'addCC',
	                    'Bcc'          => 'addBCC',
	                    'Return-Path'  => 'setReturnPath',
	                    'Content-type' => '_content-type', // This one is defined in our code, not in SES.php
	                  );
	$header_parts = explode( "\n", $headers );
	$m = new SimpleEmailServiceMessage();
	$m->addTo( $to );
	$m->setSubject( $subject );
	$m->setMessageFromString( $message );
	$m->setFrom( $default_email ); // This is overriden if defined
	foreach( $header_parts as $this_header )
	{
		foreach( $translate as $token => $func )
		{
			$token .= ':';
			if( strtolower( substr( $this_header, 0, strlen( $token ))) === strtolower( $token ))
			{
				$value = trim( substr( $this_header, strlen( $token )));
				if( is_callable( array( $m, $func )))
				{
					call_user_func( array( $m, $func ), $value );
				}
				else
				{
					if( $func === '_content-type' ) // This takes some special handling
					{
						// By the time it gets here we have:  text/html; charset=iso-8859-1
						$content_type_parts = explode( ';', $value );
						$type = trim( $content_type_parts[0] );
						// Strip off the charset= portion
						$charset_parts = trim( $content_type_parts[1] );
						list( , $charset ) = explode( '=', $charset_parts );
						$charset = trim( $charset );
						if( $type === 'text/html' )
						{
							$text_message = '';
							if( $convert_html_to_text )
							{
								$text_message = eregi_replace( "<br[\]?>", "\n", $message );
								$text_message = strip_tags( $text_message );
							}
							$m->setMessageFromString( $text_message, $message );
						}
						$m->setMessageCharset( $charset, $charset ); // 2nd parameter is HTML charset
					}
				}
			}
		}
	}
	$response = $ses->sendEmail( $m );
	return $response; // Error handling here is the responsibility of the calling function, which isn't drop-in replacement ready.
}
}
