Feature: Taxes

  Scenario: Retrieve tax rates
    Given the fixtures files are loaded:
      | sylius_taxation.yml |
    When I add "Content-Type" header equal to "application/json"
    And I send a "GET" request to "/api/tax_rates"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TaxRate",
        "@id":"/api/tax_rates",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/tax_rates/tax_rate.zero",
            "@type":"TaxRate",
            "id":"tax_rate.zero",
            "code":"tax_rate.zero",
            "amount":0,
            "name":"TVA 0% 0.00%",
            "category":"SERVICE_TAX_EXEMPT",
            "alternatives":[]
          }
        ]
      }
      """
