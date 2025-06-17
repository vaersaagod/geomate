# GeoMate Changelog

## Unreleased
### Changed 
- GeoMate no longer attempts to start a PHP session when auto redirects are disabled via the `autoRedirectEnabled` or `autoRedirectExclude` settings, or if the `addGetParameterOnRedirect` setting is set to `true`. [#67
  ](https://github.com/vaersaagod/geomate/issues/67)

## 3.0.0 - 2024-08-07
### Added
- Added support for Craft 5
### Fixed 
- Fixed an issue where GeoMate could prevent custom user behaviors from being registered. [#60](https://github.com/vaersaagod/geomate/issues/60) 
