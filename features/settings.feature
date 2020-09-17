Feature: Settings

  Scenario: Retrieve settings
    Given the setting "latlng" has value "48.856613,2.352222"
    And the setting "brand_name" has value "CoopCycle"
    And the setting "stripe_test_publishable_key" has value "pk_1234567890"
    And the setting "payment_gateway" has value "stripe",
    And the setting "mercadopago_publishable_key" has value "TEST_123456",
    And the setting "mercadopago_access_token" has value "TEST_123456",
    And the setting "google_api_key" has value "abc123456"
    And the setting "currency_code" has value "eur"
    And the setting "phone_number" has value "+33612345678"
    And the setting "administrator_email" has value "dev@coopcycle.org"
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
        "payment_gateway":"stripe",
        "mercadopago_publishable_key":"TEST_123456",
        "mercadopago_access_token":"TEST_123456"
        "google_api_key":"abc123456",
        "latlng":"48.856613,2.352222",
        "currency_code":"eur",
        "phone_number":"+33612345678",
        "administrator_email":"dev@coopcycle.org"
      }
      """
