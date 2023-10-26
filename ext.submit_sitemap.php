<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed.');
}

/**
 * Sitemap submission extension.
 */
class Submit_sitemap_ext
{
    /**
     * Extension version.
     */
    public $version;

    /**
     * Extension activation settings in extension-method => ee-extension-settings format.
     */
    private $required_extensions = array(
        'ping_on_delete' => array(
            'hook' => 'after_channel_entry_delete',
            'priority' => 10,
        ),
        'ping_on_new' => array(
            'hook' => 'after_channel_entry_insert',
            'priority' => 10,
        ),
    );

    private $ping_uri = 'ping?sitemap=';

    private $use_async = false;

    /**
     * Search engines to ping in name => url format.
     */
    private $search_engines = array(
        'aol' => 'https://aol.com',
        'bing' => 'https://bing.com',
        'duckduckgo' => 'https://duckduckgo.com',
        'google' => 'https://google.com',
        'yahoo' => 'https://yahoo.com',
    );

    /**
     * Is the current site running in production.
     */
    private $is_production;

    /**
     * Sitemap url in submissible format.
     */
    private $sitemap;

    public function __construct()
    {
        $addon = ee('Addon')->get('submit_sitemap');
        $this->version = $addon->getVersion();
        $this->sitemap = $this->load_sitemap(ee()->config->item('site_url'), '/sitemap');
        $this->is_production = $this->test_production('https://will.illinois.edu');
    }

    /**
     * Register extension methods with EE.
     */
    public function activate_extension()
    {
        if (ee('Model')->get('Extension')->filter('class', __CLASS__)->count() > 0) {
            return;
        }

        foreach ($this->required_extensions as $method => $settings) {
            $data = array(
                'class' => __CLASS__,
                'method' => $method,
                'hook' => $settings['hook'],
                'priority' => $settings['priority'],
                'version' => $this->version,
                'settings' => '',
                'enabled' => 'y',
            );

            ee('Model')->make('Extension', $data)->save();
        }
    }

    /**
     * Disable class extension methods.
     */
    public function disable_extension()
    {
        ee('Model')->get('Extension')->filter('class', __CLASS__)->delete();
    }

    /**
     * Ping search engines when channel entries are deleted and site is production.
     */
    public function ping_on_delete($entry, $values)
    {
        if ($this->is_production) {
            $this->submit_sitemaps();
        }
    }

    /**
     * Ping search engines when channel entries are created and site is in production.
     */
    public function ping_on_new($entry, $values)
    {
        if ($this->is_production) {
            $this->submit_sitemaps();
        }
    }

    /**
     * Update extension.
     */
    public function update_extension($current = '')
    {
        if ($current == '' or $current == $this->version) {
            return false;
        }

        ee()->db->where('class', __CLASS__);
        ee()->db->update(
            'extensions',
            array('version' => $this->version)
        );
    }

    /**
     * Ping search engines synchronously using php_curl.
     */
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

    /**
     * Prepare sitemap url for submission.
     */
    private function load_sitemap($host, $uri)
    {
        $sitemap = rtrim($host, '/') . '/' . ltrim($uri, '/');
        $sitemap = rawurlencode($sitemap);

        return $sitemap;
    }

    /**
     * Determine which connection method to use.
     */
    private function ping_search_engine($submission_url, $sitemap_url)
    {
        $response = $this->connect_as_curl($submission_url, $this->ping_uri, $sitemap_url);
        return $response;
    }

    /**
     * Submit sitemaps to search engines and process responses.
     */
    private function submit_sitemaps()
    {
        $responses = [];
        foreach ($this->search_engines as $engine => $url) {
            $response = $this->ping_search_engine($url, $this->sitemap);
            $responses[$engine] = $response;
        }

        foreach ($responses as $engine => $response) {
            $status = $response['http_code'];
            if (((string) $status)[0] !== '2') {
                ee('CP/Alert')->makeInline('sitemap-ping-' . $engine)
                    ->asAttention()
                    ->withTitle('Sitemap update issue')
                    ->addToBody("$engine returned status code $status on sitemap update.")
                    ->defer();
            }
        }
    }

    /**
     * Check whether the site's current url matches the expected production url.
     */
    private function test_production($production_url)
    {
        return rtrim($production_url, '/') === rtrim(ee()->config->item('site_url'), '/');
    }
}
