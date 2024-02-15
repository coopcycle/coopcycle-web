Feature: Tours

    Scenario: Retrieve task with tour
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
      | users.yml           |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/tasks/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks/1",
        "@type":"Task",
        "id":1,
        "type":"DROPOFF",
        "status":"TODO",
        "tour":{
          "@id":"/api/tours/1",
          "name":"Example tour",
          "position":@integer@
        },
        "@*@":"@*@"
      }
      """

    Scenario: Delete a tour unauthorized
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "DELETE" request to "api/tours/1"
    Then the response status code should be 403

    Scenario: Delete a tour
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | tasks.yml           |
    And the courier "sarah" is loaded:
      | email     | sarah@coopcycle.org |
      | password  | 123456              |
      | telephone | 0033612345678       |
    And the user "sarah" has role "ROLE_ADMIN"
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "DELETE" request to "api/tours/1"
    Then the response status code should be 204
    When the user "sarah" sends a "GET" request to "/api/tasks/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Task",
        "@id":"/api/tasks/1",
        "@type":"Task",
        "id":1,
        "type":"DROPOFF",
        "status":"TODO",
        "tour":null,
        "@*@":"@*@"
      }
      """

