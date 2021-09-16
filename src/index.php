<?php
ini_set('display_errors', 1); 
error_reporting(E_ALL);

//TODO multiple grilleye support via dropdown in nav?

//TODO clean up index php and start working with namespaces.

//TODO button to choose refresh every 1-5-10 seconds?
//TODO show max temp or temp range on chart?
//TODO click through on probe to show only that probe?

//TODO provide a edit session button

use DI\Container;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Slim\Factory\AppFactory;
use Slim\Flash\Messages;
use Slim\Routing\RouteCollectorProxy;
use Slim\Routing\RouteContext;

use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

use GuzzleHttp\Client;

require __DIR__ . '/../vendor/autoload.php';

//*  ██████╗ ██████╗ ███╗   ██╗████████╗ █████╗ ██╗███╗   ██╗███████╗██████╗ 
//* ██╔════╝██╔═══██╗████╗  ██║╚══██╔══╝██╔══██╗██║████╗  ██║██╔════╝██╔══██╗
//* ██║     ██║   ██║██╔██╗ ██║   ██║   ███████║██║██╔██╗ ██║█████╗  ██████╔╝
//* ██║     ██║   ██║██║╚██╗██║   ██║   ██╔══██║██║██║╚██╗██║██╔══╝  ██╔══██╗
//* ╚██████╗╚██████╔╝██║ ╚████║   ██║   ██║  ██║██║██║ ╚████║███████╗██║  ██║
//*  ╚═════╝ ╚═════╝ ╚═╝  ╚═══╝   ╚═╝   ╚═╝  ╚═╝╚═╝╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝                                                                         

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
        ]
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

//* ███╗   ███╗██╗██████╗ ██████╗ ██╗     ███████╗██╗    ██╗ █████╗ ██████╗ ███████╗
//* ████╗ ████║██║██╔══██╗██╔══██╗██║     ██╔════╝██║    ██║██╔══██╗██╔══██╗██╔════╝
//* ██╔████╔██║██║██║  ██║██║  ██║██║     █████╗  ██║ █╗ ██║███████║██████╔╝█████╗  
//* ██║╚██╔╝██║██║██║  ██║██║  ██║██║     ██╔══╝  ██║███╗██║██╔══██║██╔══██╗██╔══╝  
//* ██║ ╚═╝ ██║██║██████╔╝██████╔╝███████╗███████╗╚███╔███╔╝██║  ██║██║  ██║███████╗
//* ╚═╝     ╚═╝╚═╝╚═════╝ ╚═════╝ ╚══════╝╚══════╝ ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝
                                                                                
$twig = Twig::create(__DIR__ . '/views', ['cache' => false]);
$app->add(TwigMiddleware::create($app, $twig));

$app->add(
    function ($request, $next) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->get('flash')->__construct($_SESSION);

        return $next->handle($request);
    }
);

//*  ██████╗  █████╗  ██████╗ ███████╗███████╗
//*  ██╔══██╗██╔══██╗██╔════╝ ██╔════╝██╔════╝
//*  ██████╔╝███████║██║  ███╗█████╗  ███████╗
//*  ██╔═══╝ ██╔══██║██║   ██║██╔══╝  ╚════██║
//*  ██║     ██║  ██║╚██████╔╝███████╗███████║
//*  ╚═╝     ╚═╝  ╚═╝ ╚═════╝ ╚══════╝╚══════╝

//*   ___          _    _                      _ 
//*  |   \ __ _ __| |_ | |__  ___  __ _ _ _ __| |
//*  | |) / _` (_-< ' \| '_ \/ _ \/ _` | '_/ _` |
//*  |___/\__,_/__/_||_|_.__/\___/\__,_|_| \__,_|

$app->get('/', function (Request $request, Response $response, $args) {

    $serialNumber    = $this->get('serialNumber');
    $settings        = $this->get('settings');
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
        'grill'    => $grill,
        'probes'   => $probes,
        'session'  => $session,
        'settings' => $settings,
        'colors'   => array_values($settings['probeColors']),
        'alert'    => $this->get('flash')->getFirstMessage('alert')
    ]);
})->setName('dashboard');

//*   ___            _             
//*  / __| ___ _____(_)___ _ _  ___
//*  \__ \/ -_|_-<_-< / _ \ ' \(_-<
//*  |___/\___/__/__/_\___/_||_/__/

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
        $queryString = "page={$page}&perPage={$perPage}&fromDate={$fromDate}&toDate={$toDate}";

        foreach ($filters as $filter) 
        {
            if(in_array($filter, array_keys($meatTypes)))
            {
                $queryString .= "&meatType={$filter}";
            }
        }

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
        
        //TODO Pagination

        return $view->render($response, 'sessions.twig', [
            'filters'         => $filters,
            'sessions'        => $sessions['data'],
            'totalSessions'   => $sessions['totalElements'],
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
            'session'         => $session,
            'meatTypes'       => $meatTypes,
            'eventTypes'      => $eventTypes,
            'temperatures'    => $temperatures,
            'colors'          => $colors
        ]);
    });

});

//*   ___                 _      
//*  | _ \_ _ ___ ___ ___| |_ ___
//*  |  _/ '_/ -_|_-</ -_)  _(_-<
//*  |_| |_| \___/__/\___|\__/__/

$app->group('/presets', function (RouteCollectorProxy $group) {
    
    $group->get('', function (Request $request, Response $response, $args) {
    
        $view = Twig::fromRequest($request);
        return $view->render($response, 'presets.twig', [
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

//*   ___      _   _   _              
//*  / __| ___| |_| |_(_)_ _  __ _ ___
//*  \__ \/ -_)  _|  _| | ' \/ _` (_-<
//*  |___/\___|\__|\__|_|_||_\__, /__/
//*                          |___/    

$app->map(['GET', 'POST'], '/settings', function (Request $request, Response $response, $args) {
    $alert = [];
    
    if($request->getMethod() == 'POST')
    {
        $data = $request->getParsedBody();
     
        try {
            //Check if the phone id works If not throw an alert
            $settings = json_decode((string) $this->get('api')->get('/phones/settings', [
                'headers' => [
                    "phone-id" => $data['localsettings']['phone-id']
                ]
            ])->getBody(), true);
        } catch (\Throwable $th) {

            $view = Twig::fromRequest($request);
            return $view->render($response, 'settings.twig', [
                'localsettings'    => $data['localsettings'],
                'grilleyesettings' => [],
                'alert'            => [
                    'type' => 'danger', 
                    'message' => 'Phone ID is incorrect. Please verify'
                ]
            ]);
        }

        //Save the local settings to the .env file
        file_put_contents(__DIR__. '/../.env', str_replace("  ", "", "
            PHONEID=\"{$data['localsettings']['phone-id']}\" 
            TIMEZONE=\"{$data['localsettings']['timezone']}\"
            LIVEMINUTES={$data['localsettings']['liveMinutes']}
            TIMEFORMAT=\"{$data['localsettings']['timeformat']}\"
            PROBE_COLOR_1=\"{$data['localsettings']['probeColors'][1]}\"
            PROBE_COLOR_2=\"{$data['localsettings']['probeColors'][2]}\"
            PROBE_COLOR_3=\"{$data['localsettings']['probeColors'][3]}\"
            PROBE_COLOR_4=\"{$data['localsettings']['probeColors'][4]}\"
            PROBE_COLOR_5=\"{$data['localsettings']['probeColors'][5]}\"
            PROBE_COLOR_6=\"{$data['localsettings']['probeColors'][6]}\"
            PROBE_COLOR_7=\"{$data['localsettings']['probeColors'][7]}\"
            PROBE_COLOR_8=\"{$data['localsettings']['probeColors'][8]}\"
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
        $settings = json_decode((string) $this->get('api')->get('/phones/settings')->getBody(), true);
    } catch (\Throwable $th) {
        $settings = [];
    }

    $view = Twig::fromRequest($request);
    return $view->render($response, 'settings.twig', [
        'localsettings'    => $this->get('settings'),
        'grilleyesettings' => $settings,
        'alert'            => $this->get('flash')->getFirstMessage('alert')
    ]);

})->setName('settings');


//*   ____            _               
//*  |  _ \ _ __ ___ | |__   ___  ___ 
//*  | |_) | '__/ _ \| '_ \ / _ \/ __|
//*  |  __/| | | (_) | |_) |  __/\__ \
//*  |_|   |_|  \___/|_.__/ \___||___/
                                  
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
        'sortedpresets' => $this->get('presets'),
        'probes'        => $probes,
    ]);

})->setName('settings');                                                

//*   █████╗ ██████╗ ██╗
//*  ██╔══██╗██╔══██╗██║
//*  ███████║██████╔╝██║
//*  ██╔══██║██╔═══╝ ██║
//*  ██║  ██║██║     ██║
//*  ╚═╝  ╚═╝╚═╝     ╚═╝

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

        $session = json_decode((string) $this->get('api')->get("/grills/{$serialNumber}/sessions/current", ['timeout' => 10.0])->getBody(), true);
        
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