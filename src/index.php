<?php
ini_set('display_errors', 1); 
error_reporting(E_ALL);

//TODO clean up index php and start working with namespaces.

//TODO button to choose refresh every 1-5-10 seconds?
//TODO show max temp or temp range on chart?

// ASCII font is ANSI Regular which can be found on https://patorjk.com/software/taag/

use DI\Container;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Slim\Factory\AppFactory;
use Slim\Flash\Messages;
use Slim\Routing\RouteCollectorProxy;
use Slim\Routing\RouteContext;

use Slim\Exception\HttpNotFoundException;

use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

use GuzzleHttp\Client;

require __DIR__ . '/../vendor/autoload.php';

//  ██████  ██████  ███    ██ ████████  █████  ██ ███    ██ ███████ ██████  
// ██      ██    ██ ████   ██    ██    ██   ██ ██ ████   ██ ██      ██   ██ 
// ██      ██    ██ ██ ██  ██    ██    ███████ ██ ██ ██  ██ █████   ██████  
// ██      ██    ██ ██  ██ ██    ██    ██   ██ ██ ██  ██ ██ ██      ██   ██ 
//  ██████  ██████  ██   ████    ██    ██   ██ ██ ██   ████ ███████ ██   ██ 

$container = new Container();

AppFactory::setContainer($container);
$app = AppFactory::create();

$container->set('settings', function () {
    
    $dotenv = Dotenv\Dotenv::createMutable(__DIR__. '/../');
    if($dotenv->safeLoad() == [])
    {
        // Check if .env file was loaded. If not copy over the example file.
        copy(__DIR__. '/../.env.example', __DIR__. '/../.env');
        $dotenv->safeLoad();
    }

    return [
        'phone-id'      => $_ENV['PHONEID'],
        'timezone'      => $_ENV['TIMEZONE'],
        'timeformat'    => $_ENV['TIMEFORMAT'],
        'liveMinutes'   => $_ENV['LIVEMINUTES'],
        'pagination'    => [5, 10, 20, 50, 100],
        'probeColors'   => [
            1 => $_ENV['PROBE_COLOR_1'],   // yellow
            2 => $_ENV['PROBE_COLOR_2'],   // green
            3 => $_ENV['PROBE_COLOR_3'],   // blue
            4 => $_ENV['PROBE_COLOR_4'],   // red
            5 => $_ENV['PROBE_COLOR_5'],   // purple
            6 => $_ENV['PROBE_COLOR_6'],   // orange
            7 => $_ENV['PROBE_COLOR_7'],   // deep purple
            8 => $_ENV['PROBE_COLOR_8'],   // turqoise
        ],
        'probeVisibility'   => [
            1 => $_ENV['PROBE_VISIBILITY_1'],
            2 => $_ENV['PROBE_VISIBILITY_2'],
            3 => $_ENV['PROBE_VISIBILITY_3'],
            4 => $_ENV['PROBE_VISIBILITY_4'],
            5 => $_ENV['PROBE_VISIBILITY_5'],
            6 => $_ENV['PROBE_VISIBILITY_6'],
            7 => $_ENV['PROBE_VISIBILITY_7'],
            8 => $_ENV['PROBE_VISIBILITY_8'],
        ],
        "darkMode"  => $_ENV['DARK_MODE']
    ];
});

$container->set('flash', function () {
    $storage = [];
    return new Messages($storage);
});

$container->set('api', function ($container) {
    return new Client([
        'base_uri' => 'https://api-prod.hyperion.grilleye.com',
        'headers'  => [
            "host"            => "api-prod.hyperion.grilleye.com",
            "content-type"    => "application/json",
            "connection"      => "keep-alive",
            "accept"          => "*/*",
            "user-agent"      => "GrillEye Hyperion/1.0 (com.grilleye.hyperion; build:4; iOS 14.6.0) Alamofire/5.0.5",
            "accept-language" => "en;q=1.0",
            "accept-encoding" => "br;q=1.0, gzip;q=0.9, deflate;q=0.8",
            "phone-id"        => $container->get('settings')['phone-id'],
        ],
        'timeout'  => 5.0,
    ]);
});

$container->set('temperatureUnit', function ($container) {

    $settings = json_decode((string) $container->get('api')->get('/phones/settings')->getBody(), true);

    return "°{$settings['unitOfMeassure'][0]}";
});

$container->set('presets', function ($container) {

    $presets = json_decode((string) $container->get('api')->get('/phones/me/presets')->getBody(), true);

    $sortedPresets = [];

    foreach($presets as $preset)
    {
        $meatReadiness = str_replace('_', ' ', ucfirst(strtolower($preset['readiness'])));
        $preset['description'] = $preset['description'] ?? $meatReadiness;

        $preset['description'] = $preset['description'] ?? $meatReadiness;

        $preset['minimalTemperature'] = $preset['minimalTemperature'] != null ? "{$preset['minimalTemperature']}" : null;
        $preset['peakTemperature'] = "{$preset['peakTemperature']}";

        $sortedPresets[$preset['type']][] = $preset;
    }
    
    return $sortedPresets;
});

$container->set('serialNumber', function ($container) { 
    return json_decode((string) $container->get('api')->get('/grills')->getBody(), true)[0]['serialNumber'];
});

$container->set('meatTypes', function () {
    return [
        "CUSTOM"    => "Custom",
        "BEEF"      => "Beef",
        "CHICKEN"   => "Chicken",
        "FISH"      => "Fish",
        "HAMBURGER" => "Hamburger",
        "LAMB"      => "Lamb",
        "PORK"      => "Pork",
        "SMOKE"     => "Smoke",
        "TURKEY"    => "Turkey",
        "VEAL"      => "Veal",
    ];
});

$container->set('readiness', function () {
    return [
        "CUSTOM"      => "Custom",
        "DEFAULT"     => "Default",
        "WELL_DONE"   => "Well done",
        "MEDIUM_WELL" => "Medium well",
        "MEDIUM"      => "Medium",
        "MEDIUM_RARE" => "Medium rare",
        "RARE"        => "Rare",
        "BBQ_SMOKE"   => "Bbq smoke",
        "HOT_SMOKE"   => "Hot smoke",
        "COLD_SMOKE"  => "Coldsmoke",
    ];
});

$container->set('eventTypes', function () {
    return [
        "SET_PRESET"    => "Set preset",
        "ALARM"         => "Alarm",
        "DISCONNECTED"  => "Disconnected",
        "UPDATE_FAILED" => "Update failed",
    ];
});

// ███    ███ ██ ██████  ██████  ██      ███████ ██     ██  █████  ██████  ███████ 
// ████  ████ ██ ██   ██ ██   ██ ██      ██      ██     ██ ██   ██ ██   ██ ██      
// ██ ████ ██ ██ ██   ██ ██   ██ ██      █████   ██  █  ██ ███████ ██████  █████   
// ██  ██  ██ ██ ██   ██ ██   ██ ██      ██      ██ ███ ██ ██   ██ ██   ██ ██      
// ██      ██ ██ ██████  ██████  ███████ ███████  ███ ███  ██   ██ ██   ██ ███████ 
         
$twig = Twig::create(__DIR__ . '/views', ['cache' => false]);
$app->add(TwigMiddleware::create($app, $twig));

// https://www.slimframework.com/docs/v4/middleware/error-handling.html
$errorMiddleware = $app->addErrorMiddleware(false, false, false);

$app->add(
    function ($request, $next) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->get('flash')->__construct($_SESSION);

        return $next->handle($request);
    }
);

// ██████   █████   ██████  ███████ ███████                                 
// ██   ██ ██   ██ ██       ██      ██                                      
// ██████  ███████ ██   ███ █████   ███████                                 
// ██      ██   ██ ██    ██ ██           ██                                 
// ██      ██   ██  ██████  ███████ ███████                                 

// ██████   █████  ███████ ██   ██ ██████   ██████   █████  ██████  ██████  
// ██   ██ ██   ██ ██      ██   ██ ██   ██ ██    ██ ██   ██ ██   ██ ██   ██ 
// ██   ██ ███████ ███████ ███████ ██████  ██    ██ ███████ ██████  ██   ██ 
// ██   ██ ██   ██      ██ ██   ██ ██   ██ ██    ██ ██   ██ ██   ██ ██   ██ 
// ██████  ██   ██ ███████ ██   ██ ██████   ██████  ██   ██ ██   ██ ██████  

$app->get('/', function (Request $request, Response $response, $args) {

    // If we don't have a phone id we redirect to settings    
    $settings        = $this->get('settings');

    if($settings['phone-id'] == "")
    {
        $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('settings');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    $serialNumber    = $this->get('serialNumber');
    $temperatureUnit = $this->get('temperatureUnit');
    $timezone        = new DateTimeZone($settings['timezone']);        
        
    $grill  = json_decode((string) $this->get('api')->get("/grills")->getBody(), true)[0];
    $apiProbes = json_decode((string) $this->get('api')->get("/grills/{$serialNumber}/probes")->getBody(), true);
    
    try {
        $session = json_decode((string) $this->get('api')->get("/grills/{$serialNumber}/sessions/current")->getBody(), true);
    } catch (\Throwable $th) {
        $session = false;
    }

    if($session)
    {
        $session['colors'] = [];

        foreach($session['probesIncluded'] as $probeIndex)
        {
            $session['colors'][] = $settings['probeColors'][$probeIndex + 1];
        }
    }

    $probes = [];

    foreach($apiProbes as $key => $probe)
    {
        //Just the Index to generate the probes
        $probes[$key]['index'] = $probe['probeIndex'] + 1;
    }

    $view = Twig::fromRequest($request);

    return $view->render($response, 'dashboard.twig', [
        'grill'      => $grill,
        'probes'     => $probes,
        'session'    => $session,
        'settings'   => $settings,
        'colors'     => array_values($settings['probeColors']),
        'visibility' => array_values($settings['probeVisibility']),
        'alert'      => $this->get('flash')->getFirstMessage('alert')
    ]);
})->setName('dashboard');

// ███████ ███████ ███████ ███████ ██  ██████  ███    ██ ███████ 
// ██      ██      ██      ██      ██ ██    ██ ████   ██ ██      
// ███████ █████   ███████ ███████ ██ ██    ██ ██ ██  ██ ███████ 
//      ██ ██           ██      ██ ██ ██    ██ ██  ██ ██      ██ 
// ███████ ███████ ███████ ███████ ██  ██████  ██   ████ ███████ 

$app->group('/sessions', function (RouteCollectorProxy $group) {
    
    $group->get('', function (Request $request, Response $response, $args) {

        $filters = $request->getQueryParams();

        $settings     = $this->get('settings');
        $serialNumber = $this->get('serialNumber');
        $meatTypes    = $this->get('meatTypes');

        $fromDate = (isset($filters['fromDate']) && $filters['fromDate'] != '') ? date('dmY', strtotime($filters['fromDate'])) : '';
        $toDate   = (isset($filters['toDate']) && $filters['fromDate'] != '') ? date('dmY', strtotime($filters['toDate'])) : '';
        $page     = isset($filters['page']) ? $filters['page'] : 0;
        $perPage  = isset($filters['perPage']) ? $filters['perPage'] : 10;

        // Yes I know it's ugly but it's the only way I can make it work with multiple meat types :(
        $queryString = "perPage={$perPage}&fromDate={$fromDate}&toDate={$toDate}";

        foreach ($filters as $filter) 
        {
            if(in_array($filter, array_keys($meatTypes)))
            {
                $queryString .= "&meatType={$filter}";
            }
        }

        // For a quick solution we just send the query string as a var to the page for the pagination links
        $paginationQueryString = $queryString;

        $queryString .= "&page={$page}";

        $sessions = json_decode((string) $this->get('api')->get("/grills/{$serialNumber}/sessions", [
            'query' => $queryString
        ])->getBody(), true);

        $timezone = new DateTimeZone($settings['timezone']);

        foreach ($sessions['data'] as $key => $session) 
        {
            $from = new DateTime($session['timeCreated']);
            $to = new DateTime($session['timeFinished']);

            $to->setTimezone($timezone);
            $from->setTimezone($timezone);

            $sessions['data'][$key]['timeCreated']  = $from->format($settings['timeformat']);
            $sessions['data'][$key]['timeFinished'] = $to->format($settings['timeformat']);
            $sessions['data'][$key]['duration']     = $from->diff($to)->format("%H:%I");
        }

        $view = Twig::fromRequest($request);
        
        return $view->render($response, 'sessions.twig', [
            'settings'        => $this->get('settings'),
            'filters'         => $filters,
            'sessions'        => $sessions['data'],
            'totalPages'      => ceil($sessions['totalElements'] / $perPage),
            'queryString'     => $paginationQueryString,
            'perPage'         => $perPage,
            'pagination'      => $this->get('settings')['pagination'],
            'meatTypes'       => $meatTypes,
            'alert'           => $this->get('flash')->getFirstMessage('alert')
        ]);
    })->setName('sessions');

    $group->get('/csv/{sessionId}', function (Request $request, Response $response, $args) {

        $serialNumber = $this->get('serialNumber');
        $session = json_decode((string) $this->get('api')->get("/grills/{$serialNumber}/sessions/{$args['sessionId']}")->getBody(), true);

        // The same dirty hacks as the sessions page
        $queryString = "fromDate={$session['timeCreated']}&toDate={$session['timeFinished']}";

        foreach ($session['probesIncluded'] as $probeId) 
        {
                $queryString .= "&probes={$probeId}";
        }

        $csv = json_decode((string) $this->get('api')->get("/grills/{$serialNumber}/graphs/csv", [
            'query' => $queryString
        ])->getBody(), true);

        $response->getBody()->write($csv['csv']);

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename=grilleye-graph.csv');
    });

    $group->post('/start', function (Request $request, Response $response, $args) {
        
        $serialNumber = $this->get('serialNumber');
    
        $grilleyeApi = $this->get('api')->post("/grills/{$serialNumber}/sessions", [
            'http_errors' => false
        ]);
    
        $this->get('flash')->addMessage('alert', [
            'type' => 'success', 
            'message' => 'Session started! Dont\'t forget to set a name and choose your included probes.'
        ]);
        
        $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('dashboard');
        return $response->withStatus(302)->withHeader('Location', $url);
    });

    $group->post('/stop', function (Request $request, Response $response, $args) {
        
        $serialNumber = $this->get('serialNumber');
    
        $grilleyeApi = $this->get('api')->put("/grills/{$serialNumber}/sessions/current/finished-time", [
            'http_errors' => false
        ]);
    
        $this->get('flash')->addMessage('alert', [
            'type' => 'success', 
            'message' => 'Session stopped!.'
        ]);
        
        $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('dashboard');
        return $response->withStatus(302)->withHeader('Location', $url);
    });

    $group->map(['GET', 'POST'], '/current/settings', function (Request $request, Response $response, $args) {
  
        $serialNumber = $this->get('serialNumber');
    
        $session = json_decode((string) $this->get('api')->get("/grills/{$serialNumber}/sessions/current")->getBody(), true);

        if($request->getMethod() == 'POST')
        {
            $data = $request->getParsedBody();

            $grilleyeApi = $this->get('api')->put("/grills/{$serialNumber}/sessions/current", [
                'json' => [
                    "name" => $data['sessionName'],
                    "probesIncluded" => array_keys($data['includedprobes'])
                ],
                'http_errors' => false
            ]);

            $this->get('flash')->addMessage('alert', [
                'type' => 'success', 
                'message' => 'Session settings have been saved.'
            ]);

            $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('dashboard');
            return $response->withStatus(302)->withHeader('Location', $url);
        }


        $view = Twig::fromRequest($request);

        return $view->render($response, 'currentsession.twig', [
            'session'    => $session,
        ]);
    });

    $group->post('/delete', function (Request $request, Response $response, $args) {
    
        $data = $request->getParsedBody();
    
        $grilleyeApi = $this->get('api')->delete("/sessions/{$data['sessionId']}", [
            'http_errors' => false
        ]);
    
        if($grilleyeApi->getStatusCode() != 200)
        {
            $this->get('flash')->addMessage('alert', [
                'type' => 'danger',
                'message' => 'Session could not be deleted'
            ]);
        }
        else
        {
            $this->get('flash')->addMessage('alert', [
                'type' => 'success', 
                'message' => 'Session has been deleted'
            ]);
        }
        
        $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('sessions');
        return $response->withStatus(302)->withHeader('Location', $url);
    });

    $group->get('/{sessionId}', function (Request $request, Response $response, $args) {

        $serialNumber = $this->get('serialNumber');
        $meatTypes    = $this->get('meatTypes');
        $eventTypes   = $this->get('eventTypes');
        $settings     = $this->get('settings');

        $timezone     = new DateTimeZone($settings['timezone']);

        $session = json_decode((string) $this->get('api')->get("/grills/{$serialNumber}/sessions/{$args['sessionId']}")->getBody(), true);

        $from = new DateTime($session['timeCreated']);
        $to = new DateTime($session['timeFinished']);

        $to->setTimezone($timezone);
        $from->setTimezone($timezone);
        
        $session['timeCreated']  = $from->format($settings['timeformat']);
        $session['timeFinished'] = $to->format($settings['timeformat']);
        $session['duration']     = $from->diff($to)->format("%H:%I");
        
        $temperatures = [];
        $colors = [];
        
        foreach ($session['graphsForProbes'] as $probeTemperatures) {

            $probeIndex = $probeTemperatures['probeIndex'] + 1;
            /*
                We cant set a color per line/series when using apexcharts. Apexcharts just uses
                all the colors one by one. In order to have the same colors everywhere we have to
                fetch the colors and push them on the color array for each probe.
            */
            $colors[$probeIndex] = $settings['probeColors'][$probeIndex];

            $temperatures[$probeIndex] = [
                'name'  =>  "Probe {$probeIndex}",
                'data'  =>  array_map(function($reading) use($timezone) {

                    $timestamp = new DateTime($reading['timestamp']);
                    $timestamp->setTimezone($timezone);
                    
                    return [
                        'x' => $timestamp->format(DATE_ATOM),
                        'y' => $reading['temperature'],
                    ];
                }, $probeTemperatures['graphData']),
            ];
        }

        
        /*
        first we use the probe id to build up the temperatures array. But because we have to 
        json_encode we have to just take the values and put it in a new array otherwise we 
        will end up with an object. We do the same for the colors
        */
        ksort($temperatures);
        $temperatures = array_values($temperatures);
        ksort($colors);
        $colors = array_values($colors);
        
        $view = Twig::fromRequest($request);

        return $view->render($response, 'session.twig', [
            'settings'        => $this->get('settings'),
            'session'         => $session,
            'meatTypes'       => $meatTypes,
            'eventTypes'      => $eventTypes,
            'temperatures'    => $temperatures,
            'colors'          => $colors
        ]);
    });

});

// ██████  ██████  ███████ ███████ ███████ ████████ ███████ 
// ██   ██ ██   ██ ██      ██      ██         ██    ██      
// ██████  ██████  █████   ███████ █████      ██    ███████ 
// ██      ██   ██ ██           ██ ██         ██         ██ 
// ██      ██   ██ ███████ ███████ ███████    ██    ███████ 
                                                         
$app->group('/presets', function (RouteCollectorProxy $group) {
    
    $group->get('', function (Request $request, Response $response, $args) {
    
        $view = Twig::fromRequest($request);
        return $view->render($response, 'presets.twig', [
            'settings'        => $this->get('settings'),
            'sortedPresets'   => $this->get('presets'),
            'readiness'       => $this->get('readiness'),
            'meatTypes'       => $this->get('meatTypes'),
            'temperatureUnit' => $this->get('temperatureUnit'),
            'alert'           => $this->get('flash')->getFirstMessage('alert')
        ]);
    
    })->setName('presets');
    
    $group->map(['POST', 'PUT', 'PATCH'], '', function (Request $request, Response $response, $args) {
    
        $data = $request->getParsedBody();

        $data['readiness'] = $data['readiness'] != "" ? $data['readiness'] : "CUSTOM";

        $grilleyeApi = $this->get('api')->post("/phones/me/presets", [
            'json' => $data,
            'http_errors' => false
        ]);

        if($grilleyeApi->getStatusCode() != 200)
        {
            $this->get('flash')->addMessage('alert', [
                'type'    => 'danger',
                'message' => 'Preset could not be saved'
            ]);
        }
        else
        {
            $this->get('flash')->addMessage('alert', [
                'type'    => 'success',
                'message' => 'Preset has been saved'
            ]);
        }
        
        $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('presets');
        return $response->withStatus(302)->withHeader('Location', $url);
    
    });

    $group->post('/delete', function (Request $request, Response $response, $args) {
    
        $data = $request->getParsedBody();
    
        $grilleyeApi = $this->get('api')->delete("/phones/me/presets/{$data['presetId']}", [
            'http_errors' => false
        ]);
    
        if($grilleyeApi->getStatusCode() != 200)
        {
            $this->get('flash')->addMessage('alert', [
                'type' => 'danger',
                'message' => 'Preset could not be deleted'
            ]);
        }
        else
        {
            $this->get('flash')->addMessage('alert', [
                'type' => 'success', 
                'message' => 'Preset has been deleted'
            ]);
        }
        
        $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('presets');
        return $response->withStatus(302)->withHeader('Location', $url);
    });
});

// ███████ ███████ ████████ ████████ ██ ███    ██  ██████  ███████ 
// ██      ██         ██       ██    ██ ████   ██ ██       ██      
// ███████ █████      ██       ██    ██ ██ ██  ██ ██   ███ ███████ 
//      ██ ██         ██       ██    ██ ██  ██ ██ ██    ██      ██ 
// ███████ ███████    ██       ██    ██ ██   ████  ██████  ███████ 

$app->map(['GET', 'POST'], '/settings', function (Request $request, Response $response, $args) {
    $alert = [];
    
    if($request->getMethod() == 'POST')
    {
        $data = $request->getParsedBody();
    
        try {
            //Check if the phone id works If not throw an alert
            $grilleyeSettings = json_decode((string) $this->get('api')->get('/phones/settings', [
                'headers' => [
                    "phone-id" => $data['settings']['phone-id']
                ]
            ])->getBody(), true);
        } catch (\Throwable $th) {

            $view = Twig::fromRequest($request);
            return $view->render($response, 'settings.twig', [
                'localsettings'    => $data['settings'],
                'grilleyesettings' => [],
                'alert'            => [
                    'type' => 'danger', 
                    'message' => 'Phone ID is incorrect. Please verify'
                ]
            ]);
        }

        $visibleProbes = array_keys($data['settings']['probeVisibility']);

        for($i=1; $i < 9; $i++)
        {
            if(in_array($i, $visibleProbes))
            {
                $data['settings']['probeVisibility'][$i] = 1;
            }
            else
            {
                $data['settings']['probeVisibility'][$i] = 0;
            }
        }

        $darkMode = 0;
        if(in_array("darkMode", array_keys($data['settings'])))
        {
            $darkMode = 1;
        }

        //Save the local settings to the .env file
        file_put_contents(__DIR__. '/../.env', str_replace("  ", "", "
            PHONEID=\"{$data['settings']['phone-id']}\" 
            TIMEZONE=\"{$data['settings']['timezone']}\"
            LIVEMINUTES={$data['settings']['liveMinutes']}
            TIMEFORMAT=\"{$data['settings']['timeformat']}\"
            PROBE_COLOR_1=\"{$data['settings']['probeColors'][1]}\"
            PROBE_COLOR_2=\"{$data['settings']['probeColors'][2]}\"
            PROBE_COLOR_3=\"{$data['settings']['probeColors'][3]}\"
            PROBE_COLOR_4=\"{$data['settings']['probeColors'][4]}\"
            PROBE_COLOR_5=\"{$data['settings']['probeColors'][5]}\"
            PROBE_COLOR_6=\"{$data['settings']['probeColors'][6]}\"
            PROBE_COLOR_7=\"{$data['settings']['probeColors'][7]}\"
            PROBE_COLOR_8=\"{$data['settings']['probeColors'][8]}\"
            PROBE_VISIBILITY_1=\"{$data['settings']['probeVisibility'][1]}\"
            PROBE_VISIBILITY_2=\"{$data['settings']['probeVisibility'][2]}\"
            PROBE_VISIBILITY_3=\"{$data['settings']['probeVisibility'][3]}\"
            PROBE_VISIBILITY_4=\"{$data['settings']['probeVisibility'][4]}\"
            PROBE_VISIBILITY_5=\"{$data['settings']['probeVisibility'][5]}\"
            PROBE_VISIBILITY_6=\"{$data['settings']['probeVisibility'][6]}\"
            PROBE_VISIBILITY_7=\"{$data['settings']['probeVisibility'][7]}\"
            PROBE_VISIBILITY_8=\"{$data['settings']['probeVisibility'][8]}\"
            DARK_MODE=\"{$darkMode}\"
        "));

        //If we dont have a valid phone id all settings will be disabled and there will be no data
        if(isset($data['grilleyesettings']))
        {
            $data['grilleyesettings']['alarmOnDisconnect'] = isset($data['grilleyesettings']['alarmOnDisconnect']) ? true : false;
            $data['grilleyesettings']['notificationSound'] = isset($data['grilleyesettings']['notificationSound']) ? true : false;
    
            $grilleyeApi = $this->get('api')->put('/phones/settings', [
                'json' => $data['grilleyesettings'],
                'http_errors' => false
            ]);
    
            if($grilleyeApi->getStatusCode() != 200)
            {
                $this->get('flash')->addMessage('alert', [
                    'type' => 'danger',
                    'message' => 'Settings could not be saved, please check your values'
                ]);
            }
            else
            {
                $this->get('flash')->addMessage('alert', [
                    'type' => 'success', 
                    'message' => 'Settings have been saved'
                ]);
            }
        }
        else
        {
            //If there are no grilleye settings we are most likely initializing
            $this->get('flash')->addMessage('alert', [
                'type' => 'success', 
                'message' => 'Settings have been initialized'
            ]);
        }

        $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('settings');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    // When initializing we dont have a phone ID and can't get the settings.
    try {
        $grilleyeSettings = json_decode((string) $this->get('api')->get('/phones/settings')->getBody(), true);
    } catch (\Throwable $th) {
        $grilleyeSettings = [];
    }

    $view = Twig::fromRequest($request);
    return $view->render($response, 'settings.twig', [
        'settings'         => $this->get('settings'),
        'grilleyesettings' => $grilleyeSettings,
        'alert'            => $this->get('flash')->getFirstMessage('alert')
    ]);

})->setName('settings');

// ██████  ██████   ██████  ██████  ███████ ███████ 
// ██   ██ ██   ██ ██    ██ ██   ██ ██      ██      
// ██████  ██████  ██    ██ ██████  █████   ███████ 
// ██      ██   ██ ██    ██ ██   ██ ██           ██ 
// ██      ██   ██  ██████  ██████  ███████ ███████ 
                                                 
$app->map(['GET', 'POST'], '/probes/settings', function (Request $request, Response $response, $args) {

    $settings     = $this->get('settings');
    $serialNumber = $this->get('serialNumber');
    $presets      = $this->get('presets');
    $probes       = json_decode((string) $this->get('api')->get("/grills/{$serialNumber}/probes")->getBody(), true);

    $timezone = new DateTimeZone($settings['timezone']);
    $now = new DateTime("now");
    $now->setTimezone($timezone);

    if($request->getMethod() == 'POST')
    {
        $data = $request->getParsedBody();
        
        foreach($data['probes'] as $probeId => $probe)
        {
            // 0 => array (2) [
            //     'preset' => string (0) ""
            //     'timer' => array (3) [
            //         'hours' => string (1) "1"
            //         'minutes' => string (2) "49"
            //         'notes' => string (0) ""
            //     ]
            // ]

            // Set or delete the preset
            if($probe['preset'] != "")
            {
                $setpreset = $this->get('api')->put("/grills/{$serialNumber}/probes/{$probeId}/preset", [
                    'json' => ["presetId" => $probe['preset']],
                    'http_errors' => false
                ]);
            }
            else
            {
                $delpreset = $this->get('api')->delete("/grills/{$serialNumber}/probes/{$probeId}/preset", [
                    'http_errors' => false
                ]);
            }

            // Set or delete the timer
            (int) $totaltime = ($probe['timer']['hours'] * 3600) + ($probe['timer']['minutes'] * 60);
            
            if($totaltime > 0)
            {
                $settimer = $this->get('api')->post("/grills/{$serialNumber}/probes/{$probeId}/timer", [
                    'json' => [
                        "duration" => $totaltime,
                        "notes"    => $probe['timer']['notes']
                    ],
                    'http_errors' => false
                ]);
            }
            else
            {
                $deltimer = $this->get('api')->delete("/grills/{$serialNumber}/probes/{$probeId}/timer", [
                    'http_errors' => false
                ]);
            }
        }
        
        $this->get('flash')->addMessage('alert', [
            'type' => 'success', 
            'message' => 'Probe settings have been saved!'
        ]);

        $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('dashboard');
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    foreach($probes as $key => $probe)
    {
        $probes[$key]['timer']['hours'] = false;
        $probes[$key]['timer']['minutes'] = false;

        if($probe['timer'])
        {       
            $endtime = strtotime($probe['timer']['timeCreated']) + $probe['timer']['duration'];

            $timeleft = $endtime - $now->getTimeStamp();

            $probes[$key]['timer']['hours'] = (int) ($timeleft - ($timeleft % 3600)) / 3600;
            $probes[$key]['timer']['minutes'] = (int)round(($timeleft % 3600) / 60);
        }
    }

    $view = Twig::fromRequest($request);
    return $view->render($response, 'probesettings.twig', [
        'settings'      => $this->get('settings'),
        'sortedpresets' => $this->get('presets'),
        'probes'        => $probes,
    ]);

})->setName('settings');                                                

//  █████  ██████  ██ 
// ██   ██ ██   ██ ██ 
// ███████ ██████  ██ 
// ██   ██ ██      ██ 
// ██   ██ ██      ██ 

$app->group('/api', function (RouteCollectorProxy $group) {
    

    $group->get('/grill', function ($request, $response, array $args) {

        $serialNumber    = $this->get('serialNumber');
        $settings        = $this->get('settings');
        $temperatureUnit = $this->get('temperatureUnit');

        $timezone = new DateTimeZone($settings['timezone']);
        $now = new DateTime("now");
        $now->setTimezone($timezone);

        $grill     = json_decode((string) $this->get('api')->get("/grills")->getBody(), true)[0];
        $apiProbes = json_decode((string) $this->get('api')->get("/grills/{$serialNumber}/probes")->getBody(), true);
        
        try {
            $data['session']   = json_decode((string) $this->get('api')->get("/grills/{$serialNumber}/sessions/current")->getBody(), true);
        } catch (\Throwable $th) {
            $data['session'] = [];
        }

        unset($grill['probeData']);

        $data['grill'] = $grill;
        $data['grill']['timestamp'] = $now->format(DATE_ATOM);

        $probes = [];

        foreach($apiProbes as $key => $probe)
        {
            //Index
            $probes[$key]['index'] = $probe['probeIndex'] + 1;

            //Temperaturesetting
            //Preset
            if($probe['preset']) 
            {
                if($probe['preset']['minimalTemperature'])
                {
                    $probes[$key]['temperatureSetting'] = "{$probe['preset']['minimalTemperature']}{$temperatureUnit} - {$probe['preset']['peakTemperature']}{$temperatureUnit}";
                }
                else
                {
                    $probes[$key]['temperaturesetting'] = "{$probe['preset']['peakTemperature']}{$temperatureUnit}";
                }

                $probes[$key]['preset'] = ucfirst(strtolower($probe['preset']['type'])) . ' - ';

                if($probe['preset']['description'])
                {
                    $probes[$key]['preset'] .= ucfirst(strtolower($probe['preset']['description']));
                }
                else
                {
                    $probes[$key]['preset'] .= ucfirst(strtolower($probe['preset']['readiness']));
                }

            }
            else{
                $probes[$key]['temperaturesetting'] = "N/a";
                $probes[$key]['preset'] = "No preset set";
            }

            $timezone = new DateTimeZone($settings['timezone']);
            $now = new DateTime("now");
            $now->setTimezone($timezone);

            if($probe['timer'])
            {
                
                $endtime = strtotime($probe['timer']['timeCreated']) + $probe['timer']['duration'];
                $timerend = new DateTime();
                $timerend->setTimestamp($endtime);
                $timerend->setTimezone($timezone);
                
                $probes[$key]['timer'] = $now->diff($timerend)->format("%H:%I:%S");
            }
            else
            {
                $probes[$key]['timer'] = "00:00:00";
            }

            if($probe['predictedDoneTime'])
            {  
                $donetime = new DateTime($probe['predictedDoneTime']);
                $donetime->setTimezone($timezone);
                
                $donetime = $now->diff($donetime)->format("%H:%I");

                $probes[$key]['donetime'] = "Ready: {$donetime}";
            }
            else
            {
                $probes[$key]['donetime'] = "Ready: -";
            }

            //Temperature
            $probes[$key]['rawtemperature'] = $probe['temperature'] ?? null;
            $probes[$key]['temperature']    = $probe['temperature'] ?? "-";
            $probes[$key]['temperature']    .= $temperatureUnit;

        }

        $data['probes'] = $probes;
 
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json');

    });

    $group->get('/probes/dashboard', function ($request, $response, array $args) {

        $serialNumber    = $this->get('serialNumber');
        $settings        = $this->get('settings');
        $temperatureUnit = $this->get('temperatureUnit');

        $timezone = new DateTimeZone($settings['timezone']);
        $now = new DateTime("now");
        $now->setTimezone($timezone);

        //The maximum time back in minutes to compare all timestamps.
        $maxHistory = $now->getTimestamp() - (60 * $settings['liveMinutes']);

        $apiTemperatures = json_decode($this->get('api')->get("/grills/{$serialNumber}/graphs", ['timeout' => 10.0])->getBody(), true);

        $temperatures = [];
        
        foreach ($apiTemperatures as $apiTemperature) 
        {
            //Get the last n minutes. First we slice because all values are per minute. Later on we check if the minutes are not 
            //too long back
            $lastTemperatures = array_slice($apiTemperature['graphData'], $settings['liveMinutes'] * -1, $settings['liveMinutes']);
            
            $probeTemperatures = [];

            foreach ($lastTemperatures as $reading) 
            {
                $timestamp = new DateTime($reading['timestamp']);
                $timestamp->setTimezone($timezone);
                
                if($timestamp->getTimestamp() > $maxHistory)
                {
                    $probeTemperatures[] = [
                        'x' => $timestamp->format(DATE_ATOM),
                        'y' => $reading['temperature'],
                    ];
                }
            }

            $temperatures[$apiTemperature['probeIndex']] = [
                "name" => "Probe " . ($apiTemperature['probeIndex'] + 1),
                'data' => $probeTemperatures
            ];
        }

        sort($temperatures);

        $response->getBody()->write(json_encode(array_values($temperatures)));
        return $response
            ->withHeader('Content-Type', 'application/json');   
    });

    $group->get('/probes/currentsession', function ($request, $response, array $args) {

        $serialNumber    = $this->get('serialNumber');
        $settings        = $this->get('settings');
        $temperatureUnit = $this->get('temperatureUnit');

        $timezone = new DateTimeZone($settings['timezone']);
        $now = new DateTime("now");
        $now->setTimezone($timezone);

        try {   
            $session = json_decode((string) $this->get('api')->get("/grills/{$serialNumber}/sessions/current", ['timeout' => 10.0])->getBody(), true);
        } catch (\Throwable $th) {
            throw new HttpNotFoundException($request);
        }

        $sessionStart = new DateTime($session['timeCreated']);
        $sessionStart->setTimezone($timezone);

        /*
            The maximum time back in minutes when the session started. Since we have an array with a value per 
            minute we can do a quick slice without having to compare all the dates since we need a reading anyway 
            for each minute since the session started.
        */

        $maxHistory = (int) (($now->getTimestamp() - $sessionStart->getTimestamp()) / 60);

        $apiTemperatures = json_decode($this->get('api')->get("/grills/{$serialNumber}/graphs", ['timeout' => 10.0])->getBody(), true);

        $temperatures = [];
        
        foreach ($apiTemperatures as $apiTemperature) 
        {
            if(!in_array($apiTemperature['probeIndex'], $session['probesIncluded']))
            {
                //If the probe is not included in the session just continue. No need to wast precious cycles
                continue;
            }

            $lastTemperatures = array_slice($apiTemperature['graphData'], $maxHistory * -1, $maxHistory);
            
            $probeTemperatures = [];

            foreach ($lastTemperatures as $reading) 
            {
                $timestamp = new DateTime($reading['timestamp']);
                $timestamp->setTimezone($timezone);
                
                $probeTemperatures[] = [
                    'x' => $timestamp->format(DATE_ATOM),
                    'y' => $reading['temperature'],
                ];
            }

            $temperatures[$apiTemperature['probeIndex']] = [
                "name" => "Probe " . ($apiTemperature['probeIndex'] + 1),
                'data' => $probeTemperatures
            ];
        }

        sort($temperatures);

        $response->getBody()->write(json_encode(array_values($temperatures)));
        return $response
            ->withHeader('Content-Type', 'application/json');   
    });

});

$app->run();