Feature: Employee profiles

  Scenario: Admin creates and updates an employee profile
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/employee_profiles" with body:
      """
      {
        "user": "/api/users/1",
        "contractStartDate": "2026-01-15",
        "dateOfBirth": "1995-03-20",
        "addressStreet": "12 rue de la Paix",
        "addressPostalCode": "75002",
        "addressLocality": "Paris",
        "addressCountry": "FR",
        "salaryType": "monthly",
        "salaryAmount": "1800.00",
        "weeklyContractedHours": "35.00"
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/EmployeeProfile",
        "@id":"/api/employee_profiles/1",
        "@type":"EmployeeProfile",
        "id":1,
        "user":"/api/users/1",
        "contractStartDate":"@string@.isDateTime()",
        "dateOfBirth":"@string@.isDateTime()",
        "addressStreet":"12 rue de la Paix",
        "addressPostalCode":"75002",
        "addressLocality":"Paris",
        "addressCountry":"FR",
        "salaryType":"monthly",
        "salaryAmount":"1800.00",
        "weeklyContractedHours":"35.00",
        "createdAt":"@string@.isDateTime()"
      }
      """
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/employee_profiles/1" with body:
      """
      {
        "salaryAmount": "1900.00"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/EmployeeProfile",
        "@id":"/api/employee_profiles/1",
        "@type":"EmployeeProfile",
        "id":1,
        "user":"/api/users/1",
        "contractStartDate":"@string@.isDateTime()",
        "dateOfBirth":"@string@.isDateTime()",
        "addressStreet":"12 rue de la Paix",
        "addressPostalCode":"75002",
        "addressLocality":"Paris",
        "addressCountry":"FR",
        "salaryType":"monthly",
        "salaryAmount":"1900.00",
        "weeklyContractedHours":"35.00",
        "createdAt":"@string@.isDateTime()"
      }
      """

  Scenario: Creating a second profile for the same user is rejected
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "bob" is loaded:
      | email    | bob@coopcycle.org |
      | password | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/employee_profiles" with body:
      """
      {
        "user": "/api/users/1"
      }
      """
    Then the response status code should be 201
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/employee_profiles" with body:
      """
      {
        "user": "/api/users/1"
      }
      """
    Then the response status code should be 422

  Scenario: Dispatcher (non-admin) can not access employee profiles
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
    And the user "bob" sends a "GET" request to "/api/employee_profiles"
    Then the response status code should be 403
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/employee_profiles" with body:
      """
      {
        "user": "/api/users/1"
      }
      """
    Then the response status code should be 403

  Scenario: Courier can not access employee profiles
    Given the courier "sarah" is loaded:
      | email    | sarah@coopcycle.org |
      | password | 123456              |
    And the user "sarah" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "sarah" sends a "GET" request to "/api/employee_profiles"
    Then the response status code should be 403
