Feature: Urbantz

  Scenario: Create delivery from order
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has an API key
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the store with name "Acme" sends a "POST" request to "/api/urbantz/deliveries" with body:
      """
      [
        {
          "type":"delivery",
          "serviceTime":0,
          "maxTransitTime":0,
          "activity":"delivery",
          "instructions":"4ème étage",
          "contact":{
            "language":"fr",
            "account":"2080118",
            "person":"Test Nantais",
            "phone":"06XXXXXXX",
            "name":null,
            "buildingInfo":{
              "floor":null,
              "digicode1":""
            }
          },
          "address":{
            "country":"FR",
            "number":"4",
            "street":"Rue Perrault",
            "city":"Nantes",
            "zip":"44000",
            "latitude": 47.21160575171194,
            "longitude": -1.550412088473055
          },
          "timeWindow":{
            "start":"2021-08-27T08:25:00.000Z",
            "stop":"2021-08-27T09:00:00.000Z"
          },
          "taskId":"1269-0009999999",
          "items":[
            {
              "type":"SEC",
              "quantity":1,
              "dimensions":{
                "weight":1.082,
                "volume":9.396321
              },
              "barcode":"12690002936057",
              "barcodeEncoding":"CODE128"
            }
          ],
          "metadata":{
            "codePe":"AN7809"
          }
        }
      ]
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":@string@,
        "@type":"http://schema.org/ParcelDelivery",
        "id":@integer@,
        "pickup":{
          "@id":@string@,
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":"/api/addresses/1",
            "@type":"http://schema.org/Place",
            "contactName":null,
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":48.864577,
              "longitude":2.333338
            },
            "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
            "telephone":null,
            "name":null
          },
          "comments":"",
          "after":"2021-08-27T10:15:52+02:00",
          "before":"2021-08-27T10:30:52+02:00",
          "doneAfter":"2021-08-27T10:15:52+02:00",
          "doneBefore":"2021-08-27T10:30:52+02:00"
        },
        "dropoff":{
          "@id":@string@,
          "@type":"Task",
          "id":@integer@,
          "status":"TODO",
          "address":{
            "@id":@string@,
            "@type":"http://schema.org/Place",
            "contactName":"Test Nantais",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":47.211605751712,
              "longitude":-1.5504120884731
            },
            "streetAddress":"4 Rue Perrault, 44000 Nantes",
            "telephone":null,
            "name":null
          },
          "comments":"",
          "after":"2021-08-27T10:25:00+02:00",
          "before":"2021-08-27T11:00:00+02:00",
          "doneAfter":"2021-08-27T10:25:00+02:00",
          "doneBefore":"2021-08-27T11:00:00+02:00"
        }
      }
      """
