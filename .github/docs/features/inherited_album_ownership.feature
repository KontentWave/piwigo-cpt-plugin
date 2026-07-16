Feature: Inherited CPT ownership for Community user album trees
  # Current Cypress smoke coverage now exercises the core descendant shortcut,
  # UCP listing, guest-block-after-private-toggle, and explicit child-owner
  # override paths described in this feature.
  As a gallery user with a Community-owned root album
  I want to manage subalbums inside my album tree
  So that I can organize and protect my gallery without administrator help

  Scenario: Owner can manage a child album under their Community-owned root album
    Given user "slecna1" owns album "slecna1" through Community ownership
    And album "slecna1_album2" is a child of "slecna1"
    When user "slecna1" opens album "slecna1_album2"
    Then the CPT album privacy shortcut should be visible

  Scenario: Owner sees descendants in the UCP manager
    Given user "slecna1" owns album "slecna1" through Community ownership
    And album "slecna1_album1" is a child of "slecna1"
    And album "slecna1_album2" is a child of "slecna1"
    And album "slecna1_album3" is a child of "slecna1"
    When user "slecna1" opens their profile page
    Then the "My Galleries" section should list "slecna1"
    And the "My Galleries" section should list "slecna1_album1"
    And the "My Galleries" section should list "slecna1_album2"
    And the "My Galleries" section should list "slecna1_album3"

  Scenario: Parent ownership does not override explicit child ownership
    Given user "slecna1" owns album "slecna1" through Community ownership
    And album "slecna1_album2" is a child of "slecna1"
    And user "slecna2" explicitly owns album "slecna1_album2"
    When user "slecna1" opens album "slecna1_album2"
    Then the CPT album privacy shortcut should not be visible

  Scenario: Inherited owner can make a descendant album private
    Given user "slecna1" owns album "slecna1" through Community ownership
    And album "slecna1_album2" is a child of "slecna1"
    When user "slecna1" changes album "slecna1_album2" to private
    Then album "slecna1_album2" should be visible only to "slecna1" and admins unless shared
