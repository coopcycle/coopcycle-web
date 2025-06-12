Feature: Deliveries

  Scenario: Not authorized to create deliveries
    Given the fixtures files are loaded:
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
      | deliveries.yml      |
    And the store with name "Acme2" has an OAuth client named "Acme2"
    And the OAuth client with name "Acme2" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme2" sends a "GET" request to "/api/deliveries/1"
    Then the response status code should be 403

  Scenario: Missing time window
    Given the fixtures files are loaded:
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
            "message":@string@,
            "code":@string@
          },
          {
            "propertyPath":"items[1].task.doneBefore",
            "message":@string@,
            "code":@string@
          }
        ]
      }
      """

  Scenario: Create delivery with implicit pickup address with OAuth
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"PICKUP",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode":{"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"DROPOFF",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "comments": "Beware of the dog\nShe bites",
          "weight":null,
          "packages": [],
          "barcode":{"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "GET" request to "/api/deliveries/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/deliveries/1",
        "@type":"http://schema.org/ParcelDelivery",
        "id":1,
        "pickup":{"@*@":"@*@"},
        "dropoff":{"@*@":"@*@"},
        "tasks":@array@,
        "trackingUrl": @string@
      }
      """

  Scenario: Create delivery with weight in dropoff task
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
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
          "comments": "Beware of the dog\nShe bites",
          "weight": 2000
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"PICKUP",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "comments": "2.00 kg",
          "weight": 2000,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"DROPOFF",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "comments": "Beware of the dog\nShe bites",
          "weight": 2000,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "GET" request to "/api/deliveries/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/deliveries/1",
        "@type":"http://schema.org/ParcelDelivery",
        "id":1,
        "pickup":{"@*@":"@*@"},
        "dropoff":{"@*@":"@*@"},
        "tasks":@array@,
        "trackingUrl": @string@
      }
      """

  Scenario: Create delivery with weight and packages
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
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
          "comments": "Beware of the dog\nShe bites",
          "weight": 6000,
          "packages": [
            {"type": "XL", "quantity": 2}
          ]
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"PICKUP",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "comments": "2 × XL\n6.00 kg",
          "weight": 6000,
          "packages": [
            {
              "type": "XL",
              "name": "XL",
              "quantity": 2,
              "volume_per_package": 3,
              "short_code": "AB",
              "labels": @array@
            }
          ],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"DROPOFF",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "comments": "Beware of the dog\nShe bites",
          "weight": 6000,
          "packages": [
            {
              "type": "XL",
              "name": "XL",
              "quantity": 2,
              "volume_per_package": 3,
              "short_code": "AB",
              "labels": @array@
            }
          ],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "GET" request to "/api/deliveries/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/deliveries/1",
        "@type":"http://schema.org/ParcelDelivery",
        "id":1,
        "pickup":{"@*@":"@*@"},
        "dropoff":{"@*@":"@*@"},
        "tasks":@array@,
        "trackingUrl": @string@
      }
      """

  Scenario: Create delivery with implicit pickup address with OAuth (with before & after)
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "pickup": {
          "before": "2022-03-25 13:00"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli",
          "after": "2022-03-25 12:30",
          "before": "2022-03-25 13:30"
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"PICKUP",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"DROPOFF",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime().startsWith(\"2022-03-25T12:30:00\")",
          "before":"@string@.isDateTime().startsWith(\"2022-03-25T13:30:00\")",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with pickup & dropoff with OAuth
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"PICKUP",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"DROPOFF",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with pickup & dropoff as an admin
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "store": "/api/stores/1",
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"PICKUP",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"DROPOFF",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with pickup & dropoff as an admin in a store without pricing
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "store": "/api/stores/8",
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"PICKUP",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with pickup & dropoff as an admin in a store with invalid pricing
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "store": "/api/stores/9",
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"PICKUP",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with timeSlot and range in ISO 8601 as an admin
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "bob" is authenticated
    Given the current time is "2020-04-02 11:00:00"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "store":"/api/stores/1",
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "timeSlotUrl": "/api/time_slots/1",
          "timeSlot": "2020-04-02T10:00:00Z/2020-04-02T12:00:00Z"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "timeSlotUrl": "/api/time_slots/1",
          "timeSlot": "2020-04-02T10:00:00Z/2020-04-02T12:00:00Z"
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
          "tasks":@array@,
          "pickup":{
            "@id":"@string@.startsWith('/api/tasks')",
            "@type":"Task",
            "id":@integer@,
            "status":"TODO",
            "type":"PICKUP",
            "address":{
              "@id":"@string@.startsWith('/api/addresses')",
              "@type":"http://schema.org/Place",
              "geo":{
                "@type":"GeoCoordinates",
                "latitude":@double@,
                "longitude":@double@
              },
              "streetAddress":@string@,
              "telephone":null,
              "name":null,
              "contactName": null,
              "description": null
            },
            "doneAfter":"@string@.isDateTime()",
            "after":"@string@.isDateTime()",
            "before":"@string@.isDateTime()",
            "doneBefore":"@string@.isDateTime()",
            "comments": "",
            "weight": null,
            "packages": [],
            "barcode": {"@*@":"@*@"},
            "createdAt":"@string@.isDateTime()",
            "updatedAt":"@string@.isDateTime()",
            "tags": [],
            "metadata": {"@*@": "@*@"}
          },
          "dropoff":{
            "@id":"@string@.startsWith('/api/tasks')",
            "@type":"Task",
            "id":@integer@,
            "status":"TODO",
            "type":"DROPOFF",
            "address":{
              "@id":"@string@.startsWith('/api/addresses')",
              "@type":"http://schema.org/Place",
              "geo":{
                "@type":"GeoCoordinates",
                "latitude":@double@,
                "longitude":@double@
              },
              "streetAddress":@string@,
              "telephone":null,
              "name":null,
              "contactName": null,
              "description": null
            },
            "doneAfter":"2020-04-02T12:00:00+02:00",
            "after":"2020-04-02T12:00:00+02:00",
            "before":"2020-04-02T14:00:00+02:00",
            "doneBefore":"2020-04-02T14:00:00+02:00",
            "comments": "",
            "weight":null,
            "packages": [],
            "barcode": {"@*@":"@*@"},
            "createdAt":"@string@.isDateTime()",
            "updatedAt":"@string@.isDateTime()",
            "tags": [],
            "metadata": {"@*@": "@*@"}
          },
          "trackingUrl": @string@,
          "order": {"@*@": "@*@"}
        }
      """

  Scenario: Create delivery with implicit timeSlot as an admin
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "bob" is authenticated
    Given the current time is "2020-04-02 11:00:00"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "store":"/api/stores/1",
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "after": "2020-04-02 12:00",
          "before": "2020-04-02 14:00"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "after": "2020-04-02 12:00",
          "before": "2020-04-02 14:00"
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
          "tasks":@array@,
          "pickup":{
            "@id":"@string@.startsWith('/api/tasks')",
            "@type":"Task",
            "id":@integer@,
            "status":"TODO",
            "type":"PICKUP",
            "address":{
              "@id":"@string@.startsWith('/api/addresses')",
              "@type":"http://schema.org/Place",
              "geo":{
                "@type":"GeoCoordinates",
                "latitude":@double@,
                "longitude":@double@
              },
              "streetAddress":@string@,
              "telephone":null,
              "name":null,
              "contactName": null,
              "description": null
            },
            "doneAfter":"@string@.isDateTime()",
            "after":"@string@.isDateTime()",
            "before":"@string@.isDateTime()",
            "doneBefore":"@string@.isDateTime()",
            "comments": "",
            "weight": null,
            "packages": [],
            "barcode": {"@*@":"@*@"},
            "createdAt":"@string@.isDateTime()",
            "updatedAt":"@string@.isDateTime()",
            "tags": [],
            "metadata": {"@*@": "@*@"}
          },
          "dropoff":{
            "@id":"@string@.startsWith('/api/tasks')",
            "@type":"Task",
            "id":@integer@,
            "status":"TODO",
            "type":"DROPOFF",
            "address":{
              "@id":"@string@.startsWith('/api/addresses')",
              "@type":"http://schema.org/Place",
              "geo":{
                "@type":"GeoCoordinates",
                "latitude":@double@,
                "longitude":@double@
              },
              "streetAddress":@string@,
              "telephone":null,
              "name":null,
              "contactName": null,
              "description": null
            },
            "doneAfter":"2020-04-02T12:00:00+02:00",
            "after":"2020-04-02T12:00:00+02:00",
            "before":"2020-04-02T14:00:00+02:00",
            "doneBefore":"2020-04-02T14:00:00+02:00",
            "comments": "",
            "weight":null,
            "packages": [],
            "barcode": {"@*@":"@*@"},
            "createdAt":"@string@.isDateTime()",
            "updatedAt":"@string@.isDateTime()",
            "tags": [],
            "metadata": {"@*@": "@*@"}
          },
          "trackingUrl": @string@,
          "order": {"@*@": "@*@"}
        }
      """

  Scenario: Can't create a delivery with invalid timeSlotUrl as an admin
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "bob" is authenticated
    Given the current time is "2020-04-02 11:00:00"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "store":"/api/stores/1",
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "timeSlotUrl": "/api/time_slots/123456",
          "timeSlot": "2020-04-02T10:00:00Z/2020-04-02T12:00:00Z"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "timeSlotUrl": "/api/time_slots/123456",
          "timeSlot": "2020-04-02T10:00:00Z/2020-04-02T12:00:00Z"
        }
      }
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
        {
          "@context":"/api/contexts/Error",
          "@type":"hydra:Error",
          "hydra:title":"An error occurred",
          "hydra:description":"Item not found for \"/api/time_slots/123456\".",
          "trace":@array@
        }
      """

  Scenario: Can't create a delivery with invalid timeSlot range as an admin
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "bob" is authenticated
    Given the current time is "2020-04-02 11:00:00"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "store":"/api/stores/1",
        "pickup": {
          "address": "24, Rue de la Paix Paris",
          "timeSlotUrl": "/api/time_slots/1",
          "timeSlot": "2020-04-02T10:05:00Z/2020-04-02T12:05:00Z"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli Paris",
          "timeSlotUrl": "/api/time_slots/1",
          "timeSlot": "2020-04-02T10:05:00Z/2020-04-02T12:05:00Z"
        }
      }
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
        {
          "@context":"/api/contexts/Error",
          "@type":"hydra:Error",
          "hydra:title":"An error occurred",
          "hydra:description":"task.timeSlot.invalid",
          "trace":@array@
        }
      """

  Scenario: Create delivery with pickup & dropoff as a store owner
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "store": "/api/stores/1",
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "type":"PICKUP",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with pickup & dropoff as a store owner in a store without pricing
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme no pricing" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "store": "/api/stores/8",
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"PICKUP",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with pickup & dropoff as a store owner in a store with invalid pricing
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme invalid pricing" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "store": "/api/stores/9",
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "type":"PICKUP",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "type":"DROPOFF",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with implicit pickup address & implicit time with OAuth
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"PICKUP",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29')",
          "doneBefore":"@string@.startsWith('2018-08-29')",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29T13:30:00')",
          "doneBefore":"@string@.startsWith('2018-08-29T13:30:00')",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with details with OAuth
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
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
            "telephone": "0612345678",
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
          "type":"PICKUP",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29')",
          "doneBefore":"@string@.startsWith('2018-08-29')",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": "+33612345678",
            "name":null,
            "contactName": "John Doe",
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29T13:30:00')",
          "doneBefore":"@string@.startsWith('2018-08-29T13:30:00')",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "tasks":@array@,
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with latLng with OAuth
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
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
            "description": "Code A1B2",
            "latLng": [ 48.857127, 2.354766 ],
            "telephone": "0612345678",
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"PICKUP",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29')",
          "doneBefore":"@string@.startsWith('2018-08-29')",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":48.857127,
              "longitude":2.354766
            },
            "streetAddress":@string@,
            "telephone": "+33612345678",
            "name":null,
            "contactName": "John Doe",
            "description": "Code A1B2"
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29T13:30:00')",
          "doneBefore":"@string@.startsWith('2018-08-29T13:30:00')",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with latLng & timeSlot with OAuth
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"PICKUP",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29')",
          "doneBefore":"@string@.startsWith('2018-08-29')",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"DROPOFF",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":48.857127,
              "longitude":2.354766
            },
            "streetAddress":@string@,
            "telephone": "+33612345678",
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29T11:00')",
          "doneBefore":"@string@.startsWith('2018-08-29T11:00')",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with latLng & timeSlot ISO 8601 with OAuth
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the current time is "2020-04-02 11:00:00"
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
          "timeSlot": "2020-04-02T10:00:00Z/2020-04-02T12:00:00Z"
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"PICKUP",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":48.857127,
              "longitude":2.354766
            },
            "streetAddress":@string@,
            "telephone": "+33612345678",
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"2020-04-02T12:00:00+02:00",
          "after":"2020-04-02T12:00:00+02:00",
          "before":"2020-04-02T14:00:00+02:00",
          "doneBefore":"2020-04-02T14:00:00+02:00",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with existing address & timeSlot with OAuth
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"PICKUP",
          "status":"TODO",
          "address":{"@*@":"@*@"},
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29T11:00')",
          "doneBefore":"@string@.startsWith('2018-08-29T11:00')",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":"/api/addresses/2",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":48.864577,
              "longitude":2.333338
            },
            "streetAddress":"18, avenue Ledru-Rollin 75012 Paris 12ème",
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.startsWith('2018-08-29T11:00')",
          "doneBefore":"@string@.startsWith('2018-08-29T11:00')",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with address.telephone = false with OAuth
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
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
            "telephone": false,
            "contactName": "John Doe"
          },
          "before": "2018-08-29 13:30:00"
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON

  Scenario: Check delivery returns HTTP 400
    Given the fixtures files are loaded:
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
            "message":@string@,
            "code":null
          }
        ]
      }
      """

  Scenario: Check delivery returns HTTP 400 (with JWT) when dropoff is outside check zone
    Given the fixtures files are loaded:
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
            "message":@string@,
            "code":null
          }
        ]
      }
      """

  Scenario: Check delivery returns HTTP 200 when dropoff is in check zone
    Given the fixtures files are loaded:
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

  Scenario: Check delivery returns HTTP 201 when creating order and sending "store" key as defaut pickup
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    And the store with name "Acme" has order creation enabled
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "store": "/api/stores/1",
        "dropoff": {
          "address": "48, Rue de Rivoli",
          "doneBefore": "tomorrow 13:30"
        }
      }
      """
    Then the response status code should be 201
    And the JSON should match:
      """
    {
        "@context": "/api/contexts/Delivery",
        "@id": "/api/deliveries/1",
        "@type": "http://schema.org/ParcelDelivery",
        "id": @integer@,
        "tasks":@array@,
        "pickup": {
            "@id": "@string@.startsWith('/api/tasks')",
            "@type": "Task",
            "id": @integer@,
            "type": "PICKUP",
            "status": "TODO",
            "address": {
                "@id": "/api/addresses/1",
                "@type": "http://schema.org/Place",
                "contactName": null,
                "geo": {
                    "@type": "GeoCoordinates",
                    "latitude": @double@,
                    "longitude": @double@
                },
                "streetAddress": "272, rue Saint Honoré 75001 Paris 1er",
                "telephone": null,
                "name": null,
                "description": null
            },
            "comments": "",
            "createdAt": "@string@.isDateTime()",
            "updatedAt":"@string@.isDateTime()",
            "weight": null,
            "after": "@string@.isDateTime()",
            "before": "@string@.isDateTime()",
            "doneAfter": "@string@.isDateTime()",
            "doneBefore": "@string@.isDateTime()",
            "packages": [],
            "barcode": {"@*@":"@*@"},
            "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff": {
            "@id": "@string@.startsWith('/api/tasks')",
            "@type": "Task",
            "id": @integer@,
            "type": "DROPOFF",
            "status": "TODO",
            "address": {
                "@id": "/api/addresses/4",
                "@type": "http://schema.org/Place",
                "contactName": null,
                "geo": {
                    "@type": "GeoCoordinates",
                    "latitude": @double@,
                    "longitude": @double@
                },
                "streetAddress": @string@,
                "telephone": null,
                "name": null,
                "description": null
            },
            "comments": "",
            "createdAt": "@string@.isDateTime()",
            "updatedAt":"@string@.isDateTime()",
            "weight": null,
            "after": "@string@.isDateTime()",
            "before": "@string@.isDateTime()",
            "doneAfter": "@string@.isDateTime()",
            "doneBefore": "@string@.isDateTime()",
            "packages": [],
            "barcode": {"@*@":"@*@"},
            "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
    }
  """

  Scenario: Cancel delivery
    Given the fixtures files are loaded:
      | deliveries.yml      |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "DELETE" request to "/api/deliveries/1"
    Then the response status code should be 204

  Scenario: Create delivery with dates in UTC
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the current time is "2022-05-05 12:00:00"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "pickup": {
          "before": "2022-05-06T11:50:00+00:00"
        },
        "dropoff": {
          "address": "48, Rue de Rivoli",
          "after": "2022-05-06T09:50:00+00:00",
          "before": "2022-05-06T11:50:00+00:00"
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"PICKUP",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime().startsWith(\"2022-05-06T11:50:00\")",
          "before":"@string@.isDateTime().startsWith(\"2022-05-06T13:50:00\")",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Send delivery CSV to async import endpoint with Oauth
    Given the fixtures files are loaded:
      | stores.yml          |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "text/csv"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries/import_async" with body:
      """
        "pickup.address","pickup.timeslot","dropoff.address","dropoff.address.name","dropoff.address.telephone","dropoff.comments","dropoff.timeslot"
        "Eulogio Serdan Kalea, 22, 01012 Vitoria-Gasteiz, Espagne","2024-10-31 17:00 - 2024-10-31 20:00","Aldabe 5, 3 ezk Vitoria-Gasteiz","Amaia Marañon","652709377","","2024-10-31 17:00 - 2024-10-31 20:00"
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/DeliveryImportQueue",
        "@id": "@string@.startsWith('/api/delivery_import_queues/')",
        "@type": "DeliveryImportQueue"
      }
      """

  Scenario: Create delivery with tag and then update it with another tag
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
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
          "comments": "Beware of the dog\nShe bites",
          "weight": 2000,
          "tags": ["cold"]
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"PICKUP",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "comments": "2.00 kg",
          "weight": 2000,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"DROPOFF",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "comments": "Beware of the dog\nShe bites",
          "weight": 2000,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [{"name": "COLD", "slug": "cold", "color": "#FF0000"}],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "PUT" request to "/api/deliveries/1" with body:
    """
      {
        "dropoff":{
          "tags": ["cold", "mon-tag"]
        }
      }
    """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"@string@.startsWith('/api/deliveries')",
        "@type":"http://schema.org/ParcelDelivery",
        "id":@integer@,
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"PICKUP",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "comments": "2.00 kg",
          "weight": 2000,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"DROPOFF",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "comments": "Beware of the dog\nShe bites",
          "weight": 2000,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [{"name": "COLD", "slug": "cold", "color": "#FF0000"}, {"name": "MON TAG", "slug": "mon-tag", "color": "#FF00B4"}],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with tags as string
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
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
          "comments": "Beware of the dog\nShe bites",
          "weight": 2000,
          "tags": "cold mon-tag"
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"PICKUP",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "comments": "2.00 kg",
          "weight": 2000,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "type":"DROPOFF",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone": null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "comments": "Beware of the dog\nShe bites",
          "weight": 2000,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [{"name": "COLD", "slug": "cold", "color": "#FF0000"}, {"name": "MON TAG", "slug": "mon-tag", "color": "#FF00B4"}],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """

  Scenario: Create delivery with default courier
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_COURIER"
    And the store with name "Acme" has a default courier with username "bob"
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
          "doneBefore": "tomorrow 13:30"
        }
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "GET" request to "/api/tasks/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "isAssigned": true,
        "assignedTo": "bob",
        "@*@": "@*@"
      }
      """

  Scenario: Create delivery with given price and variant as an admin then edit it with a new price
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/deliveries" with body:
    """
    {
      "store": "/api/stores/1",
      "pickup": {
        "address": "24, Rue de la Paix",
        "doneBefore": "tomorrow 13:00"
      },
      "dropoff": {
        "address": "48, Rue de Rivoli",
        "doneBefore": "tomorrow 13:30"
      },
      "order": {
        "arbitraryPrice": {
          "variantPrice": 1200,
          "variantName": "my custom variant"
        }
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "type":"PICKUP",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """
    Then the database should contain an order with a total price 1200
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/orders/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/Order",
        "@id": "/api/orders/1",
        "@type": "http://schema.org/Order",
        "customer": null,
        "shippingAddress": {
            "@id": "/api/addresses/4",
            "@type": "http://schema.org/Place",
            "description": null,
            "geo": {
                "@type": "GeoCoordinates",
                "latitude": 50.636137,
                "longitude": 3.092335
            },
            "streetAddress": "48 Rue de Rivoli, 59800 Lille",
            "telephone": null,
            "name": null,
            "contactName": null
        },
        "events": [
            {
                "@type": "OrderEvent",
                "type": "order:created",
                "data": [],
                "createdAt": "@string@.isDateTime()"
            },
            {
                "@type": "OrderEvent",
                "type": "order:state_changed",
                "data": {
                    "newState": "new",
                    "triggeredByEvent": {
                        "name": "order:created",
                        "data": []
                    }
                },
                "createdAt": "@string@.isDateTime()"
            }
        ],
        "id": 1,
        "items": [
            {
                "@id": "/api/orders/1/items/1",
                "@type": "OrderItem",
                "id": 1,
                "quantity": 1,
                "unitPrice": 1200,
                "total": 1200,
                "adjustments": {
                    "tax": [
                        {
                            "label": "TVA 0%",
                            "amount": 0
                        }
                    ]
                },
                "name": "On demand delivery",
                "variantName": "my custom variant",
                "vendor": null,
                "player": {
                    "@id": "/api/customers/1",
                    "@type": "Customer",
                    "email": "bob@coopcycle.org",
                    "phoneNumber": null,
                    "tags": [],
                    "metadata": {"@*@": "@*@"},
                    "telephone": null,
                    "username": "bob",
                    "fullName": ""
                }
            }
        ],
        "itemsTotal": 1200,
        "total": 1200,
        "state": "accepted",
        "paymentMethod": "CARD",
        "assignedTo": null,
        "adjustments": {
            "delivery": [],
            "delivery_promotion": [],
            "order_promotion": [],
            "reusable_packaging": [],
            "tax": [],
            "tip": [],
            "incident": []
        },
        "paymentGateway": "stripe",
        "@*@": "@*@"
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/deliveries/1" with body:
    """
      {
        "order": {
          "arbitraryPrice": {
            "variantPrice": 2000,
            "variantName": "my new product name"
          }
        }
      }
    """
    Then the response status code should be 200
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/orders/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
    """
    {
      "@context": "/api/contexts/Order",
      "@id": "/api/orders/1",
      "@type": "http://schema.org/Order",
      "customer": null,
      "shippingAddress": {
          "@id": "/api/addresses/4",
          "@type": "http://schema.org/Place",
          "description": null,
          "geo": {
              "@type": "GeoCoordinates",
              "latitude": 50.636137,
              "longitude": 3.092335
          },
          "streetAddress": "48 Rue de Rivoli, 59800 Lille",
          "telephone": null,
          "name": null,
          "contactName": null
      },
      "events": [
          {
              "@type": "OrderEvent",
              "type": "order:created",
              "data": [],
              "createdAt": "@string@.isDateTime()"
          },
          {
              "@type": "OrderEvent",
              "type": "order:state_changed",
              "data": {
                  "newState": "new",
                  "triggeredByEvent": {
                      "name": "order:created",
                      "data": []
                  }
              },
              "createdAt": "@string@.isDateTime()"
          }
      ],
      "id": 1,
      "items": [
          {
              "@id": "/api/orders/1/items/2",
              "@type": "OrderItem",
              "id": 2,
              "quantity": 1,
              "unitPrice": 2000,
              "total": 2000,
              "adjustments": {
                  "tax": [
                      {
                          "label": "TVA 0%",
                          "amount": 0
                      }
                  ]
              },
              "name": "On demand delivery",
              "variantName": "my new product name",
              "vendor": null,
              "player": {
                  "@id": "/api/customers/1",
                  "@type": "Customer",
                  "email": "bob@coopcycle.org",
                  "phoneNumber": null,
                  "tags": [],
                  "metadata": {"@*@": "@*@"},
                  "telephone": null,
                  "username": "bob",
                  "fullName": ""
              }
          }
      ],
      "itemsTotal": 2000,
      "total": 2000,
      "state": "accepted",
      "paymentMethod": "CARD",
      "assignedTo": null,
      "adjustments": {
          "delivery": [],
          "delivery_promotion": [],
          "order_promotion": [],
          "reusable_packaging": [],
          "tax": [],
          "tip": [],
          "incident": []
      },
      "paymentGateway": "stripe",
      "@*@": "@*@"
    }
    """
    Then the database should contain an order with a total price 2000

  Scenario: Can not set a price as a store
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456            |
    And the user "sarah" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "sarah"
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/deliveries" with body:
    """
    {
      "store": "/api/stores/1",
      "pickup": {
        "address": "24, Rue de la Paix",
        "doneBefore": "tomorrow 13:00"
      },
      "dropoff": {
        "address": "48, Rue de Rivoli",
        "doneBefore": "tomorrow 13:30"
      },
      "order": {
        "arbitraryPrice": {
          "variantPrice": 1200,
          "variantName": "my custom variant"
        }
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "type":"PICKUP",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """
    Then the database should contain an order with a total price 499

  Scenario: Create delivery with recurrence as an admin
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/deliveries" with body:
    """
    {
      "store": "/api/stores/1",
      "pickup": {
        "address": "24, Rue de la Paix",
        "doneBefore": "tomorrow 13:00"
      },
      "dropoff": {
        "address": "48, Rue de Rivoli",
        "doneBefore": "tomorrow 13:30"
      },
      "rrule": "FREQ=WEEKLY;BYDAY=MO"
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "type":"PICKUP",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/recurrence_rules"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "\/api\/contexts\/RecurrenceRule",
        "@id": "\/api\/recurrence_rules",
        "@type": "hydra:Collection",
        "hydra:member": [
          {
            "@id": "\/api\/recurrence_rules\/1",
            "@type": "RecurrenceRule",
            "name": null,
            "rule": "FREQ=WEEKLY;BYDAY=MO",
            "template": {
              "@type": "hydra:Collection",
              "hydra:member": [
                {
                  "@context": "\/api\/contexts\/Task",
                  "@type": "Task",
                  "type": "PICKUP",
                  "address": {
                    "@id":"@string@.startsWith('/api/addresses')",
                    "@type": "http:\/\/schema.org\/Place",
                    "contactName": null,
                    "description": null,
                    "geo": {
                      "@type": "GeoCoordinates",
                      "latitude":@double@,
                      "longitude":@double@
                    },
                    "postalCode": @string@,
                    "streetAddress": @string@,
                    "telephone": null,
                    "name": null
                  },
                  "comments": "",
                  "doorstep": false,
                  "weight": null,
                  "tags": [],
                  "after": "12:45:00",
                  "before": "13:00:00",
                  "doneAfter": "12:45:00",
                  "doneBefore": "13:00:00",
                  "barcode": {"@*@":"@*@"},
                  "packages": []
                },
                {
                  "@context": "\/api\/contexts\/Task",
                  "@type": "Task",
                  "type": "DROPOFF",
                  "address": {
                    "@id":"@string@.startsWith('/api/addresses')",
                    "@type": "http:\/\/schema.org\/Place",
                    "contactName": null,
                    "description": null,
                    "geo": {
                      "@type": "GeoCoordinates",
                      "latitude":@double@,
                      "longitude":@double@
                    },
                    "postalCode": @string@,
                    "streetAddress": @string@,
                    "telephone": null,
                    "name": null
                  },
                  "comments": "",
                  "doorstep": false,
                  "weight": null,
                  "tags": [],
                  "after": "13:15:00",
                  "before": "13:30:00",
                  "doneAfter": "13:15:00",
                  "doneBefore": "13:30:00",
                  "barcode": {"@*@":"@*@"},
                  "packages": []
                }
              ]
            },
            "store": "\/api\/stores\/1",
            "orgName": "Acme",
            "isCancelled": false
          }
        ],
        "hydra:totalItems": 1
      }
      """

  Scenario: Can not create delivery with recurrence as a store
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "sarah" is loaded:
      | email      | sarah@coopcycle.org |
      | password   | 123456            |
    And the user "sarah" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "sarah"
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/deliveries" with body:
    """
    {
      "store": "/api/stores/1",
      "pickup": {
        "address": "24, Rue de la Paix",
        "doneBefore": "tomorrow 13:00"
      },
      "dropoff": {
        "address": "48, Rue de Rivoli",
        "doneBefore": "tomorrow 13:30"
      },
      "rrule": "FREQ=WEEKLY;BYDAY=MO"
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "type":"PICKUP",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {"@*@": "@*@"}
      }
      """
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/recurrence_rules"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "\/api\/contexts\/RecurrenceRule",
        "@id": "\/api\/recurrence_rules",
        "@type": "hydra:Collection",
        "hydra:member": [],
        "hydra:totalItems": 0
      }
      """
    
  Scenario: Create delivery with a saved order as an admin
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/deliveries" with body:
    """
    {
      "store": "/api/stores/1",
      "pickup": {
        "address": "24, Rue de la Paix",
        "doneBefore": "tomorrow 13:00"
      },
      "dropoff": {
        "address": "48, Rue de Rivoli",
        "doneBefore": "tomorrow 13:30"
      },
      "order": {
        "isSavedOrder": true
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
        "tasks":@array@,
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "type":"PICKUP",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight": null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":"@string@.startsWith('/api/addresses')",
            "@type":"http://schema.org/Place",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "contactName": null,
            "description": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "",
          "weight":null,
          "packages": [],
          "barcode": {"@*@":"@*@"},
          "createdAt":"@string@.isDateTime()",
          "updatedAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {
          "@id":"@string@.startsWith('/api/orders')",
          "@type":"Order",
          "id":@integer@,
          "total": @integer@,
          "taxTotal": @integer@,
          "arbitraryPrice":null,
          "isSavedOrder":true
        }
      }
      """
