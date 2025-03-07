Feature: Manage shops

  Scenario: Retrieve a store via restaurants endpoint
    Given the current time is "2021-12-22 13:00:00"
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
            "@type":"GeoCoordinates",
            "latitude":48.864577,
            "longitude":2.333338
          },
          "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
          "telephone":null,
          "name":null,
          "description": null
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
        "bannerImage":@string@,
        "fulfillmentMethods":@array@,
        "potentialAction":{
          "@type":"OrderAction",
          "target":{
            "@type":"EntryPoint",
            "urlTemplate":@string@,
            "inLanguage":"fr",
            "actionPlatform":["http://schema.org/DesktopWebPlatform"]
          },
          "deliveryMethod":["http://purl.org/goodrelations/v1#DeliveryModeOwnFleet"]
        },
        "isOpen":true,
        "hub":null,
        "loopeatEnabled":false,
        "tags":@array@,
        "badges":@array@,
        "autoAcceptOrdersEnabled": @boolean@,
        "edenredMerchantId": null,
        "edenredTRCardEnabled": false,
        "edenredSyncSent": false,
        "edenredEnabled": false
      }
      """
