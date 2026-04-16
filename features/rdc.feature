Feature: RDC Webhooks

  Scenario: Receive webhook with create event
    When I add "Content-Type" header equal to "application/json"
    And I add "X-webhook-source" header equal to "RDC"
    And I add "X-webhook-Secret" header equal to "abc"
    And I send a "POST" request to "/api/v1/webhooks/rdc" with body:
      """
      [
        {
          "metadata": {
            "notificationType": "UPDATES",
            "resourceType": "Service",
            "loUri": "http://localhost:8080/services/ec1697f082",
            "eventType": "create",
            "eventDate": "2026-03-03T13:41:25.137Z",
            "consumerId": "019c7ab7-e10a-7e5e-a36c-76c6e9a6156a",
            "correlationId": "logistics_objects-service-1772545285137",
            "loMemberIdentifier": "BOL.MEMBER.SHIPPER",
            "loRevision": 1,
            "eventMemberIdentifier": "BOL.MEMBER.SHIPPER"
          },
          "lo": {
            "id": "ec1697f082",
            "executionStatus": "SCHEDULED",
            "invoiceStatus": "NOT_INVOICED",
            "isDangerous": false,
            "serviceAgreementReference": "livraison velo",
            "serviceName": "livraison par velo ec1697f082",
            "serviceNature": "LOGISTICS",
            "serviceStatus": "APPROVED",
            "serviceSubtype": "DELIVERY",
            "serviceType": "TRANSPORT",
            "startLocation": {
              "location": {
                "address": {
                  "postalCode": "49000",
                  "addressLines": ["16 Bd de l'industrie ZI D"],
                  "addressRegion": "",
                  "addressCountry": {"countryCode": "FR", "countryName": "France"},
                  "addressLocality": "ECOUFLANT",
                  "postOfficeBoxNumber": ""
                },
                "locationName": "SHIPPER BUILDING",
                "locationType": ""
              },
              "requestedStartTimeRange": {
                "latestDateTime": "2025-12-26T07:30:00Z",
                "earliestDateTime": "2025-12-26T07:00:00Z"
              },
              "requestedEndTimeRange": {
                "latestDateTime": "2025-12-26T08:00:00Z",
                "earliestDateTime": "2025-12-26T07:30:00Z"
              }
            },
            "endLocation": {
              "location": {
                "address": {
                  "postalCode": "49100",
                  "addressLines": ["21 rue Jean Predali"],
                  "addressRegion": "",
                  "addressCountry": {"countryCode": "FR", "countryName": "France"},
                  "addressLocality": "ANGERS",
                  "postOfficeBoxNumber": ""
                },
                "locationName": "CONSIGNEE BUILDING",
                "locationType": ""
              },
              "requestedStartTimeRange": {
                "latestDateTime": "2025-12-26T09:30:59.999Z",
                "earliestDateTime": "2025-12-26T09:00:00Z"
              },
              "requestedEndTimeRange": {
                "latestDateTime": "2025-12-26T09:45:59.999Z",
                "earliestDateTime": "2025-12-26T09:15:00Z"
              }
            },
            "contacts": [
              {"role": "CUSTOMER_DISPATCH", "email": "", "telephone": "03.04.05.06.07"},
              {"role": "PROVIDER_DISPATCH", "email": "", "telephone": "01.02.03.04.05"}
            ],
            "externalReferences": [
              {"reference": "ABC12548", "description": "service n ABC12548", "externalReferenceType": "PROVIDER_ID"},
              {"reference": "C145237/2", "description": "Service n C145237/2", "externalReferenceType": "REQUESTOR_ID"}
            ],
            "provider": {"legalEntityName": "SHIPPER"},
            "requestor": {"legalEntityName": "SHIPPER"},
            "incoterm": "DDP"
          }
        }
      ]
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "status": "accepted",
        "lo_uri": "http://localhost:8080/services/ec1697f082",
        "event_type": "create"
      }
      """

  Scenario: Receive webhook with update event
    When I add "Content-Type" header equal to "application/json"
    And I add "X-webhook-source" header equal to "RDC"
    And I add "X-webhook-Secret" header equal to "abc"
    And I send a "POST" request to "/api/v1/webhooks/rdc" with body:
      """
      [
        {
          "metadata": {
            "loUri": "http://localhost:8080/services/ec1697f082",
            "eventType": "update"
          },
          "lo": {
            "id": "ec1697f082",
            "executionStatus": "PICKED_UP"
          }
        }
      ]
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "status": "accepted",
        "lo_uri": "http://localhost:8080/services/ec1697f082",
        "event_type": "update"
      }
      """

  Scenario: Receive webhook with cancel event
    When I add "Content-Type" header equal to "application/json"
    And I add "X-webhook-source" header equal to "RDC"
    And I add "X-webhook-Secret" header equal to "abc"
    And I send a "POST" request to "/api/v1/webhooks/rdc" with body:
      """
      [
        {
          "metadata": {
            "loUri": "http://localhost:8080/services/ec1697f082",
            "eventType": "cancel"
          },
          "lo": {
            "id": "ec1697f082",
            "executionStatus": "CANCELLED"
          }
        }
      ]
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "status": "accepted",
        "lo_uri": "http://localhost:8080/services/ec1697f082",
        "event_type": "cancel"
      }
      """

  Scenario: Receive webhook with invalid secret
    When I add "Content-Type" header equal to "application/json"
    And I add "X-webhook-source" header equal to "RDC"
    And I add "X-webhook-Secret" header equal to "wrong-secret"
    And I send a "POST" request to "/api/v1/webhooks/rdc" with body:
      """
      [
        {
          "metadata": {
            "loUri": "http://localhost:8080/services/test",
            "eventType": "create"
          },
          "lo": {}
        }
      ]
      """
    Then the response status code should be 403
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "error": "Invalid webhook secret"
      }
      """

  Scenario: Receive webhook with invalid source
    When I add "Content-Type" header equal to "application/json"
    And I add "X-webhook-source" header equal to "INVALID"
    And I add "X-webhook-Secret" header equal to "abc"
    And I send a "POST" request to "/api/v1/webhooks/rdc" with body:
      """
      [
        {
          "metadata": {
            "loUri": "http://localhost:8080/services/test",
            "eventType": "create"
          },
          "lo": {}
        }
      ]
      """
    Then the response status code should be 403
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "error": "Invalid webhook source"
      }
      """

  Scenario: Receive webhook with invalid JSON
    When I add "Content-Type" header equal to "application/json"
    And I add "Accept" header equal to "application/json"
    And I add "X-webhook-source" header equal to "RDC"
    And I add "X-webhook-Secret" header equal to "abc"
    And I send a "POST" request to "/api/v1/webhooks/rdc" with body:
      """
      not valid json
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "type": @string@,
        "title": @string@,
        "status": 400,
        "detail": "Invalid json message received",
        "class": @string@,
        "trace": @array@
      }
      """

  Scenario: Receive webhook with missing metadata
    When I add "Content-Type" header equal to "application/json"
    And I add "X-webhook-source" header equal to "RDC"
    And I add "X-webhook-Secret" header equal to "abc"
    And I send a "POST" request to "/api/v1/webhooks/rdc" with body:
      """
      [
        {
          "lo": {}
        }
      ]
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "error": "Invalid payload structure"
      }
      """

  Scenario: Receive webhook with missing lo field
    When I add "Content-Type" header equal to "application/json"
    And I add "X-webhook-source" header equal to "RDC"
    And I add "X-webhook-Secret" header equal to "abc"
    And I send a "POST" request to "/api/v1/webhooks/rdc" with body:
      """
      [
        {
          "metadata": {
            "loUri": "http://localhost:8080/services/test",
            "eventType": "create"
          }
        }
      ]
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "error": "Invalid payload structure"
      }
      """
