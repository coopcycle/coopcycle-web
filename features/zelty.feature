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
    Then the response status code should be 202
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"queued"}
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
    Then the response status code should be 202
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"queued"}
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
    Then the response status code should be 202
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"queued"}
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
    Then the response status code should be 202
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
    Then the response status code should be 202
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"queued"}
      """
    And I see 2 entities "AppBundle\Entity\Sylius\Product"
    And I see 0 entities "AppBundle\Entity\Sylius\ProductTaxon"

  Scenario: Two consecutive pushes with a menu containing a dish that has options do not cause duplicate key errors
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
          "name": "Test Catalog",
          "locale": "fr",
          "currency": "EUR",
          "tags": [],
          "items": [
            {
              "id": 7001,
              "type": "dish",
              "name": "Margherita",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 1200, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "tax_rules": {"tax_id": 1},
              "option_ids": [8001],
              "parts": []
            },
            {
              "id": 7002,
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
                {"menu_part_id": 9001, "has_combination": false, "prevent_customisation": false}
              ]
            }
          ],
          "menuParts": [
            {"id": 9001, "name": "Choisissez votre pizza", "dish_ids": [7001]}
          ],
          "options": [
            {"id": 8001, "name": "Garnitures", "value_ids": [8101], "minimum_choices": 0, "maximum_choices": 3}
          ],
          "optionValues": [
            {
              "id": 8101,
              "name": "Olives",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 50, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "availability": null
            }
          ]
        }
      }
      """
    Then the response status code should be 202
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/catalog/1" with body:
      """
      {
        "data": {
          "id": 100,
          "name": "Test Catalog",
          "locale": "fr",
          "currency": "EUR",
          "tags": [],
          "items": [
            {
              "id": 7001,
              "type": "dish",
              "name": "Margherita",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 1200, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "tax_rules": {"tax_id": 1},
              "option_ids": [8001],
              "parts": []
            },
            {
              "id": 7002,
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
                {"menu_part_id": 9001, "has_combination": false, "prevent_customisation": false}
              ]
            }
          ],
          "menuParts": [
            {"id": 9001, "name": "Choisissez votre pizza", "dish_ids": [7001]}
          ],
          "options": [
            {"id": 8001, "name": "Garnitures", "value_ids": [8101], "minimum_choices": 0, "maximum_choices": 3}
          ],
          "optionValues": [
            {
              "id": 8101,
              "name": "Olives",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 50, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "availability": null
            }
          ]
        }
      }
      """
    Then the response status code should be 202
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"queued"}
      """

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
    Then the response status code should be 202
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
    Then the response status code should be 202
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
    Then the response status code should be 202
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

  Scenario: menu.update disables a product when disable flag is true
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
              "id": 8001,
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
              "id": 8010,
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
                {"menu_part_id": 301, "has_combination": false, "prevent_customisation": false}
              ]
            }
          ],
          "menuParts": [
            {"id": 301, "name": "Choisissez votre pizza", "dish_ids": [8001]}
          ],
          "options": [],
          "optionValues": []
        }
      }
      """
    Then the response status code should be 202
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "8010", "enabled": true}
      """
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/menu.update" with body:
      """
      {
        "event_id": "aaa111",
        "event_name": "menu.update",
        "created_at": "2026-01-01T00:00:00Z",
        "version": "v2",
        "brand_id": 1,
        "restaurant_id": 1,
        "data": {
          "menus": [
            {"id": 8010, "name": "Formula Déjeuner", "price": 1500, "tax": 1000, "disable": true}
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
      {"code": "8010", "enabled": false}
      """

  Scenario: menu.delete disables a product
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
              "id": 8002,
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
            },
            {
              "id": 8020,
              "type": "menu",
              "name": "Formula Soir",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 1800, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "tax_rules": {"tax_id": 1},
              "option_ids": [],
              "parts": [
                {"menu_part_id": 302, "has_combination": false, "prevent_customisation": false}
              ]
            }
          ],
          "menuParts": [
            {"id": 302, "name": "Choisissez votre pizza", "dish_ids": [8002]}
          ],
          "options": [],
          "optionValues": []
        }
      }
      """
    Then the response status code should be 202
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "8020", "enabled": true}
      """
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/menu.delete" with body:
      """
      {
        "event_id": "bbb222",
        "event_name": "menu.delete",
        "created_at": "2026-01-01T00:00:00Z",
        "version": "v2",
        "brand_id": 1,
        "restaurant_id": 1,
        "data": {
          "menus": [
            {"id": 8020, "name": "Formula Soir", "price": 1800, "tax": 1000}
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
      {"code": "8020", "enabled": false}
      """

  Scenario: menu.availability_update marks a menu product as out of stock
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
              "id": 8003,
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
            },
            {
              "id": 8030,
              "type": "menu",
              "name": "Formula Express",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 1200, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "tax_rules": {"tax_id": 1},
              "option_ids": [],
              "parts": [
                {"menu_part_id": 303, "has_combination": false, "prevent_customisation": false}
              ]
            }
          ],
          "menuParts": [
            {"id": 303, "name": "Choisissez votre pizza", "dish_ids": [8003]}
          ],
          "options": [],
          "optionValues": []
        }
      }
      """
    Then the response status code should be 202
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "8030", "enabled": true}
      """
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/menu.availability_update" with body:
      """
      {
        "event_id": "ccc333",
        "event_name": "menu.availability_update",
        "created_at": "2026-01-01T00:00:00Z",
        "version": "v2",
        "brand_id": 1,
        "restaurant_id": 1,
        "data": {
          "id_menu": 8030,
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
      {"code": "8030", "enabled": false}
      """

  Scenario: option.update disables option values and updates their prices
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
              "id": 9001,
              "type": "dish",
              "name": "Calzone",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 1300, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "tax_rules": {"tax_id": 1},
              "option_ids": [901],
              "parts": []
            }
          ],
          "menuParts": [],
          "options": [
            {
              "id": 901,
              "name": "Garnitures",
              "value_ids": [9011, 9012],
              "minimum_choices": 0,
              "maximum_choices": 2
            }
          ],
          "optionValues": [
            {
              "id": 9011,
              "name": "Olives",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 50, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "availability": null
            },
            {
              "id": 9012,
              "name": "Anchois",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 100, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "availability": null
            }
          ]
        }
      }
      """
    Then the response status code should be 202
    And I see entity "AppBundle\Entity\Sylius\ProductOptionValue" with properties:
      """
      {"price": 50, "enabled": true}
      """
    And I see entity "AppBundle\Entity\Sylius\ProductOptionValue" with properties:
      """
      {"price": 100, "enabled": true}
      """
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/option.update" with body:
      """
      {
        "event_id": "ddd444",
        "event_name": "option.update",
        "created_at": "2026-01-01T00:00:00Z",
        "version": "v2",
        "brand_id": 1,
        "restaurant_id": 1,
        "data": {
          "options": [
            {
              "id": 901,
              "max_choices": 2,
              "disable": true,
              "values": [
                {"id": 9011, "name": "Olives", "price": 75},
                {"id": 9012, "name": "Anchois", "price": 150}
              ]
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
    Given the "disabled_filter" filter is disabled
    And I see entity "AppBundle\Entity\Sylius\ProductOptionValue" with properties:
      """
      {"price": 75, "enabled": false}
      """
    And I see entity "AppBundle\Entity\Sylius\ProductOptionValue" with properties:
      """
      {"price": 150, "enabled": false}
      """
    Given the "disabled_filter" filter is enabled

  Scenario: option_value.availability_update marks a single option value as out of stock
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
              "id": 9002,
              "type": "dish",
              "name": "Calzone",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 1300, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "tax_rules": {"tax_id": 1},
              "option_ids": [902],
              "parts": []
            }
          ],
          "menuParts": [],
          "options": [
            {
              "id": 902,
              "name": "Garnitures",
              "value_ids": [9021, 9022],
              "minimum_choices": 0,
              "maximum_choices": 2
            }
          ],
          "optionValues": [
            {
              "id": 9021,
              "name": "Mozza",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 60, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "availability": null
            },
            {
              "id": 9022,
              "name": "Tomates séchées",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 80, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "availability": null
            }
          ]
        }
      }
      """
    Then the response status code should be 202
    And I see 2 entities "AppBundle\Entity\Sylius\ProductOptionValue"
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/option_value.availability_update" with body:
      """
      {
        "event_id": "eee555",
        "event_name": "option_value.availability_update",
        "created_at": "2026-01-01T00:00:00Z",
        "version": "v2",
        "brand_id": 1,
        "restaurant_id": 1,
        "data": {
          "options_values_availabilities": [
            {"id_dish_option_value": 9021, "id_restaurant": 1, "outofstock": true}
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
    Given the "disabled_filter" filter is disabled
    And I see entity "AppBundle\Entity\Sylius\ProductOptionValue" with properties:
      """
      {"price": 60, "enabled": false}
      """
    And I see entity "AppBundle\Entity\Sylius\ProductOptionValue" with properties:
      """
      {"price": 80, "enabled": true}
      """
    Given the "disabled_filter" filter is enabled

  Scenario: order.status.update with production status starts preparing the order
    Given the fixtures files are loaded:
      | sylius_taxation.yml  |
      | zelty_restaurant.yml |
    And there is a Zelty order with zelty id 55001 in state "accepted"
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/order.status.update" with body:
      """
      {
        "event_id": "aaa111",
        "event_name": "order.status.update",
        "created_at": "2026-01-01T00:00:00Z",
        "version": "v2",
        "brand_id": 1,
        "restaurant_id": 1,
        "data": {
          "id": 55001,
          "restaurant_id": 1,
          "status": "production",
          "kitchen_status": "preparation"
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"success"}
      """
    And I see entity "AppBundle\Entity\Sylius\Order" with properties:
      """
      {"zeltyOrderId": 55001, "state": "started"}
      """

  Scenario: order.status.update with ready status finishes preparing the order
    Given the fixtures files are loaded:
      | sylius_taxation.yml  |
      | zelty_restaurant.yml |
    And there is a Zelty order with zelty id 55002 in state "started"
    When I add "Content-Type" header equal to "application/json"
    And I send a "POST" request to "/api/zelty/webhook/order.status.update" with body:
      """
      {
        "event_id": "bbb222",
        "event_name": "order.status.update",
        "created_at": "2026-01-01T00:00:00Z",
        "version": "v2",
        "brand_id": 1,
        "restaurant_id": 1,
        "data": {
          "id": 55002,
          "restaurant_id": 1,
          "status": "ready",
          "kitchen_status": "ready"
        }
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"success"}
      """
    And I see entity "AppBundle\Entity\Sylius\Order" with properties:
      """
      {"zeltyOrderId": 55002, "state": "ready"}
      """

  Scenario: Products removed from catalog are disabled on next push
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
              "id": 10001,
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
              "id": 10002,
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
    Then the response status code should be 202
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "10001", "enabled": true}
      """
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "10002", "enabled": true}
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
              "id": 10001,
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
    Then the response status code should be 202
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"queued"}
      """
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "10001", "enabled": true}
      """
    And I see entity "AppBundle\Entity\Sylius\Product" with properties:
      """
      {"code": "10002", "enabled": false}
      """

  Scenario: Tags define catalog sections and link both dishes and menus
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
          "name": "Test Catalog",
          "locale": "fr",
          "currency": "EUR",
          "tags": [
            {
              "id": "ZT100",
              "internal_id": "100",
              "name": "Nos Burgers",
              "description": null,
              "image": null,
              "color": null,
              "disabled": false,
              "item_ids": ["ZD200", "ZM300"]
            }
          ],
          "items": [
            {
              "id": "ZD200",
              "internal_id": "200",
              "type": "dish",
              "name": "Burger Classique",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 1200, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "tax_rules": {"tax_id": 1},
              "option_ids": [],
              "parts": []
            },
            {
              "id": "ZM300",
              "internal_id": "300",
              "type": "menu",
              "name": "Formule Burger",
              "description": null,
              "img": null,
              "disabled": false,
              "is_sold_out": false,
              "price": {"price": 1500, "is_fixed": true, "prevent_discounts": false, "overrides": []},
              "tax_rules": {"tax_id": 1},
              "option_ids": [],
              "parts": [
                {"menu_part_id": "ZMP400", "has_combination": false, "prevent_customisation": false}
              ]
            }
          ],
          "menuParts": [
            {
              "id": "ZMP400",
              "internal_id": "400",
              "type": "menu_part",
              "name": "Choisissez votre burger",
              "disabled": false,
              "is_sold_out": false,
              "minimum_choices": 1,
              "maximum_choices": 1,
              "dish_ids": ["ZD200"]
            }
          ],
          "options": [],
          "optionValues": []
        }
      }
      """
    Then the response status code should be 202
    And the response should be in JSON
    And the JSON should match:
      """
      {"status":"queued"}
      """
    And I see 2 entities "AppBundle\Entity\Sylius\Product"
    And I see entity "AppBundle\Entity\Sylius\Taxon" with properties:
      """
      {"code": "ZT100"}
      """
    And I see 2 entities "AppBundle\Entity\Sylius\ProductTaxon"

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
