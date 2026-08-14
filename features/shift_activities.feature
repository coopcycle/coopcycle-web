Feature: Shift activities

  Scenario: The default activities are available out of the box
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/shift_activities"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShiftActivity",
        "@id":"/api/shift_activities",
        "@type":"hydra:Collection",
        "hydra:member":[
          { "@id":"/api/shift_activities/1", "@type":"ShiftActivity", "id":1, "slug":"delivery", "label":"Delivery", "color":null },
          { "@id":"/api/shift_activities/2", "@type":"ShiftActivity", "id":2, "slug":"dispatch", "label":"Dispatch", "color":null },
          { "@id":"/api/shift_activities/3", "@type":"ShiftActivity", "id":3, "slug":"administration", "label":"Administration", "color":null }
        ],
        "hydra:totalItems":3
      }
      """

  Scenario: Dispatcher creates a custom activity and its slug is derived from the label
    Given the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shift_activities" with body:
      """
      {
        "label": "Commercial Prospection"
      }
      """
    Then the response status code should be 201
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShiftActivity",
        "@id":"/api/shift_activities/4",
        "@type":"ShiftActivity",
        "id":4,
        "slug":"commercial-prospection",
        "label":"Commercial Prospection",
        "color":null
      }
      """

  Scenario: Creating an activity whose slug already exists gets a unique suffix
    Given the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shift_activities" with body:
      """
      {
        "label": "Delivery"
      }
      """
    Then the response status code should be 201
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShiftActivity",
        "@id":"/api/shift_activities/4",
        "@type":"ShiftActivity",
        "id":4,
        "slug":"delivery-2",
        "label":"Delivery",
        "color":null
      }
      """

  Scenario: Dispatcher edits an activity's label, its slug never changes
    Given the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/shift_activities/1" with body:
      """
      {
        "label": "Home Delivery"
      }
      """
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShiftActivity",
        "@id":"/api/shift_activities/1",
        "@type":"ShiftActivity",
        "id":1,
        "slug":"delivery",
        "label":"Home Delivery",
        "color":null
      }
      """

  Scenario: Dispatcher sets a custom color for an activity
    Given the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/shift_activities/1" with body:
      """
      {
        "label": "Delivery",
        "color": "#ff0000"
      }
      """
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShiftActivity",
        "@id":"/api/shift_activities/1",
        "@type":"ShiftActivity",
        "id":1,
        "slug":"delivery",
        "label":"Delivery",
        "color":"#ff0000"
      }
      """
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/shift_activities/1"
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/ShiftActivity",
        "@id":"/api/shift_activities/1",
        "@type":"ShiftActivity",
        "id":1,
        "slug":"delivery",
        "label":"Delivery",
        "color":"#ff0000"
      }
      """

  Scenario: Creating an activity with an invalid color is rejected
    Given the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shift_activities" with body:
      """
      {
        "label": "Commercial Prospection",
        "color": "not-a-color"
      }
      """
    Then the response status code should be 400

  Scenario: Dispatcher deletes an activity
    Given the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "DELETE" request to "/api/shift_activities/3"
    Then the response status code should be 204
    When I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/shift_activities/3"
    Then the response status code should be 404

  Scenario: A courier can read activities but not manage them
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/shift_activities"
    Then the response status code should be 200
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "POST" request to "/api/shift_activities" with body:
      """
      {
        "label": "Commercial Prospection"
      }
      """
    Then the response status code should be 403

  Scenario: Creating a shift with an unknown activity slug is rejected
    Given the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_DISPATCHER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/shifts" with body:
      """
      {
        "activity": "does-not-exist",
        "startsAt": "2026-06-29T09:00:00",
        "endsAt": "2026-06-29T17:00:00",
        "slots": 1,
        "users": []
      }
      """
    Then the response status code should be 400
