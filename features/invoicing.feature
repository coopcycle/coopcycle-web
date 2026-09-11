Feature: Invoicing

  Scenario: Get invoice line items
    Given the PHP memory limit is set to "1024M"
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
            "organizationId":@string@,
            "date":"@string@.isDateTime()",
            "orderId":@integer@,
            "orderNumber":@string@,
            "orderState":@string@,
            "description":@string@,
            "subTotal":@integer@,
            "tax":@integer@,
            "total":@integer@,
            "exports":[],
            "needsInvoicing":@boolean@
          },
          "@array_previous_repeat@"
        ],
        "hydra:totalItems":100,
        "hydra:view":{
          "@id":"/api/invoice_line_items?page=1",
          "@type":"hydra:PartialCollectionView",
          "hydra:first":"/api/invoice_line_items?page=1",
          "hydra:last":"/api/invoice_line_items?page=4",
          "hydra:next":"/api/invoice_line_items?page=2"
        },
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/invoice_line_items{?date,state,state[],exists[exports],organization,organization[],settlement}",
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
              "variable":"organization",
              "property":"organization",
              "required":false
            },
            {
              "@type":"IriTemplateMapping",
              "variable":"organization[]",
              "property":"organization",
              "required":false
            },
            {
              "@type":"IriTemplateMapping",
              "variable":"settlement",
              "property":"settlement",
              "required":false
            }
          ]
        }
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items?page=4"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/invoice_line_items",
        "@type":"hydra:Collection",
        "hydra:member":"@array@.count(10)",
        "hydra:totalItems":100,
        "hydra:view":{
          "@id":"/api/invoice_line_items?page=4",
          "@type":"hydra:PartialCollectionView",
          "hydra:first":"/api/invoice_line_items?page=1",
          "hydra:last":"/api/invoice_line_items?page=4",
          "hydra:previous":"/api/invoice_line_items?page=3"
        },
        "hydra:search":{
          "@*@":"@*@"
        }
      }
      """

  Scenario: Get invoice line items filtered by store
    Given the PHP memory limit is set to "1024M"
    Given the fixtures files are loaded with purge:
      | setup_default.yml |
    Given the fixtures files are loaded:
      | package_delivery_orders.yml |
    Given the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items?organization=1"
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
            "organizationId":@string@,
            "date":"@string@.isDateTime()",
            "orderId":@integer@,
            "orderNumber":@string@,
            "orderState":@string@,
            "description":@string@,
            "subTotal":@integer@,
            "tax":@integer@,
            "total":@integer@,
            "exports":[],
            "needsInvoicing":@boolean@
          },
          "@array_previous_repeat@"
        ],
        "hydra:totalItems":40,
        "hydra:view":{
          "@id":"/api/invoice_line_items?organization=1\u0026page=1",
          "@type":"hydra:PartialCollectionView",
          "hydra:first":"/api/invoice_line_items?organization=1\u0026page=1",
          "hydra:last":"/api/invoice_line_items?organization=1\u0026page=2",
          "hydra:next":"/api/invoice_line_items?organization=1\u0026page=2"
        },
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/invoice_line_items{?date,state,state[],exists[exports],organization,organization[],settlement}",
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
              "variable":"organization",
              "property":"organization",
              "required":false
            },
            {
              "@type":"IriTemplateMapping",
              "variable":"organization[]",
              "property":"organization",
              "required":false
            },
            {
              "@type":"IriTemplateMapping",
              "variable":"settlement",
              "property":"settlement",
              "required":false
            }
          ]
        }
      }
      """

  Scenario: Get invoice line items filtered by multiple stores
    Given the PHP memory limit is set to "1024M"
    Given the fixtures files are loaded with purge:
      | setup_default.yml |
    Given the fixtures files are loaded:
      | package_delivery_orders.yml |
    Given the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items?organization[]=1&organization[]=5&itemsPerPage=100"
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
            "organizationId":@string@,
            "date":"@string@.isDateTime()",
            "orderId":@integer@,
            "orderNumber":@string@,
            "orderState":@string@,
            "description":@string@,
            "subTotal":@integer@,
            "tax":@integer@,
            "total":@integer@,
            "exports":[],
            "needsInvoicing":@boolean@
          },
          "@array_previous_repeat@"
        ],
        "hydra:totalItems":@integer@,
        "hydra:view":{
          "@id": "/api/invoice_line_items?itemsPerPage=100&organization%5B%5D=1&organization%5B%5D=5",
          "@type": "hydra:PartialCollectionView"
        },
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/invoice_line_items{?date,state,state[],exists[exports],organization,organization[],settlement}",
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
              "variable":"organization",
              "property":"organization",
              "required":false
            },
            {
              "@type":"IriTemplateMapping",
              "variable":"organization[]",
              "property":"organization",
              "required":false
            },
            {
              "@type":"IriTemplateMapping",
              "variable":"settlement",
              "property":"settlement",
              "required":false
            }
          ]
        }
      }
      """

  Scenario: Get invoice line items grouped by organization
    Given the PHP memory limit is set to "1024M"
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
            "organizationId":@string@,
            "organizationLegalName":@string@,
            "storeName":@string@,
            "ordersCount":@integer@,
            "subTotal":@integer@,
            "tax":@integer@,
            "total":@integer@
          },
          "@array_previous_repeat@"
        ],
        "hydra:totalItems":8,
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/invoice_line_items/grouped_by_organization{?date,state,state[],exists[exports],organization,organization[],settlement}",
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
              "variable":"organization",
              "property":"organization",
              "required":false
            },
            {
              "@type":"IriTemplateMapping",
              "variable":"organization[]",
              "property":"organization",
              "required":false
            },
            {
              "@type":"IriTemplateMapping",
              "variable":"settlement",
              "property":"settlement",
              "required":false
            }
          ]
        }
      }
      """

  Scenario: Get invoice line items by date
    Given the PHP memory limit is set to "1024M"
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

  Scenario: Get invoice line items filtered by restaurant
    Given the PHP memory limit is set to "1024M"
    Given the fixtures files are loaded with purge:
      | setup_default.yml |
    Given the fixtures files are loaded:
      | foodtech_orders.yml |
    Given the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items?organization=/api/restaurants/1"
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
            "organizationId":"/api/restaurants/1",
            "date":"@string@.isDateTime()",
            "orderId":@integer@,
            "orderNumber":@string@,
            "orderState":@string@,
            "description":@string@,
            "subTotal":@integer@,
            "tax":@integer@,
            "total":@integer@,
            "exports":[],
            "needsInvoicing":@boolean@
          },
          "@array_previous_repeat@"
        ],
        "hydra:totalItems":@integer@,
        "hydra:view":{
          "@*@":"@*@"
        },
        "hydra:search":{
          "@*@":"@*@"
        }
      }
      """

  Scenario: Get invoice line items with a mix of last mile and foodtech orders
    Given the PHP memory limit is set to "1024M"
    Given the fixtures files are loaded with purge:
      | setup_default.yml |
    Given the fixtures files are loaded:
      | package_delivery_orders.yml |
      | foodtech_orders.yml         |
    Given the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items?itemsPerPage=1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/invoice_line_items",
        "@type":"hydra:Collection",
        "hydra:member":@array@,
        "hydra:totalItems":200,
        "hydra:view":{
          "@*@":"@*@"
        },
        "hydra:search":{
          "@*@":"@*@"
        }
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items/grouped_by_organization?itemsPerPage=30"
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
            "@id":@string@,
            "organizationId":@string@,
            "organizationLegalName":@string@,
            "storeName":@string@,
            "ordersCount":@integer@,
            "subTotal":@integer@,
            "tax":@integer@,
            "total":@integer@
          },
          "@array_previous_repeat@"
        ],
        "hydra:totalItems":16,
        "hydra:view":{
          "@*@":"@*@"
        },
        "hydra:search":{
          "@*@":"@*@"
        }
      }
      """

  Scenario: Get invoice line items filtered by settlement status
    Given the PHP memory limit is set to "1024M"
    Given the fixtures files are loaded with purge:
      | setup_default.yml |
    Given the fixtures files are loaded:
      | foodtech_orders.yml |
    Given the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items?settlement=needs_invoicing"
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
            "organizationId":@string@,
            "date":"@string@.isDateTime()",
            "orderId":@integer@,
            "orderNumber":@string@,
            "orderState":@string@,
            "description":@string@,
            "subTotal":350,
            "tax":0,
            "total":350,
            "exports":[],
            "needsInvoicing":true
          },
          "@array_previous_repeat@"
        ],
        "hydra:totalItems":33,
        "hydra:view":{
          "@*@":"@*@"
        },
        "hydra:search":{
          "@*@":"@*@"
        }
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items?settlement=settled"
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
            "organizationId":@string@,
            "date":"@string@.isDateTime()",
            "orderId":@integer@,
            "orderNumber":@string@,
            "orderState":@string@,
            "description":@string@,
            "subTotal":0,
            "tax":0,
            "total":0,
            "exports":[],
            "needsInvoicing":false
          },
          "@array_previous_repeat@"
        ],
        "hydra:totalItems":67,
        "hydra:view":{
          "@*@":"@*@"
        },
        "hydra:search":{
          "@*@":"@*@"
        }
      }
      """

  Scenario: Get invoice line items filtered by settlement status with a mix of last mile and foodtech orders
    Given the PHP memory limit is set to "1024M"
    Given the fixtures files are loaded with purge:
      | setup_default.yml |
    Given the fixtures files are loaded:
      | package_delivery_orders.yml |
      | foodtech_orders.yml         |
    Given the user "admin" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items?settlement=needs_invoicing&itemsPerPage=1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Order",
        "@id":"/api/invoice_line_items",
        "@type":"hydra:Collection",
        "hydra:member":@array@,
        "hydra:totalItems":133,
        "hydra:view":{
          "@*@":"@*@"
        },
        "hydra:search":{
          "@*@":"@*@"
        }
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items?settlement=settled"
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
            "organizationId":"@string@.startsWith('/api/restaurants/')",
            "date":"@string@.isDateTime()",
            "orderId":@integer@,
            "orderNumber":@string@,
            "orderState":@string@,
            "description":@string@,
            "subTotal":0,
            "tax":0,
            "total":0,
            "exports":[],
            "needsInvoicing":false
          },
          "@array_previous_repeat@"
        ],
        "hydra:totalItems":67,
        "hydra:view":{
          "@*@":"@*@"
        },
        "hydra:search":{
          "@*@":"@*@"
        }
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "admin" sends a "GET" request to "/api/invoice_line_items?organization=/api/stores/1&settlement=settled"
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
          "@*@":"@*@"
        },
        "hydra:search":{
          "@*@":"@*@"
        }
      }
      """
