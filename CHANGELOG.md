# GeoMate Changelog

## 1.0.5 - 2019-07-04
### Added
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
