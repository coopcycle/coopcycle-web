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

  Scenario: Missing time window
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
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
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
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
          "id":@integer@,
          "address":{
            "@context":"/api/contexts/Address",
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null
          },
          "doneBefore":"@string@.isDateTime()"
        },
        "dropoff":{
          "id":@integer@,
          "address":{
            "@context":"/api/contexts/Address",
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null
          },
          "doneBefore":"@string@.isDateTime()",
          "comments": "Beware of the dog\nShe bites"
        },
        "color":@string@
      }
      """

  Scenario: Create delivery with implicit pickup address with OAuth (with before & after)
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
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
          "id":@integer@,
          "address":{
            "@context":"/api/contexts/Address",
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null
          },
          "doneBefore":"@string@.isDateTime()"
        },
        "dropoff":{
          "id":@integer@,
          "address":{
            "@context":"/api/contexts/Address",
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null
          },
          "doneBefore":"@string@.isDateTime()"
        },
        "color":@string@
      }
      """

  Scenario: Create delivery with pickup & dropoff with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
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
          "id":@integer@,
          "address":{
            "@context":"/api/contexts/Address",
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null
          },
          "doneBefore":"@string@.isDateTime()"
        },
        "dropoff":{
          "id":@integer@,
          "address":{
            "@context":"/api/contexts/Address",
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null
          },
          "doneBefore":"@string@.isDateTime()"
        },
        "color":@string@
      }
      """

  Scenario: Create delivery with implicit pickup address & implicit time with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
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
          "id":@integer@,
          "address":{
            "@context":"/api/contexts/Address",
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null
          },
          "doneBefore":"@string@.startsWith('2018-08-29')"
        },
        "dropoff":{
          "id":@integer@,
          "address":{
            "@context":"/api/contexts/Address",
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null
          },
          "doneBefore":"@string@.startsWith('2018-08-29T13:30:00')"
        },
        "color":@string@
      }
      """

  Scenario: Create delivery with latLng with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
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
          "id":@integer@,
          "address":{
            "@context":"/api/contexts/Address",
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null
          },
          "doneBefore":"@string@.startsWith('2018-08-29')"
        },
        "dropoff":{
          "id":@integer@,
          "address":{
            "@context":"/api/contexts/Address",
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":48.857127,
              "longitude":2.354766
            },
            "streetAddress":@string@,
            "telephone": "+33612345678",
            "name":null
          },
          "doneBefore":"@string@.startsWith('2018-08-29T13:30:00')"
        },
        "color":@string@
      }
      """

  Scenario: Create delivery with latLng & timeSlot with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
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
          "id":@integer@,
          "address":{
            "@context":"/api/contexts/Address",
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null
          },
          "doneBefore":"@string@.startsWith('2018-08-29')"
        },
        "dropoff":{
          "id":@integer@,
          "address":{
            "@context":"/api/contexts/Address",
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":48.857127,
              "longitude":2.354766
            },
            "streetAddress":@string@,
            "telephone": "+33612345678",
            "name":null
          },
          "doneBefore":"@string@.startsWith('2018-08-29T11:00')"
        },
        "color":@string@
      }
      """

  Scenario: Create delivery with existing address & timeSlot with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
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
          "id":@integer@,
          "address":@...@,
          "doneBefore":"@string@.startsWith('2018-08-29T11:00')"
        },
        "dropoff":{
          "id":@integer@,
          "address":{
            "@context":"/api/contexts/Address",
            "@id":"/api/addresses/2",
            "@type":"http://schema.org/Place",
            "geo":{
              "latitude":48.864577,
              "longitude":2.333338
            },
            "streetAddress":"18, avenue Ledru-Rollin 75012 Paris 12ème",
            "telephone":null,
            "name":null
          },
          "doneBefore":"@string@.startsWith('2018-08-29T11:00')"
        },
        "color":"#b2213a"
      }
      """

  Scenario: Check delivery returns HTTP 400
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the store with name "Acme" has check expression "distance < 4000"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries/check" with body:
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

  Scenario: Check delivery returns HTTP 200
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the store with name "Acme" has check expression "distance < 10000"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries/check" with body:
      """
      {
        "dropoff": {
          "address": "48, Rue de Rivoli",
          "doneBefore": "tomorrow 13:30"
        }
      }
      """
    Then the response status code should be 200
