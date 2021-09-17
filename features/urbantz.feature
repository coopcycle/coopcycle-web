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

  Scenario: Receive webhook for TaskChanged event
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has an API key
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the store with name "Acme" sends a "POST" request to "/api/urbantz/webhook/TaskChanged" with body:
      """
      [
        {
          "initialSequence": 0,
          "trackingId": "abcdef-123456-4abc-acbacbfcabcfbcbfbacbfab",
          "extTrackId": "dlv_pQB5NV1LzOyXJPEP30xgjaGkYo3WlZvA",
          "type": "delivery",
          "taskId": "my-task-id",
          "taskReference": "task-ref-001-d",
          "progress": "ANNOUNCED",
          "status": "PENDING",
          "client": "507f191e810c19729de860ea",
          "hub": "507f191e810c19729de860ea",
          "hubName": "My Warehouse",
          "associatedName": "Lumikko Oyj",
          "associated": "5c3c63c23c32c30cb3cc1234c",
          "dependency": "507f191e810c19729de860ea,",
          "hasDependency": "507f191e810c19729de860ea,",
          "round": "R01",
          "archived": false,
          "actualTime": {
            "arrive": {
              "when": "2019-03-24T12:34:56.123Z",
              "location": {
                "type": "Point",
                "geometry": [
                  0
                ]
              },
              "forced": false,
              "isCorrectAddress": false
            }
          },
          "order": "5c98f80a6b3dc61664c05cbf",
          "paymentType": "CCOD",
          "platform": "5c98f80a6b3dc61664c05cbf",
          "platformName": "Cortedal SYS, Oy",
          "endpoint": "5c3c645e3b37f30b3fc0240f",
          "errorLocation": {
            "addressLines": [
              "string"
            ],
            "cleanScore": 0,
            "geocodeScore": 98,
            "location": {
              "type": "Point",
              "geometry": [
                0
              ]
            }
          },
          "issues": [
            {
              "type": "GEOCODING",
              "code": 0,
              "line": 0,
              "error": "string"
            }
          ],
          "notifications": [
            {
              "notifiationId": "string",
              "sentDate": "2019-12-23T12:34:56.123Z",
              "by": {
                "firstName": "John",
                "lastName": "Doe",
                "email": "john.doe@example.3w",
                "phoneNumber": "32123123123",
                "picture": "/some/folder/image/c1235b6a253cd12d3",
                "reference": {
                  "name": "self",
                  "value": "john.doe@example.3w"
                },
                "_id": "507f191e810c19729de860ea",
                "id": "507f191e810c19729de860ea",
                "externalId": "string"
              }
            }
          ],
          "notificationSettings": {
            "sms": false,
            "email": false
          },
          "optimizationGroup": "string",
          "optimizationCount": 0,
          "replanned": false,
          "sourceHash": "string",
          "updated": "2019-03-24T12:34:56.123Z",
          "when": "2019-03-24T12:34:56.123Z",
          "products": [
            {
              "productId": "001234",
              "name": "CHEESE",
              "description": "Goat cheese pack, 400gr",
              "type": "food",
              "barcode": "11231212121",
              "quantity": 3,
              "unitPrice": 13.95,
              "isSubstitution": false,
              "quantityRejected": 0,
              "rejectedReason": {
                "_id": "507f191e810c19729de860ea",
                "name": "damaged"
              }
            }
          ],
          "hasRejectedProducts": false,
          "activity": "classic",
          "sequence": 1,
          "id": "5c98f80a6b3dc61664c05cbb",
          "flux": "5c3c63c23c32c30cb3cc1234c",
          "collectedAmount": 10.2,
          "closureDate": "2019-03-25T15:50:50.123Z",
          "by": "5c3c63c23c32c30cb3cc1234c",
          "attempts": 0,
          "arriveTime": "2019-03-25T15:50:50.123Z",
          "announcement": "507f191e810c19729de860ea",
          "shift": "string",
          "serviceTime": 0,
          "measuredServiceTime": {
            "durationSeconds": 0,
            "isLastOfRound": true,
            "areTrackingDataSuitable": true
          },
          "date": "2019-03-13T12:34:56.012Z",
          "product": "string",
          "metadata": {
            "property1": 23,
            "property2": "Hello World"
          },
          "dimensions": {
            "weight": 200,
            "volume": 0.2
          },
          "timeWindow": {
            "start": "2019-03-13T12:34:56.012Z",
            "stop": "2019-03-13T12:34:56.012Z"
          },
          "timeWindow2": {
            "start": "2019-03-13T12:34:56.012Z",
            "stop": "2019-03-13T12:34:56.012Z"
          },
          "contact": {
            "account": "ACC123456789",
            "name": "Acme Inc.",
            "person": "Paco Jones",
            "phone": "+32 477 99 99 99",
            "email": "something@not-a-real-email.org",
            "extraPhones": [
              "+32 477 99 99 99"
            ],
            "extraEmails": [
              "something@not-a-real-email.org"
            ],
            "language": "fr",
            "buildingInfo": {
              "floor": 5,
              "hasElevator": true,
              "digicode1": "1234A",
              "digicode2": "4321A",
              "hasInterphone": true,
              "interphoneCode": "4524#"
            }
          },
          "location": {
            "building": "A cool building name",
            "number": "251",
            "street": "Avenue Louise",
            "city": "Brussels",
            "zip": "1050",
            "origin": "string",
            "country": "Belgium",
            "location": {
              "geometry": [
                0
              ],
              "type": "Point"
            },
            "address": "251 avenue louise, 1050 Brussels, Belgium",
            "addressLines": [
              "string"
            ],
            "cleanScore": 41,
            "geocodeScore": 98
          },
          "source": {
            "building": "A cool building name",
            "number": "251",
            "street": "Avenue Louise",
            "location": {
              "geometry": [
                0
              ],
              "type": "Point"
            },
            "address": "251 Avenue Louise, 1050 BRUXELLES, BELGIQUE",
            "city": "Brussels",
            "zip": "1050",
            "country": "Belgium",
            "cleanScore": 41,
            "geocodeScore": 98,
            "addressLines": [
              "251 Avenue Louise",
              "1050 Brussels",
              "Belgium"
            ]
          },
          "hasBeenPaid": false,
          "price": 0,
          "driver": "5c98f80a6b3dc61664c05cbb",
          "items": [
            {
              "_id": "507f191e810c19729de860ea",
              "type": "Box",
              "status": "PENDING",
              "name": "Coca Cola pack",
              "description": "Big box with a red mark on the top",
              "barcode": "09876543211234",
              "barcodeEncoding": "CODE128",
              "reference": "09876543211234",
              "quantity": 1,
              "processedQuantity": 1,
              "dimensions": {
                "weight": 200,
                "volume": 0.2
              },
              "damaged": {
                "confirmed": false,
                "pictures": [
                  "string"
                ]
              },
              "labels": [
                "frozen"
              ],
              "skills": [
                "speaks_french"
              ],
              "log": [
                {
                  "_id": "507f191e810c19729de860ea",
                  "when": "2019-03-13T12:34:56.012Z",
                  "to": "PENDING",
                  "by": "string"
                }
              ],
              "metadata": {
                "property1": 23,
                "property2": "Hello World"
              },
              "conditionalChecklists": [
                {
                  "name": "string",
                  "questions": [
                    {
                      "question": "string",
                      "tips": "string",
                      "success": 0,
                      "failed": 0
                    }
                  ]
                }
              ],
              "group": "string"
            }
          ]
        }
      ]
      """
    Then the response status code should be 200
    # And print last response

  Scenario: Receive webhook for TaskChanged event
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has an API key
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the store with name "Acme" sends a "POST" request to "/api/urbantz/webhook/Foo" with body:
      """
      [
        {
          "extTrackId": "dlv_pQB5NV1LzOyXJPEP30xgjaGkYo3WlZvA"
        }
      ]
      """
    Then the response status code should be 404
