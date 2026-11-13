<?php

use Surface\Toolkits\Demo\DemoHomeController;
use Surface\Toolkits\Demo\KitchenSinkController;
use Surface\Toolkits\Navigation\ToolkitPageRoute as Route;

// The pages a new application opens with. An application's own routes/navigation.php is loaded
// after this file: a route of the same name there takes the place of the one here.

Route::get('/', [DemoHomeController::class, 'index']);
Route::get('/kitchen-sink', [KitchenSinkController::class, 'index']);
