Feature: Stores

  Scenario: Retrieve store
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/stores/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Store",
        "@id":"/api/stores/1",
        "@type":"http://schema.org/Store",
        "name":"Acme",
        "enabled":true,
        "address":{
          "@id":"/api/addresses/1",
          "@type":"http://schema.org/Place",
          "geo":{
            "latitude":48.864577,
            "longitude":2.333338
          },
          "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
          "telephone":null,
          "name":null
        },
        "timeSlot":"/api/time_slots/1"
      }
      """

  Scenario: Retrieve time slot
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/time_slots/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TimeSlot",
        "@id":"/api/time_slots/1",
        "@type":"TimeSlot",
        "name":"Acme time slot",
        "choices":[
          {
            "startTime":"12:00:00",
            "endTime":"14:00:00"
          },
          {
            "startTime":"14:00:00",
            "endTime":"17:00:00"
          }
        ],
        "interval":"2 days",
        "workingDaysOnly":true
      }
      """
