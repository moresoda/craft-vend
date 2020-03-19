# Vend Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).


## Unreleased


## 2.1.3 - 2020-03-19

### Added
- Added a link out to the order in the Vend sales screen for valid Vend orders - currently only visible in the orders index if the Vend Order ID field has been added to the view.


## 2.1.2 - 2020-03-18

### Fixed
- Fixed an issue where the Vend Customer on guest orders wasn’t getting sent to Vend with the order.


## 2.1.1 - 2020-03-18

### Fixed
- Fixed an issue where guest orders weren’t getting sent to Vend because there wasn’t a User to save the resulting Vend Customer ID on to.


## 2.1.0 - 2020-03-18

### Added
- Added the ability to send an order to Vend from the CP order edit view.
- Added a new Vend Order ID field - make sure to add this to the Order field layout in Commerce > System Settings > Order Fields.

### Changed
- Now storing the Vend Order ID on the Commerce Order after registering a sale.


## 2.0.11 - 2020-03-18

### Fixed
- Fixed logging in webhook and redirected the output to our own file.


## 2.0.10 - 2020-03-16

### Fixed
- Changed `hasStock` to `hasUnlimitedStock` which is `true` if the Vend `has_inventory` field is `false`.


## 2.0.9 - 2020-03-16

### Added
- Added `optionValueOrName` and `hasStock` to variant JSON in feeds.


## 2.0.8 - 2020-03-09

### Fixed
- Fixed an issue where live environments couldn’t access the parked sales screen.


## 2.0.7 - 2020-03-09

### Changed
- Changed how orders are validated before sending to Vend, now it only sends orders that wholly have Variants with product IDs on them. If there is a missing product ID or another kind of purchasable in there then it won’t send. 


## 2.0.6 - 2020-02-25

### Added
- Added parked sales for when a sale fails to go through. Add an email in commerce to handle these then select it in the plugin settings.


## 2.0.5 - 2020-01-31

### Added
- Added some instructions for which URLs to use with Feed Me.

### Changed
- Un-pinned the oauth client version now its been fixed upstream ([PR](https://github.com/venveo/craft-oauthclient/pull/27)).

### Fixed
- Fixed a missing `setFieldValue()`.


## 2.0.4 - 2020-01-31

### Changed
- Allow Commerce 2 or 3 to be installed.


## 2.0.3 - 2020-01-31

### Added
- Sales will now be registered with Vend if the option is switched on in the general settings.
- Added shipping settings.


## 2.0.2 - 2020-01-28

### Fixed
- Fixed an issue with the oauth client by pinning it to version 2.1.2.


## 2.0.1 - 2020-01-28

### Added
- Added Vend tags to the import profiles.
- Added Vend customer group setting.

### Fixed
- Fixed an issue where the discount product was being imported.


## 2.0.0 - 2020-01-24

### Added
- Initial, nearly fully functional release.
