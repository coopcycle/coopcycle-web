Feature: Invoicing

  Scenario: Get invoice line items
    Given the fixtures files are loaded:
      | cypress://setup.yml |
    Given the fixtures files are loaded with no purge:
      | cypress://package_delivery_orders.yml |
    Given the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items"
    Then print last response
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/orders",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@type":"Order",
            "@id":@string@,
            "storeId":@integer@,
            "date":"@string@.isDateTime()",
            "orderId":@integer@,
            "orderNumber":@string@,
            "description":@string@,
            "subTotal":@integer@,
            "tax":@integer@,
            "total":@integer@,
            "exports":[],
            "invitation":null,
            "paymentGateway":@string@
          },
          "@array_previous_repeat@"
        ],
        "hydra:totalItems":1000,
        "hydra:view":{
          "@id":"/api/invoice_line_items?page=1",
          "@type":"hydra:PartialCollectionView",
          "hydra:first":"/api/invoice_line_items?page=1",
          "hydra:last":"/api/invoice_line_items?page=34",
          "hydra:next":"/api/invoice_line_items?page=2"
        },
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/invoice_line_items{?date,state,state[],exists[exports],store,store[]}",
          "hydra:variableRepresentation":"BasicRepresentation",
          "hydra:mapping":[
            {
              "@type":"IriTemplateMapping",
              "variable":"date",
              "property":"date",
              "required":false
            },
            {
              "@type":"IriTemplateMapping",
              "variable":"state",
              "property":"state",
              "required":false
            },
            {
              "@type":"IriTemplateMapping",
              "variable":"state[]",
              "property":"state",
              "required":false
            },
            {
              "@type":"IriTemplateMapping",
              "variable":"exists[exports]",
              "property":"exports",
              "required":false
            },
            {
              "@type":"IriTemplateMapping",
              "variable":"store",
              "property":"store",
              "required":false
            },
            {
              "@type":"IriTemplateMapping",
              "variable":"store[]",
              "property":"store",
              "required":false
            }
          ]
        }
      }
      """
