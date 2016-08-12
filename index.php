<?php
/* 
 * Copyright (C) 2016 Michael J. Hartwick <hartwick at hartwick.com>
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
	include_once(dirname(__FILE__)."/includes/db.inc");
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
		try {
			$stmt = $pdo->prepare($query);
			$stmt->execute(array($email));
		} catch(\PDOException $ex) {
			error_log($ex->getMessage());
			exit(-3);
		}
		if($stmt->rowCount() > 0) {
			$row = $stmt->fetch(\PDO::FETCH_NUM);
			$addr = explode("@", $email);
			$user = $addr[0];
			$domain = $addr[1];
			if($agent === "Thunderbird") {
				$clientconfig = $reply->createElement("clientConfig");
				$clientconfig->setAttribute("version", "1.1");
				$emailprovider = $reply->createElement("emailProvider");
				$emailprovider->setAttribute("id", $providerid);
				$emailprovider->appendChild($reply->createElement("domain", $domain));
				$emailprovider->appendChild($reply->createElement("displayName", $provider));
				if($row[2] === "N" || $row[2] === '0') {
					if(isset($servers['IMAPS'])) {
						$imaps = $reply->createElement("incomingServer");
						$imaps->setAttribute("type", "imap");
						$imaps->appendChild($reply->createElement("hostname", $servers['IMAPS']['server']));
						$imaps->appendChild($reply->createElement("port", $servers['IMAPS']['port']));
						$imaps->appendChild($reply->createElement("socketType", "SSL"));
						$imaps->appendChild($reply->createElement("authentication", "password-cleartext"));
						$imaps->appendChild($reply->createElement("username", $email));
						$emailprovider->appendChild($imaps);
					}
					if(isset($servers['IMAP'])) {
						$imap = $reply->createElement("incomingServer");
						$imap->setAttribute("type", "imap");
						$imap->appendChild($reply->createElement("hostname", $servers['IMAP']['server']));
						$imap->appendChild($reply->createElement("port", $servers['IMAP']['port']));
						if($servers['IMAP']['starttls'] === \TRUE) {
							$imap->appendChild($reply->createElement("socketType", "STARTTLS"));
						} else {
							$imap->appendChild($reply->createElement("socketType", "plain"));
						}
						$imap->appendChild($reply->createElement("authentication", "password-cleartext"));
						$imap->appendChild($reply->createElement("username", $email));
						$emailprovider->appendChild($imap);
					}
				}
				if($row[1] === "N" || $row[1] === '0') {
					if(isset($servers['POP3S'])) {
						$pop3s = $reply->createElement("incomingServer");
						$pop3s->setAttribute("type", "pop3");
						$pop3s->appendChild($reply->createElement("hostname", $servers['POP3S']['server']));
						$pop3s->appendChild($reply->createElement("port", $servers['POP3S']['port']));
						$pop3s->appendChild($reply->createElement("socketType", "SSL"));
						$pop3s->appendChild($reply->createElement("authentication", "password-cleartext"));
						$pop3s->appendChild($reply->createElement("username", $email));
						$emailprovider->appendChild($pop3s);
					}
					if(isset($servers['POP3'])) {
						$pop3 = $reply->createElement("incomingServer");
						$pop3->setAttribute("type", "pop3");
						$pop3->appendChild($reply->createElement("hostname", $servers['POP3']['server']));
						$pop3->appendChild($reply->createElement("port", $servers['POP3']['port']));
						if($servers['POP3']['starttls'] === \TRUE) {
							$pop3->appendChild($reply->createElement("socketType", "STARTTLS"));
						} else {
							$pop3->appendChild($reply->createElement("socketType", "plain"));
						}
						$pop3->appendChild($reply->createElement("authentication", "password-cleartext"));
						$pop3->appendChild($reply->createElement("username", $email));
						$emailprovider->appendChild($pop3);
					}
				}
				if($row[3] === "Y" || $row[3] === '0') {
					$smtp = $reply->createElement("outgoingServer");
					$smtp->setAttribute("type", "smtp");
					$smtp->appendChild($reply->createElement("hostname", $servers['SMTP']['server']));
					$smtp->appendChild($reply->createElement("port", $servers['SMTP']['port']));
					if($servers['SMTP']['starttls'] === \TRUE) {
						$smtp->appendChild($reply->createElement("socketType", "STARTTLS"));
					} else {
						$smtp->appendChild($reply->createElement("socketType", "plain"));
					}
					$smtp->appendChild($reply->createElement("authentication", "password-cleartext"));
					$smtp->appendChild($reply->createElement("username", $email));
					$emailprovider->appendChild($smtp);
				}
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
				$user->appendChild($reply->createElement("DisplayName", $row[0]));
				$response->appendChild($user);
				$account = $reply->createElement("Account");
				$account->appendChild($reply->createElement("AccountType", "email"));
				$account->appendChild($reply->createElement("Action", "settings"));
				$account->appendChild($reply->createElement("ServiceHome", $home));
				if($row[1] === "N" || $row[1] === '0') {
					if(isset($servers['POP3S'])) {
						$protocol = $reply->createElement("Protocol");
						$protocol->appendChild($reply->createElement("Type", "POP3"));
						$protocol->appendChild($reply->createElement("Server", $servers['POP3']['server']));
						$protocol->appendChild($reply->createElement("Port", $servers['POP3']['server']));
						$protocol->appendChild($reply->createElement("LoginName", $email));
						$protocol->appendChild($reply->createElement("DomainRequired", "on"));
						$protocol->appendChild($reply->createElement("SPA", "off"));
						$protocol->appendChild($reply->createElement("SSL", "on"));
						$protocol->appendChild($reply->createElement("AuthRequired", "on"));
						$protocol->appendChild($reply->createElement("TTL", "24"));
						$account->appendChild($protocol);
						unset($protocol);
					}
					if(isset($servers['POP3'])) {
						$protocol = $reply->createElement("Protocol");
						$protocol->appendChild($reply->createElement("Type", "POP3"));
						$protocol->appendChild($reply->createElement("Server", $servers['POP3']['server']));
						$protocol->appendChild($reply->createElement("Port", $servers['POP3']['server']));
						$protocol->appendChild($reply->createElement("LoginName", $email));
						$protocol->appendChild($reply->createElement("DomainRequired", "on"));
						$protocol->appendChild($reply->createElement("SPA", "off"));
						$protocol->appendChild($reply->createElement("SSL", "off"));
						$protocol->appendChild($reply->createElement("AuthRequired", "on"));
						$protocol->appendChild($reply->createElement("TTL", "24"));
						$account->appendChild($protocol);
						unset($protocol);
					}
				}
				if($row[2] === "N" || $row[2] === '0') {
					if(isset($servers['IMAPS'])) {
						$protocol = $reply->createElement("Protocol");
						$protocol->appendChild($reply->createElement("Type", "IMAP"));
						$protocol->appendChild($reply->createElement("Server", $servers['IMAPS']['server']));
						$protocol->appendChild($reply->createElement("Port", $servers['IMAPS']['server']));
						$protocol->appendChild($reply->createElement("LoginName", $email));
						$protocol->appendChild($reply->createElement("DomainRequired", "on"));
						$protocol->appendChild($reply->createElement("SPA", "off"));
						$protocol->appendChild($reply->createElement("SSL", "on"));
						$protocol->appendChild($reply->createElement("AuthRequired", "on"));
						$protocol->appendChild($reply->createElement("TTL", "24"));
						$account->appendChild($protocol);
						unset($protocol);
					}
					if(isset($servers['IMAP'])) {
						$protocol = $reply->createElement("Protocol");
						$protocol->appendChild($reply->createElement("Type", "IMAP"));
						$protocol->appendChild($reply->createElement("Server", $servers['IMAP']['server']));
						$protocol->appendChild($reply->createElement("Port", $servers['IMAP']['server']));
						$protocol->appendChild($reply->createElement("LoginName", $email));
						$protocol->appendChild($reply->createElement("DomainRequired", "on"));
						$protocol->appendChild($reply->createElement("SPA", "off"));
						$protocol->appendChild($reply->createElement("SSL", "off"));
						$protocol->appendChild($reply->createElement("AuthRequired", "on"));
						$protocol->appendChild($reply->createElement("TTL", "24"));
						$account->appendChild($protocol);
						unset($protocol);
					}
				}
				if ($row[3] === "Y" || $row[3] === '0') {
					$protocol = $reply->createElement("Protocol");
					$protocol->appendChild($reply->createElement("Type", "SMTP"));
					$protocol->appendChild($reply->createElement("Server", $servers['SMTP']['server']));
					$protocol->appendChild($reply->createElement("Port", $servers['SMTP']['port']));
					$protocol->appendChild($reply->createElement("LoginName", $email));
					$protocol->appendChild($reply->createElement("DomainRequired", "on"));
					$protocol->appendChild($reply->createElement("SPA", "off"));
					$protocol->appendChild($reply->createElement("Encryption", "Auto"));
					$protocol->appendChild($reply->createElement("AuthRequired", "on"));
					$protocol->appendChild($reply->createElement("TTL", "24"));
					$account->appendChild($protocol);
					unset($protocol);
				}
				$response->appendChild($account);
				$ad->appendChild($response);
				$reply->appendChild($ad);
			}
		}
	}
	header("Content-type: application/xml");
	echo $reply->saveXML();
	error_log($reply->saveXML());
