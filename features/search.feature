Feature: Search

  Scenario: Search restaurants with Typesense
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_locales.yml  |
      | products.yml        |
      | restaurants.yml     |
    When I send a "GET" request to "/api/search/shops_products?q=nodai"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
         "@context":"/api/contexts/Search",
         "@id":"/api/search/shops_products",
         "@type":"hydra:Collection",
         "hydra:member":[
            {
               "category":[
                  "featured",
                  "exclusive"
               ],
               "cuisine":[
                  "asian"
               ],
               "enabled":true,
               "id":"1",
               "name":"Nodaiwa",
               "sortable_id":1,
               "type":"restaurant",
               "result_type":"shop",
               "image_url":""
            }
         ],
         "hydra:totalItems":1,
         "hydra:view":{
            "@id":"/api/search/shops_products?q=nodai",
            "@type":"hydra:PartialCollectionView"
         }
      }
      """

  Scenario: Search restaurants by product name with Typesense
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | sylius_locales.yml  |
      | products.yml        |
      | restaurants.yml     |
    When I send a "GET" request to "/api/search/shops_products?q=pizza"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Search",
        "@id":"/api/search/shops_products",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "id":"1",
            "name":"Pizza",
            "shop_id":1,
            "shop_name":"Nodaiwa",
            "sortable_id":1,
            "result_type":"product",
            "shop_enabled": true,
            "image_url": ""
          }
        ],
        "hydra:totalItems":1,
        "hydra:view":{
          "@id":"/api/search/shops_products?q=pizza",
          "@type":"hydra:PartialCollectionView"
        }
      }
      """
