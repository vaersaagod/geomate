<?php
/**
 * GeoMate plugin for Craft CMS 5.x
 *
 * Look up visitors location data based on their IP and easily redirect them to the correct site..
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\geomate\services;

use craft\base\Component;
use craft\models\Site;
use vaersaagod\geomate\GeoMate;
use vaersaagod\geomate\models\Settings;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     1.0.0
 *
 * @property null|string|Site $overrideCookie
 * @property string $cookie
 */
class CookieService extends Component
{
    /**
     * @param Site $site
     */
    public function setOverrideCookie($site)
    {
        /** @var Settings $settings */
        $settings = GeoMate::getInstance()->getSettings();
        $this->setCookie($settings->redirectOverrideCookieName, $site->handle, empty($settings->cookieDuration) ? 0 : $settings->cookieDuration + time());
    }

    /**
     * @return null|string
     */
    public function getOverrideCookie()
    {
        /** @var Settings $settings */
        $settings = GeoMate::getInstance()->getSettings();
        return $this->getCookie($settings->redirectOverrideCookieName);
    }

    /**
     * @param string $name
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     */
    private function setCookie($name, $value = '', $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = false)
    {
        if ($value === '') {
            $expire = time() - 3600;
        }

        setcookie($name, $value, ['expires' => (int)$expire, 'path' => $path, 'domain' => $domain, 'secure' => $secure, 'httponly' => $httponly]);
        $_COOKIE[$name] = $value;
    }

    /**
     * @param string $name
     * @return null|string
     */
    private function getCookie($name = '')
    {
        if (isset($_COOKIE[$name])) {
            return $_COOKIE[$name];
        }

        return null;
    }
}
