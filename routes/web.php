<?php
/*
  |--------------------------------------------------------------------------
  | Application Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register all of the routes for an application.
  | It is a breeze. Simply tell Lumen the URIs it should respond to
  | and give it the Closure to call when that URI is requested.
  |
 */

$app->get('/', function () use ($app) {
    return $app->version();
});

$app->post(
    '/deploy/{projects}',
    function ($projects, \Illuminate\Http\Request $request) use ($app) {
        if (!getenv('HOME')) {
            putenv('HOME=' . base_path('home'));
        }
        $response = [];
        $base = realpath(base_path().'/..');
        foreach (explode(',', $projects) as $project) {
            try {
                $path = $base.'/'.$project;
                if(!file_exists($path)) {
                    continue;
                }
                chdir($path);
                $res = shell_exec('git pull 2>&1');
                $log = shell_exec('git log -1 2>&1');
                foreach (glob('.githooks/post-pull*') as $filename) {
                    if (is_executable($filename)) {
                        $log .= '> ' . $filename . "\n";
                        $log .= shell_exec($filename . ' 2>&1');
                    } elseif (substr($filename, -4) === ".php") {
                        $log .= '# ' . $filename . "\n";
                        runPHP($filename);
                    }
                }
                $response[] = [
                    "name" => $project,
                    "response" => $res,
                    "log" => explode("\n", $log),
                ];
            } catch (Exception $exception) {
                $response[] = [
                    "name" => $project,
                    "error" => $exception,
                ];
            }
        }
        return json_encode($response, JSON_PRETTY_PRINT);
    }
);

/**
 * Run a shell command
 * 
 * @param string $command
 * @param int $followTime
 */
function run($command, $followTime = 1000)
{
    echo "$command\n";
    $filename = tempnam();
    exec("$command >> $filename 2>&1 &");
    usleep($followTime);
    echo file_get_contents($filename);
}

/**
 * 
 * @param type $filename
 */
function runPHP($filename)
{
    ob_start();
    include($filename);
    $res = ob_get_contents();
    ob_end_clean();
    return $res;
}
