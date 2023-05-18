<?php

namespace WebScraperBundle;
use Symfony\Component\DependencyInjection\Container;
use FilesBundle\Image;
use FilesBundle\Helper\FileSystem;
use FasterImage\FasterImage;

use Exception;

class WebScraper {

    private Container $container;

    /**
     * @param Container $container
     */
    function __construct(Container $container)
    {
        $this->container = $container;
    }

    function getImages() {
        return new ImageScrapper($this->container);
    } 
}

class BaseScrapper {
}

class ImageScrapper extends BaseScrapper {

    private string $html;
    private string $url = "";
    private int $minWidth = 0;
    private int $minHeight = 0;
    private Container $container;

    /**
     * @param Container $container
     */
    function __construct(Container $container)
    {
        $this->container = $container;
    }

    function setHtml(string $html) {
        $this->html = $html;
        return $this;
    }

    function setUrl(string $url) {
        $this->url = $url;
        return $this;
    }

    function setMinWidth(float $minWidth) {
        $this->minWidth = $minWidth;
        return $this;
    }

    function setMinHeight(float $minHeight) {
        $this->minHeight = $minHeight;
        return $this;
    }

    protected function filterImageUrls(array $imgUrls) {
        $client = new FasterImage;

        $batchResult = $client->batch($imgUrls);

        $imgUrls = array_filter($imgUrls, function($url)
            use ($batchResult) {

            $imageData = $batchResult[$url];
            list($imgWidth, $imgHeight) = $imageData['size'];
            if ($imgWidth < $this->minWidth) return false;
            if ($imgHeight < $this->minHeight) return false;

            return true;
        });
        
        return $imgUrls;
    }

    function getUrls() {
        $url = $this->url;
        $html = $this->html ?? file_get_contents($url);

        $imgRegex = $this->container
            ->getParameter("html_img_regex");
        $originRegex = $this->container
            ->getParameter("url_origin_regex");

        preg_match_all($imgRegex, $html, $matches);
        $origin = preg_match($originRegex, $url, $originMatches) ? reset($originMatches) : null;

        $imgSrcList = array_map(function($imgSrc) use ($url) {
            return FileSystem::resolveSrc($url, $imgSrc);
        }, $matches[1]);

        $imgUrls = array_unique($imgSrcList);

        return array_values($this->filterImageUrls($imgUrls));
    }

    function get() {

        $imgUrls = $this->getUrls();

        $images = array_map(function($imgUrl) {
            # For SVG
            # libmagickcore-6.q16-2-extra
            # potrace
            return new Image($imgUrl);
        }, $imgUrls);

        return array_values($images);
    }
}