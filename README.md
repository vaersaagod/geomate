# GeoMate plugin for Craft CMS 3.x

GeoMate is a friend in need for all things geolocation. IP to geo lookup, automatic 
redirects (based on country, continent, language, etc), site switcher... You name it.  

![Screenshot](resources/img/plugin-logo.png)


## Requirements

This plugin requires Craft CMS 3.0.0 or later. The plugin also requires the zlib PHP extension.

## Installation

To install the plugin, either install it from the plugin store, or follow these instructions:

1. Install with composer via `composer require vaersaagod/geomate` from your project directory.
2. Install the plugin in the Craft Control Panel under Settings → Plugins, or from the command line via `./craft install/plugin geomate`.
3. For GeoMate to do anything, you need to [configure it](#configuring), and [download the geolocation database](#downloading-the-geolocation-database).

---

## GeoMate Overview

GeoMate helps you detect the location and language preferences of you visitors, and lets you set up
fine-grained rules to help you redirect users to the correct site, or show location/language specific 
information in your templates.

GeoMate relies on self-hosted Maxmind GeoIP2 databases for geolocation, and no external services 
are needed to look up IP information. By default GeoMate use the free Maxmind GeoLite2
database, but can easily be configured to use commercial versions of the database as long as it's 
in the MaxMind DB file format. 

---

## Downloading the geolocation database

GeoMate comes with a handy utility that helps you download the database. After installing you can
access it by going to Utilities > GeoMate from the control panel main menu. Please check that the 
settings is as desired, and download the databases by clicking the "Update now" button.

You can also download the database by accessing the `geomate/database/update-database` controller 
action directly, or set up a cron job that hits it at regular intervals. The action URL for your 
installation is shown in the utility.

---

## Using GeoMate

You can get information about the user's location through the `craft.geomate.country`, `craft.geomate.countryCode`
and `craft.geomate.city` [template variables](#template-variables). 

By configuring the `redirectMap` config setting, you can define the rules for what location
or language information is required for each of your sites. If you want to automatically redirect your users
to the appropriate site, you can enable the `autoRedirectEnabled` config setting, or you can use the 
`craft.geomate.redirectInformation` template variable to get the information inside your templates, 
and display a banner or popup to trigger the user to switch site.

GeoMate also provides a helper to build a site switcher, the `craft.geomate.getSiteLinks` template variable, 
and some twig functions, `addOverrideParam` and `addRedirectParam`, to add the necessary parameters to ensure 
that GeoMate picks up that the user has selected a spesific site.

There's quite a few config settings that can be used to tweak stuff, so make sure you read through
it to get an idea of what the defaults are, and how you can use them to your needs.

_When working locally, you need to override the IP by using the `forceIp` config setting for
GeoMate to do anything useful (since it would try to look up 127.0.0.1 if you didn't, and
that won't return any results)._

---

## Configuring

GeoMate can be configured by creating a file named `geomate.php` in your Craft config folder, 
and overriding settings as needed. 

### cacheEnabled [bool]
*Default: `true`*  
Enables or disables caching of IP data.

### cacheDuration [string|int]
*Default: `P7D`*  
Duration that looked up IP data should be cached, set as a date interval string or an int indicating the number of seconds.

### useSeparateLogfile [bool]
*Default: `true`*  
When enabled, GeoMate will create and use its own log file named `geomate.log` in Craft's log path.

### logLevel [int]
*Default: `\yii\log\Logger::LEVEL_ERROR`*  
When using GeoMate's log file (ie `useSeparateLogfile` being set to `true`), you can specify what log levels
should be logged. By default, only errors will be logged, but if you set it to `\yii\log\Logger::LEVEL_WARNING` 
or  `\yii\log\Logger::LEVEL_INFO` you'll get more information. 

### dbPath [string]
*Default: `''`*  
Path to GeoIP databases. If none is given (default), the database will be
stored in `/storage/geomate` or whichever path is defined as Craft's storage path. 

### countryDbFilename [string]
*Default: `'GeoLite2-Country.mmdb'`*  
File name of the GeopIP _country_ database.

### cityDbFilename [string]
*Default: `'GeoLite2-City.mmdb'`*  
File name of the GeopIP _city_ database.

### countryDbDownloadUrl [string]
*Default: `'http://geolite.maxmind.com/download/geoip/database/GeoLite2-Country.mmdb.gz'`*  
Download URL for the GeopIP _country_ database.

### cityDbDownloadUrl [string]
*Default: `'http://geolite.maxmind.com/download/geoip/database/GeoLite2-City.mmdb.gz'`*  
Download URL for the GeopIP _city_ database.

### downloadDbIfMissing [bool]
*Default: `false`*  
If a given database is missing when GeoMate tries to do an IP lookup, it'll 
fail silently, log the error, and not do a redirect or return any redirect information. 
If you enable this setting, GeoMate will try to download and unpack the database
if it is missing. 

Make sure you're certain that download works before enabling this. If something goes 
wrong during the download, GeoMate will continue to try on every request, which could 
take up alot of resources depending on what fails.  

### autoRedirectEnabled [bool]
*Default: `false`*  
Set this to `true` to enable automatic redirects of users to sites based on the `redirectMap` config setting.

### redirectMap [array]
*Default: `[]`*  
This powerful config setting enables you to create detailed rules for redirecting users to 
your different sites, based on detected information about location or language.

The easiest way to use this setting is to map site handles to country codes:

``` 
'redirectMap' => [
    'norwegian' => 'no',
    'swedish' => 'se',
    'global' => '*' 
]
```

In this example, there are three sites with handles `norwegian`, `swedish` and `global`. Visitors
from Norway is redirected to the norwegian site, visitors from Sweden are redirected to the
swedish site, and the rest is sent to the global site.

By default, it's assumed that the value is the detected country code. If `redirectMapSimpleModeKey`
is set to `language` though, the users browser language is used.

But, you can also use more advanced rules:

```
'redirectMap' => [
    'norwegian' => [
        'country' => 'no',
    ],
    'eu' => [
        'continent' => 'eu',
        'isInEuropeanUnion' => true
    ],
    'europe' => [
        'continent' => 'eu',
    ],
    'us' => [
        'country' => 'us'
    ],    
    'global' => '*' 
]
```

The rules are parsed top to bottom, and _the first match is used_. So swapping the order of 
`eu` and `europe` i the above example would make every visitor from europe go to the site
with handle `europe`.

If you don't add a site with a wildcard rule (ie `'global' => '*'`), _the visitor will not
get redirected from the site they landed on if no other rule matched_. 

You can also use the detected browser language when setting up your rules:

```
'redirectMap' => [
    'norsk' => [
        'language' => 'no',
    ],
    'us' => [ // matches 'en-US'
        'language' => 'en',
        'languageRegion' => 'us',
    ],
    'canada' => [ // matches 'en-CA'
        'language' => 'en',
        'languageRegion' => 'ca',
    ],
    'english' => [ // matches all english language codes, 'en', 'en-US', 'en-NZ', etc.
        'language' => 'en',
    ],
]
```
  
You can even use combinations of geolocation and language information, although 
that might get a bit... edge-case:

``` 
/*
 * We have this very special site that we only want to redirect
 * people to if they're located in Norway, but have a browser
 * with jamaican english as their preferred language (yeah, our 
 * site is all about norwegian reggea and jerk chicken).
 */
'redirectMap' => [   
    'special' => [
        'country' => 'no',
        'language' => 'en',
        'languageRegion' => 'jm'
    ],
    'normal' => '*' 
]
```

Please note that this setting is not only used when `autoRedirectEnabled` is set to `true`, but also
when you use `craft.geomate.redirectInformation`. 

### redirectMatchingElementOnly [bool]
*Default: `false`*  
When set to `true`, matching based on the `redirectMap` will only happen if the request has
a matched element, and that element is available in the matched site. When set to `false`, the
user will be redirected to the root of the matched site, if a matching element could not be 
found.

### redirectMapSimpleModeKey [string]
*Default: `'country'`*  
When using the simple syntax for `redirectMap`, by default it's assumed that the value is a 
country code. When set to `'language'`, it will instead be matched with accepted languages.   

### redirectIgnoreBots [bool]
*Default: `true`*  
By default, bots will not be redirected. Disable this to also redirect bots (may impact SEO, so beware).

### redirectIgnoreAdmins [bool]
*Default: `true`*  
By default, admins will not be redirected. Disable this to also redirect admin users. 

### redirectIgnoreUserGroups [array]
*Default: `[]`*  
An array of user groups that should not be redirect. Example:

```
'redirectIgnoreUserGroups' => ['editors', 'subscribers'],
``` 

### redirectIgnoreUrlPatterns [array]
*Default: `[]`*  
An array of url patterns that should not be redirect. The patterns can use regexp, and matches 
towards the full path of the request. To do an exact match, you can prefix the patter with `=`. 

Example:

```
'redirectIgnoreUrlPatterns' => [
     // Matches '/robots.txt' directly
    '=/robots.txt', 

     // Matches anything that starts with '/dont-redirect/'
    '/^\/dont-redirect\//',

     //Matches a range of sitemap urls like '/sitemap.xml', '/no/sitemap.xml', '/sitemap_portfolio_1.xml', etc.
    '/^\/(no\/|en\/)*sitemap([\s\S])*\.xml$/',
],
```

### redirectOverrideCookieName [string]
*Default: `'GeoMateRedirectOverride'`*  
Name of the cookie that registers if a user has overridden the preferred site (via a site switcher 
for instance).

### cookieDuration [int]
*Default: `43200`*  
Duration of the cookies. 

### addGetParameterOnRedirect [bool]
*Default: `false`*  
By default, a session (flash) variable is set when a user is redirected, which is picked up by
GeoMate to detect if the user was redirected. In some cases, this is not ideal, for instance if 
the site is served through a front-side cache (Cloudflare, Varnish, or similar) and you want to
notify the user about being redirected. By enabling this parameter, a query string will be appended
to the URL instead.

### redirectOverrideParam [string]
*Default: `'__geom'`*  
Name of the query string parameter that is added to the URL if the user overrides site redirection. 

### redirectedParam [string]
*Default: `'__redir'`*  
Name of the query string parameter that is added to the URL if the user is redirected 
(and `addGetParameterOnRedirect` is `true`). 

### paramValue [string]
*Default: `'✪'`*  
Value of the query string parameters that GeoMate add.

### forceIp [null|string]
*Default: `null`*  
Force an IP to be used for geolocation lookup. In local environments, this needs to be set to a 
valid IP address for GeoMate to work, since your local IP won't return any results. Can also be used
to debug IP's from different locations.  

### fallbackIp [null|string]
*Default: `null`*  
You can supply a fallback IP that will be used if the supplied IP can't be found. It's probably a good
idea to _not_ use this, and instead implement some default functionality in your templates instead. 

### minimumAcceptLanguageQuality [int]
*Default: `80`*  
The `Accept-Language` header supplied by the browser may contain any number of languages, which all 
have a quality parameter (in this case, the range is from 0 to 100) that indicates how proficient 
the user is in these languages. This parameter indicates what quality level a language needs to have 
for GeoMate to consider it a valid language.  

---

## Template variables

### craft.geomate.country([ip=null])
Returns country information in the form of a `\GeoIp2\Model\Country` model. If no information is 
found, `null` will be returned.

_By default the IP address of the current request will be used, but you can also use the optional ip 
parameter to get information based on a specific IP._

### craft.geomate.countryCode([ip=null])
Returns the two-character country code as a `string`. If no information is found, `null` will be returned.

_By default the IP address of the current request will be used, but you can also use the optional ip 
parameter to get information based on a specific IP._

### craft.geomate.city([ip=null])
Returns country information in the form of a `\GeoIp2\Model\City` model. If no information is 
found, `null` will be returned.

_By default the IP address of the current request will be used, but you can also use the optional ip 
parameter to get information based on a specific IP._

### craft.geomate.redirectInformation([ip=null])
Returns redirect information based on your redirect configuration as a RedirectInfo model. This
information can be used to display information to the user about which site you think they should
visit, and let them switch if they want. Example:

```
{% set redirectInfo = craft.geomate.redirectInformation() %}

{% if redirectInfo %}
    <div class="popup">
        <p>
            You are currently visiting our {{ currentSite.name }} site. 
            <a href="{{ redirectInfo.url | addOverrideParam }}">Click here</a> to go to our {{ redirectInfo.site.name }} site.
        </p>
    </div>
{% endif %}
```  

_By default the IP address of the current request will be used, but you can also use the optional ip 
parameter to get information based on a specific IP._

### craft.geomate.isCrawler()
Returns `true` if the current request is from a crawler.

### craft.geomate.isRedirected()
Returns `true` if the current request was redirected.

### craft.geomate.isOverridden()
Returns `true` if the user has overridden the preferred site.

### craft.geomate.getSiteLinks()
Returns an array of objects containing sites and redirect URLs for each of them. Useful for
building site switchers, for instance like this:

```
{% set siteLinks = craft.geomate.getSiteLinks() %}
<nav>
    <ul>
        {% for siteLink in siteLinks %}
            <li><a href="{{ siteLink.url | addOverrideParam }}">{{ siteLink.site.name }}</a></li>
        {% endfor %}
    </ul>
</nav>
```

### craft.geomate.getLanguages()
Return an array of AcceptedLanguage models, containing information about the users preferred 
browser languages. 

```
{% set languages = craft.geomate.getLanguages() %}
{% for language in languages %}
    <p>
        Quality: {{ language.quality }}<br>
        Language: {{ language.language }}<br>
        Region: {{ language.region }}<br>
        Script: {{ language.script }}
    </p>
{% endfor %}
```

---

## Twig filters

### addOverrideParam
Adds the override param and value to an URL. 
*You should always add this when linking between your sites, for instance in a site switcher*.

### addRedirectParam
Adds the redirect param and value to an URL. Not really that useful, but it's there. :)

---

## Price, license and support

The plugin is released under the MIT license, meaning you can do what ever you want with it as long 
as you don't blame us. **It's free**, which means there is absolutely no support included, but you 
might get it anyway. Just post an issue here on github if you have one, and we'll see what we can do. 

## Changelog

See [CHANGELOG.MD](https://raw.githubusercontent.com/vaersaagod/geomate/master/CHANGELOG.md).

## Credits

Brought to you by [Værsågod](https://www.vaersaagod.no)

This product includes GeoLite2 data created by MaxMind, available from
[http://www.maxmind.com](http://www.maxmind.com).

Icon designed by [Freepik from Flaticon](https://www.flaticon.com/authors/freepik).
