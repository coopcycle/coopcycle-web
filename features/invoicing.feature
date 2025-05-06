Feature: Invoicing

  Scenario: Get invoice line items
    Given the fixtures files are loaded with purge:
      | cypress://setup_default.yml |
    Given the fixtures files are loaded:
      | cypress://package_delivery_orders.yml |
    Given the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/invoice_line_items",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@type":"InvoiceLineItem",
            "storeId":@integer@,
            "date":"@string@.isDateTime()",
            "orderId":@integer@,
            "orderNumber":@string@,
            "description":@string@,
            "subTotal":@integer@,
            "tax":@integer@,
            "total":@integer@,
            "exports":[]
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
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items?page=34"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/invoice_line_items",
        "@type":"hydra:Collection",
        "hydra:member":"@array@.count(10)",
        "hydra:totalItems":1000,
        "hydra:view":{
          "@id":"/api/invoice_line_items?page=34",
          "@type":"hydra:PartialCollectionView",
          "hydra:first":"/api/invoice_line_items?page=1",
          "hydra:last":"/api/invoice_line_items?page=34",
          "hydra:previous":"/api/invoice_line_items?page=33"
        },
        "hydra:search":{
          "@*@":"@*@"
        }
      }
      """

  Scenario: Get invoice line items filtered by store
    Given the fixtures files are loaded with purge:
      | cypress://setup_default.yml |
    Given the fixtures files are loaded:
      | cypress://package_delivery_orders.yml |
    Given the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items?store=1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/invoice_line_items",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@type":"InvoiceLineItem",
            "storeId":@integer@,
            "date":"@string@.isDateTime()",
            "orderId":@integer@,
            "orderNumber":@string@,
            "description":@string@,
            "subTotal":@integer@,
            "tax":@integer@,
            "total":@integer@,
            "exports":[]
          },
          "@array_previous_repeat@"
        ],
        "hydra:totalItems":259,
        "hydra:view":{
          "@id":"/api/invoice_line_items?store=1\u0026page=1",
          "@type":"hydra:PartialCollectionView",
          "hydra:first":"/api/invoice_line_items?store=1\u0026page=1",
          "hydra:last":"/api/invoice_line_items?store=1\u0026page=9",
          "hydra:next":"/api/invoice_line_items?store=1\u0026page=2"
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

  Scenario: Get invoice line items filtered by multiple stores
    Given the fixtures files are loaded with purge:
      | cypress://setup_default.yml |
    Given the fixtures files are loaded:
      | cypress://package_delivery_orders.yml |
    Given the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items?store[]=1&store[]=2"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/invoice_line_items",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@type":"InvoiceLineItem",
            "storeId":@integer@,
            "date":"@string@.isDateTime()",
            "orderId":@integer@,
            "orderNumber":@string@,
            "description":@string@,
            "subTotal":@integer@,
            "tax":@integer@,
            "total":@integer@,
            "exports":[]
          },
          "@array_previous_repeat@"
        ],
        "hydra:totalItems":@integer@,
        "hydra:view":{
          "@id":"/api/invoice_line_items?store%5B%5D=1\u0026store%5B%5D=2\u0026page=1",
          "@type":"hydra:PartialCollectionView",
          "hydra:first":"/api/invoice_line_items?store%5B%5D=1\u0026store%5B%5D=2\u0026page=1",
          "hydra:last":"/api/invoice_line_items?store%5B%5D=1\u0026store%5B%5D=2\u0026page=10",
          "hydra:next":"/api/invoice_line_items?store%5B%5D=1\u0026store%5B%5D=2\u0026page=2"
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

  Scenario: Get invoice line items grouped by organization
    Given the fixtures files are loaded with purge:
      | cypress://setup_default.yml |
    Given the fixtures files are loaded:
      | cypress://package_delivery_orders.yml |
    Given the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items/grouped_by_organization"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/invoice_line_items/grouped_by_organization",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@type":"InvoiceLineItemGroupedByOrganization",
            "storeId":@integer@,
            "organizationLegalName":@string@,
            "ordersCount":@integer@,
            "subTotal":@integer@,
            "tax":@integer@,
            "total":@integer@
          },
          "@array_previous_repeat@"
        ],
        "hydra:totalItems":53,
        "hydra:view":{
          "@id":"/api/invoice_line_items/grouped_by_organization?page=1",
          "@type":"hydra:PartialCollectionView",
          "hydra:first":"/api/invoice_line_items/grouped_by_organization?page=1",
          "hydra:last":"/api/invoice_line_items/grouped_by_organization?page=2",
          "hydra:next":"/api/invoice_line_items/grouped_by_organization?page=2"
        },
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/invoice_line_items/grouped_by_organization{?date,state,state[],exists[exports],store,store[]}",
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
