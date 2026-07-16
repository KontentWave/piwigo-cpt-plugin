# Filename: ucp_album_management.feature

Feature: Album Self-Management in User Control Panel
  As an album owner
  I want to manage my albums' details and privacy directly from my profile page
  And manage descendant albums under my Community user album when ownership is inherited
  And maintain a public profile for my main gallery page
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
    Then the name field for the album should contain "Amazing Alpine Adventure"

  Scenario: Owner can make an album private
    Given I am logged in as "gallery_owner"
    And I am on my profile page
    And I see the "My Galleries" section
    When I choose "Private" in the visibility selector for the album "My Trip Photos"
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
    When I choose "Public" in the visibility selector for the album "My Secret Project"
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

  Scenario: Owner can save album changes from an AJAX-driven profile page
    Given I am logged in as "gallery_owner"
    And I am on a theme-driven profile page that saves CPT changes through AJAX
    And I see the "My Galleries" section
    When I fill in the name field for "My Trip Photos" with "Amazing Alpine Adventure"
    And I click the "Save Changes" button
    Then I should see a "Your changes have been saved." confirmation message
    And the name field for the album should contain "Amazing Alpine Adventure"

  Scenario: Owner can share an album with selected users from the UCP editor
    Given I am logged in as "gallery_owner"
    And I am on my profile page
    And I see the "My Galleries" section
    When I choose "Shared with selected users" in the visibility selector for the album "My Trip Photos"
    And I select "regular_visitor" in the shared users picker for the album "My Trip Photos"
    And I click the "Save Changes" button
    Then I should see a "Your changes have been saved." confirmation message

    When I log out
    And I log in as "regular_visitor"
    And I go to the main gallery
    Then I should see the album "My Trip Photos"


  # Phase 2 hardening: CPT should not depend on Community assigning the same
  # user directly to every child album. Community may mark only the user's root
  # album with community_user; CPT must inherit ownership through the album tree.

  Scenario: Owner sees descendant albums below their Community user album in the UCP manager
    Given "gallery_owner" owns a public root album named "slecna1" through Community
    And the album "slecna1" has a public subalbum named "slecna1_album1" with no direct Community owner
    And the album "slecna1" has a public subalbum named "slecna1_album2" with no direct Community owner
    And the album "slecna1" has a public subalbum named "slecna1_album3" with no direct Community owner
    And I am logged in as "gallery_owner"
    When I go to my profile page
    Then I should see a "My Galleries" section
    And I should see a management block for the album "slecna1"
    And I should see a management block for the album "slecna1_album1"
    And I should see a management block for the album "slecna1_album2"
    And I should see a management block for the album "slecna1_album3"

  Scenario: Owner can change privacy on a descendant album inherited from their Community user album
    Given "gallery_owner" owns a public root album named "slecna1" through Community
    And the album "slecna1" has a public subalbum named "slecna1_album2" with no direct Community owner
    And I am logged in as "gallery_owner"
    And I am on my profile page
    And I see the "My Galleries" section
    When I choose "Private" in the visibility selector for the album "slecna1_album2"
    And I click the "Save Changes" button
    Then I should see a "Your changes have been saved." confirmation message

    When I log out
    And I log in as "regular_visitor"
    And I go to the album page for "slecna1"
    Then I should not see the album "slecna1_album2"

  Scenario: Owner sees the album-page privacy shortcut on a descendant album
    Given "gallery_owner" owns a public root album named "slecna1" through Community
    And the album "slecna1" has a public subalbum named "slecna1_album2" with no direct Community owner
    And I am logged in as "gallery_owner"
    When I go to the album page for "slecna1_album2"
    Then I should see an "Album privacy" section
    And I should see a "Change the album to private" button

  Scenario: Non-owner cannot manage a descendant album under another user's Community root album
    Given "gallery_owner" owns a public root album named "slecna1" through Community
    And the album "slecna1" has a public subalbum named "slecna1_album2" with no direct Community owner
    And I am logged in as "regular_visitor"
    When I go to my profile page
    Then I should not see a management block for the album "slecna1_album2"

    When I go to the album page for "slecna1_album2"
    Then I should not see an "Album privacy" section

  Scenario: Owner sees the album-page privacy shortcut on an owned public album
    Given I am logged in as "gallery_owner"
    And I go to the album page for "My Trip Photos"
    Then I should see an "Album privacy" section
    And I should see a "Change the album to private" button

  Scenario: Owner can make an album private from the album page and public again later
    Given I am logged in as "gallery_owner"
    And I go to the album page for "My Trip Photos"
    When I click the "Change the album to private" button
    Then I should see an "Album privacy updated." confirmation message

    When I log out
    And I log in as "regular_visitor"
    And I go to the main gallery
    Then I should not see the album "My Trip Photos"

    When I log out
    And I log in as "gallery_owner"
    And I go to the album page for "My Trip Photos"
    And I click the "Change the album to public" button
    Then I should see an "Album privacy updated." confirmation message


  # Phase 3: Owner Public Profile Metadata.
  # CPT owns the editor, storage, validation, and public profile assignment.
  # Tag Groups supplies controlled vocabulary. The Bootstrap Darkroom theme renders the table.

  Scenario: Owner sees the My Profile section in UCP
    Given "gallery_owner" owns a public root album named "slecna1" through Community
    And I am logged in as "gallery_owner"
    When I go to my profile page
    Then I should see a "My Profile" section
    And I should see explanatory text that these details may be displayed publicly

  Scenario: Owner can save public profile fields
    Given "gallery_owner" owns a public root album named "slecna1" through Community
    And the Tag Groups vocabulary contains "Nationality" with value "Ukrainian"
    And the Tag Groups vocabulary contains "Eyes" with value "blue eyes"
    And the Tag Groups vocabulary contains "Hair" with value "brown hair"
    And I am logged in as "gallery_owner"
    And I am on my profile page
    When I open the "My Profile" section
    And I choose "Ukrainian" for "Nationality"
    And I fill in "Age" with "24 rokov"
    And I fill in "Measurements" with "160 cm, 55 kg, chodidlo 37 EU"
    And I choose "blue eyes" for "Eyes"
    And I choose "brown hair" for "Hair"
    And I click the "Save Public Profile" button
    Then I should see a "Your public profile has been saved." confirmation message

  Scenario: Visitor sees the structured public profile table on the owner root album
    Given "gallery_owner" owns a public root album named "slecna1" through Community
    And "gallery_owner" has saved public profile field "Nationality" as "Ukrainian"
    And "gallery_owner" has saved public profile field "Age" as "24 rokov"
    And "gallery_owner" has saved public profile field "Measurements" as "160 cm, 55 kg, chodidlo 37 EU"
    When I go to the album page for "slecna1"
    Then I should see a public profile table
    And the public profile table should contain "Nationality" with value "Ukrainian"
    And the public profile table should contain "Age" with value "24 rokov"
    And the public profile table should contain "Measurements" with value "160 cm, 55 kg, chodidlo 37 EU"
    And the public profile table should appear after the album description on desktop Bootstrap Darkroom pages

  Scenario: Public profile table falls back to album description when no profile metadata exists
    Given "gallery_owner" owns a public root album named "slecna1" through Community
    And the album "slecna1" has description "Pokusny popis"
    And "gallery_owner" has not saved any public profile fields
    When I go to the album page for "slecna1"
    Then I should not see a public profile table
    And I should see the album description "Pokusny popis"

  Scenario: Bootstrap Darkroom mobile layout places the profile after the first album and description
    Given "gallery_owner" owns a public root album named "slecna1" through Community
    And "gallery_owner" has saved public profile field "Age" as "24 rokov"
    And the album "slecna1" has description "Pokusny popis"
    And the album "slecna1" has a public subalbum named "slecna1_album1"
    And the album "slecna1" has a public subalbum named "slecna1_album2"
    When I go to the album page for "slecna1" on a mobile Bootstrap Darkroom viewport
    Then I should see the first album card before the album description
    And I should see the public profile table after the album description
    And I should see the remaining album cards after the public profile table

  Scenario: Non-owner cannot edit another user's public profile
    Given "gallery_owner" owns a public root album named "slecna1" through Community
    And I am logged in as "regular_visitor"
    When I go to my profile page
    Then I should not see a public profile editor for "slecna1"

    When I submit a crafted request to update the public profile for "slecna1"
    Then the request should be rejected
    And the public profile for "slecna1" should remain unchanged

  Scenario: Empty public profile values are not displayed
    Given "gallery_owner" owns a public root album named "slecna1" through Community
    And "gallery_owner" has saved public profile field "Nationality" as "Ukrainian"
    And "gallery_owner" has cleared public profile field "Measurements"
    When I go to the album page for "slecna1"
    Then the public profile table should contain "Nationality" with value "Ukrainian"
    And the public profile table should not contain a row for "Measurements"
