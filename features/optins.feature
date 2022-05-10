Feature: Optin Consents

  Scenario: Retrieve user consents
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | optin_consents.yml  |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/me/optin-consents"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/OptinConsent",
        "@id": "/api/me/optin-consents",
        "@type": "hydra:Collection",
        "hydra:member": [
            {
                "@id": @string@,
                "@type": "OptinConsent",
                "id": @integer@,
                "user": "@string@.startsWith('/api/users')",
                "type": @string@,
                "createdAt": "@string@.isDateTime()",
                "withdrawedAt": null,
                "accepted": false,
                "asked": false
            },
            "@array_previous@"
        ],
        "hydra:totalItems": 2
      }
      """

  Scenario: User accepts the consent to receive the Newsletter
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | optin_consents.yml  |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/me/optin-consents" with body:
      """
      {
        "type": "NEWSLETTER",
        "accepted": true,
        "asked": true
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/OptinConsent",
        "@id": "/api/me/optin-consents",
        "@type": "hydra:Collection",
        "hydra:member": [
            {
                "type": "MARKETING",
                "accepted": false,
                "asked": false,
                "@*@": "@*@"
            },
            {
                "type": "NEWSLETTER",
                "accepted": true,
                "asked": true,
                "@*@": "@*@"
            }
        ],
        "hydra:totalItems": 2
      }
      """

  Scenario: User rejects consent for Marketing
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | optin_consents.yml  |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/me/optin-consents" with body:
      """
      {
        "type": "MARKETING",
        "accepted": false,
        "asked": true
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context": "/api/contexts/OptinConsent",
        "@id": "/api/me/optin-consents",
        "@type": "hydra:Collection",
        "hydra:member": [
            {
                "type": "MARKETING",
                "accepted": false,
                "asked": true,
                "@*@": "@*@"
            },
            "@...@"
        ],
        "hydra:totalItems": 2
      }
      """
