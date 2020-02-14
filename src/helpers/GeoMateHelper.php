<?php
/**
 * GeoMate plugin for Craft CMS 3.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\geomate\helpers;


use Craft;
use craft\base\Element;
use craft\models\Site;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Teto\HTTP\AcceptLanguage;
use vaersaagod\geomate\GeoMate;
use vaersaagod\geomate\models\AcceptedLanguage;
use vaersaagod\geomate\models\Settings;

/**
 * GeoMate Helper
 *
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class GeoMateHelper
{


    /**
     * @return bool
     */
    public static function isCrawler(): bool
    {
        $crawlerDetect = new CrawlerDetect();
        return $crawlerDetect->isCrawler();
    }

    /**
     * @return bool
     */
    public static function isRedirected(): bool
    {
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();
        return GeoMate::$isRedirected || (Craft::$app->getRequest()->getParam($settings->redirectedParam, '') !== '');
    }

    /**
     * @return bool
     */
    public static function isOverridden(): bool
    {
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();
        return Craft::$app->getRequest()->getParam($settings->redirectOverrideParam, '') !== '';
    }

    /**
     * @return array
     */
    public static function getLanguages(): array
    {
        $r = [];
        
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $languages = AcceptLanguage::getLanguages($_SERVER['HTTP_ACCEPT_LANGUAGE'], 100);
    
            foreach ($languages as $quality => $info) {
                $languageModel = new AcceptedLanguage();
                $languageModel->quality = (int)$quality;
                $languageModel->language = $info[0]['language'] ?? null;
                $languageModel->region = $info[0]['region'] ?? null;
                $languageModel->script = $info[0]['script'] ?? null;
    
                $r[] = $languageModel;
            }
        }

        return $r;
    }

    /**
     * @param string|array $languageCode
     * @param int $quality
     * @return bool
     */
    public static function isAcceptedLanguage($languageCode, $quality = 80): bool
    {
        $languages = self::getLanguages();

        /** @var AcceptedLanguage $language */
        foreach ($languages as $language) {
            if ($language->quality >= $quality) {
                if (\is_array($languageCode)) {
                    foreach ($languageCode as $singleLanguageCode) {
                        if (strtolower($language->language) === strtolower($singleLanguageCode)) {
                            return true;
                        }
                    }
                } elseif (strtolower($language->language) === strtolower($languageCode)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string|array $languageCode
     * @param int $quality
     * @return bool
     */
    public static function isAcceptedLanguageRegion($languageCode, $quality = 80): bool
    {
        $languages = self::getLanguages();

        /** @var AcceptedLanguage $language */
        foreach ($languages as $language) {
            if ($language->quality >= $quality) {
                if (\is_array($languageCode)) {
                    foreach ($languageCode as $singleLanguageCode) {
                        if (strtolower($language->region) === strtolower($singleLanguageCode)) {
                            return true;
                        }
                    }
                } elseif (strtolower($language->region) === strtolower($languageCode)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public static function getSiteLinks(): array
    {
        $r = [];
        $allSites = Craft::$app->getSites()->getAllSites();

        foreach ($allSites as $site) {
            $url = self::getCurrentLinkForSite($site);
            $r[] = ['site' => $site, 'url' => $url];
        }

        return $r;
    }

    /**
     * @param Site $site
     * @param bool $elementMatchOnly
     * @return bool|string
     */
    public static function getCurrentLinkForSite($site, $elementMatchOnly = false)
    {
        // Get the site URL for the found site, this will be the fallback if we're not on an element's url
        $url = $elementMatchOnly ? null : $site->baseUrl;

        // Check if we're on an element's url, then prefer to redirect to that url
        /** @var Element $currentElement */
        $currentElement = Craft::$app->getUrlManager()->getMatchedElement();

        if ($currentElement && isset($currentElement->url) && $currentElement->url !== '') {
            $redirectElement = Craft::$app->getElements()->getElementById($currentElement->id, \get_class($currentElement), $site->id);

            if ($redirectElement && $redirectElement->enabled && $redirectElement->enabledForSite && isset($redirectElement->url) && $redirectElement->url !== '') {
                $url = $redirectElement->url;
            }
        }

        return Craft::parseEnv($url);
    }

    /**
     * @param string $url
     * @param string $param
     * @param string $value
     * @return string
     */
    public static function addUrlParam($url, $param, $value): string
    {
        return $url . (strpos($url, '?') === false ? '?' : '&') . $param . '=' . $value;
    }

    /**
     * @param string $url
     * @param string $queryString
     * @return string
     */
    public static function addQueryString($url, $queryString): string
    {
        return $url . (strpos($url, '?') === false ? '?' : '&') . $queryString;
    }
}
