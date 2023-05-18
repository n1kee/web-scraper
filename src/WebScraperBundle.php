<?php

namespace WebScraperBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * A bundle for web scraping.
 *
 */
class WebScraperBundle extends AbstractBundle {
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/../config/services.yaml');
    
        #$definition = $builder
            #->autowire('aaa.web-scraper', WebScraper::class);
    }
}
