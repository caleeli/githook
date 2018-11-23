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
                        $log .= runPHP($filename);
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
                    "error" => $exception->getMessage(),
                ];
                return response(json_encode($response, JSON_PRETTY_PRINT), 401);
            }
        }
        return json_encode($response, JSON_PRETTY_PRINT);
    }
);

/**
 * Run a shell command
 * 
 * @param string $command
 * @param boolean $parallel
 * @param int $followTime microseconds
 */
function run($command, $parallel = false, $followTime = 600000)
{
    echo "$command\n";
    $filename = public_path('log/' . uniqid() . '.txt');
    $filenameRun = tempnam('/tmp', 'run');
    $filenameDone = tempnam('/tmp', 'done');
    unlink($filename);
    file_exists($filenameDone) ? unlink($filenameDone) : null;
    file_put_contents($filenameRun,
        "#!/bin/bash\n$command\necho 'done' > $filenameDone");
    chmod($filenameRun, 0777);
    $t = microtime(true) + $followTime / 1000000;
    exec("$filenameRun > $filename 2>&1 & ");
    while (microtime(true) < $t) {
        usleep($parallel ? $followTime : 20000);
        clearstatcache();
        if ($parallel || file_exists($filenameDone)) {
            break;
        }
    }
    echo url('/log/' . basename($filename));
    file_exists($filenameRun) ? unlink($filenameRun) : null;
    file_exists($filenameDone) ? unlink($filenameDone) : null;
}

/**
 * 
 * @param type $filename
 */
function runPHP($filename)
{
    ob_start();
    require ($filename);
    $res = ob_get_contents();
    ob_end_clean();
    return $res;
}
