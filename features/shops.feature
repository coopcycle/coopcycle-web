Feature: Manage shops

  Scenario: Retrieve a store via restaurants endpoint
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_locales.yml  |
      | products.yml        |
      | shops.yml           |
    When I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/restaurants/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Restaurant",
        "@id":"/api/restaurants/1",
        "@type":"http://schema.org/GroceryStore",
        "id":1,
        "name":"Flower Express",
        "description":null,
        "enabled":true,
        "depositRefundEnabled":false,
        "depositRefundOptin":true,
        "address":{
          "@id":"/api/addresses/1",
          "@type":"http://schema.org/Place",
          "geo":{
            "latitude":48.864577,
            "longitude":2.333338
          },
          "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
          "telephone":null,
          "name":null
        },
        "state":"normal",
        "telephone":null,
        "openingHoursSpecification":[
          {
            "@type":"OpeningHoursSpecification",
            "opens":"11:30",
            "closes":"14:30",
            "dayOfWeek":[
              "Monday",
              "Tuesday",
              "Wednesday",
              "Thursday",
              "Friday",
              "Saturday"
            ]
          }
        ],
        "specialOpeningHoursSpecification":[],
        "image":@string@,
        "fulfillmentMethods":@array@
      }
      """
