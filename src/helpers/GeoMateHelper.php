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
        return GeoMate::$isRedirected || (Craft::$app->getRequest()->getQueryParam($settings->redirectedParam, '') !== '');
    }

    public static function isOverridden(): bool
    {
        /** @var Settings $settings */
        $settings = GeoMate::getInstance()->getSettings();
        return Craft::$app->getRequest()->getQueryParam($settings->redirectOverrideParam, '') !== '';
    }

    public static function getLanguages(): array
    {
        $r = [];

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            try {
                $languages = AcceptLanguage::getLanguages(self::filterAcceptLanguageHeader($_SERVER['HTTP_ACCEPT_LANGUAGE']), 100);
            } catch (\Throwable) {
                // A malformed Accept-Language header is treated as no accepted languages
                return [];
            }

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
        // Get the site URL for the found site, this will be the fallback if we're not on an element's url.
        // Site::getBaseUrl() already resolves environment variables and aliases in the base URL.
        $url = $elementMatchOnly ? null : $site->getBaseUrl();

        // Check if we're on an element's url, then prefer to redirect to that url
        /** @var Element $currentElement */
        $currentElement = Craft::$app->getUrlManager()->getMatchedElement();

        if ($currentElement && $currentElement->getUrl() !== null && $currentElement->getUrl() !== '') {
            $redirectElement = Craft::$app->getElements()->getElementById($currentElement->getId(), $currentElement::class, $site->id);

            if ($redirectElement && $redirectElement->enabled && $redirectElement->getEnabledForSite($site->id) && (method_exists($redirectElement, 'getUrl') && $redirectElement->getUrl() !== null && $redirectElement->getUrl() !== '')) {
                // Element URLs are returned exactly as Craft builds them, they should not be parsed for environment variables
                $url = $redirectElement->getUrl();
            }
        }

        return $url;
    }

    public static function addUrlParam(string $url, string $param, string $value): string
    {
        return UrlHelper::url($url, [$param => $value]);
    }

    /**
     * @param string $url
     * @param array|string $queryString A query string, or an array of query params
     * @return string
     */
    public static function addQueryString(string $url, array|string $queryString): string
    {
        return UrlHelper::url($url, $queryString);
    }

    /**
     * Replaces the credentials in the given URLs (userinfo, and the values of sensitive query parameters
     * like `license_key`) with a placeholder wherever they occur in a text, for instance in an exception
     * message that includes a database download URL.
     *
     * @param string $text
     * @param array<string|null> $urls
     * @return string
     */
    public static function redactUrlCredentials(string $text, array $urls): string
    {
        $credentials = [];

        foreach ($urls as $url) {
            if (!is_string($url) || $url === '') {
                continue;
            }

            foreach (self::getUrlCredentials($url) as $credential) {
                $decoded = urldecode($credential);
                array_push($credentials, $credential, $decoded, rawurldecode($credential), rawurlencode($decoded), urlencode($decoded));
            }
        }

        $credentials = array_unique(array_filter($credentials, static fn(string $credential): bool => $credential !== ''));

        // Longest first, so a credential is never partially replaced by a shorter variant of it
        usort($credentials, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        return str_replace($credentials, str_repeat('•', 8), $text);
    }

    /**
     * Removes language tags that are longer than intl can parse (INTL_MAX_LOCALE_LEN) from an
     * Accept-Language header, since they make the parser throw. No browser sends tags that long.
     *
     * @param string $header
     * @return string
     */
    private static function filterAcceptLanguageHeader(string $header): string
    {
        $maxLength = defined('INTL_MAX_LOCALE_LEN') ? INTL_MAX_LOCALE_LEN : 156;

        $tags = array_filter(
            explode(',', $header),
            static fn(string $tag): bool => strlen(trim(explode(';', $tag, 2)[0])) <= $maxLength
        );

        return implode(',', $tags);
    }

    /**
     * Returns the credentials in a URL: the userinfo parts, and the values of query parameters with sensitive
     * names (according to Craft's `sensitiveKeywords`). A URL that can't be parsed is treated as a credential.
     *
     * @param string $url
     * @return string[]
     */
    private static function getUrlCredentials(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return [$url];
        }

        $credentials = [];

        if (isset($parts['user'])) {
            $credentials[] = $parts['user'];
        }

        if (isset($parts['pass'])) {
            $credentials[] = $parts['pass'];
        }

        if (!isset($parts['query']) || $parts['query'] === '') {
            return $credentials;
        }

        $security = Craft::$app->getSecurity();

        foreach (explode('&', $parts['query']) as $pair) {
            if (!str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);

            if ($value !== '' && $security->isSensitive(urldecode($key))) {
                $credentials[] = $value;
            }
        }

        return $credentials;
    }
}
