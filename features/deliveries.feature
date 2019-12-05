Feature: Deliveries

  Scenario: Not authorized to create deliveries
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "pickup": {
          "address": "24, Rue de la Paix",
          "doneBefore": "tomorrow 13:00"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli",
          "doneBefore": "tomorrow 13:30"
        }
      }
      """
    Then the response status code should be 403

  Scenario: Not authorized to read delivery
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    And the store with name "Acme2" has an OAuth client named "Acme2"
    And the OAuth client with name "Acme2" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme2" sends a "GET" request to "/api/deliveries/1"
    Then the response status code should be 403

  Scenario: Missing time window
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "dropoff": {
          "address": "48, Rue de Rivoli"
        }
      }
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ConstraintViolationList",
        "@type":"ConstraintViolationList",
        "hydra:title":"An error occurred",
        "hydra:description":@string@,
        "violations":[
          {
            "propertyPath":"items[0].task.doneBefore",
            "message":@string@
          },
          {
            "propertyPath":"items[1].task.doneBefore",
            "message":@string@
          }
        ]
      }
      """

  Scenario: Create delivery with implicit pickup address with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "pickup": {
          "doneBefore": "tomorrow 13:00"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli",
          "doneBefore": "tomorrow 13:30",
          "comments": "Beware of the dog\nShe bites"
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"@string@.startsWith('/api/deliveries')",
        "@type":"http://schema.org/ParcelDelivery",
        "id":@integer@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null,
            "contactName": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "comments": ""
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null,
            "contactName": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "comments": "Beware of the dog\nShe bites"
        }
      }
      """
    Given the OAuth client "Acme" sends a "GET" request to "/api/deliveries/1"
    Then the response status code should be 200

  Scenario: Create delivery with implicit pickup address with OAuth (with before & after)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "pickup": {
          "before": "tomorrow 13:00"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli",
          "before": "tomorrow 13:30"
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"@string@.startsWith('/api/deliveries')",
        "@type":"http://schema.org/ParcelDelivery",
        "id":@integer@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": ""
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": ""
        }
      }
      """

  Scenario: Create delivery with pickup & dropoff with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "pickup": {
          "address": "24, Rue de la Paix",
          "doneBefore": "tomorrow 13:00"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli",
          "doneBefore": "tomorrow 13:30"
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"@string@.startsWith('/api/deliveries')",
        "@type":"http://schema.org/ParcelDelivery",
        "id":@integer@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": ""
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": ""
        }
      }
      """

  Scenario: Create delivery with implicit pickup address & implicit time with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "dropoff": {
          "address": "48, Rue de Rivoli",
          "doneBefore": "2018-08-29 13:30:00"
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"@string@.startsWith('/api/deliveries')",
        "@type":"http://schema.org/ParcelDelivery",
        "id":@integer@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29')",
          "doneBefore":"@string@.startsWith('2018-08-29')",
          "comments": ""
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29T13:30:00')",
          "doneBefore":"@string@.startsWith('2018-08-29T13:30:00')",
          "comments": ""
        }
      }
      """

  Scenario: Create delivery with latLng with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "dropoff": {
          "address": {
            "streetAddress": "48, Rue de Rivoli Paris",
            "latLng": [48.857127, 2.354766],
            "telephone": "+33612345678",
            "contactName": "John Doe"
          },
          "before": "2018-08-29 13:30:00"
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"@string@.startsWith('/api/deliveries')",
        "@type":"http://schema.org/ParcelDelivery",
        "id":@integer@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29')",
          "doneBefore":"@string@.startsWith('2018-08-29')",
          "comments": ""
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":48.857127,
              "longitude":2.354766
            },
            "streetAddress":@string@,
            "telephone": "+33612345678",
            "name":null,
            "contactName": "John Doe"
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29T13:30:00')",
          "doneBefore":"@string@.startsWith('2018-08-29T13:30:00')",
          "comments": ""
        }
      }
      """

  Scenario: Create delivery with latLng & timeSlot with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "dropoff": {
          "address": {
            "streetAddress": "48, Rue de Rivoli Paris",
            "latLng": [48.857127, 2.354766],
            "telephone": "+33612345678"
          },
          "timeSlot": "2018-08-29 10:00-11:00"
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"@string@.startsWith('/api/deliveries')",
        "@type":"http://schema.org/ParcelDelivery",
        "id":@integer@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29')",
          "doneBefore":"@string@.startsWith('2018-08-29')",
          "comments": ""
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":48.857127,
              "longitude":2.354766
            },
            "streetAddress":@string@,
            "telephone": "+33612345678",
            "name":null,
            "contactName": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29T11:00')",
          "doneBefore":"@string@.startsWith('2018-08-29T11:00')",
          "comments": ""
        }
      }
      """

  Scenario: Create delivery with existing address & timeSlot with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "dropoff": {
          "address": "/api/addresses/2",
          "timeSlot": "2018-08-29 10:00-11:00"
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"@string@.startsWith('/api/deliveries')",
        "@type":"http://schema.org/ParcelDelivery",
        "id":@integer@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":@...@,
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29T11:00')",
          "doneBefore":"@string@.startsWith('2018-08-29T11:00')",
          "comments": ""
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"/api/addresses/2",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":48.864577,
              "longitude":2.333338
            },
            "streetAddress":"18, avenue Ledru-Rollin 75012 Paris 12ème",
            "telephone":null,
            "name":null,
            "contactName": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29T11:00')",
          "doneBefore":"@string@.startsWith('2018-08-29T11:00')",
          "comments": ""
        }
      }
      """

  Scenario: Check delivery returns HTTP 400
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has check expression "distance < 4000"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries/assert" with body:
      """
      {
        "dropoff": {
          "address": "48, Rue de Rivoli",
          "doneBefore": "tomorrow 13:30"
        }
      }
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ConstraintViolationList",
        "@type":"ConstraintViolationList",
        "hydra:title":"An error occurred",
        "hydra:description":@string@,
        "violations":[
          {
            "propertyPath":"items",
            "message":@string@
          }
        ]
      }
      """

  Scenario: Check delivery returns HTTP 400 (with JWT)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    Given the store with name "Acme" has check expression "distance < 4000"
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/deliveries/assert" with body:
      """
      {
        "store": "/api/stores/1",
        "dropoff": {
          "address": "48, Rue de Rivoli",
          "doneBefore": "tomorrow 13:30"
        }
      }
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ConstraintViolationList",
        "@type":"ConstraintViolationList",
        "hydra:title":"An error occurred",
        "hydra:description":@string@,
        "violations":[
          {
            "propertyPath":"items",
            "message":@string@
          }
        ]
      }
      """

  Scenario: Check delivery returns HTTP 200
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has check expression "distance < 10000"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries/assert" with body:
      """
      {
        "dropoff": {
          "address": "48, Rue de Rivoli",
          "doneBefore": "tomorrow 13:30"
        }
      }
      """
    Then the response status code should be 200
