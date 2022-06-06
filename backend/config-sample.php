<?php
error_reporting(E_ALL);

// Mikrotik hotspot router API credentials
$api_host = '1.2.3.4';
$api_user = 'api_user';
$api_pass = 'password';

// Hotspot parameters
$hotspot_url = 'https://hotspot.domain'; // This is the url of the Mikrotik router
$hotspot_profile = 'sch-wifi'; // This hotspot profile will be set for the clients
$hotspot_server = 'sch-wifi'; // The hotspot server which is used for this backend

$oauth_id = ''; // Auth.SCH client id
$oauth_secret = ''; // Auth.SCH client secret

$backend_login = false; // False if HTTPS login is configured properly. If true, login will be performed via the API
$debug = false;
