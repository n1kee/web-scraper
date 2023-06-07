<?php

namespace WebScraperBundle;

use Symfony\Component\DependencyInjection\Container;
use FilesBundle\Image;
use FilesBundle\Helper\FileSystem;
use FasterImage\FasterImage;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Amp\Http\Client\Request;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;

/**
 * A class for web scraping.
 */
class WebScraper
{
    /**
     * @param Container $container
     */
    public function __construct(
        protected string $cacheDir,
        protected Container $container,
        protected HttpClientInterface $httpClient,
        protected string $webpageCacheLifetime,
    ) {
    }

    /**
     * Get's a web scraper for working with images.
     *
     * @return ImageScraper
     */
    public function getImages()
    {
        return new ImageScraper(
            $this->cacheDir,
            $this->container,
            $this->httpClient,
            $this->webpageCacheLifetime,
        );
    }
}

class ImageScraper
{
    private string $html;
    private string $url = "";
    private int $minWidth = 0;
    private int $minHeight = 0;

    /**
     * @param Container $container
     */
    public function __construct(
        protected string $cacheDir,
        protected Container $container,
        protected HttpClientInterface $httpClient,
        protected string $webpageCacheLifetime,
    ) {
        $this->cache = new FilesystemAdapter();
    }

    /**
     * Set's html of a web page for scraping.
     *
     * @param string $html
     * @return ImageScraper
     */
    public function setHtml(string $html)
    {
        $this->html = $html;
        return $this;
    }

    /**
     * Set's an URL for scraping.
     *
     * @param string $url A URL for scraping.
     * @return ImageScraper
     */
    public function setUrl(string $url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Set's HTTP headers for the requests.
     *
     * @param string[] $headers List of HTTP headers.
     * @return ImageScraper
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Set's a minimum with of images to be downloaded.
     *
     * @param float $minWidth Minimum image width.
     * @return ImageScraper
     */
    public function setMinWidth(float $minWidth)
    {
        $this->minWidth = $minWidth;
        return $this;
    }

    /**
     * Set's minimum height of images to be downloaded.
     *
     * @param float $minHeight Minimum image height.
     * @return ImageScraper
     */
    public function setMinHeight(float $minHeight)
    {
        $this->minHeight = $minHeight;
        return $this;
    }

    /**
     * Filters images URL's.
     *
     * @param array $imgUrls Array of URL's to be filtered.
     * @return array List of filtered URL's.
     */
    protected function filterImageUrls(array $imgUrls)
    {
        $client = new FasterImage();

        $batchResult = $client->batch($imgUrls);

        $imgUrls = array_filter($imgUrls, function ($url) use ($batchResult) {

            $imageData = $batchResult[$url];
            list($imgWidth, $imgHeight) = $imageData['size'];
            if ($imgWidth < $this->minWidth) {
                return false;
            }
            if ($imgHeight < $this->minHeight) {
                return false;
            }

            return true;
        });

        return $imgUrls;
    }

    public function request(string | array $urlList, int $expiresAfter = null)
    {
        $result = [];

        if (!is_array($urlList)) {
            $urlList = [ $urlList ];
        }

        foreach ($urlList as $url) {
            $cacheKey = "web_scraper.{$url}";
            $cacheItem = $this->cache->getItem($cacheKey);
            $cachedValue = $cacheItem->get();
            $response = null;
            if (!$cachedValue) {
                $response = $this->httpClient
                    ->request('GET', $url, [
                        "headers" => $this->headers
                    ]);
            }

            $result []= function () use (
                $cacheItem,
                $cacheKey,
                $cachedValue,
                $response,
                $expiresAfter,
            ) {
                if ($cacheItem->isHit()) {
                    return $cachedValue;
                }
                $responseContent = $response->getContent();
                $cacheItem->set($cacheKey, $responseContent);
                if ($expiresAfter) {
                    $cacheItem->expiresAfter($expiresAfter);
                }
                return $responseContent;
            };
        }

        return $result;
    }

    /**
     * Get's URL's of images from the web page.
     *
     * @return array List of URL's.
     */
    public function getUrls()
    {
        $url = $this->url;
        $html = $this->html ?? $this->request($url, $this->webpageCacheLifetime)[0]();

        $imgRegex = $this->container
            ->getParameter("html_img_regex");
        $originRegex = $this->container
            ->getParameter("url_origin_regex");

        preg_match_all($imgRegex, $html, $matches);
        $origin = preg_match($originRegex, $url, $originMatches) ? reset($originMatches) : null;

        $imgSrcList = array_map(function ($imgSrc) use ($url) {
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
    public function get()
    {

        $imgUrls = $this->getUrls();

        $imagesResponses = $this->request($imgUrls);

        # Download images asynchronously.
        $images = array_map(function ($response) {
            $imgContent = $response();
            $img = new Image();
            $img->readImageBlob($imgContent);
            return $img;
        }, $imagesResponses);

        return array_values($images);
    }
}
