Feature: Fleet management

  Scenario: Create trailer and add vehicle
    Given the fixtures files are loaded:
      | task_list.yml        |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "bob" is authenticated
    Given I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "POST" request to "/api/trailers" with body:
      """
      {
        "name": "Trailer",
        "maxVolumeUnits": 10,
        "maxWeight": 50,
        "color": "#FFFFFF",
        "isElectric": false
      }
      """
    Then the response status code should be 201
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Trailer",
        "@id":"/api/trailers/1",
        "@type":"Trailer",
        "id":1,
        "name":"Trailer",
        "maxVolumeUnits":10,
        "maxWeight":50,
        "color":"#FFFFFF",
        "isElectric":false,
        "electricRange":null,
        "compatibleVehicles":[]
      }
      """
    Given I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/trailers/1/vehicles" with body:
      """
      {
        "compatibleVehicles": ["/api/vehicles/1"]
      }
      """
    Then the response status code should be 200
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Trailer",
        "@id":"/api/trailers/1/vehicles",
        "@type":"Trailer",
        "id":1,
        "name":"Trailer",
        "maxVolumeUnits":10,
        "maxWeight":50,
        "color":"#FFFFFF",
        "isElectric":false,
        "electricRange":null,
        "compatibleVehicles":[
          "/api/vehicles/1"
        ]
      }
      """
