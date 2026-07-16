# Filename: cpt_profile_extraction.feature

Feature: CPT profile extraction compatibility
  As a maintainer
  I want CPT to stop owning profile data after the Owner Profile plugin is installed
  So that CPT remains focused on album privacy and ownership

  Scenario: CPT skips My Profile when Owner Profile plugin is active
    Given the CPT plugin is active
    And the Owner Profile plugin is active
    And I am logged in as an album owner
    When I open my Piwigo profile page
    Then CPT should attach the "My Galleries" section
    And CPT should not attach its legacy "My Profile" section
    And Owner Profile should attach the "My Profile" section

  Scenario: CPT album management still works after extraction
    Given the CPT plugin is active
    And the Owner Profile plugin is active
    And I am logged in as an album owner
    When I change one of my albums from public to private
    Then CPT should update the album status
    And CPT should synchronize owner/admin access
    And the profile data should not be modified

  Scenario: PLG can still use CPT album visibility helpers
    Given the CPT plugin is active
    And PLG is active
    When PLG captures a visibility snapshot
    Then CPT should provide album visibility mode
    And CPT should provide shared user ids
