Feature: Incidents

  Scenario: Report incident
    Given the fixtures files are loaded:
      | tasks.yml |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/incidents" with body:
      """
      {
        "description": "PACKAGE WET",
        "failureReasonCode": "DAMAGED",
        "task": "/api/tasks/2"
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Incident",
        "@id":"@string@",
        "@type":"Incident",
        "id":@integer@,
        "title":"Endommagé",
        "status":"OPEN",
        "priority":@integer@,
        "task":"/api/tasks/2",
        "failureReasonCode":"DAMAGED",
        "description":"PACKAGE WET",
        "images":[],
        "events":[],
        "createdBy":"/api/users/1",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "tags":[],
        "metadata": {"@*@": "@*@"}
      }
      """

  Scenario: Report incident (with metadata)
    Given the fixtures files are loaded:
      | tasks.yml |
    And the courier "bob" is loaded:
      | email     | bob@coopcycle.org |
      | password  | 123456            |
      | telephone | 0033612345678     |
    And the user "bob" is authenticated
    And the tasks with comments matching "#bob" are assigned to "bob"
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/incidents" with body:
      """
      {
        "description": "PACKAGE WET",
        "failureReasonCode": "DAMAGED",
        "task": "/api/tasks/2",
        "metadata": [
          {"foo":"bar"},
          {"baz":"bat"}
        ]
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Incident",
        "@id":"@string@",
        "@type":"Incident",
        "id":@integer@,
        "title":"Endommagé",
        "status":"OPEN",
        "priority":@integer@,
        "task":"/api/tasks/2",
        "failureReasonCode":"DAMAGED",
        "description":"PACKAGE WET",
        "images":[],
        "events":[],
        "createdBy":"/api/users/1",
        "createdAt":"@string@.isDateTime()",
        "updatedAt":"@string@.isDateTime()",
        "tags":[],
        "metadata": [
          {"foo":"bar"},
          {"baz":"bat"}
        ]
      }
      """
