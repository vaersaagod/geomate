# GeoMate Changelog

## Unreleased
### Fixed
- Fixed an issue where GeoMate could prevent custom user behaviors from being registered. [#60](https://github.com/vaersaagod/geomate/issues/60)

## 2.1.2 - 2024-02-20  
### Fixed
- Fixed parsing of config values in settings (fixes #59)

## 2.1.1 - 2023-03-24  
### Fixed
- Fixed a bug that could result in a PHP exception if a site link ended up being `null`. Fixes #58

## 2.1.0 - 2022-10-04
### Changed  
- Auto-redirects based on browser language now always work, regardless of GeoMate having access to a geolocation database or not. #53  
### Fixed
- Fixed an issue where GeoMate could throw an exception if the geolocation database was missing, and the `redirectMapSimpleModeKey` setting was set to `language`. #52  

## 2.0.0 - 2022-05-04
### Added
- Added Craft 4 support.  

## 1.3.1.2 - 2021-07-20
### Fixed
- Fixed accidental use of macro from Craft 3.6 (fixes #43).

## 1.3.1.1 - 2021-07-20
### Fixed
- Fixed changelog

## 1.3.1 - 2021-07-20
### Fixed
- Fixed an issue where the `redirectOverrideParam` could be included multiple times in the URL query string
- Fixed an issue where Geomate could redirect Preview requests  

## 1.3.0 - 2021-03-03
### Added
- Added console command `geomate/database/update-database` to update database from the console (Thanks, @johnnynotsolucky).

### Fixed
- Fixed an issue with database downloads when using Craft 3.6 with Guzzle 7.
- Fixed an issue where Geomate could redirect Preview requests

## 1.2.1 - 2020-03-20
### Changed
- Changed behavior of `Application::EVENT_INIT` event handler, the GeoMate handler is now prepended instead of appended (fixes #29).

## 1.2.0 - 2020-02-14
### Added
- Added support for `autoRedirectExclude` which can be used to exclude sites from automatic redirect when `$autoRedirectEnabled` is `true` (fixes #24).

### Fixed
- Fixed an issue with the `redirectMatchingElementOnly` config setting where GeoMate would try to redirect to a different site even if an element was not enabled (fixes #25).

## 1.1.0.2 - 2020-01-08
### Fixed
- Fixed spelling errors and readme.

## 1.1.0.1 - 2020-01-08
### Fixed
- Fixed an issue where unpacking the new .tar.gz file would result in a corrupt database (fixes #22).

## 1.1.0 - 2020-01-07

> {warning} As of December 30th 2019, the GeoLite2 databases are no longer publicly available [due to compliance with GDPR and CCPA](https://blog.maxmind.com/2019/12/18/significant-changes-to-accessing-and-using-geolite2-databases/). Previously, the public URLs for these databases were set as defaults in the GeoMate configuration. As of GeoMate 1.1.0, these have been removed, and you now need to register a maxmind account, get a license key, and configure the download URLs yourself. See the ["Downloading the geolocation database"](https://github.com/vaersaagod/geomate#downloading-the-geolocation-database) below for more info on how to do this.

### Changed
- Changed default values for `countryDbDownloadUrl` and `cityDbDownloadUrl` to `null`. URLs now needs to be added manually to be able to use GeoMate to download the databases (see #21).

## 1.0.6 - 2019-07-25
### Added
- Added parsing of Craft style env variables in site urls (fixes #14).

## 1.0.5 - 2019-07-04
### Fixed
- Fixes issue where `$_SERVER['HTTP_ACCEPT_LANGUAGE']` was not set and `redirectMapSimpleModeKey` was set to `language` (fixes #10).

## 1.0.4 - 2019-03-15
### Added
- Added support for using arrays in redirect map values.

## 1.0.3 - 2019-03-11
### Fixed
- Fixes issue where `getMatchedElement` was called before the application was fully initialized (#6).

## 1.0.2 - 2018-11-01
### Fixed
- Fixes an issue where a successful geodb lookup was required even if you were only using browser language or region to redirect (Thanks, @kyle51north).

## 1.0.1 - 2018-09-09
### Fixed
- Fixes an issue that would create an error message in console requests.

## 1.0.0 - 2018-08-29
### Added
- Initial public release
