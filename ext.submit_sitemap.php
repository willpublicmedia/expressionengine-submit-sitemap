<?php

if (!defined('BASEPATH')) 
{
    exit ('No direct script access allowed.');
}

class Submit_sitemap_ext
{
    public $version;

    private $required_extensions = array(
        'ping_on_delete' => array(
            'hook' => 'after_channel_entry_delete',
            'priority' => 10
        ),
        'ping_on_new' => array(
            'hook' => 'after_channel_entry_insert',
            'priority' => 10
        )
    );

    private $ping_uri = 'ping?sitemap=';

    private $search_engines = array(
        'aol' => 'https://aol.com',
        'bing' => 'https://bing.com',
        'duckduckgo' => 'https://duckduckgo.com',
        'google' => 'https://google.com',
        'yahoo' => 'https://yahoo.com'
    );
    
    private $is_production;

    private $sitemap;

    function __construct()
    {
        $addon = ee('Addon')->get('submit_sitemap');
        $this->version = $addon->getVersion();
        $this->sitemap = $this->load_sitemap(site_url(), '/sitemap');
        $this->use_async = $this->test_async();
        $this->is_production = $this->test_production('https://will.illinois.edu');
    }

    public function activate_extension()
    {
        if (ee('Model')->get('Extension')->filter('class', __CLASS__)->count() > 0)
        {
            return;
        }

        foreach ($this->required_extensions as $method => $settings)
        {
            $data = array(
                'class' => __CLASS__,
                'method' => $method,
                'hook' => $settings['hook'],
                'priority' => $settings['priority'],
                'version' => $this->version,
                'settings' => '',
                'enabled' => 'y'
            );
            
            ee('Model')->make('Extension', $data)->save();
        }
    }

    public function disable_extension()
    {
        ee('Model')->get('Extension')->filter('class', __CLASS__)->delete();
    }

    public function ping_on_delete($entry, $values)
    {
        if ($this->is_production)
        {
            $this->submit_sitemaps();
        }
    }

    public function ping_on_new($entry, $values)
    {
        if ($this->is_production)
        {
            $this->submit_sitemaps();
        }
    }

    private function connect_async($search_url, $sitemap_url)
    {
        $client = new GuzzleHttp\Client([
            'base_uri' => $search_url
        ]);

        $promise = $client->requestAsync('GET', '/ping', [
            'query' => ['sitemap' => $sitemap_url]
        ]);

        $promise->then(
            function (ResponseInterface $res)
            {
                $status = $res->getStatusCode();
                if (((string)$status)[0] !== '2')
                {
                    ee('CP/Alert')->makeInline('sitemap-ping')
                        ->asAttention()
                        ->withTitle('Sitemap update issue')
                        ->addToBody("$search_url returned status $status.")
                        ->defer();
                }
            },
            function (RequestException $err)
            {
                $message = $err->getMessage();
                ee('CP/Alert')->makeInline('sitemap-ping')
                    ->asWarning()
                    ->withTitle('Sitemap update issue')
                    ->addToBody("$search_url failed with error message $message.")
                    ->defer();
            }
        );

        return $promise;
    }

    private function connect_as_curl($search_url, $ping_uri, $sitemap_url)
    {
        $url = $search_url . '/' . $ping_uri . $sitemap_url;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch);
        curl_close($ch);

        return $status;
    }

    private function load_sitemap($host, $uri)
    {
        $sitemap = rtrim($host, '/') . '/' . ltrim($uri, '/');
        $sitemap = rawurlencode($sitemap);

        return $sitemap;
    }

    private function ping_search_engine($submission_url, $sitemap_url)
    {
        if ($this->use_async === false)
        {
            $response = $this->connect_as_curl($submission_url, $this->ping_uri, $sitemap_url);
            return $response;
        }

        $response = $this->connect_async($submission_url, $sitemap_url);
        return $response;
    }

    private function submit_sitemaps()
    {
        $responses = [];
        foreach ($this->search_engines as $engine => $url)
        {
            $response = $this->ping_search_engine($url, $this->sitemap);
            $responses[$engine] = $response;
        }

        if ($this->use_async)
        {
            GuzzleHttp\Promise\settle(array_values($responses))->wait();
        }
        else
        {
            foreach ($responses as $engine => $response)
            {
                $status = $response['http_code'];
                if (((string)$status)[0] !== '2')
                {
                    ee('CP/Alert')->makeInline('sitemap-ping')
                        ->asAttention()
                        ->withTitle('Sitemap update issue')
                        ->addToBody("$engine returned status code $status on sitemap update.")
                        ->defer();
                }
            }
        }
    }

    private function test_async()
    {
        if (file_exists(__DIR__ . '/vendor/autoload.php'))
        {
            require_once(__DIR__ . '/vendor/autoload.php');
            return true;
        }
        
        return false;
    }

    private function test_production($production_url)
    {
        return rtrim($production_url, '/') === rtrim(site_url(), '/');
    }
}