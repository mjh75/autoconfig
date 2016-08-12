<?php
	include_once(dirname(__FILE__)."/config.inc");
	try {
		$pdo = new \PDO("mysql:host={$dbhost};dbname={$db}", $dbuser, $dbpwd);
	} catch (\PDOException $e) {
		error_log($e->getMessage());
		exit();
	}
	$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

