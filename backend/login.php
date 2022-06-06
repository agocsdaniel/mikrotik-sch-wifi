<pre>
<?php
error_reporting(E_ALL);
session_set_cookie_params(3*24*60*60);
session_start();

require('config.php');

if(isset($_REQUEST['mac'])) $_SESSION['mac'] = $_REQUEST['mac'];
if(isset($_REQUEST['logout'])) $_SESSION['logout'] = true;
if(isset($_REQUEST['mac']) && !isset($_REQUEST['logout'])) unset($_SESSION['logout']);

require 'AuthSCHClient.class.php';
$oa = new AuthSCHClient;

if (isset($_GET['code'])) {
	$data = $oa->getData();
	$_SESSION['data'] = $data;
	$_SESSION['username'] = $data->linkedAccounts->schacc;
	$_SESSION['user_email'] = $data->mail;
	$_SESSION['user_displayname'] = $data->displayName;
//	header('Location: /login.php');
//	exit();
}

if(isset($_SESSION['mac']) and isset($_SESSION['data']) and isset($_SESSION['logout'])) {
	unset($_SESSION['logout']);
	
	require('routeros_api.class.php');
	$API = new RouterosAPI();
	$API->debug = $debug;

	if ($API->connect($api_host, $api_user, $api_pass)) {
		// Check if user exists
		$user = $API->comm('/ip/hotspot/user/print', [
			".proplist"=> ".id,name,comment",
			'?name' => $_SESSION['username']
		]);
		if(count($user) == 0) {
			exit('Nincs ilyen user');
		}

		// Check if host exists
		$host = $API->comm('/ip/hotspot/host/print', [
			"?to-address" => $_SERVER['REMOTE_HOST'],
		]);
		if(count($host) == 0) {
			$API->disconnect();
			exit("Nem vagy belépve?!");
		}

		// Check whether this host-user pair really online
		$active = $API->comm('/ip/hotspot/active/print', [
			'?user' => $_SESSION['username'],
			'?mac-address' => $_SESSION['mac'],
			'?address' => $host[0]['to-address']
		]);
		if(count($active) == 0) {
			$API->disconnect();
			exit("Nem vagy belépve!");
		}

		// Log host out
		$del_resp = $API->comm('/ip/hotspot/active/remove', [
			'numbers' => $active[0]['.id'],
		]);

		// Remove cookies of the host to prevent auto-login with the previously used credentials
		$num_cookies = 1;
		while($num_cookies > 0) {
			$cookies = $API->comm('/ip/hotspot/cookie/print', [
				'?user' => $_SESSION['username'],
				'?mac-address' => $_SESSION['mac'],
			]);
			if($num_cookies == 0) {
				$API->disconnect();
				exit("Nem vagy belépve!!");
			}

			$num_cookies = count($cookies);

			$del_resp = $API->comm('/ip/hotspot/cookie/remove', [
				'numbers' => $cookies[0]['.id'],
			]);
		}

		session_destroy();
		$API->disconnect();
	}
	
	header('Location: ' . $hotspot_url . '/login'); // Go back to the hotspot login page
}


if(isset($_SESSION['mac']) and isset($_SESSION['data'])) {
	// Generate temporary password for the user
	if(!isset($_SESSION['password'])) $_SESSION['password'] = md5(uniqid(rand(), true));

	require('routeros_api.class.php');
	$API = new RouterosAPI();
	$API->debug = $debug;

	if ($API->connect($api_host, $api_user, $api_pass)) {
		// Create or update user
		$user = $API->comm('/ip/hotspot/user/print', [".proplist"=> ".id,name,comment", '?name' => $_SESSION['username']]);
		if(count($user) == 0) {
			$API->comm("/ip/hotspot/user/add", [
				"name" => $_SESSION['username'],
				"email" => $_SESSION['user_mail'],
				"profile" => $hotspot_profile,
				"server" => $hotspot_server,
				"comment" => iconv("UTF-8","Windows-1250", $_SESSION['user_displayname']),
				"password" => $_SESSION['password'],
			]);
		} else if(count($user) == 1) {
			$API->comm("/ip/hotspot/user/set", [
				"numbers" => $user[0]['.id'],
				"password" => $_SESSION['password'],
			]);
		}

		// Check if host is really connected
		// TODO: Check IP
		$host = $API->comm('/ip/hotspot/host/print', [
			"?mac-address" => $_SESSION['mac'],
		]);
		if(count($host) == 0) {
			$API->disconnect();
			exit("Host doesn't seem to be connected");
		}

		// If backend login is enabled, do it
		if($backend_login) {
			// Log the user in
			$bind_resp = $API->comm('/ip/hotspot/active/login', [
				'user' => $_SESSION['username'],
				'password' => $_SESSION['password'],
				'domain' => '', //user domain
				'mac-address' => $_SESSION['mac'],
				'ip' => $host[0]['to-address']
			]);

			if(isset($bind_resp['!trap'])) {
?>
<html>
<head>
<title>SCH WiFi belépés</title>
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="expires" content="-1">
</head>
<body>
<center>
<h3>
Valami nem sikerült, bocsi!
</h3>
</center>
<footer style="position:absolute; bottom:10">
Hiba:
<?php
			echo $bind_resp['!trap'][0]['message'];
?>
</footer>
</body>
</html>
<?php
				$API->disconnect();
				exit();
			}

		}
		
		$API->disconnect();

		// Log the user in when using HTTPS and go to the status page if not
		if($backend_login) 
			header('Location: ' . $hotspot_url . '/status');
		else 
			header('Location: ' . $hotspot_url . '/login?username=' . $_SESSION['username'] . '&password=' . $_SESSION['password']);
	}

}
?>

</pre>
