Feature: Webhooks

  Scenario: Create webhook
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/webhooks" with body:
      """
      {
        "event":"delivery.completed",
        "url":"https://example.com/webhook"
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Webhook",
        "@id":"/api/webhooks/1",
        "@type":"Webhook",
        "url":"https://example.com/webhook",
        "event":"delivery.completed",
        "secret":@string@
      }
      """

  Scenario: Create webhook with invalid event
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "POST" request to "/api/webhooks" with body:
      """
      {
        "event":"foo.bar",
        "url":"https://example.com/webhook"
      }
      """
    Then the response status code should be 400
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ConstraintViolationList",
        "@type":"ConstraintViolationList",
        "hydra:title":"An error occurred",
        "hydra:description":@string@,
        "violations":[
          {
            "propertyPath":"event",
            "message":@string@,
            "code":@string@
          }
        ]
      }
      """

  Scenario: Can't create webhook as user
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/webhooks" with body:
      """
      {
        "event":"delivery.completed",
        "url":"https://example.com/webhook"
      }
      """
    Then the response status code should be 403

  Scenario: Retrieve webhook
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
      | webhooks.yml        |
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "GET" request to "/api/webhooks/1"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Webhook",
        "@id":"/api/webhooks/1",
        "@type":"Webhook",
        "url":"https://example.com/webhook",
        "event":"delivery.completed"
      }
      """

  Scenario: Not authorized to retrieve webhook
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
      | webhooks.yml        |
    And the OAuth client with name "Acme2" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme2" sends a "GET" request to "/api/webhooks/1"
    Then the response status code should be 403

  Scenario: Delete webhook
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
      | webhooks.yml        |
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "DELETE" request to "/api/webhooks/1"
    Then the response status code should be 204
