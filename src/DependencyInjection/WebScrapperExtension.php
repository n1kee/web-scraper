<?php

namespace WebScrapperBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class WebScrapperExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $containerBuilder)
    {var_dump('We\'re alive!');die;
        $loader = new YamlFileLoader(
            $containerBuilder,
            new FileLocator(__DIR__.'/../../config')
        );
        $loader->load('services.yaml');

        $this->addAnnotatedClassesToCompile([
            // you can define the fully qualified class names...
            // 'App\\Controller\\DefaultController',
            // ... but glob patterns are also supported:
            //'**Bundle\\Controller\\',

            // ...
        ]);
    }
}