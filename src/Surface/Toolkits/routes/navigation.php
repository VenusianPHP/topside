<?php

use Surface\Toolkits\Demo\Controllers\DemoHomeController;
use Surface\Toolkits\Navigation\ToolkitPageRoute as Route;

Route::get('/', [DemoHomeController::class, 'index']);