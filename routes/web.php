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
        //Load nvm
        if (!getenv('NVM_DIR')) {
            putenv('NVM_DIR=' . base_path('home/.nvm'));
            exec('[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"');
        }
        echo "HOME: ",getenv('HOME'),"\n";
        echo "NVM_DIR: ",getenv('NVM_DIR'),"\n";
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
 * @param int $followTime microseconds
 */
function run($command)
{
    echo "$command\n";
    $filename = base_path('public/log/' . uniqid() . '.txt');
    $filenameRun = tempnam('/tmp', 'run');
    $nvmLoader = '[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"';
    file_put_contents($filenameRun, "#!/bin/bash\n$nvmLoader\n$command");
    chmod($filenameRun, 0777);
    exec("$filenameRun > $filename 2>&1 &");
    echo url('/log/' . basename($filename)), "\n";
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

function formatResponse($response)
{
    foreach($response as $tag) {
        echo $tag['name'], "\n";
        echo 
    }
}