Feature: Multi-step deliveries

  Scenario: Create delivery with pickup & dropoff with OAuth
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the setting "latlng" has value "48.856613,2.352222"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "tasks": [
          {
            "type": "pickup",
            "address": "24, Rue de la Paix",
            "before": "tomorrow 13:00"
          },
          {
            "type": "dropoff",
            "address": "48, Rue de Rivoli",
            "before": "tomorrow 13:30",
            "weight": 1500
          }
        ]
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
          "type": "PICKUP",
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
          "comments": "1.50 kg",
          "weight":1500,
          "packages": [],
          "barcode": @array@,
          "createdAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
          "id":@integer@,
          "type": "DROPOFF",
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
          "weight":1500,
          "packages": [],
          "barcode": @array@,
          "createdAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {
          "@id":"@string@.startsWith('/api/orders')",
          "@type":"http://schema.org/Order",
          "number": @string@,
          "total": @integer@,
          "taxTotal": @integer@,
          "paymentGateway": @string@
        }
      }
      """

  Scenario: Create delivery with pickup & dropoff + packages with OAuth
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the setting "latlng" has value "48.856613,2.352222"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "tasks": [
          {
            "type": "pickup",
            "address": "24, Rue de la Paix",
            "before": "tomorrow 13:00"
          },
          {
            "type": "dropoff",
            "address": "48, Rue de Rivoli",
            "before": "tomorrow 13:30",
            "weight": 1500,
            "packages": [
              {"type": "XL", "quantity": 2}
            ]
          },
          {
            "type": "dropoff",
            "address": "52, Rue de Rivoli, Paris France",
            "before": "tomorrow 14:30",
            "weight": 1500,
            "packages": [
              {"type": "XL", "quantity": 2}
            ]
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/deliveries/1",
        "@type":"http://schema.org/ParcelDelivery",
        "id":@integer@,
        "tasks":@array@,
        "pickup":{
          "@id":@string@,
          "@type":"Task",
          "id":@integer@,
          "type":"PICKUP",
          "status":"TODO",
          "address":{
            "@id":@string@,
            "@type":"http://schema.org/Place",
            "contactName":null,
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@number@,
              "longitude":@number@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "description": null
          },
          "comments":"4 × XL\n3.00 kg",
          "weight":3000,
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneAfter":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "packages":[
            {
              "type":"XL",
              "name":"XL",
              "quantity":4,
              "volume_per_package": 3,
              "short_code": "XL",
              "labels":@array@
            }
          ],
          "barcode": @array@,
          "createdAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":@string@,
          "@type":"Task",
          "type":"DROPOFF",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":@string@,
            "@type":"http://schema.org/Place",
            "contactName":null,
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@number@,
              "longitude":@number@
            },
            "streetAddress":@string@,
            "telephone":null,
            "name":null,
            "description": null
          },
          "comments":"",
          "weight":1500,
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneAfter":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "packages":[
            {
              "type":"XL",
              "name":"XL",
              "quantity":2,
              "volume_per_package": 3,
              "short_code": "XL",
              "labels":@array@
            }
          ],
          "barcode": @array@,
          "createdAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {
          "@id":"@string@.startsWith('/api/orders')",
          "@type":"http://schema.org/Order",
          "number": @string@,
          "total": @integer@,
          "taxTotal": @integer@,
          "paymentGateway": @string@
        }
      }
      """

  Scenario: Create delivery with multiple pickups & 1 dropoff + packages with OAuth
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the setting "latlng" has value "48.856613,2.352222"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "tasks": [
          {
            "type": "pickup",
            "address": "24, Rue de la Paix Paris",
            "before": "tomorrow 13:00"
          },
          {
            "type": "pickup",
            "address": "22, Rue de la Paix Paris",
            "before": "tomorrow 13:15"
          },
          {
            "type": "dropoff",
            "address": "48, Rue de Rivoli Paris",
            "before": "tomorrow 13:30",
            "weight": 1500,
            "packages": [
              {"type": "XL", "quantity": 2}
            ]
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/deliveries/1",
        "@type":"http://schema.org/ParcelDelivery",
        "id":1,
        "tasks":[
          {
            "@type":"Task",
            "@id":"/api/tasks/1",
            "type":"PICKUP",
            "packages":[],
            "@*@":"@*@"
          },
          {
            "@type":"Task",
            "type":"PICKUP",
            "@id":"/api/tasks/2",
            "packages":[],
            "@*@":"@*@"
          },
          {
            "@type":"Task",
            "type":"DROPOFF",
            "@id":"/api/tasks/3",
            "packages":[
              {
                "type":"XL",
                "name":"XL",
                "quantity":2,
                "volume_per_package": 3,
                "short_code": "XL",
                "labels":@array@
              }
            ],
            "@*@":"@*@"
          }
        ],
        "pickup":{
          "@id":"/api/tasks/1",
          "@type":"Task",
          "id":@integer@,
          "type":"PICKUP",
          "status":"TODO",
          "address":{
            "@id":@string@,
            "@type":"http://schema.org/Place",
            "contactName":null,
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":"24 Rue de la Paix, 75002 Paris",
            "telephone":null,
            "name":null,
            "description": null
          },
          "comments":"2 × XL\n1.50 kg",
          "weight":null,
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneAfter":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "packages":[],
          "barcode": @array@,
          "createdAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"/api/tasks/3",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":@string@,
            "@type":"http://schema.org/Place",
            "contactName":null,
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":48.856872,
              "longitude":2.354618
            },
            "streetAddress":"48 Rue de Rivoli, 75004 Paris",
            "telephone":null,
            "name":null,
            "description": null
          },
          "comments":"",
          "weight":1500,
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneAfter":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "packages":[
            {
              "type":"XL",
              "name":"XL",
              "quantity":2,
              "volume_per_package": 3,
              "short_code": "XL",
              "labels":@array@
            }
          ],
          "barcode": @array@,
          "createdAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {
          "@id":"@string@.startsWith('/api/orders')",
          "@type":"http://schema.org/Order",
          "number": @string@,
          "total": @integer@,
          "taxTotal": @integer@,
          "paymentGateway": @string@
        }
      }
      """

  Scenario: Create delivery with multiple pickups & 1 dropoff, without time slot for pickups
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the setting "latlng" has value "48.856613,2.352222"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "tasks": [
          {
            "type": "pickup",
            "address": "24, Rue de la Paix Paris"
          },
          {
            "type": "pickup",
            "address": "22, Rue de la Paix Paris"
          },
          {
            "type": "dropoff",
            "address": "48, Rue de Rivoli Paris",
            "before": "tomorrow 13:30",
            "weight": 1500,
            "packages": [
              {"type": "XL", "quantity": 2}
            ]
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/deliveries/1",
        "@type":"http://schema.org/ParcelDelivery",
        "id":1,
        "tasks":@array@,
        "pickup":{
          "@id":"/api/tasks/1",
          "@type":"Task",
          "id":@integer@,
          "type":"PICKUP",
          "status":"TODO",
          "address":{
            "@id":@string@,
            "@type":"http://schema.org/Place",
            "contactName":null,
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":@double@,
              "longitude":@double@
            },
            "streetAddress":"24 Rue de la Paix, 75002 Paris",
            "telephone":null,
            "name":null,
            "description": null
          },
          "comments":"2 × XL\n1.50 kg",
          "weight":null,
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneAfter":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "packages":[],
          "barcode": @array@,
          "createdAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "dropoff":{
          "@id":"/api/tasks/3",
          "@type":"Task",
          "id":@integer@,
          "type":"DROPOFF",
          "status":"TODO",
          "address":{
            "@id":@string@,
            "@type":"http://schema.org/Place",
            "contactName":null,
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":48.856872,
              "longitude":2.354618
            },
            "streetAddress":"48 Rue de Rivoli, 75004 Paris",
            "telephone":null,
            "name":null,
            "description": null
          },
          "comments":"",
          "weight":1500,
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneAfter":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "packages":[
            {
              "type":"XL",
              "name":"XL",
              "quantity":2,
              "volume_per_package": 3,
              "short_code": "XL",
              "labels":@array@
            }
          ],
          "barcode": @array@,
          "createdAt":"@string@.isDateTime()",
          "tags": [],
          "metadata": {"@*@": "@*@"}
        },
        "trackingUrl": @string@,
        "order": {
          "@id":"@string@.startsWith('/api/orders')",
          "@type":"http://schema.org/Order",
          "number": @string@,
          "total": @integer@,
          "taxTotal": @integer@,
          "paymentGateway": @string@
        }
      }
      """

  Scenario: Suggest delivery optimizations with OAuth
    Given the fixtures files are loaded:
      | stores.yml          |
    Given the setting "latlng" has value "48.856613,2.352222"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries/suggest_optimizations" with body:
      """
      {
        "tasks": [
          {
            "type": "pickup",
            "address": "24 Rue de Rivoli, 75004 Paris",
            "after": "tomorrow 13:00",
            "before": "tomorrow 13:15"
          },
          {
            "type": "dropoff",
            "address": "45 Rue d'Ulm, 75005 Paris",
            "after": "tomorrow 13:45",
            "before": "tomorrow 15:30"
          },
          {
            "type": "dropoff",
            "address": "45 Rue de Rivoli, 75001 Paris",
            "after": "tomorrow 13:15",
            "before": "tomorrow 13:30"
          }
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
          "@context": {"@*@": "@*@"},
          "@type": "OptimizationSuggestions",
          "@id": @string@,
          "suggestions": [
            {
              "@type": "OptimizationSuggestion",
              "@id": @string@,
              "gain": {
                "type": "distance",
                "amount": @integer@
              },
              "order": [
                0,
                2,
                1
              ]
            }
          ]
      }
      """

  Scenario: Create delivery with multiple pickups & 1 dropoff + packages in pickups with OAuth
    Given the fixtures files are loaded:
      | sylius_products.yml |
      | sylius_taxation.yml |
      | payment_methods.yml |
      | stores.yml          |
    Given the setting "latlng" has value "48.856613,2.352222"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/deliveries" with body:
      """
      {
        "tasks": [
          {
            "type": "pickup",
            "address": "24, Rue de la Paix Paris",
            "before": "tomorrow 13:00",
            "weight": 1500,
            "packages": [
              {"type": "XL", "quantity": 2}
            ]
          },
          {
            "type": "pickup",
            "address": "22, Rue de la Paix Paris",
            "before": "tomorrow 13:15",
            "weight": 1500,
            "packages": [
              {"type": "XL", "quantity": 3}
            ]
          },
          {
            "type": "dropoff",
            "address": "48, Rue de Rivoli Paris",
            "before": "tomorrow 13:30"
          }
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@type":"http://schema.org/ParcelDelivery",
        "@id":"/api/deliveries/1",
        "id":@integer@,
        "pickup":{"@*@":"@*@"},
        "dropoff":{"@*@":"@*@"},
        "tasks":[
          {
            "@type":"Task",
            "@id":"/api/tasks/1",
            "type":"PICKUP",
            "packages":[
              {
                "type":"XL",
                "name":"XL",
                "quantity":2,
                "volume_per_package": 3,
                "short_code": "XL",
                "labels":@array@
              }
            ],
            "@*@":"@*@"
          },
          {
            "@type":"Task",
            "type":"PICKUP",
            "@id":"/api/tasks/2",
            "packages":[
              {
                "type":"XL",
                "name":"XL",
                "quantity":3,
                "volume_per_package": 3,
                "short_code": "XL",
                "labels":@array@
              }
            ],
            "@*@":"@*@"
          },
          {
            "@type":"Task",
            "type":"DROPOFF",
            "@id":"/api/tasks/3",
            "packages":[
              {
                "type":"XL",
                "name":"XL",
                "quantity":5,
                "volume_per_package": 3,
                "short_code": "XL",
                "labels":@array@
              }
            ],
            "@*@":"@*@"
          }
        ],
        "trackingUrl":@string@,
        "order": {
          "@id":"@string@.startsWith('/api/orders')",
          "@type":"http://schema.org/Order",
          "number": @string@,
          "total": @integer@,
          "taxTotal": @integer@,
          "paymentGateway": @string@
        }
      }
      """
    Given I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "GET" request to "/api/tasks/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """git
      {
        "@id":"/api/tasks/1",
        "type":"PICKUP",
        "status":"TODO",
        "packages":[
          {
            "name":"XL",
            "type":"XL",
            "quantity":2,
            "volume_per_package":3,
            "short_code":"XL",
            "labels":"@array@.count(2)"
          }
        ],
        "@*@":"@*@"
      }
      """
