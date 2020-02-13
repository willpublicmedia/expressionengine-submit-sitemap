<?php

if (!defined('BASEPATH')) 
{
    exit ('No direct script access allowed.');
}

class Submit_sitemap_ext
{
    public $version;

    private $required_extensions = array(
        'submit_sitemaps' => array(
            'hook' => 'after_channel_entry_save',
            'priority' => 10
        )
    );

    private $ping_uri = 'ping?sitemap=';

    private $search_engines = array(
        'google' => 'https://google.com'
    );

    private $sitemap;

    function __construct()
    {
        $addon = ee('Addon')->get('submit_sitemap');
        $this->version = $addon->getVersion();
        $this->sitemap = $this->load_sitemap(site_url(), '/sitemap');
        $this->use_async = $this->test_async();
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

    public function submit_sitemaps($entry, $values)
    {
        foreach (array_values($this->search_engines) as $engine)
        {
            $this->ping_search_engine($engine, $this->sitemap);
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
                return $res->getStatusCode();
            },
            function (RequestException $err)
            {
                return $err->getMessage();
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
            return;
        }

        $response = $this->connect_async($submission_url, $sitemap_url);
        $response = $response->wait();
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
}