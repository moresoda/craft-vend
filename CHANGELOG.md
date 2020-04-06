# Vend Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).


## Unreleased


## 2.3.1 - 2020-04-06

### Fixed
- Fixed an issue where API requests running via the console couldn’t refresh the access token if needed.


## 2.3.0 - 2020-04-03

### Changed
- Moved the register sale trigger to a queue job delayed by 30s, this should clean up most race condition circumstances when the order saving gets delayed after purchase.


## 2.2.6 - 2020-04-03

### Fixed
- Fixed an issue with more recent versions of Craft and fetching products from the Entries channel to import.


## 2.2.5 - 2020-03-31

### Added
- Added `preRunAction` config variable that lets you trigger a POST request to an action of your choice before the run feed action is called. The action requested must return JSON with `success:true` in the payload for it to continue.


## 2.2.4 - 2020-03-30

> {note} After installing this update make sure to add the new Vend Date Created field to the field layout on the Vend Products section.
> {note} You will also need to map the new Vend Date Created field in the Vend products Feed Me feed - this should be set to the `dateCreated/date` option on the element mapping screen.

### Added
- Added a new Vend Date Created field to the Vend Products section and modified the product import API to sort by date created. 

### Changed
- Updated the fast feed tools to have an ordering options - you can now order by Date Created or Date Updated.


## 2.2.3 - 2020-03-30

### Fixed
- Fixed an issue where the Commerce product feeds wouldn’t cascade as quickly as they could.


## 2.2.2 - 2020-03-30

### Fixed
- Fixed changelog for 2.2.0.


## 2.2.1 - 2020-03-30

### Fixed
- Fixed schema version not being bumped for previous release.


## 2.2.0 - 2020-03-30

> {note} After installing this update make sure to add the new Vend Date Updated field to the field layout on the Vend Products section.
> {note} You will also need to map the new Vend Date Updated field in the Vend products Feed Me feed - this should be set to the `dateUpdated/date` option on the element mapping screen.

### Added
- Added a new Vend Date Updated field to the Vend Products section and modified the product import API to sort by date updated.
- Added a new import API for running faster feeds - this still requires the full Vend product import but then after that skips the inventory feed and instead processes all the Commerce product import feeds with a limit set from one of the new CP tools.
- Added two tools that let you run the full or fast feed from the Dashboard via two new widgets or from the Vend > Sync CP tab. 

### Changed
- Feed Me feeds now cascade so that you only need to start the Vend products feed. After that finishes the inventory and Commerce product import feeds will start up on their own.


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

> {note} After installing this update make sure to add the new Vend Order ID field to the Order field layout in Commerce > System Settings > Order Fields.

### Added
- Added the ability to send an order to Vend from the CP order edit view.
- Added a new Vend Order ID field.

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
