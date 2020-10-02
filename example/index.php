<?php
use Pepabo\OAuth2\Client\Provider\ColorMeShop;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (!session_start()) {
    die('Failed to start session');
}

if (!($clientId = getenv('COLORME_CLIENT_ID'))) {
    die('You should set environment variable, like `export COLORME_CLIENT_ID="XXXXXX"`');
}
if (!($clientSecret = getenv('COLORME_CLIENT_SECRET'))) {
    die('You should set environment variable, like `export COLORME_CLIENT_SECRET="XXXXXX"`');
}

$request = Laminas\Diactoros\ServerRequestFactory::fromGlobals(
    $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
);

$route = new League\Route\Router;

$provider = new ColorMeShop([
    'clientId'     => $clientId,
    'clientSecret' => $clientSecret,
    'redirectUri'  => sprintf(
        'http://%s:%d/callback',
        $_SERVER['SERVER_NAME'],
        $_SERVER['SERVER_PORT']
    ),
]);

/*
 * /
 */
$route->map(
    'GET',
    '/',
    function (ServerRequestInterface $request) use ($provider) {
        $response = new Laminas\Diactoros\Response;
        if (!isset($_SESSION['token'])) {
            $authUrl = $provider->getAuthorizationUrl();
            $_SESSION['oauth2state'] = $provider->getState();
            return $response->withStatus(302)->withHeader('Location', $authUrl);
        }

        $token = unserialize($_SESSION['token']);
        $response->getBody()->write(<<<__EOS__
We got an access token.<br />
token: {$token->getToken()}<br />
<a href="/reset">reset</a>
__EOS__
        );

        return $response;
    }
);

/*
 * /callback
 */
$route->map(
    'GET',
    '/callback',
    function (ServerRequestInterface $request) use ($provider) {
        $response = new Laminas\Diactoros\Response;
        $q = $request->getQueryParams();
        if (!isset($q['state']) || $q['state'] !== $_SESSION['oauth2state']) {
            unset($_SESSION['oauth2state']);
            die('invalid state');
        }

        $token = $provider->getAccessToken('authorization_code', [
            'code' => $q['code'],
        ]);
        $_SESSION['token'] = serialize($token);

        return $response->withStatus(302)->withHeader('Location', '/');
    }
);

/*
 * /reset
 */
$route->map(
    'GET',
    '/reset',
    function (ServerRequestInterface $request) {
        $response = new Laminas\Diactoros\Response;
        unset($_SESSION['token']);
        return $response->withStatus(302)->withHeader('Location', '/');
    }
);

$response = $route->dispatch($request);
(new Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($response);
