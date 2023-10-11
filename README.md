# ExpressionEngine Submit Sitemap Extension

An ExpressionEngine extension that pings a preselected list of search engines when the sitemap has been updated.

## Installation

1. Clone the repository to `/system/user/addons/submit_sitemap`. Note that the cloned working directory must be named `submit_sitemap`.
2. _Optional:_ From the working directory, run `composer install` to install asynchronous connection tools.
3. From the control panel's Addon menu, install the extension.

## Operation

The extension will run after channel entry save when entries are created or deleted. It will not run on entry update, as the site structure will not have changed.

By default, the extension will use php_curl and make a series of synchronous requests. If available, the extension will use async connection methods. Tool availability is defined by `composer.json` and is checked automatically at runtime.

To avoid accidental submission of dev sites, the extension will only run in production, currently defined as `https://will.illinois.edu` and matched against `site_url()` at runtime.

## Assumptions

- Sitemap uri is `/sitemap`.
- Sitemap is generated using the `{exp:channel:entries}` tag or similar.
- The predefined list of search engines is standard.
- Production site is `https://will.illinois.edu`.

## Dependencies

- ExpressionEngine v3+
- [Composer](https://getcomposer.org) _(optional)_

## Changelog

### 2.0.0

- update guzzle (breaking changes)

### 1.0.5

- update guzzle

### 1.0.4

- update guzzle
- update docs url

### 1.0.3

- update guzzle

### 1.0.2

- update guzzle

### 1.0.1

- Correct call to `site_url` config item.
