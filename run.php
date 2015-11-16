<?php

/*
 * Scans a mailbox, gets the timestamp and outputs it in public/data.json to be shown in a graph
 *
 */
use Symfony\Component\Console\Application;

require 'vendor/autoload.php';

define('APPLICATION_NAME', 'Index Mail messages');
define('CREDENTIALS_PATH', 'config/mailindex.json');
define('CLIENT_SECRET_PATH', 'config/client_secret.json');
define(
'SCOPES', implode(
    ' ',
    array(
        Google_Service_Gmail::GMAIL_COMPOSE,
        Google_Service_Gmail::GMAIL_MODIFY,
        Google_Service_Gmail::GMAIL_READONLY,
    )
)
);

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName(APPLICATION_NAME);
    $client->setScopes(SCOPES);
    $client->setAuthConfigFile(CLIENT_SECRET_PATH);
    $client->setAccessType('offline');

    // Load previously authorized credentials from a file.
    $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
    if (file_exists($credentialsPath)) {
        $accessToken = file_get_contents($credentialsPath);
    } else {
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = trim(fgets(STDIN));

        // Exchange authorization code for an access token.
        $accessToken = $client->authenticate($authCode);

        // Store the credentials to disk.
        if ( ! file_exists(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0700, true);
        }
        file_put_contents($credentialsPath, $accessToken);
        printf("Credentials saved to %s\n", $credentialsPath);
    }
    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $client->refreshToken($client->getRefreshToken());
        file_put_contents($credentialsPath, $client->getAccessToken());
    }

    return $client;
}

/**
 * Expands the home directory alias '~' to the full path.
 *
 * @param string $path the path to expand.
 *
 * @return string the expanded path.
 */
function expandHomeDirectory($path)
{
    $homeDirectory = getenv('HOME');
    if (empty( $homeDirectory )) {
        $homeDirectory = getenv("HOMEDRIVE").getenv("HOMEPATH");
    }

    return str_replace('~', realpath($homeDirectory), $path);
}

$application = new Application();
$application->add(new \Mpom\GenerateCommand());
$application->add(new \Mpom\EnhanceCommand());
$application->run();;