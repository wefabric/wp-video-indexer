<?php

namespace Wefabric\WPVideoIndexer\Providers;

use Wefabric\WPSupport\WPSupport;
use Wefabric\WPVideoIndexer\Hooks\VideoIndexerHook;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register(): void
    {
        WPSupport::getInstance()->addHooks([
            VideoIndexerHook::class,
        ]);
    }
}
