# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.7.8] - 2024-06-11

### Fixed

- Fix for "Unable to drag a group from Unassigned column to rider's assignment"

## [2.7.7] - 2024-06-06

### Changed

- Enable polygon selection in dispatch
- Set marker color to rider color when showing polyline

### Fixed

- Fix for assignment not correctly set on dispatch map
- Fix do not show deleted shops in create delivery/order dropdown
- Various catering improvements

## [2.7.1] - 2024-06-04

### Added

- Add a filter for "exclude tags" by @Atala in #4353

### Changed

- New method to get setup vendor information
- Invitation link moved to confirmed registration screen
- Re-architecture the "TaskList" entity in the backend by @Atala in #4267

### Fixed

- Fix bug in task list live updates that were dispatched to all admins 

## [2.6.0] - 2024-05-29

### Added

- Allow auto-accepting orders

## [2.5.3] - 2024-05-20

### Fixed

- Fixes for item deletion in admin
- Order pickup before dropoff in unassigned tasks

## [2.4.3] - 2024-05-07

### Added

- First version for incidents + ability to report incidents from dispatch

### Changed

- Show unassigned tasks when filtering out by courier in the dispatch

## [2.3.0] - 2024-04-22

### Added

- Allow synchronizing Edenred merchant IDs.

## [2.2.1] - 2024-04-16

### Fixed

- Do not add the other tasks of an order when drag'n droping a tour in dispatch

### Added

- Add a link to odoo instance in admin navbar if any

### Changed

- Show incidents as default in dispatch
- Dispatch filters are persistent

## [2.2.0] - 2024-04-09

### Changed

- Make "optimize rider assignments" not break when they are tours in the rider's assignment
- Ability to reorder unassigned tasks and unassigned tours
- When adding tasks in tours, sort them in the tour according to their order in "Unassigned tasks
- Fix for dispatchers that are also riders not able to see all tasks in the web dispatch
 
### Fixed

- Fix for "Misleading information about available time slots"
- Fix for "Unable to change filters on restaurants list page"
- Show some message on restaurants list page when restaurant is unavailable

## [2.0.2] - 2024-03-19

### Fixed

- Filter out duplicate tasks in "unassigned tasks / unassigned tours" (bug with the options for sorting unassigned tasks)
- When assigning orders/tasks to a route they continue to appear in the unassigned column as well as the routes column #4067
- Move order tasks together on drag n drop (even for unassign) #4064
- Clear out task selection on create tour, modify tour, create group, modify group #4071
- Remove annoying toast about "incorrect" tasks selection

## [2.0.0] - 2024-03-19

### Changed

- Implement new design for restaurant page ✨

## [1.31.1] - 2024-03-15

### Changed

- Allow to highlight several tasks on the map even if no action is available for tasks

## [1.30.1] - 2024-03-05

### Fixed

- Bug fixes for Tours beta feature. Issues #4012, #4013, #4016

## [1.30.0] - 2024-03-05

### Added

- Introduce DBSchenker automatic import

## [1.29.1] - 2024-02-15

### Changed

- Use new React18 createRoot to render the dispatch right panel

## [1.29.0] - 2024-02-15

### Changed

- Incidents display on the dashboard: in task detail and in assigned tasks
- Fix for creating a tour from several tasks
- Upgrade to react 18

## [1.28.0] - 2024-02-13

- Allow to modify assigned tours with drag'n drop
- Assign linked tasks together, unassign separately
- Fix tour ordering mess when assigning a tour with linked tasks
- Add early design for Business Account/catering

## [1.25.1] - 2024-01-18

### Fixed

- Ignore empty lines in spreadsheets.
- Allow restoring cancelled orders that were previously accepted.

## [1.25.0] - 2024-01-16

### Changed

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
