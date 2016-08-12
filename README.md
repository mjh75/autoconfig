# autoconfig

This project will generate the XML files required to setup Thunderbird and
Outlook to support IMAP, POP3 and SMTP. Most of the configurable options
are located in includes/config.inc so you can change them as required.

## Configuration

The credentials to use to connect to the MySQL database using the PHP
PDO functionality are provided in this file.

	$dbhost = "localhost";
	$dbuser = "user";
	$dbpwd = "password";
	$db = "db";

The query is a SQL query that returns the users name and flags to indicate
if POP3 is disabled, IMAP is disabled and SMTP is enabled. Note the flags
are true to disable for POP3 and IMAP, and true to enable SMTP. The true can
be either Y or 1, false is N or 0. Since the SQL query is run as a prepared
statement with the email address passed as the only data make sure you have
something like `email` = ? to allow the prepared statement to work.

	$query = "SELECT `name`, `disablepop3`, `disableimap`, `smtpaccess` FROM `users` WHERE `email` = ?";

The web page of the provider, this is sent for the Outlook configuration, but
so far it has not been seen anywhere in Outlook.

	$home = "http://www.example.com/";

The provider is used by Thunderbird for the display name (I have only seen
this for the name of the SMTP server), providerid is sent as part of the
Thunderbird configuration and I have not found much in the way of documentation
on this so for now I am just going with a domain name.

	$provider = "Provider";
	$providerid = "example.com";


$servers is a multidimensional array that contains the various protocols and
the server settings for them. The supported protocols are:

* IMAPS
* IMAP
* POP3S
* POP3
* SMTP

For each of these protocols there is an array element server for the hostname
of the server for that protocol, an element port for the TCP port of that
protocol, and for IMAP, POP3 and SMTP there is an element starttls to indicate
if STARTTLS is to be used. IMAPS and POP3S do not use this element since they
are using SSL by definition.

## Apache

There is a sample configuration file for Apache in etc/apache2. I am not a
big fan of .htaccess files so I have added the appropriate Rewrite rules to 
the sample configuration file. Adjust as you see fit.
