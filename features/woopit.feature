Feature: Woopit

  Scenario: Receive & confirm quote
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme" has an API key
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json"
    And the store with name "Acme" sends a "POST" request to "/api/woopit/quotes" with body:
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
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then print last response
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
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
      """
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json"
    # https://woop.stoplight.io/docs/carrier/b3A6MzYyMDcwOTU-delivery-request
    And the store with name "Acme" sends a "POST" request to "/api/woopit/deliveries" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "quoteId": "yXJPEP30xgja",
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
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then print last response
    Then the response status code should be 200
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
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme" has an API key
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json"
    And the store with name "Acme" sends a "POST" request to "/api/woopit/quotes" with body:
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
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then print last response
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
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
      """
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json"
    # https://woop.stoplight.io/docs/carrier/b3A6MzYyMDcwOTU-delivery-request
    And the store with name "Acme" sends a "DELETE" request to "/api/woopit/quotes/yXJPEP30xgja"

  Scenario: Receive, confirm quote & cancel delivery
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme" has an API key
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json"
    And the store with name "Acme" sends a "POST" request to "/api/woopit/quotes" with body:
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
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
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
      """
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json"
    # https://woop.stoplight.io/docs/carrier/b3A6MzYyMDcwOTU-delivery-request
    And the store with name "Acme" sends a "POST" request to "/api/woopit/deliveries" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "quoteId": "yXJPEP30xgja",
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
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "deliveryId":"yXJPEP30xgja"
      }
      """
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json"
    And the store with name "Acme" sends a "DELETE" request to "/api/woopit/deliveries/yXJPEP30xgja"
    Then print last response
    Then the response status code should be 204

  Scenario: Receive, confirm quote & update delivery
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_taxation.yml |
      | stores.yml          |
    And the setting "subject_to_vat" has value "1"
    And the store with name "Acme" has an API key
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json"
    And the store with name "Acme" sends a "POST" request to "/api/woopit/quotes" with body:
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
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
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
      """
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json"
    # https://woop.stoplight.io/docs/carrier/b3A6MzYyMDcwOTU-delivery-request
    And the store with name "Acme" sends a "POST" request to "/api/woopit/deliveries" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "quoteId": "yXJPEP30xgja",
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
            "quantity": 2
          }
        ],
        "services": [
          "SERVICE_SELECTED_ROOM"
        ]
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "deliveryId":"yXJPEP30xgja"
      }
      """
    When I add "Content-Type" header equal to "application/merge-patch+json"
    And I add "Accept" header equal to "application/json"
    And the store with name "Acme" sends a "PATCH" request to "/api/woopit/deliveries/yXJPEP30xgja" with body:
      """
      {
        "orderId": "65zq1d5qz1d56q1",
        "referenceNumber": "65zq1d5qz1d56q1-456",
        "quoteId": "yXJPEP30xgja",
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
              "start": "2019-12-04T13:00:00+0000",
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
              "start": "2019-12-06T14:00:00+0000",
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
    Then the response status code should be 204
