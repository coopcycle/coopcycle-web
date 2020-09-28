Feature: Users

  Scenario: Not authorized to list users
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | users.yml           |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/users?roles[]=ROLE_COURIER"
    Then the response status code should be 403

  Scenario: Not authorized to retrieve user
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | users.yml           |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/users/2"
    Then the response status code should be 403

  Scenario: User can retrieve him/herself
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | users.yml           |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/users/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/User",
        "@id":"/api/users/1",
        "@type":"User",
        "username":"bob",
        "email":"bob@demo.coopcycle.org",
        "givenName":null,
        "familyName":null,
        "telephone":null,
        "roles":[
          "ROLE_USER"
        ],
        "addresses":[]
      }
      """

  Scenario: Retrieve users filtered by role
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | users.yml           |
    And the user "bob" has role "ROLE_ADMIN"
    And the user "sarah" has role "ROLE_COURIER"
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/users?roles[]=ROLE_COURIER"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/User",
        "@id":"/api/users",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"@string@.startsWith('/api/users')",
            "@type":"User",
            "username":"sarah",
            "email":"sarah@demo.coopcycle.org",
            "givenName":null,
            "familyName":null,
            "telephone":null,
            "addresses":@array@,
            "roles":[
              "ROLE_COURIER",
              "ROLE_USER"
            ]
          }
        ],
        "hydra:totalItems":1,
        "hydra:view":{
          "@id":"/api/users?roles%5B%5D=ROLE_COURIER",
          "@type":"hydra:PartialCollectionView"
        },
        "hydra:search":{
          "@type":"hydra:IriTemplate",
          "hydra:template":"/api/users{?roles}",
          "hydra:variableRepresentation":"BasicRepresentation",
          "hydra:mapping":[
            {
              "@type":"IriTemplateMapping",
              "variable":"roles",
              "property":"roles",
              "required":false
            }
          ]
        }
      }
      """

  Scenario: User can update his/her telephone
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | users.yml           |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | givenName  | John              |
      | familyName | Doe               |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/users/1" with body:
      """
      {
        "telephone": "+33612345678"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/User",
        "@id":"/api/users/1",
        "@type":"User",
        "username":"bob",
        "email":"bob@demo.coopcycle.org",
        "givenName":"John",
        "familyName":"Doe",
        "telephone":"+33612345678",
        "roles":[
          "ROLE_USER"
        ],
        "addresses":@array@
      }
      """

  # This is needed by the app
  # The field is named "telephone"
  # https://github.com/coopcycle/coopcycle-app/blob/33135cffd10e54c271b581a9f7ce063f4200fff1/src/redux/Checkout/actions.js#L677-L688
  Scenario: User can update the phone number associated to his/her customer account
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | users.yml           |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
      | givenName  | John              |
      | familyName | Doe               |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "PUT" request to "/api/customers/1" with body:
      """
      {
        "telephone": "+33612345678"
      }
      """
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Customer",
        "@id":"/api/customers/1",
        "@type":"Customer",
        "email":"bob@demo.coopcycle.org",
        "phoneNumber":"+33612345678",
        "telephone":"+33612345678",
        "username":"bob"
      }
      """
