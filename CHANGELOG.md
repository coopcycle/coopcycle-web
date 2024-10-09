# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.16.0] - 2024-10-09

### Fixed
* The delivery order form is now navigatable with TAB key
* Multi-dropoff reorder suggestions show only on delivery form submit
* You can select with TAB or ENTER key from the address autossugest dropdown
* Phone number and contact name are now optional for admins in the delivery order form

## [3.15.0] - 2024-10-07

### Fixed
* The "global foodtech delay" now solely applies to pickup times

## [3.14.11] - 2024-09-27

### Added
* Allow limiting deliveries to a zone/distance for a store.

## [3.14.8] - 2024-09-26

### Added
* Validate SIRET using INSEE APIs, to make sure it works with Edenred.

## [3.14.0] - 2024-09-24

### Added
* Allow auto-assiging deliveries from a store to a rider.

## [3.13.0] - 2024-09-24

### Changed
* Paying with Edenred with a credit card complement now generates two payments.

## [3.9.11] - 2024-09-12

### Added
* Ability to filter out unassigned tours on dispatch map
* Ability to filter out assigned tasks on dispatch map

### Fixed
* Deliveries/orders created from dispatch recurrent rules are not in the right order

## [3.9.6] - 2024-09-10

### Added
* Show order number in the dispatch panel, not task id

### Fixed
* Crash when creating an incident from the dispatch panel

## [3.8.1] - 2024-08-21

### Added
* Sugggest a more optimized route when creating a delivery.

## [3.7.9] - 2024-08-21

### Fixed
* Crash when taking actions or adding comments to a incident

### Changed
* Move the "Take action" button close to the header section in incident view

## [3.7.6] - 2024-08-20

### Fixed
*  Crash when bookmarking a new order: https://github.com/coopcycle/coopcycle-web/issues/4560
*  Bug on the delivery order form. It is not possible to add packages (clicking on the button had no effect).

## [3.7.3] - 2024-08-19

### Added
* Introduce vehicles, warehouses and trailers by @Atala in https://github.com/coopcycle/coopcycle-web/pull/4485
* Enable trailers edit
* Show order number in orders/deliveries-related screens
* 'Duplicate an order' button by @vladimir-8 in https://github.com/coopcycle/coopcycle-web/pull/4474
* Add the ability for admins to bookmark/save orders by @vladimir-8 in https://github.com/coopcycle/coopcycle-web/pull/4489
* Add order subscriptions by @vladimir-8 in https://github.com/coopcycle/coopcycle-web/pull/4511
* Format price in exports.
* Transporters: Add support for multiple synchronisation mechanism by @r0xsh in https://github.com/coopcycle/coopcycle-web/pull/4486

### Changed
* Do not send geofencing notification to dispatchers and admin
* Add the ability for a dispatcher to undo optimization

### Fixed
* Crash in dispatch when task.metadata is NULL (legacy tasks)
* As a dispatcher I want to be able to filter by "only this rider"
* Vehicles/trailers deletion by Atala
* Fix dispatch map crash when expanding marker's popup
* Stripe payment failed for some customers when they selected an option to save their card for future payments: https://github.com/coopcycle/coopcycle/issues/62
* Some situations where delivery fee was added twice: https://github.com/coopcycle/coopcycle-web/issues/3929

## [3.4.1] - 2024-07-08

### Added
* Show volumes and weight in the dispatch for individual tasks, and sum for tours/routes and for what is assigned to a messenger. Includes a toggle setting. By @Atala in https://github.com/coopcycle/coopcycle-web/pull/4469
* As a dispatcher, I can change the color of the dropoff markers, or show a colored line that demostrates the way the tasks are arranged into a route by @Atala in https://github.com/coopcycle/coopcycle-web/pull/4479
* Allow dispatch to reorder timeslots on Store settings, which changes the way they are shown to a store when they use the order creation form by @r0xsh in https://github.com/coopcycle/coopcycle-web/pull/4417
* Show task(s) in the right hand columns when selecting a task on the map by @Atala in #4455

### Changed
* Move the search input from right panel to top bar to improve usability and show results better by @Atala in https://github.com/coopcycle/coopcycle-web/pull/4482

### Fixed
* Allow last-mile orders to be paid after the delivery has been completed
* In foodtech orders dashboard, fix search + show search result on top of column

## [3.0.4] - 2024-06-27

### Added

* FOOD TECH: Timing Modal (web only). Display a modal when the client is about to make an order that's going to be rescheduled to the next shift

## [3.0.2] - 2024-06-27

### Fixed

* In dispatch, save use_avatar_colors between refresh by @Atala in https://github.com/coopcycle/coopcycle-web/pull/4467
* Add "dispatch" next to the top bar lock icon to go to admin by @Atala in https://github.com/coopcycle/coopcycle-web/pull/4453
* Improve search in order foodtech dashboard by @Atala in https://github.com/coopcycle/coopcycle-web/pull/4470

## [3.0.1] - 2024-06-26

### Changed

* Propose the user to login when registration email exists by @lucasferraro in https://github.com/coopcycle/coopcycle-web/pull/4411

## [3.0.0] - 2024-06-24

### Changed

* Support PHP 8.3
* Dispatch : add the ability to filter by tags, stores, restaurants for both global filters and unassigned tasks by @Atala in #4440

## [2.9.4] - 2024-06-20

### Changed

* Improve search results in the dispatch panel


## [2.9.3] - 2024-06-19

### Fixed

* **Right-click assign in dispatch:** An error in how assignation was working for right-click (was assigning to the first rider of the planning), now fixed.

## [2.9.2] - 2024-06-19

### Changed

* **Support multi points in time based pricing functions:** Now we can use the rules "dropoff/pickup time range length" in pricing rules that are activated for multiple-dropoff orders.

## [2.9.0] - 2024-06-18

### Fixed

* **The ‘Close restaurant for a day’ button closes restaurant for 2 days:** Now when a restaurant uses the "off" button in the CoopCycle app it will only close them until end of that day, reopening the next day automatically
* **A customer can create an order for a disabled (deactivated) restaurant:** Customers could order in a restaurant even though they were closed, but not anymore! (only for web orders for now)
* **Order is created with a preparation scheduled in the past:** We had an issue with incorrect calculations about time ranges for food deliveries that is now fixed. (only for web orders for now)
* **Potential Fix for assignment bug when dispatching on two different tabs on two different days (reported by Naofood):** Since orders can come in for tomorrow, dispatchers had multiple tabs open in food tech. This created problems for the notification system, which we believe we have now fixed.
* **Crash when setting "use avatar color" to Yes:** Just a random crash bug

## [2.8.2] - 2024-06-12

### Changed

- **Allow massively starting tasks:**  Want your last mile provider or local shop to know you have recieved a package or a whole pallet of packages? Right click on multiple tasks now has a "start tasks" option that will inform EDIFACT connected last mile providers that you have correctly recieved these packages, and show an order as started for a shop in their panel.
- **Revamp view of EDIFACT messages:**  Added more information to timeline below the bar code in an order detail page to add information requested by last mile providers in France

## [2.8.1] - 2024-06-11

### Fixed

- Fix for "Unable to drag a group from Unassigned column to rider's assignment" | Assigning groups to riders was broken, we fixed it

## [2.7.7] - 2024-06-06

### Changed

- Enable polygon selection in dispatch | Now we can draw whatever we want, not just rectangles
- Set marker color to rider color when showing polyline | Points on the map assigned to a rider will change color when the map icon is selected to improve visualisation of the routes assigend to each person in the map

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
