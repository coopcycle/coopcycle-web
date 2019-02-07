Feature: Settings

  Scenario: Retrieve settings
    Given the setting "latlng" has value "48.856613,2.352222"
    And the setting "stripe_test_publishable_key" has value "pk_1234567890"
    And the setting "google_api_key" has value "abc123456"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/settings"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "country":"fr",
        "locale":"fr",
        "stripe_publishable_key":"pk_1234567890",
        "google_api_key":"abc123456",
        "latlng":"48.856613,2.352222",
        "piwik_site_id": null
      }
      """

  Scenario: Retrieve settings as hash
    Given the setting "latlng" has value "48.856613,2.352222"
    And the setting "stripe_test_publishable_key" has value "pk_1234567890"
    And the setting "google_api_key" has value "abc123456"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to "/api/settings?format=hash"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should be equal to:
      """
      "f586b490f853f1f7256b10d98664d851df7d1410"
      """
