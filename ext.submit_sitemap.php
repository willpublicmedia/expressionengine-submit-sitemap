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

    function __construct()
    {
        $addon = ee('Addon')->get('submit_sitemap');
        $this->version = $addon->getVersion();
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

    public function submit_sitemap($entry, $values)
    {
        return;
    }
}