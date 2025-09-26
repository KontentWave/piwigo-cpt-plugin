# Filename: ucp_album_management.feature

Feature: Album Self-Management in User Control Panel
  As an album owner
  I want to manage my albums' details and privacy directly from my profile page
  So that I can be more self-sufficient and not rely on an administrator.

  Background:
    Given a registered user "gallery_owner" exists with the password "password123"
    And "gallery_owner" owns a public album named "My Trip Photos"
    And another registered user "regular_visitor" exists with the password "password123"

  Scenario: Owner sees the management section and can edit album details
    Given I am logged in as "gallery_owner"
    When I go to my profile page
    Then I should see a "My Galleries" section
    And I should see a management block for the album "My Trip Photos"

    When I fill in the name field for "My Trip Photos" with "Amazing Alpine Adventure"
    And I fill in the description field for "My Trip Photos" with "Photos from our 2025 trip."
    And I click the "Save Changes" button
    Then I should see a "Your changes have been saved." confirmation message
    And the name field for the album should contain "Amazing Alpine Adventure"

  Scenario: Owner can make an album private
    Given I am logged in as "gallery_owner"
    And I am on my profile page
    And I see the "My Galleries" section
    When I check the "Make this gallery private" box for the album "My Trip Photos"
    And I click the "Save Changes" button
    Then I should see a "Your changes have been saved." confirmation message
    
    When I log out
    And I log in as "regular_visitor"
    And I go to the main gallery
    Then I should not see the album "My Trip Photos"

  Scenario: Owner can make a private album public again
    Given "gallery_owner" owns a private album named "My Secret Project"
    And I am logged in as "gallery_owner"
    And I am on my profile page
    And I see the "My Galleries" section
    When I uncheck the "Make this gallery private" box for the album "My Secret Project"
    And I click the "Save Changes" button
    Then I should see a "Your changes have been saved." confirmation message
    
    When I log out
    And I log in as "regular_visitor"
    And I go to the main gallery
    Then I should see the album "My Secret Project"

  Scenario: User with no albums does not see the management section
    Given a registered user "new_user" exists who owns no albums
    When I log in as "new_user"
    And I go to my profile page
    Then I should not see a "My Galleries" section

  Scenario: Limited mode banner appears when only fallback albums qualify
    Given the installation lacks a categories ownership column
    And I am logged in as "gallery_owner"
    And I go to my profile page
    And I have at least one album where all photos were uploaded by "gallery_owner"
    Then I should see a "Limited mode" banner in the profile page