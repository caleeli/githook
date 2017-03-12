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
        $response = [];
        $base = realpath(base_path().'/..');
        $branch = @$request->json()->get('repository')['default_branch'];
        foreach (explode(',', $projects) as $project) {
            $path = $base.'/'.$project;
            chdir($path);
            $res = shell_exec('git pull');
            $response[] = [
                "name" => $project,
                "response" => $res,
            ];
        }
        return $response;
    }
);
