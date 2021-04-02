Feature: Time slots

  Scenario: Retrieve time slot choices
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    Given the current time is "2020-04-02 11:00:00"
    And the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "GET" request to "/api/time_slots/choices"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TimeSlot",
        "@id":"/api/time_slots/choices",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@type":"TimeSlotChoice",
            "@id":@string@,
            "value":"2020-04-02T10:00:00Z/2020-04-02T12:00:00Z"
          },
          {
            "@type":"TimeSlotChoice",
            "@id":@string@,
            "value":"2020-04-02T12:00:00Z/2020-04-02T15:00:00Z"
          },
          {
            "@type":"TimeSlotChoice",
            "@id":@string@,
            "value":"2020-04-03T10:00:00Z/2020-04-03T12:00:00Z"
          },
          {
            "@type":"TimeSlotChoice",
            "@id":@string@,
            "value":"2020-04-03T12:00:00Z/2020-04-03T15:00:00Z"
          }
        ],
        "hydra:totalItems":4
      }
      """
