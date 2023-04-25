Feature: Woopit

  Scenario: Receive & confirm quote
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | stores.yml          |
      | woopit_integrations.yml          |
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme2" has an API key
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    And the store with name "Acme2" sends a "POST" request to "/api/woopit/quotes" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "retailer": {
          "name": "Enseigne A",
          "code": "enseigne-a",
          "store": {
            "id": "store123",
            "name": "Magasin C",
            "contact": {
              "firstName": "Pierre",
              "lastName": "Dupond",
              "phone": "+33600000000",
              "email": "pierre.dupond@mail.fr"
            }
          }
        },
        "picking": {
          "location": {
            "addressLine1": "24, Rue de la Paix",
            "addressLine2": "Entrée B",
            "postalCode": "75002",
            "city": "Paris",
            "country": "FR",
            "elevator": false,
            "floor": 4,
            "comment": "Paquet à l'accueil",
            "doorCode": "1234"
          },
          "infos": "Travaux sur le boulevard.",
          "interval": [
            {
              "start": "2019-12-04T12:30:00+0000",
              "end": "2019-12-04T13:30:00+0000"
            }
          ]
        },
        "delivery": {
          "location": {
            "addressLine1": "48, Rue de Rivoli",
            "addressLine2": "Appt 9",
            "elevator": true,
            "floor": 2,
            "postalCode": "75004",
            "city": "Paris",
            "country": "FR",
            "doorCode": "5678",
            "comment": "Ascenseur à gauche"
          },
          "interval": [
            {
              "start": "2019-12-06T13:00:00+0000",
              "end": "2019-12-06T15:00:00+0000"
            }
          ]
        },
        "packages": [
          {
            "id": 1,
            "length": {
              "value": 121.8,
              "unit": "cm"
            },
            "width": {
              "value": 57.5,
              "unit": "cm"
            },
            "height": {
              "value": 55,
              "unit": "cm"
            },
            "weight": {
              "value": 33.4,
              "unit": "kg"
            },
            "products": [
              {
                "type": "TYPOLOGY_GENERIC",
                "ean": "4897567123548",
                "cug": "e21fe21",
                "label": "Produit label 1",
                "quantity": 1
              }
            ],
            "quantity": 2
          },
          {
            "length": {
                "value": 12,
                "unit": "cm"
            },
            "width": {
                "value": 12,
                "unit": "cm"
            },
            "height": {
                "value": 12,
                "unit": "cm"
            },
            "weight": {
                "value": 1,
                "unit": "kg"
            },
            "quantity": 1,
            "products": [
                {
                    "quantity": 1,
                    "type": "TYPOLOGY_GENERIC",
                    "label": "Produit 1"
                }
            ]
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then print last response
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      [
        {
          "quoteId":"yXJPEP30xgja",
          "price":{
            "taxExcludedAmount":416,
            "taxIncludedAmount":499,
            "taxAmount":83,
            "currency":"EUR"
          },
          "vehicleType":"VEHICLE_TYPE_BIKE"
        }
      ]
      """
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    # https://woop.stoplight.io/docs/carrier/b3A6MzYyMDcwOTU-delivery-request
    And the store with name "Acme2" sends a "POST" request to "/api/woopit/deliveries" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "quoteId": "yXJPEP30xgja",
        "retailer": {
          "name": "Enseigne A",
          "code": "enseigne-a",
          "store": {
            "id": "store123",
            "name": "Magasin C",
            "contact": {
              "firstName": "Pierre",
              "lastName": "Dupond",
              "phone": "+33600000000",
              "email": "pierre.dupond@mail.fr"
            }
          }
        },
        "picking": {
          "location": {
            "addressLine1": "24, Rue de la Paix",
            "addressLine2": "Entrée B",
            "elevator": false,
            "floor": 4,
            "postalCode": "75002",
            "city": "Paris",
            "country": "FR",
            "comment": "Paquet à l'accueil",
            "doorCode": "1234"
          },
          "infos": "Travaux sur le boulevard.",
          "interval": [
            {
              "start": "2019-12-04T12:30:00+0000",
              "end": "2019-12-04T13:30:00+0000"
            }
          ]
        },
        "delivery": {
          "location": {
            "addressLine1": "48, Rue de Rivoli",
            "addressLine2": "Appt 9",
            "elevator": true,
            "floor": 2,
            "postalCode": "75004",
            "city": "Paris",
            "country": "FR",
            "doorCode": "5678",
            "comment": "Ascenseur à gauche"
          },
          "interval": [
            {
              "start": "2019-12-06T13:00:00+0000",
              "end": "2019-12-06T15:00:00+0000"
            }
          ]
        },
        "packages": [
          {
            "id": 1,
            "length": {
              "value": 121.8,
              "unit": "cm"
            },
            "width": {
              "value": 57.5,
              "unit": "cm"
            },
            "height": {
              "value": 55,
              "unit": "cm"
            },
            "weight": {
              "value": 33.4,
              "unit": "kg"
            },
            "products": [
              {
                "type": "TYPOLOGY_GENERIC",
                "ean": "4897567123548",
                "cug": "e21fe21",
                "label": "Produit label 1",
                "quantity": 1
              }
            ],
            "quantity": 2
          },
          {
            "length": {
                "value": 12,
                "unit": "cm"
            },
            "width": {
                "value": 12,
                "unit": "cm"
            },
            "height": {
                "value": 12,
                "unit": "cm"
            },
            "weight": {
                "value": 1,
                "unit": "kg"
            },
            "quantity": 1,
            "products": [
                {
                    "quantity": 1,
                    "type": "TYPOLOGY_GENERIC",
                    "label": "Produit 1"
                }
            ]
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then print last response
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "deliveryId":"yXJPEP30xgja"
      }
      """

  Scenario: Receive & cancel quote
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | stores.yml          |
      | woopit_integrations.yml          |
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme2" has an API key
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    And the store with name "Acme2" sends a "POST" request to "/api/woopit/quotes" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "retailer": {
          "name": "Enseigne A",
          "code": "enseigne-a",
          "store": {
            "id": "store123",
            "name": "Magasin C",
            "contact": {
              "firstName": "Pierre",
              "lastName": "Dupond",
              "phone": "+33600000000",
              "email": "pierre.dupond@mail.fr"
            }
          }
        },
        "picking": {
          "location": {
            "addressLine1": "24, Rue de la Paix",
            "addressLine2": "",
            "postalCode": "75002",
            "city": "Paris",
            "country": "FR"
          },
          "infos": "Travaux sur le boulevard.",
          "interval": [
            {
              "start": "2019-12-04T12:30:00+0000",
              "end": "2019-12-04T13:30:00+0000"
            }
          ]
        },
        "delivery": {
          "location": {
            "addressLine1": "48, Rue de Rivoli",
            "addressLine2": "",
            "elevator": true,
            "floor": 2,
            "postalCode": "75004",
            "city": "Paris",
            "country": "FR"
          },
          "interval": [
            {
              "start": "2019-12-06T13:00:00+0000",
              "end": "2019-12-06T15:00:00+0000"
            }
          ]
        },
        "packages": [
          {
            "id": 1,
            "length": {
              "value": 121.8,
              "unit": "cm"
            },
            "width": {
              "value": 57.5,
              "unit": "cm"
            },
            "height": {
              "value": 55,
              "unit": "cm"
            },
            "weight": {
              "value": 33.4,
              "unit": "kg"
            },
            "products": [
              {
                "type": "TYPOLOGY_GENERIC",
                "ean": "4897567123548",
                "cug": "e21fe21",
                "label": "Produit label 1",
                "quantity": 1
              }
            ],
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then print last response
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      [
        {
          "quoteId":"yXJPEP30xgja",
          "price":{
            "taxExcludedAmount":416,
            "taxIncludedAmount":499,
            "taxAmount":83,
            "currency":"EUR"
          },
          "vehicleType":"VEHICLE_TYPE_BIKE"
        }
      ]
      """
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    # https://woop.stoplight.io/docs/carrier/b3A6MzYyMDcwOTU-delivery-request
    And the store with name "Acme2" sends a "DELETE" request to "/api/woopit/quotes/yXJPEP30xgja"

  Scenario: Receive, confirm quote & cancel delivery
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | stores.yml          |
      | woopit_integrations.yml          |
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme2" has an API key
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    And the store with name "Acme2" sends a "POST" request to "/api/woopit/quotes" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "retailer": {
          "name": "Enseigne A",
          "code": "enseigne-a",
          "store": {
            "id": "store123",
            "name": "Magasin C",
            "contact": {
              "firstName": "Pierre",
              "lastName": "Dupond",
              "phone": "+33600000000",
              "email": "pierre.dupond@mail.fr"
            }
          }
        },
        "picking": {
          "location": {
            "addressLine1": "24, Rue de la Paix",
            "addressLine2": "",
            "postalCode": "75002",
            "city": "Paris",
            "country": "FR"
          },
          "infos": "Travaux sur le boulevard.",
          "interval": [
            {
              "start": "2019-12-04T12:30:00+0000",
              "end": "2019-12-04T13:30:00+0000"
            }
          ]
        },
        "delivery": {
          "location": {
            "addressLine1": "48, Rue de Rivoli",
            "addressLine2": "",
            "elevator": true,
            "floor": 2,
            "postalCode": "75004",
            "city": "Paris",
            "country": "FR"
          },
          "interval": [
            {
              "start": "2019-12-06T13:00:00+0000",
              "end": "2019-12-06T15:00:00+0000"
            }
          ]
        },
        "packages": [
          {
            "id": 1,
            "length": {
              "value": 121.8,
              "unit": "cm"
            },
            "width": {
              "value": 57.5,
              "unit": "cm"
            },
            "height": {
              "value": 55,
              "unit": "cm"
            },
            "weight": {
              "value": 33.4,
              "unit": "kg"
            },
            "products": [
              {
                "type": "TYPOLOGY_GENERIC",
                "ean": "4897567123548",
                "cug": "e21fe21",
                "label": "Produit label 1",
                "quantity": 1
              }
            ],
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And print last response
    And the JSON should match:
      """
      [{
        "quoteId":"yXJPEP30xgja",
        "price":{
          "taxExcludedAmount":416,
          "taxIncludedAmount":499,
          "taxAmount":83,
          "currency":"EUR"
        },
        "vehicleType":"VEHICLE_TYPE_BIKE"
      }]
      """
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    # https://woop.stoplight.io/docs/carrier/b3A6MzYyMDcwOTU-delivery-request
    And the store with name "Acme2" sends a "POST" request to "/api/woopit/deliveries" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "quoteId": "yXJPEP30xgja",
        "retailer": {
          "name": "Enseigne A",
          "code": "enseigne-a",
          "store": {
            "id": "store123",
            "name": "Magasin C",
            "contact": {
              "firstName": "Pierre",
              "lastName": "Dupond",
              "phone": "+33600000000",
              "email": "pierre.dupond@mail.fr"
            }
          }
        },
        "picking": {
          "location": {
            "addressLine1": "24, Rue de la Paix",
            "addressLine2": "",
            "postalCode": "75002",
            "city": "Paris",
            "country": "FR"
          },
          "infos": "Travaux sur le boulevard.",
          "interval": [
            {
              "start": "2019-12-04T12:30:00+0000",
              "end": "2019-12-04T13:30:00+0000"
            }
          ]
        },
        "delivery": {
          "location": {
            "addressLine1": "48, Rue de Rivoli",
            "addressLine2": "",
            "elevator": true,
            "floor": 2,
            "postalCode": "75004",
            "city": "Paris",
            "country": "FR"
          },
          "interval": [
            {
              "start": "2019-12-06T13:00:00+0000",
              "end": "2019-12-06T15:00:00+0000"
            }
          ]
        },
        "packages": [
          {
            "id": 1,
            "length": {
              "value": 121.8,
              "unit": "cm"
            },
            "width": {
              "value": 57.5,
              "unit": "cm"
            },
            "height": {
              "value": 55,
              "unit": "cm"
            },
            "weight": {
              "value": 33.4,
              "unit": "kg"
            },
            "products": [
              {
                "type": "TYPOLOGY_GENERIC",
                "ean": "4897567123548",
                "cug": "e21fe21",
                "label": "Produit label 1",
                "quantity": 1
              }
            ],
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "deliveryId":"yXJPEP30xgja"
      }
      """
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json"
    And the store with name "Acme2" sends a "DELETE" request to "/api/woopit/deliveries/yXJPEP30xgja"
    Then print last response
    Then the response status code should be 204

  Scenario: Receive, confirm quote & update delivery
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | stores.yml          |
      | woopit_integrations.yml          |
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme2" has an API key
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    And the store with name "Acme2" sends a "POST" request to "/api/woopit/quotes" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "retailer": {
          "name": "Enseigne A",
          "code": "enseigne-a",
          "store": {
            "id": "store123",
            "name": "Magasin C",
            "contact": {
              "firstName": "Pierre",
              "lastName": "Dupond",
              "phone": "+33600000000",
              "email": "pierre.dupond@mail.fr"
            }
          }
        },
        "picking": {
          "location": {
            "addressLine1": "24, Rue de la Paix",
            "addressLine2": "",
            "postalCode": "75002",
            "city": "Paris",
            "country": "FR"
          },
          "infos": "Travaux sur le boulevard.",
          "interval": [
            {
              "start": "2019-12-04T12:30:00+0000",
              "end": "2019-12-04T13:30:00+0000"
            }
          ]
        },
        "delivery": {
          "location": {
            "addressLine1": "48, Rue de Rivoli",
            "addressLine2": "",
            "elevator": true,
            "floor": 2,
            "postalCode": "75004",
            "city": "Paris",
            "country": "FR"
          },
          "interval": [
            {
              "start": "2019-12-06T13:00:00+0000",
              "end": "2019-12-06T15:00:00+0000"
            }
          ]
        },
        "packages": [
          {
            "id": 1,
            "length": {
              "value": 121.8,
              "unit": "cm"
            },
            "width": {
              "value": 57.5,
              "unit": "cm"
            },
            "height": {
              "value": 55,
              "unit": "cm"
            },
            "weight": {
              "value": 33.4,
              "unit": "kg"
            },
            "products": [
              {
                "type": "TYPOLOGY_GENERIC",
                "ean": "4897567123548",
                "cug": "e21fe21",
                "label": "Produit label 1",
                "quantity": 1
              }
            ],
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      [
        {
          "quoteId":"yXJPEP30xgja",
          "price":{
            "taxExcludedAmount":416,
            "taxIncludedAmount":499,
            "taxAmount":83,
            "currency":"EUR"
          },
          "vehicleType":"VEHICLE_TYPE_BIKE"
        }
      ]
      """
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    # https://woop.stoplight.io/docs/carrier/b3A6MzYyMDcwOTU-delivery-request
    And the store with name "Acme2" sends a "POST" request to "/api/woopit/deliveries" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "quoteId": "yXJPEP30xgja",
        "retailer": {
          "name": "Enseigne A",
          "code": "enseigne-a",
          "store": {
            "id": "store123",
            "name": "Magasin C",
            "contact": {
              "firstName": "Pierre",
              "lastName": "Dupond",
              "phone": "+33600000000",
              "email": "pierre.dupond@mail.fr"
            }
          }
        },
        "picking": {
          "location": {
            "addressLine1": "24, Rue de la Paix",
            "addressLine2": "",
            "postalCode": "75002",
            "city": "Paris",
            "country": "FR"
          },
          "infos": "Travaux sur le boulevard.",
          "interval": [
            {
              "start": "2019-12-04T12:30:00+0000",
              "end": "2019-12-04T13:30:00+0000"
            }
          ],
          "contact": {
            "firstName": "joe",
            "lastName": "doe",
            "phone": "+3312345678"
          }
        },
        "delivery": {
          "location": {
            "addressLine1": "48, Rue de Rivoli",
            "addressLine2": "",
            "elevator": true,
            "floor": 2,
            "postalCode": "75004",
            "city": "Paris",
            "country": "FR"
          },
          "interval": [
            {
              "start": "2019-12-06T13:00:00+0000",
              "end": "2019-12-06T15:00:00+0000"
            }
          ],
          "contact": {
            "firstName": "path",
            "lastName": "met",
            "phone": "+3312345687"
          }
        },
        "packages": [
          {
            "id": 1,
            "length": {
              "value": 121.8,
              "unit": "cm"
            },
            "width": {
              "value": 57.5,
              "unit": "cm"
            },
            "height": {
              "value": 55,
              "unit": "cm"
            },
            "weight": {
              "value": 33.4,
              "unit": "kg"
            },
            "products": [
              {
                "type": "TYPOLOGY_GENERIC",
                "ean": "4897567123548",
                "cug": "e21fe21",
                "label": "Produit label 1",
                "quantity": 1
              }
            ],
            "quantity": 1
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "deliveryId":"yXJPEP30xgja"
      }
      """
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    And the store with name "Acme2" sends a "PATCH" request to "/api/woopit/deliveries/yXJPEP30xgja" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "quoteId": "yXJPEP30xgja",
        "picking": {
          "address": {
            "addressLine1": "24, Rue de la Paix",
            "addressLine2": "Entrée B",
            "postalCode": "75002",
            "city": "Paris",
            "country": "FR",
            "floor": 3,
            "doorCode": "A"
          },
          "infos": "Travaux sur le boulevard.",
          "interval": [
            {
              "start": "2019-12-04T13:00:00+0000",
              "end": "2019-12-04T13:30:00+0000"
            }
          ],
          "contact": {
            "firstName": "joe",
            "lastName": "pass",
            "phone": "+3312345678"
          }
        },
        "delivery": {
          "address": {
            "addressLine1": "20, Rue Malher",
            "addressLine2": "Entrée D",
            "elevator": true,
            "floor": 2,
            "postalCode": "75004",
            "city": "Paris",
            "country": "FR",
            "doorCode": 23
          },
          "interval": [
            {
              "start": "2019-12-06T14:00:00+0000",
              "end": "2019-12-06T15:00:00+0000"
            }
          ],
          "contact": {
            "firstName": "path",
            "lastName": "met",
            "phone": "+3345671238"
          }
        },
        "packages": [
          {
            "id": 1,
            "length": {
              "value": 121.8,
              "unit": "cm"
            },
            "width": {
              "value": 77.5,
              "unit": "cm"
            },
            "height": {
              "value": 55,
              "unit": "cm"
            },
            "weight": {
              "value": 33.4,
              "unit": "kg"
            },
            "products": [
              {
                "type": "TYPOLOGY_GENERIC",
                "ean": "4897567123548",
                "cug": "e21fe21",
                "label": "Produit label 1",
                "quantity": 1
              }
            ],
            "quantity": 1
          },
          {
            "length": {
                "value": 12,
                "unit": "cm"
            },
            "width": {
                "value": 12,
                "unit": "cm"
            },
            "height": {
                "value": 12,
                "unit": "cm"
            },
            "weight": {
                "value": 1,
                "unit": "kg"
            },
            "quantity": 2,
            "products": [
                {
                    "quantity": 1,
                    "type": "TYPOLOGY_GENERIC",
                    "label": "Produit 1"
                }
            ]
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 204

  Scenario: Receive & refuse quote due to non existing store
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | stores.yml          |
      | woopit_integrations.yml          |
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme2" has an API key
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    And the store with name "Acme2" sends a "POST" request to "/api/woopit/quotes" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "retailer": {
          "name": "Enseigne A",
          "code": "enseigne-a",
          "store": {
            "id": "store456",
            "name": "Magasin C",
            "contact": {
              "firstName": "Pierre",
              "lastName": "Dupond",
              "phone": "+33600000000",
              "email": "pierre.dupond@mail.fr"
            }
          }
        },
        "picking": {
          "location": {
            "addressLine1": "24, Rue de la Paix",
            "addressLine2": "Entrée B",
            "postalCode": "75002",
            "city": "Paris",
            "country": "FR",
            "elevator": false,
            "floor": 4,
            "comment": "Paquet à l'accueil",
            "doorCode": "1234"
          },
          "infos": "Travaux sur le boulevard.",
          "interval": [
            {
              "start": "2019-12-04T12:30:00+0000",
              "end": "2019-12-04T13:30:00+0000"
            }
          ]
        },
        "delivery": {
          "location": {
            "addressLine1": "48, Rue de Rivoli",
            "addressLine2": "Appt 9",
            "elevator": true,
            "floor": 2,
            "postalCode": "75004",
            "city": "Paris",
            "country": "FR",
            "doorCode": "5678",
            "comment": "Ascenseur à gauche"
          },
          "interval": [
            {
              "start": "2019-12-06T13:00:00+0000",
              "end": "2019-12-06T15:00:00+0000"
            }
          ]
        },
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 202
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "reasons": [
          "REFUSED_EXCEPTION"
        ],
        "comments": "The store with ID store456 does not exist"
      }
      """

  Scenario: Receive & refuse quote due to delivery area not covered
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | stores.yml          |
      | woopit_integrations.yml          |
    Given the geojson file "woopit_zone" for a zone is loaded
    And the store with name "Acme" has a check expression for zone "woopit_zone"
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme2" has an API key
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    And the store with name "Acme2" sends a "POST" request to "/api/woopit/quotes" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "retailer": {
          "name": "Enseigne A",
          "code": "enseigne-a",
          "store": {
            "id": "store123",
            "name": "Magasin C",
            "contact": {
              "firstName": "Pierre",
              "lastName": "Dupond",
              "phone": "+33600000000",
              "email": "pierre.dupond@mail.fr"
            }
          }
        },
        "picking": {
          "location": {
            "addressLine1": "24, Rue de la Paix",
            "addressLine2": "Entrée B",
            "postalCode": "75002",
            "city": "Paris",
            "country": "FR",
            "elevator": false,
            "floor": 4,
            "comment": "Paquet à l'accueil",
            "doorCode": "1234"
          },
          "infos": "Travaux sur le boulevard.",
          "interval": [
            {
              "start": "2019-12-04T12:30:00+0000",
              "end": "2019-12-04T13:30:00+0000"
            }
          ]
        },
        "delivery": {
          "location": {
            "addressLine1": "153 Bd de Magenta",
            "addressLine2": "Appt 9",
            "elevator": true,
            "floor": 2,
            "postalCode": "75010",
            "city": "Paris",
            "country": "FR",
            "doorCode": "5678",
            "comment": "Ascenseur à gauche"
          },
          "interval": [
            {
              "start": "2019-12-06T13:00:00+0000",
              "end": "2019-12-06T15:00:00+0000"
            }
          ]
        },
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 202
    And the response should be in JSON
    And print last response
    And the JSON should match:
      """
      {
        "reasons": [
          "REFUSED_AREA"
        ],
        "comments": "The collection address is in an area that is not covered by our teams"
      }
      """

  Scenario: Receive & reject quote due to weight
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | stores.yml          |
      | woopit_integrations.yml          |
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme2" has an API key
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    And the store with name "Acme2" sends a "POST" request to "/api/woopit/quotes" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "retailer": {
          "name": "Enseigne A",
          "code": "enseigne-a",
          "store": {
            "id": "store123",
            "name": "Magasin C",
            "contact": {
              "firstName": "Pierre",
              "lastName": "Dupond",
              "phone": "+33600000000",
              "email": "pierre.dupond@mail.fr"
            }
          }
        },
        "picking": {
          "location": {
            "addressLine1": "24, Rue de la Paix",
            "addressLine2": "Entrée B",
            "postalCode": "75002",
            "city": "Paris",
            "country": "FR",
            "elevator": false,
            "floor": 4,
            "comment": "Paquet à l'accueil",
            "doorCode": "1234"
          },
          "infos": "Travaux sur le boulevard.",
          "interval": [
            {
              "start": "2019-12-04T12:30:00+0000",
              "end": "2019-12-04T13:30:00+0000"
            }
          ]
        },
        "delivery": {
          "location": {
            "addressLine1": "48, Rue de Rivoli",
            "addressLine2": "Appt 9",
            "elevator": true,
            "floor": 2,
            "postalCode": "75004",
            "city": "Paris",
            "country": "FR",
            "doorCode": "5678",
            "comment": "Ascenseur à gauche"
          },
          "interval": [
            {
              "start": "2019-12-06T13:00:00+0000",
              "end": "2019-12-06T15:00:00+0000"
            }
          ]
        },
        "packages": [
          {
            "id": 1,
            "length": {
              "value": 121.8,
              "unit": "cm"
            },
            "width": {
              "value": 77.5,
              "unit": "cm"
            },
            "height": {
              "value": 55,
              "unit": "cm"
            },
            "weight": {
              "value": 53.4,
              "unit": "kg"
            },
            "products": [
              {
                "type": "TYPOLOGY_GENERIC",
                "ean": "4897567123548",
                "cug": "e21fe21",
                "label": "Produit label 1",
                "quantity": 1
              }
            ],
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 202
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "reasons": [
          "REFUSED_TOO_HEAVY"
        ]
      }
      """

  Scenario: Receive & reject quote due to width
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | stores.yml          |
      | woopit_integrations.yml          |
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme2" has an API key
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    And the store with name "Acme2" sends a "POST" request to "/api/woopit/quotes" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "retailer": {
          "name": "Enseigne A",
          "code": "enseigne-a",
          "store": {
            "id": "store123",
            "name": "Magasin C",
            "contact": {
              "firstName": "Pierre",
              "lastName": "Dupond",
              "phone": "+33600000000",
              "email": "pierre.dupond@mail.fr"
            }
          }
        },
        "picking": {
          "location": {
            "addressLine1": "24, Rue de la Paix",
            "addressLine2": "Entrée B",
            "postalCode": "75002",
            "city": "Paris",
            "country": "FR",
            "elevator": false,
            "floor": 4,
            "comment": "Paquet à l'accueil",
            "doorCode": "1234"
          },
          "infos": "Travaux sur le boulevard.",
          "interval": [
            {
              "start": "2019-12-04T12:30:00+0000",
              "end": "2019-12-04T13:30:00+0000"
            }
          ]
        },
        "delivery": {
          "location": {
            "addressLine1": "48, Rue de Rivoli",
            "addressLine2": "Appt 9",
            "elevator": true,
            "floor": 2,
            "postalCode": "75004",
            "city": "Paris",
            "country": "FR",
            "doorCode": "5678",
            "comment": "Ascenseur à gauche"
          },
          "interval": [
            {
              "start": "2019-12-06T13:00:00+0000",
              "end": "2019-12-06T15:00:00+0000"
            }
          ]
        },
        "packages": [
          {
            "id": 1,
            "length": {
              "value": 121.8,
              "unit": "cm"
            },
            "width": {
              "value": 77.5,
              "unit": "cm"
            },
            "height": {
              "value": 55,
              "unit": "cm"
            },
            "weight": {
              "value": 23.4,
              "unit": "kg"
            },
            "products": [
              {
                "type": "TYPOLOGY_GENERIC",
                "ean": "4897567123548",
                "cug": "e21fe21",
                "label": "Produit label 1",
                "quantity": 1
              }
            ],
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 202
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "reasons": [
          "REFUSED_TOO_LARGE"
        ],
        "comments": "The size of one or more packages exceeds our acceptance limit of 60.00 cm"
      }
      """

  Scenario: Receive & reject quote due to height
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | stores.yml          |
      | woopit_integrations.yml          |
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme2" has an API key
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    And the store with name "Acme2" sends a "POST" request to "/api/woopit/quotes" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "retailer": {
          "name": "Enseigne A",
          "code": "enseigne-a",
          "store": {
            "id": "store123",
            "name": "Magasin C",
            "contact": {
              "firstName": "Pierre",
              "lastName": "Dupond",
              "phone": "+33600000000",
              "email": "pierre.dupond@mail.fr"
            }
          }
        },
        "picking": {
          "location": {
            "addressLine1": "24, Rue de la Paix",
            "addressLine2": "Entrée B",
            "postalCode": "75002",
            "city": "Paris",
            "country": "FR",
            "elevator": false,
            "floor": 4,
            "comment": "Paquet à l'accueil",
            "doorCode": "1234"
          },
          "infos": "Travaux sur le boulevard.",
          "interval": [
            {
              "start": "2019-12-04T12:30:00+0000",
              "end": "2019-12-04T13:30:00+0000"
            }
          ]
        },
        "delivery": {
          "location": {
            "addressLine1": "48, Rue de Rivoli",
            "addressLine2": "Appt 9",
            "elevator": true,
            "floor": 2,
            "postalCode": "75004",
            "city": "Paris",
            "country": "FR",
            "doorCode": "5678",
            "comment": "Ascenseur à gauche"
          },
          "interval": [
            {
              "start": "2019-12-06T13:00:00+0000",
              "end": "2019-12-06T15:00:00+0000"
            }
          ]
        },
        "packages": [
          {
            "id": 1,
            "length": {
              "value": 121.8,
              "unit": "cm"
            },
            "width": {
              "value": 57.5,
              "unit": "cm"
            },
            "height": {
              "value": 75,
              "unit": "cm"
            },
            "weight": {
              "value": 33.4,
              "unit": "kg"
            },
            "products": [
              {
                "type": "TYPOLOGY_GENERIC",
                "ean": "4897567123548",
                "cug": "e21fe21",
                "label": "Produit label 1",
                "quantity": 1
              }
            ],
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 202
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "reasons": [
          "REFUSED_TOO_LARGE"
        ],
        "comments": "The size of one or more packages exceeds our acceptance limit of 70.00 cm"
      }
      """

  Scenario: Receive & reject quote due to length
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | stores.yml          |
      | woopit_integrations.yml          |
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme2" has an API key
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    And the store with name "Acme2" sends a "POST" request to "/api/woopit/quotes" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "retailer": {
          "name": "Enseigne A",
          "code": "enseigne-a",
          "store": {
            "id": "store123",
            "name": "Magasin C",
            "contact": {
              "firstName": "Pierre",
              "lastName": "Dupond",
              "phone": "+33600000000",
              "email": "pierre.dupond@mail.fr"
            }
          }
        },
        "picking": {
          "location": {
            "addressLine1": "24, Rue de la Paix",
            "addressLine2": "Entrée B",
            "postalCode": "75002",
            "city": "Paris",
            "country": "FR",
            "elevator": false,
            "floor": 4,
            "comment": "Paquet à l'accueil",
            "doorCode": "1234"
          },
          "infos": "Travaux sur le boulevard.",
          "interval": [
            {
              "start": "2019-12-04T12:30:00+0000",
              "end": "2019-12-04T13:30:00+0000"
            }
          ]
        },
        "delivery": {
          "location": {
            "addressLine1": "48, Rue de Rivoli",
            "addressLine2": "Appt 9",
            "elevator": true,
            "floor": 2,
            "postalCode": "75004",
            "city": "Paris",
            "country": "FR",
            "doorCode": "5678",
            "comment": "Ascenseur à gauche"
          },
          "interval": [
            {
              "start": "2019-12-06T13:00:00+0000",
              "end": "2019-12-06T15:00:00+0000"
            }
          ]
        },
        "packages": [
          {
            "id": 1,
            "length": {
              "value": 161.8,
              "unit": "cm"
            },
            "width": {
              "value": 57.5,
              "unit": "cm"
            },
            "height": {
              "value": 65,
              "unit": "cm"
            },
            "weight": {
              "value": 33.4,
              "unit": "kg"
            },
            "products": [
              {
                "type": "TYPOLOGY_GENERIC",
                "ean": "4897567123548",
                "cug": "e21fe21",
                "label": "Produit label 1",
                "quantity": 1
              }
            ],
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 202
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "reasons": [
          "REFUSED_TOO_LARGE"
        ],
        "comments": "The size of one or more packages exceeds our acceptance limit of 160.00 cm"
      }
      """

  Scenario: Receive & reject quote due to not allowed frozen products
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | stores.yml          |
      | woopit_integrations.yml          |
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme2" has an API key
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json, text/plain, */*"
    And the store with name "Acme2" sends a "POST" request to "/api/woopit/quotes" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "retailer": {
          "name": "Enseigne A",
          "code": "enseigne-a",
          "store": {
            "id": "store123",
            "name": "Magasin C",
            "contact": {
              "firstName": "Pierre",
              "lastName": "Dupond",
              "phone": "+33600000000",
              "email": "pierre.dupond@mail.fr"
            }
          }
        },
        "picking": {
          "location": {
            "addressLine1": "24, Rue de la Paix",
            "addressLine2": "Entrée B",
            "postalCode": "75002",
            "city": "Paris",
            "country": "FR",
            "elevator": false,
            "floor": 4,
            "comment": "Paquet à l'accueil",
            "doorCode": "1234"
          },
          "infos": "Travaux sur le boulevard.",
          "interval": [
            {
              "start": "2019-12-04T12:30:00+0000",
              "end": "2019-12-04T13:30:00+0000"
            }
          ]
        },
        "delivery": {
          "location": {
            "addressLine1": "48, Rue de Rivoli",
            "addressLine2": "Appt 9",
            "elevator": true,
            "floor": 2,
            "postalCode": "75004",
            "city": "Paris",
            "country": "FR",
            "doorCode": "5678",
            "comment": "Ascenseur à gauche"
          },
          "interval": [
            {
              "start": "2019-12-06T13:00:00+0000",
              "end": "2019-12-06T15:00:00+0000"
            }
          ]
        },
        "packages": [
          {
            "id": 1,
            "length": {
              "value": 121.8,
              "unit": "cm"
            },
            "width": {
              "value": 12.5,
              "unit": "cm"
            },
            "height": {
              "value": 12,
              "unit": "cm"
            },
            "weight": {
              "value": 3.4,
              "unit": "kg"
            },
            "products": [
              {
                "type": "TYPOLOGY_FROZEN",
                "ean": "4897567123548",
                "cug": "e21fe21",
                "label": "Produit label 1",
                "quantity": 1
              }
            ],
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 202
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "reasons": [
          "REFUSED_EXCEPTION"
        ],
        "comments": "No availability of product type TYPOLOGY_FROZEN"
      }
      """