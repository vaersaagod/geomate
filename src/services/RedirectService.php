<?php
/**
 * GeoMate plugin for Craft CMS 4.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2022 Værsågod
 */

namespace vaersaagod\geomate\services;

use Craft;
use craft\base\Component;
use craft\errors\SiteNotFoundException;

use GeoIp2\Model\Country;

use vaersaagod\geomate\GeoMate;
use vaersaagod\geomate\helpers\GeoMateHelper;
use vaersaagod\geomate\models\RedirectInfo;
use vaersaagod\geomate\models\Settings;

use yii\base\InvalidConfigException;
use yii\log\Logger;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 */
class RedirectService extends Component
{
    // Public Methods
    // =========================================================================

    /**
     *
     */
    public function autoRedirect()
    {
        $redirectInfo = $this->getRedirectInfo();
        if (empty($redirectInfo)) {
            return;
        }

        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();
        if (!$settings->addGetParameterOnRedirect) {
            Craft::$app->getSession()->setFlash('geomateIsRedirected');
        }

        Craft::$app->getResponse()->redirect(
            $settings->addGetParameterOnRedirect ?
                GeoMateHelper::addUrlParam($redirectInfo->url, $settings->redirectedParam, $settings->paramValue) :
                $redirectInfo->url
        );
    }

    /**
     * @param null|string $ip
     * @param null|array $customMap
     * @return null|RedirectInfo
     */
    public function getRedirectInfo($ip = null, $customMap = null)
    {
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();

        // Ignore because... crawler?
        if ($settings->redirectIgnoreBots && GeoMateHelper::isCrawler()) {
            GeoMate::log('Redirect ignored because a crawler was detected.', Logger::LEVEL_INFO);
            return null;
        }

        // Ignore because... user?
        if ($this->shouldIgnoreUser()) {
            GeoMate::log('Redirect ignored because of user credentials (user is either an admin or in a usergroup that was configured to be ignored).', Logger::LEVEL_INFO);
            return null;
        }

        // Ignore because... url?
        if ($this->shouldIgnoreUrl()) {
            GeoMate::log('Redirect ignored because URL matched ignored pattern.', Logger::LEVEL_INFO);
            return null;
        }
        
        $redirectMap = $customMap ?: $settings->redirectMap;
        $isOverridden = false;

        if ($overrideCookie = GeoMate::$plugin->cookie->getOverrideCookie()) {
            $applicableSite = Craft::$app->getSites()->getSiteByHandle($overrideCookie);
            $isOverridden = true;
        } else {
            $applicableSiteHandle = $this->getSiteHandleFromRedirectMap($redirectMap, $ip);

            if ($applicableSiteHandle === null) {
                return null;
            }

            $applicableSite = Craft::$app->getSites()->getSiteByHandle($applicableSiteHandle);
        }

        try {
            $currentSite = Craft::$app->getSites()->getCurrentSite();
        } catch (SiteNotFoundException) {
            return null;
        }

        if (!$applicableSite) {
            return null;
        }

        if ($currentSite->handle === $applicableSite->handle) {
            return null;
        }

        $redirectUrl = GeoMateHelper::getCurrentLinkForSite($applicableSite, $settings->redirectMatchingElementOnly);

        if (!$redirectUrl || empty($redirectUrl)) {
            return null;
        }
        
        $queryString = Craft::$app->getRequest()->getQueryStringWithoutPath();
        
        if ($queryString !== '') {
            $redirectUrl = GeoMateHelper::addQueryString($redirectUrl, $queryString);
        }

        $info = new RedirectInfo();
        $info->site = $applicableSite;
        $info->url = $redirectUrl;
        $info->isOverridden = $isOverridden;

        return $info;
    }

    /**
     * @param string $val
     */
    public function addOverrideParam($val): string
    {
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();
        return GeoMateHelper::addUrlParam($val, $settings->redirectOverrideParam, $settings->paramValue);
    }

    /**
     * @param string $val
     */
    public function addRedirectParam($val): string
    {
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();
        return GeoMateHelper::addUrlParam($val, $settings->redirectedParam, $settings->paramValue);
    }

    /**
     *
     */
    public function registerOverride()
    {
        try {
            $currentSite = Craft::$app->getSites()->getCurrentSite();
            GeoMate::$plugin->cookie->setOverrideCookie($currentSite);
        } catch (SiteNotFoundException) {
        }
    }


    // Private Methods
    // =========================================================================
    /**
     * @param array $redirectMap
     * @param string|null $ip
     * @return string|null
     */
    private function getSiteHandleFromRedirectMap(array $redirectMap, ?string $ip = null): ?string
    {
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();

        foreach ($redirectMap as $siteHandle => $criteria) {
            $isApplicable = true;

            if (\is_string($criteria) && $criteria !== '*') { // assume this is country code or language
                $criteriaVal = $criteria;
                $criteria = [];
                $criteria[$settings->redirectMapSimpleModeKey] = $criteriaVal;
            }

            $countryInfo = false;

            if (\is_array($criteria)) {
                foreach ($criteria as $criteriaKey => $criteriaVal) {

                    $needsCountryInfo = !in_array($criteriaKey, ['language', 'languageRegion']);

                    if ($needsCountryInfo && $countryInfo === false) {
                        $countryInfo = GeoMate::$plugin->geo->getCountryInfo($ip);
                    }

                    if ($needsCountryInfo && !$countryInfo instanceof Country) {
                        $isApplicable = false;
                        GeoMate::log("Unable to match \"$criteriaKey\" due to missing or invalid GeoIP2 database", Logger::LEVEL_WARNING);
                        continue;
                    }

                    switch ($criteriaKey) {
                        case 'country':
                            if (\is_array($criteriaVal)) {
                                if (!\in_array(strtolower($countryInfo->country->isoCode), $criteriaVal, true)) {
                                    $isApplicable = false;
                                }
                            } elseif (strtolower($countryInfo->country->isoCode) !== strtolower($criteriaVal)) {
                                $isApplicable = false;
                            }

                            break;
                        case 'continent':
                            if (\is_array($criteriaVal)) {
                                if (!\in_array(strtolower($countryInfo->continent->code), $criteriaVal, true)) {
                                    $isApplicable = false;
                                }
                            } elseif (strtolower($countryInfo->continent->code) !== strtolower($criteriaVal)) {
                                $isApplicable = false;
                            }

                            break;
                        case 'isInEuropeanUnion':
                            if ($countryInfo->country->isInEuropeanUnion !== $criteriaVal) {
                                $isApplicable = false;
                            }

                            break;
                        case 'language':
                            if (!GeoMateHelper::isAcceptedLanguage($criteriaVal, $settings->minimumAcceptLanguageQuality)) {
                                $isApplicable = false;
                            }

                            break;
                        case 'languageRegion':
                            if (!GeoMateHelper::isAcceptedLanguageRegion($criteriaVal, $settings->minimumAcceptLanguageQuality)) {
                                $isApplicable = false;
                            }

                            break;
                    }
                }
            }

            if ($isApplicable) {
                return $siteHandle;
            }
        }

        return null;
    }

    private function shouldIgnoreUser(): bool
    {
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();
        $user = Craft::$app->getUser()->getIdentity() ?? null;

        if ($user) {
            if ($settings->redirectIgnoreAdmins && $user->admin) {
                return true;
            }

            if ($settings->redirectIgnoreUserGroups && $settings->redirectIgnoreUserGroups !== []) {
                foreach ($settings->redirectIgnoreUserGroups as $ignoreGroup) {
                    if ($user->isInGroup($ignoreGroup)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function shouldIgnoreUrl(): bool
    {
        /** @var Settings $settings */
        $settings = GeoMate::$plugin->getSettings();

        try {
            $url = Craft::$app->getRequest()->getUrl();
        } catch (InvalidConfigException) {
            return false;
        }

        if ($settings->redirectIgnoreUrlPatterns && \count($settings->redirectIgnoreUrlPatterns)) {
            foreach ($settings->redirectIgnoreUrlPatterns as $ignorePattern) {
                if ($ignorePattern[0] === '=') {
                    $exactMatch = substr($ignorePattern, 1);
                    if ($url === $exactMatch) {
                        return true;
                    }
                } elseif (preg_match($ignorePattern, $url)) {
                    return true;
                }
            }
        }

        return false;
    }
}
