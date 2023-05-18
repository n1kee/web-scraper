<?php

namespace WebScraperBundle;
use Symfony\Component\DependencyInjection\Container;
use FilesBundle\Image;
use FilesBundle\Helper\FileSystem;
use FasterImage\FasterImage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use Exception;

class WebScraper {

    /**
     * @param Container $container
     */
    function __construct(
        protected Container $container,
        protected HttpClientInterface $httpClient,
    ) {
    }

    function getImages() {
        return new ImageScrapper($this->container, $this->httpClient);
    } 
}

class BaseScrapper {
}

class ImageScrapper extends BaseScrapper {

    private string $html;
    private string $url = "";
    private int $minWidth = 0;
    private int $minHeight = 0;

    /**
     * @param Container $container
     */
    function __construct(
        protected Container $container,
        protected HttpClientInterface $httpClient,
    ) {
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

        $imagesResponses = array_map(function($imgUrl) {
            return $this->httpClient->request('GET', $imgUrl);
        }, $imgUrls);

        $images = array_map(function($response) {
            $imgContent = $response->getContent();
            $img = new Image();
            $img->readImageBlob($imgContent);
            return $img;
        }, $imagesResponses);

        return array_values($images);
    }
}