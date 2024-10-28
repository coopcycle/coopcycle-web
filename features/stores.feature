Feature: Stores

  Scenario: Not authorized to list stores
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/stores"
    Then the response status code should be 403

  Scenario: Not authorized to retrieve store
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/stores"
    Then the response status code should be 403

  Scenario: Not authorized to list store deliveries with JWT
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme2" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/stores/1/deliveries?order[dropoff.before]=asc"
    Then the response status code should be 403

  Scenario: Not authorized to list store deliveries with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    Given the store with name "Acme2" has an OAuth client named "Acme2"
    And the OAuth client with name "Acme2" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme2" sends a "GET" request to "/api/stores/1/deliveries?order[dropoff.before]=desc"
    Then the response status code should be 403

  Scenario: List my stores
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/me/stores"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Store",
        "@id":"/api/stores",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/stores/1",
            "id":1,
            "@type":"http://schema.org/Store",
            "name":"Acme",
            "enabled":true,
            "address":@...@,
            "timeSlot":"/api/time_slots/1"
          }
        ],
        "hydra:totalItems":1
      }
      """

  Scenario: Retrieve store
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/stores/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Store",
        "@id":"/api/stores/1",
        "@type":"http://schema.org/Store",
        "id":1,
        "name":"Acme",
        "enabled":true,
        "address":{
          "@id":"/api/addresses/1",
          "@type":"http://schema.org/Place",
          "geo":{
            "@type":"GeoCoordinates",
            "latitude":48.864577,
            "longitude":2.333338
          },
          "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
          "telephone":null,
          "name":null,
          "description": null
        },
        "timeSlot":"/api/time_slots/1",
        "timeSlots":@array@,
        "weightRequired":@boolean@,
        "packagesRequired":@boolean@
      }
      """

  Scenario: Retrieve store with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    Given the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "GET" request to "/api/stores/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Store",
        "@id":"/api/stores/1",
        "@type":"http://schema.org/Store",
        "id":1,
        "name":"Acme",
        "enabled":true,
        "address":{
          "@id":"/api/addresses/1",
          "@type":"http://schema.org/Place",
          "geo":{
            "@type":"GeoCoordinates",
            "latitude":48.864577,
            "longitude":2.333338
          },
          "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
          "telephone":null,
          "name":null,
          "description": null
        },
        "timeSlot":"/api/time_slots/1",
        "timeSlots":@array@,
        "weightRequired":@boolean@,
        "packagesRequired":@boolean@
      }
      """

  Scenario: Retrieve time slot with opening hours
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/time_slots/2"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TimeSlot",
        "@id":"/api/time_slots/2",
        "@type":"TimeSlot",
        "name":"Time slot with opening hours",
        "interval":"2 days",
        "priorNotice":null,
        "workingDaysOnly":false,
        "openingHoursSpecification":[
          {
            "@type":"OpeningHoursSpecification",
            "opens":"10:00",
            "closes":"11:00",
            "dayOfWeek":[
              "Monday",
              "Tuesday",
              "Wednesday",
              "Thursday",
              "Friday",
              "Saturday"
            ]
          },
          {
            "@type":"OpeningHoursSpecification",
            "opens":"11:00",
            "closes":"13:00",
            "dayOfWeek":[
              "Monday",
              "Tuesday",
              "Wednesday",
              "Thursday",
              "Friday",
              "Saturday"
            ]
          },
          {
            "@type":"OpeningHoursSpecification",
            "opens":"14:00",
            "closes":"15:00",
            "dayOfWeek":[
              "Sunday"
            ]
          }
        ],
        "choices":[]
      }
      """

  Scenario: Retrieve time slot with opening hours with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    Given the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "GET" request to "/api/time_slots/2"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TimeSlot",
        "@id":"/api/time_slots/2",
        "@type":"TimeSlot",
        "name":"Time slot with opening hours",
        "interval":"2 days",
        "priorNotice":null,
        "workingDaysOnly":false,
        "openingHoursSpecification":[
          {
            "@type":"OpeningHoursSpecification",
            "opens":"10:00",
            "closes":"11:00",
            "dayOfWeek":[
              "Monday",
              "Tuesday",
              "Wednesday",
              "Thursday",
              "Friday",
              "Saturday"
            ]
          },
          {
            "@type":"OpeningHoursSpecification",
            "opens":"11:00",
            "closes":"13:00",
            "dayOfWeek":[
              "Monday",
              "Tuesday",
              "Wednesday",
              "Thursday",
              "Friday",
              "Saturday"
            ]
          },
          {
            "@type":"OpeningHoursSpecification",
            "opens":"14:00",
            "closes":"15:00",
            "dayOfWeek":[
              "Sunday"
            ]
          }
        ],
        "choices":[]
      }
      """

  Scenario: List store deliveries with JWT, ordered by dropoff desc
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/stores/1/deliveries?order[dropoff.before]=desc"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/stores/1/deliveries",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/deliveries/2",
            "@type":"http://schema.org/ParcelDelivery",
            "id":2,
            "pickup":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":@integer@,
              "status":@string@,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T18:30:00+01:00",
              "doneBefore":"2019-11-12T18:30:00+01:00",
              "comments": ""
            },
            "dropoff":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":@integer@,
              "status":@string@,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T20:30:00+01:00",
              "doneBefore":"2019-11-12T20:30:00+01:00",
              "comments": ""
            },
            "trackingUrl": @string@
          },
          {
            "@id":"/api/deliveries/1",
            "@type":"http://schema.org/ParcelDelivery",
            "id":1,
            "pickup":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":@integer@,
              "status":@string@,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T18:30:00+01:00",
              "doneBefore":"2019-11-12T18:30:00+01:00",
              "comments": ""
            },
            "dropoff":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":@integer@,
              "status":@string@,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T19:30:00+01:00",
              "doneBefore":"2019-11-12T19:30:00+01:00",
              "comments": ""
            },
            "trackingUrl": @string@
          }
        ],
        "hydra:totalItems":2,
        "hydra:view":@...@,
        "hydra:search":@...@
      }
      """

  Scenario: List store deliveries with JWT, ordered by dropoff asc
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/stores/1/deliveries?order[dropoff.before]=asc"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/stores/1/deliveries",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/deliveries/1",
            "@type":"http://schema.org/ParcelDelivery",
            "id":1,
            "pickup":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":@integer@,
              "status":@string@,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T18:30:00+01:00",
              "doneBefore":"2019-11-12T18:30:00+01:00",
              "comments": ""
            },
            "dropoff":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":@integer@,
              "status":@string@,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T19:30:00+01:00",
              "doneBefore":"2019-11-12T19:30:00+01:00",
              "comments": ""
            },
            "trackingUrl": @string@
          },
          {
            "@id":"/api/deliveries/2",
            "@type":"http://schema.org/ParcelDelivery",
            "id":2,
            "pickup":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":@integer@,
              "status":@string@,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T18:30:00+01:00",
              "doneBefore":"2019-11-12T18:30:00+01:00",
              "comments": ""
            },
            "dropoff":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":@integer@,
              "status":@string@,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T20:30:00+01:00",
              "doneBefore":"2019-11-12T20:30:00+01:00",
              "comments": ""
            },
            "trackingUrl": @string@
          }
        ],
        "hydra:totalItems":2,
        "hydra:view":@...@,
        "hydra:search":@...@
      }
      """

  Scenario: List store deliveries with OAuth, ordered by dropoff desc
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    Given the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "GET" request to "/api/stores/1/deliveries?order[dropoff.before]=desc"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/stores/1/deliveries",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/deliveries/2",
            "@type":"http://schema.org/ParcelDelivery",
            "id":2,
            "pickup":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":@integer@,
              "status":@string@,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T18:30:00+01:00",
              "doneBefore":"2019-11-12T18:30:00+01:00",
              "comments": ""
            },
            "dropoff":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":@integer@,
              "status":@string@,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T20:30:00+01:00",
              "doneBefore":"2019-11-12T20:30:00+01:00",
              "comments": ""
            },
            "trackingUrl": @string@
          },
          {
            "@id":"/api/deliveries/1",
            "@type":"http://schema.org/ParcelDelivery",
            "id":1,
            "pickup":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":@integer@,
              "status":@string@,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T18:30:00+01:00",
              "doneBefore":"2019-11-12T18:30:00+01:00",
              "comments": ""
            },
            "dropoff":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":@integer@,
              "status":@string@,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T19:30:00+01:00",
              "doneBefore":"2019-11-12T19:30:00+01:00",
              "comments": ""
            },
            "trackingUrl": @string@
          }
        ],
        "hydra:totalItems":2,
        "hydra:view":@...@,
        "hydra:search":@...@
      }
      """

  Scenario: Retrieve store dropoff addresses
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/stores/2/addresses?type=dropoff"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Address",
        "@id":"/api/stores/2/addresses",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "contactName":null,
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":48.884625,
              "longitude":2.322084
            },
            "streetAddress":"18 Rue des Batignolles",
            "telephone":null,
            "name":null,
            "description": null
          }
        ],
        "hydra:totalItems":1,
        "hydra:view":{
          "@id":"/api/stores/2/addresses?type=dropoff",
          "@type":"hydra:PartialCollectionView"
        }
      }
      """

  Scenario: Reorder store time slots
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/stores/6"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
          "@context": "/api/contexts/Store",
          "@id": "/api/stores/6",
          "@type": "http://schema.org/Store",
          "id": 6,
          "name": "Acme 6",
          "enabled": true,
          "address": {"@*@":"@*@"},
          "timeSlot": "/api/time_slots/1",
          "timeSlots": [
              "/api/time_slots/1",
              "/api/time_slots/2"
          ],
          "weightRequired":@boolean@,
          "packagesRequired":@boolean@
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/merge-patch+json"
    When the user "bob" sends a "PATCH" request to "/api/stores/6" with body:
      """
      {
        "@id": "/api/stores/6",
        "timeSlots": [
          "/api/time_slots/2",
          "/api/time_slots/1",
          "/api/time_slots/3"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
          "@context": "/api/contexts/Store",
          "@id": "/api/stores/6",
          "@type": "http://schema.org/Store",
          "id": 6,
          "name": "Acme 6",
          "enabled": true,
          "address": {"@*@":"@*@"},
          "timeSlot": "/api/time_slots/1",
          "timeSlots": [
              "/api/time_slots/2",
              "/api/time_slots/1",
              "/api/time_slots/3"
          ],
          "weightRequired":@boolean@,
          "packagesRequired":@boolean@
      }
      """

  Scenario: Retrieve store timeslots
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/stores/1/time_slots"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Store",
        "@id": "/api/stores",
        "@type": "hydra:Collection",
        "hydra:member": [
            {
                "@id": "/api/time_slots/1",
                "@type": "TimeSlot",
                "name": @string@
            },
            {
                "@id": "/api/time_slots/2",
                "@type": "TimeSlot",
                "name": @string@
            }
        ],
        "hydra:totalItems": 2
      }
      """

  Scenario: Retrieve timeslots opening hours
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    Given the current time is "2024-05-31 11:00:00"
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/time_slots/1/choices"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
           "@context": {
               "@vocab": "http://nginx_test/api/docs.jsonld#",
               "hydra": "http://www.w3.org/ns/hydra/core#",
               "choices": "TimeSlotChoices/choices"
           },
           "@type": "TimeSlotChoices",
           "@id": @string@,
           "choices": [
               {
                   "@context": "/api/contexts/TimeSlotChoice",
                   "@id": @string@,
                   "@type": "TimeSlotChoice",
                   "value": "2024-05-31T10:00:00Z/2024-05-31T12:00:00Z",
                   "label": "Aujourd'hui entre 12:00 et 14:00"
               },
               {
                   "@context": "/api/contexts/TimeSlotChoice",
                   "@id": @string@,
                   "@type": "TimeSlotChoice",
                   "value": "2024-05-31T12:00:00Z/2024-05-31T15:00:00Z",
                   "label": "Aujourd'hui entre 14:00 et 17:00"
               }
           ]
      }
      """

  Scenario: Retrieve packages
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/stores/1/packages"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
           "@context": "/api/contexts/Store",
           "@id": "/api/stores",
           "@type": "hydra:Collection",
           "hydra:member": [
               {
                   "@type": "Package",
                   "@id": @string@,
                   "name": "SMALL"
               },
               {
                   "@type": "Package",
                   "@id": @string@,
                   "name": "XL"
               }
           ],
           "hydra:totalItems": 2
      }
      """
