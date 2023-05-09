<?php

namespace WebScrapperBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class WebScrapperBundle extends AbstractBundle {
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/../config/services.yaml');
    
        #$definition = $builder
            #->autowire('aaa.web-scrapper', WebScrapper::class);
    }
}
