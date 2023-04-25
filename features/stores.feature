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
          "name":null
        },
        "timeSlot":"/api/time_slots/1"
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
          "name":null
        },
        "timeSlot":"/api/time_slots/1"
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
        "choices":[],
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
        ]
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
        "choices": [],
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
        ]
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
              "id":3,
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
              "id":4,
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
              "id":1,
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
              "id":2,
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
              "id":1,
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
              "id":2,
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
              "id":3,
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
              "id":4,
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
              "id":3,
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
              "id":4,
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
              "id":1,
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
              "id":2,
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
            "name":null
          }
        ],
        "hydra:totalItems":1,
        "hydra:view":{
          "@id":"/api/stores/2/addresses?type=dropoff",
          "@type":"hydra:PartialCollectionView"
        }
      }
      """
