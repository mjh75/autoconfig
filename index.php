<?php
/* 
 * Copyright (C) 2016, 2017 Michael J. Hartwick <hartwick at hartwick.com>
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
	include_once(dirname(__FILE__)."/vendor/autoload.php");
	include_once(dirname(__FILE__)."/includes/config.inc");
	$requesturi = filter_input(INPUT_SERVER, "REQUEST_URI");
	if(strncasecmp($requesturi, "/mail/config-v1.1.xml", 21) === 0) {
		$agent = "Thunderbird";
		$email = filter_input(INPUT_GET, 'emailaddress', FILTER_SANITIZE_EMAIL);
	} else if(strncasecmp($requesturi, "/autodiscover/autodiscover.xml", 30) === 0) {
		$agent = "Outlook";
		$raw = file_get_contents('php://input');
		if(strlen($raw) > 10 && strncasecmp($raw, '<?xml version="1.0"', 19) === 0) {
			$document = new \DOMDocument;
			$document->loadXML($raw, LIBXML_DTDLOAD);
			$addresses = $document->getElementsByTagNameNS("http://schemas.microsoft.com/exchange/autodiscover/outlook/requestschema/2006", "EMailAddress");
			foreach($addresses as $element) {
				$email = filter_var($element->nodeValue, FILTER_SANITIZE_EMAIL);
			}
		} else {
			exit(-2);
		}
	}
	if(empty($email)) {
		exit(-1);
	} else {
		$reply = new \DOMDocument('1.0', 'UTF-8');
		$reply->formatOutput = \TRUE;
		$addr = explode("@", $email);
		$user = $addr[0];
		$domain = $addr[1];
		$subad = new \com\hartwick\autodiscover($domain);
		$isad = new \com\hartwick\autodiscover($domain, "_imaps");
		$iad = new \com\hartwick\autodiscover($domain, "_imap");
		$psad = new \com\hartwick\autodiscover($domain, "_pop3s");
		$pad = new \com\hartwick\autodiscover($domain, "_pop3");
		$sub = $subad->CheckRR();
		$is = $isad->CheckRR();
		$i = $iad->CheckRR();
		$ps = $psad->CheckRR();
		$p = $pad->CheckRR();
		if($agent === "Thunderbird") {
			$clientconfig = $reply->createElement("clientConfig");
			$clientconfig->setAttribute("version", "1.1");
			$emailprovider = $reply->createElement("emailProvider");
			$emailprovider->setAttribute("id", $providerid);
			$emailprovider->appendChild($reply->createElement("domain", $domain));
			$emailprovider->appendChild($reply->createElement("displayName", $provider));
			if(!empty($is[0]) && $is[1] !== "0") {
				$imaps = $reply->createElement("incomingServer");
				$imaps->setAttribute("type", "imap");
				$imaps->appendChild($reply->createElement("hostname", $is[0]));
				$imaps->appendChild($reply->createElement("port", $is[1]));
				$imaps->appendChild($reply->createElement("socketType", "SSL"));
				$imaps->appendChild($reply->createElement("authentication", "password-cleartext"));
				$imaps->appendChild($reply->createElement("username", $email));
				$emailprovider->appendChild($imaps);
			}
			if(!empty($i[0]) && $i[1] !== "0") {
				$imap = $reply->createElement("incomingServer");
				$imap->setAttribute("type", "imap");
				$imap->appendChild($reply->createElement("hostname", $i[0]));
				$imap->appendChild($reply->createElement("port", $i[1]));
				$imap->appendChild($reply->createElement("socketType", "plain"));
				$imap->appendChild($reply->createElement("authentication", "password-cleartext"));
				$imap->appendChild($reply->createElement("username", $email));
				$emailprovider->appendChild($imap);
			}
			if(!empty($ps[0]) && $ps[1] !== "0") {
				$pop3s = $reply->createElement("incomingServer");
				$pop3s->setAttribute("type", "pop3");
				$pop3s->appendChild($reply->createElement("hostname", $ps[0]));
				$pop3s->appendChild($reply->createElement("port", $ps[1]));
				$pop3s->appendChild($reply->createElement("socketType", "SSL"));
				$pop3s->appendChild($reply->createElement("authentication", "password-cleartext"));
				$pop3s->appendChild($reply->createElement("username", $email));
				$emailprovider->appendChild($pop3s);
			}
			if(!empty($p[0]) && $p[1] !== "0") {
				$pop3 = $reply->createElement("incomingServer");
				$pop3->setAttribute("type", "pop3");
				$pop3->appendChild($reply->createElement("hostname", $p[0]));
				$pop3->appendChild($reply->createElement("port", $p[1]));
				$pop3->appendChild($reply->createElement("socketType", "plain"));
				$pop3->appendChild($reply->createElement("authentication", "password-cleartext"));
				$pop3->appendChild($reply->createElement("username", $email));
				$emailprovider->appendChild($pop3);
			}
			$smtp = $reply->createElement("outgoingServer");
			$smtp->setAttribute("type", "smtp");
			$smtp->appendChild($reply->createElement("hostname", $sub[0]));
			$smtp->appendChild($reply->createElement("port", $sub[1]));
			$smtp->appendChild($reply->createElement("socketType", "STARTTLS"));
			$smtp->appendChild($reply->createElement("authentication", "password-cleartext"));
			$smtp->appendChild($reply->createElement("username", $email));
			$emailprovider->appendChild($smtp);
			$clientconfig->appendChild($emailprovider);
			$clientconfigupdate = $reply->createElement("clientConfigUpdate");
			$servername = filter_input(INPUT_SERVER, "HTTP_HOST");
			if(empty($servername)) {
				$servername = filter_input(INPUT_SERVER, "SERVER_NAME");
			}
			$uri = filter_input(INPUT_SERVER, "REQUEST_URI");
			$clientconfigupdate->setAttribute("url", "https://$servername$uri");
			$clientconfig->appendChild($clientconfigupdate);
			$reply->appendChild($clientconfig);
		} elseif ($agent === "Outlook") {
			$ad = $reply->createElementNS("http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006", "Autodiscover");
			$response = $reply->createElementNS("http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a", "Response");
			$user = $reply->createElement("User");
			$user->appendChild($reply->createElement("DisplayName", $email));
			$response->appendChild($user);
			$account = $reply->createElement("Account");
			$account->appendChild($reply->createElement("AccountType", "email"));
			$account->appendChild($reply->createElement("Action", "settings"));
			$account->appendChild($reply->createElement("ServiceHome", $home));
			if(!empty($ps[0]) && $ps[1] !== "0") {
				$protocol = $reply->createElement("Protocol");
				$protocol->appendChild($reply->createElement("Type", "POP3"));
				$protocol->appendChild($reply->createElement("Server", $ps[0]));
				$protocol->appendChild($reply->createElement("Port", $ps[1]));
				$protocol->appendChild($reply->createElement("LoginName", $email));
				$protocol->appendChild($reply->createElement("DomainRequired", "on"));
				$protocol->appendChild($reply->createElement("SPA", "off"));
				$protocol->appendChild($reply->createElement("SSL", "on"));
				$protocol->appendChild($reply->createElement("AuthRequired", "on"));
				$protocol->appendChild($reply->createElement("TTL", "24"));
				$account->appendChild($protocol);
				unset($protocol);
			}
			if(!empty($p[0]) && $p[1] !== "0") {
				$protocol = $reply->createElement("Protocol");
				$protocol->appendChild($reply->createElement("Type", "POP3"));
				$protocol->appendChild($reply->createElement("Server", $p[0]));
				$protocol->appendChild($reply->createElement("Port", $p[1]));
				$protocol->appendChild($reply->createElement("LoginName", $email));
				$protocol->appendChild($reply->createElement("DomainRequired", "on"));
				$protocol->appendChild($reply->createElement("SPA", "off"));
				$protocol->appendChild($reply->createElement("SSL", "off"));
				$protocol->appendChild($reply->createElement("AuthRequired", "on"));
				$protocol->appendChild($reply->createElement("TTL", "24"));
				$account->appendChild($protocol);
				unset($protocol);
			}
			if(!empty($is[0]) && $is[1] !== "0") {
				$protocol = $reply->createElement("Protocol");
				$protocol->appendChild($reply->createElement("Type", "IMAP"));
				$protocol->appendChild($reply->createElement("Server", $is[0]));
				$protocol->appendChild($reply->createElement("Port", $is[1]));
				$protocol->appendChild($reply->createElement("LoginName", $email));
				$protocol->appendChild($reply->createElement("DomainRequired", "on"));
				$protocol->appendChild($reply->createElement("SPA", "off"));
				$protocol->appendChild($reply->createElement("SSL", "on"));
				$protocol->appendChild($reply->createElement("AuthRequired", "on"));
				$protocol->appendChild($reply->createElement("TTL", "24"));
				$account->appendChild($protocol);
				unset($protocol);
			}
			if(!empty($i[0]) && $i[1] !== "0") {
				$protocol = $reply->createElement("Protocol");
				$protocol->appendChild($reply->createElement("Type", "IMAP"));
				$protocol->appendChild($reply->createElement("Server", $i[0]));
				$protocol->appendChild($reply->createElement("Port", $i[1]));
				$protocol->appendChild($reply->createElement("LoginName", $email));
				$protocol->appendChild($reply->createElement("DomainRequired", "on"));
				$protocol->appendChild($reply->createElement("SPA", "off"));
				$protocol->appendChild($reply->createElement("SSL", "off"));
				$protocol->appendChild($reply->createElement("AuthRequired", "on"));
				$protocol->appendChild($reply->createElement("TTL", "24"));
				$account->appendChild($protocol);
				unset($protocol);
			}
			$protocol = $reply->createElement("Protocol");
			$protocol->appendChild($reply->createElement("Type", "SMTP"));
			$protocol->appendChild($reply->createElement("Server", $sub[0]));
			$protocol->appendChild($reply->createElement("Port", $sub[1]));
			$protocol->appendChild($reply->createElement("LoginName", $email));
			$protocol->appendChild($reply->createElement("DomainRequired", "on"));
			$protocol->appendChild($reply->createElement("SPA", "off"));
			$protocol->appendChild($reply->createElement("Encryption", "Auto"));
			$protocol->appendChild($reply->createElement("AuthRequired", "on"));
			$protocol->appendChild($reply->createElement("TTL", "24"));
			$account->appendChild($protocol);
			unset($protocol);
			$response->appendChild($account);
			$ad->appendChild($response);
			$reply->appendChild($ad);
		}
	}
	header("Content-type: application/xml");
	echo $reply->saveXML();
