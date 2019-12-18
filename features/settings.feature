Feature: Settings

  Scenario: Retrieve settings
    Given the setting "latlng" has value "48.856613,2.352222"
    And the setting "brand_name" has value "CoopCycle"
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
        "brand_name":"CoopCycle",
        "country":"fr",
        "locale":"fr",
        "stripe_publishable_key":"pk_1234567890",
        "google_api_key":"abc123456",
        "latlng":"48.856613,2.352222"
      }
      """
