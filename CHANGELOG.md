# GeoMate Changelog

## Unreleased 
### Fixed
- Fixed a bug where the `useSeparateLogfile` setting didn't, in fact, make GeoMate write logs to its own file. [#66](https://github.com/vaersaagod/geomate/issues/66) 
### Changed 
- GeoMate no longer attempts to start a PHP session when auto redirects are disabled via the `autoRedirectEnabled` or `autoRedirectExclude` settings, or if the `addGetParameterOnRedirect` setting is set to `true`. [#67
  ](https://github.com/vaersaagod/geomate/issues/67)

## 3.0.0 - 2024-08-07
### Added
- Added support for Craft 5
### Fixed 
- Fixed an issue where GeoMate could prevent custom user behaviors from being registered. [#60](https://github.com/vaersaagod/geomate/issues/60) 
