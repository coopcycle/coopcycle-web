Feature: Zelty catalog push webhook

  Scenario: Import catalog with a dish
    Given the fixtures files are loaded:
      | sylius_taxation.yml  |
      | zelty_restaurant.yml |
    And the Zelty taxes API will return:
      """
      {"taxes":[{"id":1,"name":"TVA 10%","rate":1000}]}
      """
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/catalog/1" with body:
      """
      {
        "data": {
          "id": 100,
          "name": "Pizza Roma Catalog",
          "locale": "fr",
          "currency": "EUR",
          "tags": [],
          "items": [
            {
              "id": 1001,
              "type": "dish",
              "name": "Margherita",
              "description": "Tomate, Mozzarella",
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {
                "price": 1200,
                "is_fixed": true,
                "prevent_discounts": false,
                "overrides": []
              },
              "tax_rules": {
                "tax_id": 1
              },
              "option_ids": [],
              "parts": []
            }
          ],
          "menuParts": [],
          "options": [],
          "optionValues": []
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"success"}
      """
    And I see 1 entities "AppBundle\Entity\Sylius\Product"
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "1001", "enabled": true}
      """
    And I see 1 entities "AppBundle\Entity\Sylius\ProductVariant"
    And I see entity "AppBundle\Entity\Sylius\ProductVariant" with properties:
      """
      {"code": "1001_variant", "price": 1200}
      """

  Scenario: Import catalog with multiple dishes and a menu
    Given the fixtures files are loaded:
      | sylius_taxation.yml  |
      | zelty_restaurant.yml |
    And the Zelty taxes API will return:
      """
      {"taxes":[{"id":1,"name":"TVA 10%","rate":1000},{"id":2,"name":"TVA 20%","rate":2000}]}
      """
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/catalog/1" with body:
      """
      {
        "data": {
          "id": 100,
          "name": "Pizza Roma Catalog",
          "locale": "fr",
          "currency": "EUR",
          "tags": [],
          "items": [
            {
              "id": 2001,
              "type": "dish",
              "name": "Margherita",
              "description": "Tomate, Mozzarella",
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {
                "price": 1200,
                "is_fixed": true,
                "prevent_discounts": false,
                "overrides": []
              },
              "tax_rules": {"tax_id": 1},
              "option_ids": [],
              "parts": []
            },
            {
              "id": 2002,
              "type": "dish",
              "name": "Regina",
              "description": "Tomate, Jambon, Champignons",
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {
                "price": 1400,
                "is_fixed": true,
                "prevent_discounts": false,
                "overrides": []
              },
              "tax_rules": {"tax_id": 1},
              "option_ids": [],
              "parts": []
            },
            {
              "id": 3001,
              "type": "menu",
              "name": "Formula Déjeuner",
              "description": "Entrée + Plat + Dessert",
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {
                "price": 1500,
                "is_fixed": true,
                "prevent_discounts": false,
                "overrides": []
              },
              "tax_rules": {"tax_id": 1},
              "option_ids": [],
              "parts": [
                {"menu_part_id": 101, "has_combination": false, "prevent_customisation": false}
              ]
            }
          ],
          "menuParts": [
            {
              "id": 101,
              "name": "Choisissez votre pizza",
              "dish_ids": [2001, 2002]
            }
          ],
          "options": [],
          "optionValues": []
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"success"}
      """
    And I see 3 entities "AppBundle\Entity\Sylius\Product"
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "2001"}
      """
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "2002"}
      """
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "3001"}
      """
    And I see entity "AppBundle\Entity\Sylius\ProductVariant" with properties:
      """
      {"code": "2001_variant", "price": 1200}
      """
    And I see entity "AppBundle\Entity\Sylius\ProductVariant" with properties:
      """
      {"code": "2002_variant", "price": 1400}
      """
    And I see entity "AppBundle\Entity\Sylius\ProductVariant" with properties:
      """
      {"code": "3001_variant", "price": 1500}
      """

  Scenario: Import catalog with a dish having options
    Given the fixtures files are loaded:
      | sylius_taxation.yml  |
      | zelty_restaurant.yml |
    And the Zelty taxes API will return:
      """
      {"taxes":[{"id":1,"name":"TVA 10%","rate":1000}]}
      """
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/catalog/1" with body:
      """
      {
        "data": {
          "id": 100,
          "name": "Pizza Roma Catalog",
          "locale": "fr",
          "currency": "EUR",
          "tags": [],
          "items": [
            {
              "id": 4001,
              "type": "dish",
              "name": "Calzone",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {
                "price": 1300,
                "is_fixed": true,
                "prevent_discounts": false,
                "overrides": []
              },
              "tax_rules": {"tax_id": 1},
              "option_ids": [501],
              "parts": []
            }
          ],
          "menuParts": [],
          "options": [
            {
              "id": 501,
              "name": "Garnitures supplémentaires",
              "value_ids": [601, 602],
              "minimum_choices": 0,
              "maximum_choices": 3
            }
          ],
          "optionValues": [
            {
              "id": 601,
              "name": "Olives",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {
                "price": 50,
                "is_fixed": true,
                "prevent_discounts": false,
                "overrides": []
              },
              "availability": null
            },
            {
              "id": 602,
              "name": "Anchois",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {
                "price": 100,
                "is_fixed": true,
                "prevent_discounts": false,
                "overrides": []
              },
              "availability": null
            }
          ]
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"success"}
      """
    And I see 1 entities "AppBundle\Entity\Sylius\Product"
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "4001", "enabled": true}
      """
    And I see 1 entities "AppBundle\Entity\Sylius\ProductVariant"
    And I see entity "AppBundle\Entity\Sylius\ProductVariant" with properties:
      """
      {"code": "4001_variant", "price": 1300}
      """
    And I see 1 entities "AppBundle\Entity\Sylius\ProductOption"
    And I see 2 entities "AppBundle\Entity\Sylius\ProductOptionValue"
    And I see entity "AppBundle\Entity\Sylius\ProductOptionValue" with properties:
      """
      {"price": 50, "enabled": true}
      """
    And I see entity "AppBundle\Entity\Sylius\ProductOptionValue" with properties:
      """
      {"price": 100, "enabled": true}
      """

  Scenario: Two consecutive catalog pushes do not cause duplicate key errors
    Given the fixtures files are loaded:
      | sylius_taxation.yml  |
      | zelty_restaurant.yml |
    And the Zelty taxes API will return:
      """
      {"taxes":[{"id":1,"name":"TVA 10%","rate":1000}]}
      """
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/catalog/1" with body:
      """
      {
        "data": {
          "id": 100,
          "name": "Pizza Roma Catalog",
          "locale": "fr",
          "currency": "EUR",
          "tags": [],
          "items": [
            {
              "id": 5001,
              "type": "dish",
              "name": "Margherita",
              "description": "Tomate, Mozzarella",
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 1200, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "tax_rules": {"tax_id": 1},
              "option_ids": [],
              "parts": []
            },
            {
              "id": 6001,
              "type": "menu",
              "name": "Formula Déjeuner",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 1500, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "tax_rules": {"tax_id": 1},
              "option_ids": [],
              "parts": [
                {"menu_part_id": 201, "has_combination": false, "prevent_customisation": false}
              ]
            }
          ],
          "menuParts": [
            {"id": 201, "name": "Choisissez votre pizza", "dish_ids": [5001]}
          ],
          "options": [],
          "optionValues": []
        }
      }
      """
    Then the response status code should be 200
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/catalog/1" with body:
      """
      {
        "data": {
          "id": 100,
          "name": "Pizza Roma Catalog",
          "locale": "fr",
          "currency": "EUR",
          "tags": [],
          "items": [
            {
              "id": 5001,
              "type": "dish",
              "name": "Margherita",
              "description": "Tomate, Mozzarella",
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 1200, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "tax_rules": {"tax_id": 1},
              "option_ids": [],
              "parts": []
            },
            {
              "id": 6001,
              "type": "menu",
              "name": "Formula Déjeuner",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 1500, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "tax_rules": {"tax_id": 1},
              "option_ids": [],
              "parts": [
                {"menu_part_id": 201, "has_combination": false, "prevent_customisation": false}
              ]
            }
          ],
          "menuParts": [
            {"id": 201, "name": "Choisissez votre pizza", "dish_ids": [5001]}
          ],
          "options": [],
          "optionValues": []
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"success"}
      """
    And I see 2 entities "AppBundle\Entity\Sylius\Product"
    And I see 1 entities "AppBundle\Entity\Sylius\ProductTaxon"

  Scenario: dish.update disables a product when disable flag is true
    Given the fixtures files are loaded:
      | sylius_taxation.yml  |
      | zelty_restaurant.yml |
    And the Zelty taxes API will return:
      """
      {"taxes":[{"id":1,"name":"TVA 10%","rate":1000}]}
      """
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/catalog/1" with body:
      """
      {
        "data": {
          "id": 100,
          "name": "Pizza Roma Catalog",
          "locale": "fr",
          "currency": "EUR",
          "tags": [],
          "items": [
            {
              "id": 7001,
              "type": "dish",
              "name": "Margherita",
              "description": "Tomate, Mozzarella",
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 1200, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "tax_rules": {"tax_id": 1},
              "option_ids": [],
              "parts": []
            }
          ],
          "menuParts": [],
          "options": [],
          "optionValues": []
        }
      }
      """
    Then the response status code should be 200
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "7001", "enabled": true}
      """
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/dish.update" with body:
      """
      {
        "event_id": "abc123",
        "event_name": "dish.update",
        "created_at": "2026-01-01T00:00:00Z",
        "version": "v2",
        "brand_id": 1,
        "restaurant_id": 1,
        "data": {
          "dishes": [
            {"id": 7001, "name": "Margherita", "price": 1200, "tax": 1000, "disable": true}
          ]
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"success"}
      """
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "7001", "enabled": false}
      """

  Scenario: dish.delete disables a product
    Given the fixtures files are loaded:
      | sylius_taxation.yml  |
      | zelty_restaurant.yml |
    And the Zelty taxes API will return:
      """
      {"taxes":[{"id":1,"name":"TVA 10%","rate":1000}]}
      """
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/catalog/1" with body:
      """
      {
        "data": {
          "id": 100,
          "name": "Pizza Roma Catalog",
          "locale": "fr",
          "currency": "EUR",
          "tags": [],
          "items": [
            {
              "id": 7002,
              "type": "dish",
              "name": "Regina",
              "description": "Tomate, Jambon",
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 1400, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "tax_rules": {"tax_id": 1},
              "option_ids": [],
              "parts": []
            }
          ],
          "menuParts": [],
          "options": [],
          "optionValues": []
        }
      }
      """
    Then the response status code should be 200
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "7002", "enabled": true}
      """
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/dish.delete" with body:
      """
      {
        "event_id": "def456",
        "event_name": "dish.delete",
        "created_at": "2026-01-01T00:00:00Z",
        "version": "v2",
        "brand_id": 1,
        "restaurant_id": 1,
        "data": {
          "dishes": [
            {"id": 7002, "name": "Regina", "price": 1400, "tax": 1000}
          ]
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"success"}
      """
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "7002", "enabled": false}
      """

  Scenario: dish.availability_update marks a product as out of stock
    Given the fixtures files are loaded:
      | sylius_taxation.yml  |
      | zelty_restaurant.yml |
    And the Zelty taxes API will return:
      """
      {"taxes":[{"id":1,"name":"TVA 10%","rate":1000}]}
      """
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/catalog/1" with body:
      """
      {
        "data": {
          "id": 100,
          "name": "Pizza Roma Catalog",
          "locale": "fr",
          "currency": "EUR",
          "tags": [],
          "items": [
            {
              "id": 7003,
              "type": "dish",
              "name": "Calzone",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 1300, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "tax_rules": {"tax_id": 1},
              "option_ids": [],
              "parts": []
            }
          ],
          "menuParts": [],
          "options": [],
          "optionValues": []
        }
      }
      """
    Then the response status code should be 200
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "7003", "enabled": true}
      """
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/dish.availability_update" with body:
      """
      {
        "event_id": "ghi789",
        "event_name": "dish.availability_update",
        "created_at": "2026-01-01T00:00:00Z",
        "version": "v2",
        "brand_id": 1,
        "restaurant_id": 1,
        "data": {
          "id_dish": 7003,
          "id_restaurant": 1,
          "outofstock": true
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"success"}
      """
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "7003", "enabled": false}
      """

  Scenario: Restaurant not found returns 404
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/catalog/999" with body:
      """
      {
        "data": {
          "id": 100,
          "name": "Test Catalog",
          "locale": "fr",
          "currency": "EUR",
          "tags": [],
          "items": [],
          "menuParts": [],
          "options": [],
          "optionValues": []
        }
      }
      """
    Then the response status code should be 404
    And the response should be in JSON
    And the JSON should match:
      """
      {"error":"Restaurant not found"}
      """
