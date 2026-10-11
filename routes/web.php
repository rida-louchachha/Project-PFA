<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

Route::get('/{any}', function () {
    $index = public_path('spa/index.html');
    abort_unless(File::exists($index), 404);
    return Response::file($index);
})->where('any', '^(?!api).*$');
