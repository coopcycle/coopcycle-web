Feature: Invoicing

  Scenario: Get invoice line items
    Given the fixtures files are loaded with purge:
      | setup_default.yml |
    Given the fixtures files are loaded:
      | package_delivery_orders.yml |
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
            "@id":@string@,
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
      | setup_default.yml |
    Given the fixtures files are loaded:
      | package_delivery_orders.yml |
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
            "@id":@string@,
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
        "hydra:totalItems":250,
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
      | setup_default.yml |
    Given the fixtures files are loaded:
      | package_delivery_orders.yml |
    Given the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items?store[]=1&store[]=35&itemsPerPage=100"
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
            "@id":@string@,
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
          "@id": "/api/invoice_line_items?store%5B%5D=1\u0026store%5B%5D=35\u0026itemsPerPage=100\u0026page=1",
          "@type": "hydra:PartialCollectionView",
          "hydra:first": "/api/invoice_line_items?store%5B%5D=1\u0026store%5B%5D=35\u0026itemsPerPage=100\u0026page=1",
          "hydra:last": "/api/invoice_line_items?store%5B%5D=1\u0026store%5B%5D=35\u0026itemsPerPage=100\u0026page=3",
          "hydra:next": "/api/invoice_line_items?store%5B%5D=1\u0026store%5B%5D=35\u0026itemsPerPage=100\u0026page=2"
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
      | setup_default.yml |
    Given the fixtures files are loaded:
      | package_delivery_orders.yml |
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
            "@id": @string@,
            "storeId":@integer@,
            "organizationLegalName":@string@,
            "ordersCount":@integer@,
            "subTotal":@integer@,
            "tax":@integer@,
            "total":@integer@
          },
          "@array_previous_repeat@"
        ],
        "hydra:totalItems":50,
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

  Scenario: Get invoice line items by date
    Given the fixtures files are loaded with purge:
      | setup_default.yml |
    Given the fixtures files are loaded:
      | package_delivery_orders.yml |
    Given the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items?date[after]=2025-01-01&date[before]=2025-01-03"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/invoice_line_items",
        "@type":"hydra:Collection",
        "hydra:member":[],
        "hydra:totalItems":0,
        "hydra:view":{
          "@id":"/api/invoice_line_items?date%5Bafter%5D=2025-01-01&date%5Bbefore%5D=2025-01-03",
          "@type":"hydra:PartialCollectionView"
        },
        "hydra:search":{
          "@*@":"@*@"
        }
      }
      """
