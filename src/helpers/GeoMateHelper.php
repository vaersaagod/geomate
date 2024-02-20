<?php
/**
 * GeoMate plugin for Craft CMS 5.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\geomate\helpers;

use Craft;
use craft\base\Element;
use craft\helpers\App;
use craft\helpers\UrlHelper;
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
    public static function isCrawler(): bool
    {
        return (new CrawlerDetect())->isCrawler();
    }

    public static function isRedirected(): bool
    {
        /** @var Settings $settings */
        $settings = GeoMate::getInstance()->getSettings();
        return GeoMate::$isRedirected || (Craft::$app->getRequest()->getParam($settings->redirectedParam, '') !== '');
    }

    public static function isOverridden(): bool
    {
        /** @var Settings $settings */
        $settings = GeoMate::getInstance()->getSettings();
        return Craft::$app->getRequest()->getParam($settings->redirectOverrideParam, '') !== '';
    }

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

    public static function isAcceptedLanguage(array|string $languageCode, int $quality = 80): bool
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

    public static function isAcceptedLanguageRegion(array|string $languageCode, int $quality = 80): bool
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

    public static function getCurrentLinkForSite(Site $site, bool $elementMatchOnly = false): bool|null|string
    {
        // Get the site URL for the found site, this will be the fallback if we're not on an element's url
        $url = $elementMatchOnly ? null : $site->getBaseUrl();
        
        // Check if we're on an element's url, then prefer to redirect to that url
        /** @var Element $currentElement */
        $currentElement = Craft::$app->getUrlManager()->getMatchedElement();
        
        if ($currentElement && $currentElement->getUrl() !== null && $currentElement->getUrl() !== '') {
            $redirectElement = Craft::$app->getElements()->getElementById($currentElement->getId(), $currentElement::class, $site->id);

            if ($redirectElement && $redirectElement->enabled && $redirectElement->getEnabledForSite($site->id) && (method_exists($redirectElement, 'getUrl') && $redirectElement->getUrl() !== null && $redirectElement->getUrl() !== '')) {
                $url = $redirectElement->url;
            }
        }
        
        return App::parseEnv($url);
    }

    public static function addUrlParam(string $url, string $param, string $value): string
    {
        return UrlHelper::url($url, [$param => $value]);
    }

    public static function addQueryString(string $url, string $queryString): string
    {
        return UrlHelper::url($url, $queryString);
    }
}
