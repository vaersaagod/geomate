# GeoMate Changelog

## 3.2.0 - 2026-11-27
### Added
- Added the `updateDatabaseKey` config setting. When set, requests to the `geomate/database/update-database` action URL must include the key.
### Changed
- Changed the `geomate/database/update-database` action URL so that requests without a valid key only download new databases when the installed ones are missing or more than six hours old, at most once every ten minutes, and never in parallel.
- Changed the GeoMate utility's update button to use a POST request.
### Fixed
- Fixed an issue where element URLs used for site links, redirect information and automatic redirects could be parsed for environment variables.
- Fixed an issue where a failed automatic database download (`downloadDbIfMissing`) caused errors on front-end requests and was retried on every request. Failed downloads are now logged and retried after 10 minutes.
- Fixed an issue where the GeoMate utility displayed credentials included in the database download URLs.
- Fixed an issue where automatic redirects and site override responses could be stored by shared caches.
- Fixed an issue where database updates rewrote the live database files in place, so lookups during an update could read a partially written file. Downloaded databases are now validated before they replace the existing ones.
- Fixed an error that could occur when the override cookie or the `Accept-Language` header was malformed.
- Fixed an issue where requests for published resources (`/cpresources`) could be redirected or get the override cookie.
- Fixed an issue where the override and redirected parameters were also read from the request body.
- Fixed an issue where GeoMate started a PHP session, and set a session cookie, on front-end requests from visitors without an existing session when `addGetParameterOnRedirect` is `false`.
- Fixed an issue where cached IP lookups weren't cleared when the databases were updated.
- Fixed an issue where errors from failed database downloads, and the name of the temporary download file, could include credentials from the download URL (like the MaxMind license key).
- Fixed an error that occurred when an IP address that isn't valid was passed to the `craft.geomate.country`, `craft.geomate.countryCode`, `craft.geomate.city` or `craft.geomate.redirectInformation` template variables. No lookup is made for these IP addresses.
- Fixed an error that occurred when a redirect or `craft.geomate.redirectInformation` was resolved for a URL with more query string parameters than PHP's `max_input_vars` setting allows.
- Fixed an issue where a stalled database download could run indefinitely. Downloads now time out after 10 minutes, or after 15 seconds if the download server can't be reached, unless `config/guzzle.php` sets other timeouts.

## 3.1.1 - 2026-11-19 
### Fixed
- Fixed an issue where the redirect included the whole page response.

## 3.1.0 - 2025-06-17 
### Fixed
- Fixed a bug where the `useSeparateLogfile` setting didn't, in fact, make GeoMate write logs to its own file. [#66](https://github.com/vaersaagod/geomate/issues/66)
- Fixed a bug where cookies created by GeoMate would immediately expire if the `cookieDuration` setting was set to `0`, instead of creating a session cookie. [#63](https://github.com/vaersaagod/geomate/issues/63)
### Changed 
- GeoMate no longer attempts to start a PHP session when auto redirects are disabled via the `autoRedirectEnabled` or `autoRedirectExclude` settings, or if the `addGetParameterOnRedirect` setting is set to `true`. [#67
  ](https://github.com/vaersaagod/geomate/issues/67)

## 3.0.0 - 2024-08-07
### Added
- Added support for Craft 5
### Fixed 
- Fixed an issue where GeoMate could prevent custom user behaviors from being registered. [#60](https://github.com/vaersaagod/geomate/issues/60) 
