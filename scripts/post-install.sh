#!/usr/bin/env bash

set -e

composer require laravel/octane
php artisan install:api
php artisan octane:install --server=swoole
php artisan key:generate
npm install --save-dev chokidar
php artisan optimize:clear
php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --watch --poll
