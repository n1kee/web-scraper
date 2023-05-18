<?php

namespace WebScraperBundle;
use Symfony\Component\DependencyInjection\Container;
use FilesBundle\Image;
use FilesBundle\Helper\FileSystem;
use FasterImage\FasterImage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The class for web scraping.
 *
 */
class WebScraper {

    /**
     * @param Container $container
     */
    function __construct(
        protected Container $container,
        protected HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Get's a web scrapper for working with images.
     *
     * @return ImageScrapper
     */
    function getImages() {
        return new ImageScrapper($this->container, $this->httpClient);
    } 
}

class ImageScrapper {

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

    /**
     * Set's html of a web page for scraping.
     *
     * @param string $html
     * @return ImageScrapper
     */
    function setHtml(string $html) {
        $this->html = $html;
        return $this;
    }

    /**
     * Set's a URL for scraping.
     *
     * @param string $url A URL for scraping.
     * @return ImageScrapper
     */
    function setUrl(string $url) {
        $this->url = $url;
        return $this;
    }

    /**
     * Set's a minimum with of images to be downloaded.
     *
     * @param float $minWidth Minimum image width.
     * @return ImageScrapper
     */
    function setMinWidth(float $minWidth) {
        $this->minWidth = $minWidth;
        return $this;
    }

    /**
     * Set's minimum height of images to be downloaded.
     *
     * @param float $minHeight Minimum image height.
     * @return ImageScrapper
     */
    function setMinHeight(float $minHeight) {
        $this->minHeight = $minHeight;
        return $this;
    }

    /**
     * Filters images URL's.
     *
     * @param array $imgUrls Array of URL's to be sorted.
     * @return ImageScrapper
     */
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

    /**
     * Get's URL's of images from the web page.
     *
     * @return array URL's of images from the web page.
     */
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

    /**
     * Gets images from the web page.
     *
     * @return array Paths of the saved images.
     */
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