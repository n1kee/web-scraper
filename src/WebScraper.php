<?php

namespace WebScraperBundle;
use Symfony\Component\DependencyInjection\Container;
use FilesBundle\Image;
use FilesBundle\Helper\FileSystem;
use FasterImage\FasterImage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A class for web scraping.
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
     * Get's a web scraper for working with images.
     *
     * @return ImageScraper
     */
    function getImages() {
        return new ImageScraper($this->container, $this->httpClient);
    } 
}

class ImageScraper {

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
     * @return ImageScraper
     */
    function setHtml(string $html) {
        $this->html = $html;
        return $this;
    }

    /**
     * Set's an URL for scraping.
     *
     * @param string $url A URL for scraping.
     * @return ImageScraper
     */
    function setUrl(string $url) {
        $this->url = $url;
        return $this;
    }

    /**
     * Set's a minimum with of images to be downloaded.
     *
     * @param float $minWidth Minimum image width.
     * @return ImageScraper
     */
    function setMinWidth(float $minWidth) {
        $this->minWidth = $minWidth;
        return $this;
    }

    /**
     * Set's minimum height of images to be downloaded.
     *
     * @param float $minHeight Minimum image height.
     * @return ImageScraper
     */
    function setMinHeight(float $minHeight) {
        $this->minHeight = $minHeight;
        return $this;
    }

    /**
     * Filters images URL's.
     *
     * @param array $imgUrls Array of URL's to be filtered.
     * @return array List of filtered URL's.
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
     * @return array List of URL's.
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

        # Download images asynchronously.
        $images = array_map(function($response) {
            $imgContent = $response->getContent();
            $img = new Image();
            $img->readImageBlob($imgContent);
            return $img;
        }, $imagesResponses);

        return array_values($images);
    }
}