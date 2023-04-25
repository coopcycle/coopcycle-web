Feature: Urbantz

  Scenario: Receive webhook for TasksAnnounced event
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has an API key
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the store with name "Acme" sends a "POST" request to "/api/urbantz/webhook/tasks_announced" with body:
      """
      [
        {
          "source":{
            "location":{
              "type":"Point",
              "geometry":[

              ]
            },
            "addressLines":[

            ],
            "geocodeScore":0,
            "cleanScore":0,
            "number":"4",
            "street":"Rue Perrault",
            "city":"Nantes",
            "zip":"44000",
            "country":"FR",
            "address":"Rue Perrault 4 44000 Nantes FR"
          },
          "location":{
            "location":{
              "type":"Point",
              "geometry":[
                -1.5506787323970848,
                47.21125182318541
              ]
            },
            "addressLines":[
              "Rue Perrault",
              "44000",
              "Nantes",
              "FRA"
            ],
            "geocodeScore":80,
            "cleanScore":0,
            "number":null,
            "street":"Rue Perrault",
            "city":"Nantes",
            "zip":"44000",
            "country":"FRA",
            "address":"Rue Perrault 44000 Nantes FRA",
            "origin":"ADDRESS_BOOK",
            "precision":"street"
          },
          "notificationSettings":{
            "sms":true,
            "email":true
          },
          "contact":{
            "buildingInfo":{
              "floor":null,
              "digicode1":""
            },
            "extraEmails":[

            ],
            "extraPhones":[

            ],
            "account":"2080118",
            "name":null,
            "person":"Test Nantais",
            "phone":"06XXXXXXX",
            "language":"fr"
          },
          "requires":{
            "dispatcher":{
              "scan":false
            },
            "driver":{
              "prepCheckList":false,
              "prepScan":false,
              "signatureAndComment":false,
              "signatureAndItemConcerns":false,
              "signature":false,
              "scan":false,
              "comment":false,
              "photo":false,
              "contactless":false
            },
            "stop":{
              "onSite":false
            },
            "failure":{
              "photo":false
            },
            "dropOff":{
              "driver":{
                "prepCheckList":false,
                "prepScan":false,
                "signatureAndComment":false,
                "signatureAndItemConcerns":false,
                "signature":false,
                "scan":false,
                "comment":false,
                "photo":false
              },
              "stop":{
                "onSite":false
              }
            }
          },
          "delay":{
            "time":0,
            "when":"2021-09-23T15:12:03.876Z"
          },
          "timeWindow":{
            "start":"2021-09-23T08:25:00.000Z",
            "stop":"2021-09-23T09:00:00.000Z"
          },
          "actualTime":{
            "arrive":{
              "location":{
                "type":"Point",
                "geometry":[

                ]
              }
            }
          },
          "execution":{
            "contactless":{
              "forced":false
            },
            "timer":{
              "timestamps":[

              ]
            }
          },
          "collect":{
            "activated":false
          },
          "assets":{
            "deliver":[

            ],
            "return":[

            ]
          },
          "status":"PENDING",
          "activity":"classic",
          "skills":[],
          "labels":[],
          "attempts":1,
          "carrierAssociationRejected":null,
          "numberOfPlannings":0,
          "timeWindowMargin":0,
          "optimizationCount":0,
          "hasBeenPaid":null,
          "closureDate":null,
          "lastOfflineUpdatedAt":null,
          "replanned":false,
          "archived":false,
          "setToInvoice":false,
          "paymentType":null,
          "collectedAmount":0,
          "categories":[],
          "_id":"614c99434a4181badb8fff6c",
          "announcement":"614c994356e310cc1208a649",
          "date":"2021-09-23T00:00:00.000Z",
          "endpoint":"6128960cb13a3dd81ac9d1ac",
          "taskId":"1269-00099999991",
          "type":"delivery",
          "announcementUpdate":"614c994356e310cc1208a649",
          "attachments":[],
          "by":"6128955c9ad1277b740efc33",
          "categoriesDetails":[],
          "client":"Coopcycle",
          "customerCalls":[],
          "dimensions":{
            "bac":0,
            "volume":9.396321,
            "weight":1.082
          },
          "flux":"612895c82132e8cab82a147a",
          "hasRejectedProducts":false,
          "id":"614c99434a4181badb8fff6c",
          "imagePath":"https://backend.urbantz.com/pictures/platforms/612894c781af110161fa20cd/",
          "instructions":"4ème étage",
          "issues":[],
          "items":[
            {
              "damaged":{
                "confirmed":false,
                "pictures":[

                ],
                "picturesInfo":[

                ]
              },
              "status":"PENDING",
              "quantity":1,
              "labels":[

              ],
              "skills":[

              ],
              "lastOfflineUpdatedAt":null,
              "_id":"614c99434a4181c5298fff6f",
              "type":"SEC",
              "barcode":"12690002936057",
              "barcodeEncoding":"CODE128",
              "dimensions":{
                "weight":1.082,
                "bac":0,
                "volume":9.396321
              },
              "log":[
                {
                  "_id":"614c99434a418124478fff70",
                  "to":"PENDING",
                  "when":"2021-09-23T15:12:03.870Z"
                }
              ]
            }
          ],
          "log":[
            {
              "_id":"614c99434a4181a4278fff73",
              "to":"GEOCODED",
              "when":"2021-09-23T15:12:03.892Z",
              "by":null
            }
          ],
          "metadata":{
            "codePe":"AN7809"
          },
          "notifications":[],
          "platform":"612894c781af110161fa20cd",
          "platformName":"Les Coursiers Nantais",
          "products":[],
          "progress":"GEOCODED",
          "returnedProducts":[],
          "serviceTime":0,
          "trackingId":"614c9943-4a4181ba-db8fff6c-bd321648",
          "updated":"2021-09-23T15:12:03.869Z",
          "when":"2021-09-23T15:12:03.869Z",
          "hub":"61289572c2b7aab94f380d76",
          "hubName":"Coopcycle",
          "targetFlux":"61289572c2b7aab94f380d76__612895c82132e8cab82a147a_2021-09-23",
          "zone":null,
          "zoneId":null,
          "order":"614c99434a418164848fff7d"
        }
      ]
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/UrbantzWebhook",
        "@id":"/api/urbantz_webhooks/tasks_announced",
        "@type":"UrbantzWebhook",
        "deliveries":[
          {
            "@id":"/api/deliveries/1",
            "@type":"http://schema.org/ParcelDelivery",
            "trackingUrl": @string@
          }
        ]
      }
      """

  Scenario: Receive webhook for TasksAnnounced event with multiple hubs
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has an API key
    And the store with name "Acme" is associated with Urbantz hub "61289572c2b7aab94f380d76"
    And the store with name "Acme2" is associated with Urbantz hub "61289572c2b7aab94f380d77"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the store with name "Acme" sends a "POST" request to "/api/urbantz/webhook/tasks_announced" with body:
      """
      [
        {
          "source":{
            "location":{
              "type":"Point",
              "geometry":[

              ]
            },
            "addressLines":[

            ],
            "geocodeScore":0,
            "cleanScore":0,
            "number":"4",
            "street":"4 Rue Perrault",
            "city":"Nantes",
            "zip":"44000",
            "country":"FR",
            "address":"Rue Perrault 4 44000 Nantes FR"
          },
          "location":{
            "location":{
              "type":"Point",
              "geometry":[
                -1.5506787323970848,
                47.21125182318541
              ]
            },
            "addressLines":[
              "Rue Perrault",
              "44000",
              "Nantes",
              "FRA"
            ],
            "geocodeScore":80,
            "cleanScore":0,
            "number":null,
            "street":"Rue Perrault",
            "city":"Nantes",
            "zip":"44000",
            "country":"FRA",
            "address":"Rue Perrault 44000 Nantes FRA",
            "origin":"ADDRESS_BOOK",
            "precision":"street"
          },
          "notificationSettings":{
            "sms":true,
            "email":true
          },
          "contact":{
            "buildingInfo":{
              "floor":null,
              "digicode1":""
            },
            "extraEmails":[

            ],
            "extraPhones":[

            ],
            "account":"2080118",
            "name":null,
            "person":"Test Nantais",
            "phone":"06XXXXXXX",
            "language":"fr"
          },
          "requires":{
            "dispatcher":{
              "scan":false
            },
            "driver":{
              "prepCheckList":false,
              "prepScan":false,
              "signatureAndComment":false,
              "signatureAndItemConcerns":false,
              "signature":false,
              "scan":false,
              "comment":false,
              "photo":false,
              "contactless":false
            },
            "stop":{
              "onSite":false
            },
            "failure":{
              "photo":false
            },
            "dropOff":{
              "driver":{
                "prepCheckList":false,
                "prepScan":false,
                "signatureAndComment":false,
                "signatureAndItemConcerns":false,
                "signature":false,
                "scan":false,
                "comment":false,
                "photo":false
              },
              "stop":{
                "onSite":false
              }
            }
          },
          "delay":{
            "time":0,
            "when":"2021-09-23T15:12:03.876Z"
          },
          "timeWindow":{
            "start":"2021-09-23T08:25:00.000Z",
            "stop":"2021-09-23T09:00:00.000Z"
          },
          "actualTime":{
            "arrive":{
              "location":{
                "type":"Point",
                "geometry":[

                ]
              }
            }
          },
          "execution":{
            "contactless":{
              "forced":false
            },
            "timer":{
              "timestamps":[

              ]
            }
          },
          "collect":{
            "activated":false
          },
          "assets":{
            "deliver":[

            ],
            "return":[

            ]
          },
          "status":"PENDING",
          "activity":"classic",
          "skills":[],
          "labels":[],
          "attempts":1,
          "carrierAssociationRejected":null,
          "numberOfPlannings":0,
          "timeWindowMargin":0,
          "optimizationCount":0,
          "hasBeenPaid":null,
          "closureDate":null,
          "lastOfflineUpdatedAt":null,
          "replanned":false,
          "archived":false,
          "setToInvoice":false,
          "paymentType":null,
          "collectedAmount":0,
          "categories":[],
          "_id":"614c99434a4181badb8fff6c",
          "announcement":"614c994356e310cc1208a649",
          "date":"2021-09-23T00:00:00.000Z",
          "endpoint":"6128960cb13a3dd81ac9d1ac",
          "taskId":"1269-00099999991",
          "type":"delivery",
          "announcementUpdate":"614c994356e310cc1208a649",
          "attachments":[],
          "by":"6128955c9ad1277b740efc33",
          "categoriesDetails":[],
          "client":"Coopcycle",
          "customerCalls":[],
          "dimensions":{
            "bac":0,
            "volume":9.396321,
            "weight":1.082
          },
          "flux":"612895c82132e8cab82a147a",
          "hasRejectedProducts":false,
          "id":"614c99434a4181badb8fff6c",
          "imagePath":"https://backend.urbantz.com/pictures/platforms/612894c781af110161fa20cd/",
          "instructions":"4ème étage",
          "issues":[],
          "items":[
            {
              "damaged":{
                "confirmed":false,
                "pictures":[

                ],
                "picturesInfo":[

                ]
              },
              "status":"PENDING",
              "quantity":1,
              "labels":[

              ],
              "skills":[

              ],
              "lastOfflineUpdatedAt":null,
              "_id":"614c99434a4181c5298fff6f",
              "type":"SEC",
              "barcode":"12690002936057",
              "barcodeEncoding":"CODE128",
              "dimensions":{
                "weight":1.082,
                "bac":0,
                "volume":9.396321
              },
              "log":[
                {
                  "_id":"614c99434a418124478fff70",
                  "to":"PENDING",
                  "when":"2021-09-23T15:12:03.870Z"
                }
              ]
            }
          ],
          "log":[
            {
              "_id":"614c99434a4181a4278fff73",
              "to":"GEOCODED",
              "when":"2021-09-23T15:12:03.892Z",
              "by":null
            }
          ],
          "metadata":{
            "codePe":"AN7809"
          },
          "notifications":[],
          "platform":"612894c781af110161fa20cd",
          "platformName":"Les Coursiers Nantais",
          "products":[],
          "progress":"GEOCODED",
          "returnedProducts":[],
          "serviceTime":0,
          "trackingId":"614c9943-4a4181ba-db8fff6c-bd321648",
          "updated":"2021-09-23T15:12:03.869Z",
          "when":"2021-09-23T15:12:03.869Z",
          "hub":"61289572c2b7aab94f380d77",
          "hubName":"Coopcycle",
          "targetFlux":"61289572c2b7aab94f380d76__612895c82132e8cab82a147a_2021-09-23",
          "zone":null,
          "zoneId":null,
          "order":"614c99434a418164848fff7d"
        }
      ]
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/UrbantzWebhook",
        "@id":"/api/urbantz_webhooks/tasks_announced",
        "@type":"UrbantzWebhook",
        "deliveries":[
          {
            "@id":"/api/deliveries/1",
            "@type":"http://schema.org/ParcelDelivery",
            "trackingUrl": @string@
          }
        ]
      }
      """
    Given the store with name "Acme2" has an API key
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the store with name "Acme2" sends a "GET" request to "/api/deliveries/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/deliveries/1",
        "@type":"http://schema.org/ParcelDelivery",
        "id":1,
        "pickup":{
          "@id":"/api/tasks/1",
          "@type":"Task",
          "id":1,
          "status":"TODO",
          "address":{
            "@id":"/api/addresses/2",
            "@type":"http://schema.org/Place",
            "contactName":null,
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":48.864577,
              "longitude":2.333338
            },
            "streetAddress":"18, avenue Ledru-Rollin 75012 Paris 12ème",
            "telephone":null,
            "name":null
          },
          "comments":"Coopcycle\n\nCommande n° 1269-00099999991\n0 × bac(s)\n1.082 kg\n\n\n1.08 kg",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneAfter":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "weight":1082,
          "packages":[],
          "createdAt":"@string@.isDateTime()",
          "tour":null
        },
        "dropoff":{
          "@id":"/api/tasks/2",
          "@type":"Task",
          "id":2,
          "status":"TODO",
          "address":{
            "@id":"/api/addresses/4",
            "@type":"http://schema.org/Place",
            "contactName":"Test Nantais",
            "geo":{
              "@type":"GeoCoordinates",
              "latitude":47.211251823185,
              "longitude":-1.5506787323971
            },
            "streetAddress":"4 Rue Perrault, 44000 Nantes",
            "telephone":null,
            "name":null
          },
          "comments":"",
          "after":"@string@.isDateTime()",
          "before":"@string@.isDateTime()",
          "doneAfter":"@string@.isDateTime()",
          "doneBefore":"@string@.isDateTime()",
          "weight":1082,
          "packages": [],
          "createdAt":"@string@.isDateTime()",
          "tour":null
        },
        "trackingUrl": @string@
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

  Scenario: Receive webhook for unknown event
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
