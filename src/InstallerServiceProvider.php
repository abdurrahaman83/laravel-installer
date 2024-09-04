<?php
namespace Abdurrahaman\Installer;

use Illuminate\Support\ServiceProvider;

class InstallerServiceProvider extends ServiceProvider{

    public function register()
    {

    }

    public function boot()
    {
        require_once __DIR__ . '/../src/helpers/helper.php';
        $this->loadRoutesFrom(__DIR__ .'/routes/web.php');
        $this->loadViewsFrom(__DIR__ .'/views','installer');
    }

}
