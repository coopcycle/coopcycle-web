Feature: Shift presets

  Scenario: Dispatcher creates, then edits a shift preset; couriers can't edit
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shift_presets" with body:
      """
      {
        "name": "Morning",
        "activity": "delivery",
        "startTime": "09:00",
        "endTime": "17:00",
        "slots": 1,
        "breakMinutes": 30,
        "comment": "Bring your bike lock"
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/shift_presets/1" with body:
      """
      {
        "name": "Short morning",
        "activity": "dispatch",
        "startTime": "09:00",
        "endTime": "12:00",
        "slots": 2,
        "breakMinutes": 0,
        "comment": null
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShiftPreset",
        "@id":"/api/shift_presets/1",
        "@type":"ShiftPreset",
        "id":1,
        "name":"Short morning",
        "activity":"dispatch",
        "startTime":"09:00",
        "endTime":"12:00",
        "slots":2,
        "breakMinutes":0,
        "comment":null,
        "requiredSkills":[]
      }
      """
    Given the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "PUT" request to "/api/shift_presets/1" with body:
      """
      {
        "name": "Hacked",
        "activity": "delivery",
        "startTime": "09:00",
        "endTime": "12:00",
        "slots": 1
      }
      """
    Then the response status code should be 403
