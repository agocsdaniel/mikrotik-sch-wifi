# SCH Wifi Captive Portal

Mikrotik hotspot setup for Schönherz dormitory.

### Installation

Install the backend on a PHP webserver with public domain and HTTPS. The backend must have access to the router which provides the hotspot. Replace `hotspot.example.com` in the hotspot html files to the domain of the PHP server. Copy the hotspot folder to that router and set up the hotspot to use these files. The router must have a domain name and it is strongly advised that you set up HTTPS for it.

You have to obtain a valid developer credential to the Auth.SCH oauth2 server. As redirect url, you should specify your backend domain and the /login.php path.

### Login flow

1. User connects to the hotspot
1. Mikrotik Hotspot redirects user to the login.html
2. When the user clicks login button, they will be redirected to the backend (login.php) and it sends the user to the Auth.sch login page
3. The user logs in and gets redirected to the login.php
4. The login.php queries the router for the user which made the login and logs them in.
	* If backend_login is enabled, the user will be logged in through the API, so the user won't see anything of it
	* If you set up HTTPS properly, you don't have to use backend_login. This way the user is redirected with a generated password to the router's HTTPS API. This method is better, because the router can save HTTP cookies to the user device, so there will be no need to re-login on every connection.
1. The user is logged in.
