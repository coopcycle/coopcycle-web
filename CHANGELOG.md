# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.30.1] - 2024-03-05
 - Bug fixes for Tours beta feature. Issues #4012, #4013, #4016

## [1.30.0] - 2024-03-05

- Introduce DBSchenker automatic import

## [1.29.1] - 2024-02-15

- Use new React18 createRoot to render the dispatch right panel

## [1.29.0] - 2024-02-15

- Incidents display on the dashboard : in task detail and in assigned tasks
- Fix for creating a tour from several tasks
- Upgrade to react 18

## [1.28.0] - 2024-02-13

- Allow to modify assigned tours with drag'n drop
- Assign linked tasks together, unassign separately
- Fix tour ordering mess when assigning a tour with linked tasks
- Add early design for Business Account/catering

## [1.25.1] - 2024-01-18

- Ignore empty lines in spreadsheets.
- Allow restoring cancelled orders that were previously accepted.

## [1.25.0] - 2024-01-16

- Improve orders search
- Improve dispatch dashboard drag'n'drop

## [1.24.0] - 2024-01-12

### Added

- Fix 504 errors when uploading big delivery import files.
- Show errors line by line in spreadsheet context.
- Allow redownloading spreadsheet file with missing rows.

## [1.22.0] - 2023-12-20

### Added

- Allow creating empty tours.
- Allow renaming tours.

## [1.19.0] - 2023-12-04

### Added

- Implement new design for restaurant cards.
- Allow searching stock photos for restaurant banners.

## [1.18.0] - 2023-11-29

### Added

- Allow configuring packages in recurrent rules ([#3884](https://github.com/coopcycle/coopcycle-web/issues/3884)).
- Introduce business accounts ([#3848](https://github.com/coopcycle/coopcycle-web/pull/3848)).
- Add chart with map of orders per zone.

## [1.17.0] - 2023-11-21

### Added

- Use [Base 32](https://www.crockford.com/base32.html) to generate order numbers.
- Create orders from recurring tasks.
- Display delivery state in lists.

## [1.16.3] - 2023-11-14

### Added

- Embed Cube Playground into admin.

## [1.16.2] - 2023-11-10

### Fixed

- Fix error when saving a shop that can't be found in the search engine.

## [1.16.0] - 2023-11-08

### Added

- Cancel orders when all linked tasks are cancelled.
- Show popup when adding zero waste products to cart.

### Changed

- Show comment icon for address or order instructions.

## [1.15.0] - 2023-10-30

### Added

- Allow creating custom lists of failure reasons for shops & stores.

## [1.14.0] - 2023-10-26

### Added

- Allow configuring which notifications (top bar & email) are sent to admins/dispatchers.

## [1.13.12] - 2023-10-25

### Added

- A new option allows applying pricing conditions to all tasks, even if there are only 2 tasks.

## [0.11.0] - 2021-03-24

### Changed

- `Configuration` has been removed from the top menu and the sub-level settings have been moved under `Deliveries`.

## [0.10.3] - 2021-03-05

### Added

- Allow configuring recurrence rules for tasks.

## [0.10.2] - 2020-12-28

### Changed

- Use Stripe Strong Customer Authentication.
- Complete rewrite of opening hours & holidays management.

## [0.10.1] - 2020-12-08

### Changed

- Send receipt by email after fulfillment.

## [0.10.0] - 2020-12-07

### Added

- Introduce route optimization by [@andrewcunnin](https://github.com/andrewcunnin).
- Add a "lightning bolt" button to optimize tasks assigned to a messenger.
