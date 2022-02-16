Feature: Multi-step deliveries

  Scenario: Create delivery with pickup & dropoff with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
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
        "pickup":{
          "@id":"@string@.startsWith('/api/tasks')",
          "@type":"Task",
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
            "contactName": null
          },
          "doneAfter":"@string@.isDateTime()",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "comments": "1.50 kg",
          "weight":1500,
          "packages": []
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
              "@type":"GeoCoordinates",
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
          "comments": "",
          "weight":1500,
          "packages": []
        }
      }
      """

  Scenario: Create delivery with pickup & dropoff + packages with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
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
        "pickup":{
          "@id":@string@,
          "@type":"Task",
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
            "name":null
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
              "quantity":4
            }
          ]
        },
        "dropoff":{
          "@id":@string@,
          "@type":"Task",
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
            "name":null
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
              "quantity":2
            }
          ]
        }
      }
      """
