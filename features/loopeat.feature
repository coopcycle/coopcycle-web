Feature: Loopeat

	Scenario: Update Loopeat returns
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
    Given the user "bob" has created a cart at restaurant with id "1"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/orders/1/loopeat_returns" with body:
      """
      {
        "returns": [
					{
						"format_id": 1,
						"quantity": 1
					}
        ]
      }
      """
    Then print last response

  @debug
  Scenario: Update Loopeat formats
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | products.yml        |
      | restaurants.yml     |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | telephone  | 0033612345678     |
    Given the user "bob" has ordered something at the restaurant with id "1"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/orders/1/loopeat_formats" with body:
      """
      {
        "items": [
          {
            "@type": "LoopeatFormat",
            "orderItem":{
              "@id":"/api/orders/1/items/1"
            },
            "formats":[
              {
                "format_id":2,
                "quantity":"3"
              },
              {
                "format_id":5,
                "quantity":8
              }
            ]
          }
        ]
      }
      """
    Then print last response
