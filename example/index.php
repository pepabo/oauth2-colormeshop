<?php
use Pepabo\OAuth2\Client\Provider\ColorMeShop;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (!session_start()) {
    die('Filed to start session');
}

if (!($clientId = getenv('COLORME_CLIENT_ID'))) {
    die('You should set environment variable, like `export COLORME_CLIENT_ID="XXXXXX"');
}
if (!($clientSecret = getenv('COLORME_CLIENT_SECRET'))) {
    die('You should set environment variable, like `export COLORME_CLIENT_SECRET="XXXXXX"');
}

$container = new League\Container\Container;
$container->share('response', Zend\Diactoros\Response::class);
$container->share('request', function () {
    return Zend\Diactoros\ServerRequestFactory::fromGlobals(
        $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
    );
});
$container->share('emitter', Zend\Diactoros\Response\SapiEmitter::class);
$route = new League\Route\RouteCollection($container);

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
    function (ServerRequestInterface $request, ResponseInterface $response) use ($provider) {
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
    function (ServerRequestInterface $request, ResponseInterface $response) use ($provider) {
        $q = $request->getQueryParams();
        if (!isset($q['state']) || $q['state'] !== $_SESSION['oauth2state']) {
            unset($_SESSION['oauth2state']);
            die('invlid state');
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
    function (ServerRequestInterface $request, ResponseInterface $response) {
        unset($_SESSION['token']);
        return $response->withStatus(302)->withHeader('Location', '/');
    }
);

$response = $route->dispatch($container->get('request'), $container->get('response'));
$container->get('emitter')->emit($response);
