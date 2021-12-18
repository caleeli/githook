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

global $changed;
$changed = null;
global $lastCommit;
$lastCommit = 'HEAD@{1}';
global $logFile;

$app->get('/', function () use ($app) {
    return $app->version();
});

$app->post(
    '/deploy/{projects}',
    function ($projects, \Illuminate\Http\Request $request) use ($app) {
        global $lastCommit;
        global $logFile;
        global $changed;
        $changed = null;
        $payload = $request->input('payload') ? json_decode($request->input('payload'), true) : $request->all();
        $lastCommit = $payload['before'] ?? null;
        $logFile = uniqid() . '.txt';
        if (!getenv('HOME')) {
            putenv('HOME=' . base_path('home'));
        }
        //Load nvm
        if (!getenv('NVM_DIR')) {
            putenv('NVM_DIR=' . base_path('home/.nvm'));
            exec('[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"');
        }
        $response = [];
        $response[] = ['HOME' => getenv('HOME'), 'NVM_DIR' => getenv('NVM_DIR')];
        $base = realpath(base_path() . '/..');
        foreach (explode(',', $projects) as $project) {
            try {
                $path = $base . '/' . $project;
                if (!file_exists($path)) {
                    continue;
                }
                chdir($path);
                $res = shell_exec('git pull 2>&1');
                $log = shell_exec('git log -1 2>&1');
                foreach (glob('.githooks/post-pull*') as $filename) {
                    if (is_executable($filename)) {
                        $log .= '> ' . $filename . "\n";
                        $log .= shell_exec($filename . ' 2>&1');
                    } elseif (substr($filename, -4) === '.php') {
                        $log .= '# ' . $filename . "\n";
                        $log .= runPHP($filename);
                    }
                }
                $response[] = [
                    'name' => $project,
                    'response' => $res,
                    'log' => $log,
                ];
            } catch (Exception $exception) {
                $response[] = [
                    'name' => $project,
                    'error' => $exception->getMessage(),
                ];
                return response(formatResponse($response, JSON_PRETTY_PRINT), 401);
            }
        }
        return formatResponse($response, JSON_PRETTY_PRINT);
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
    global $logFile;
    echo "$command\n";
    $filename = base_path('public/log/' . $logFile);
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
    try {
        require $filename;
    } catch (Throwable $exception) {
        echo $exception->getMessage();
    }
    $res = ob_get_contents();
    ob_end_clean();
    return $res;
}

function formatResponse($response)
{
    $string = '';
    foreach ($response as $tag) {
        foreach ($tag as $name => $content) {
            $string .= '#' . $name . "\n";
            $string .= is_string($content) ? $content : json_encode($content);
            $string .= "\n";
        }
    }
    return $string;
}

function changed($lastCommit)
{
    return explode("\n", trim(shell_exec('git diff --name-only HEAD@{0} ' . $lastCommit . '~1 2>&1')));
}

/**
 * Return the string if a file inside array $paths have changed
 *
 * @param array $paths
 * @param string $string
 *
 * @return string
 */
function onchange(array $paths, $string)
{
    global $lastCommit;
    global $changed;
    if (!isset($changed)) {
        $changed = changed($lastCommit);
    }
    foreach ($paths as $path) {
        $length = strlen($path);
        foreach ($changed as $filename) {
            if (substr($filename, 0, $length) === $path) {
                return $string;
            }
        }
    }
    return '';
}

function getLastCommit()
{
    return trim(shell_exec('git rev-parse HEAD 2>&1'));
}

function email($email, $subject, $message)
{
    global $logFile;
    $logUrl = url('/log/' . basename($logFile));
    return 'php ' . __DIR__ . '/../artisan' . ' mail ' . escapeshellarg($email) . ' ' . escapeshellarg($subject) . ' ' . escapeshellarg($message) . ' ' . escapeshellarg($logUrl) . ';';
}
