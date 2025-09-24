## CPT_project_sheet.md (The Present 📜)

This document is the living technical specification for the "Core Privacy Toggle" plugin. It will be updated as development progresses.

### Phase 1 (MVP): UCP Album Management & Core Privacy Toggle (Draft)

#### `Action`

Empower non-admin gallery owners by integrating album management controls (name, description, privacy, and representative image) directly into their User Control Panel (UCP), enhancing self-sufficiency and reducing administrative overhead.

### `Task`

This is the sequential plan to implement the feature.

1.  **Plugin Structure Setup:**

    - Create the plugin directory `core_privacy_toggle/`.
    - Inside, create `main.inc.php`, `template/`, and a new `js/` directory.

2.  **Hook into the User Profile Page:**

    - In `main.inc.php`, use `add_event_handler('loc_begin_profile', 'commplus_setup_ucp_tabs');` to prepare and inject our new functionality.

3.  **Conditional Logic (within `ctp_setup_ucp_tabs`):**

    - Get the current `user_id`.
    - Query the `CATEGORIES_TABLE` to get a **count** of albums owned by the user.
    - **If the user owns zero albums, the function stops here.** The UI remains unchanged for them.

4.  **Prepare and Inject Tab Content:**

    - If the user owns albums, fetch the full album data (id, name, comment, status).
    - Assign the album data to a Smarty template variable (`UCP_ALBUMS`).
    - Parse the album management form from a template file (`template/ucp_album_manager.tpl`) into a PHP variable.
    - Use Piwigo's `combine_script` function to:
      - Pass the parsed HTML content to the frontend as a JavaScript variable.
      - Load a new JavaScript file: `js/ucp_tabs.js`.

5.  **Create the Tab Interface with JavaScript (`js/ucp_tabs.js`):**

    - This script will execute on the profile page to **dynamically restructure the UI**.
    - On page load, it will find the main profile form (`#profile`).
    - It will create the necessary HTML for a tabbed interface (e.g., a `<ul>` for tab navigation and `<div>` containers for tab content).
    - It will **move the existing profile form elements** into the first tab panel, labeled "Profile".
    - It will inject our new album management form HTML (passed from PHP) into the second tab panel, labeled "My Galleries".
    - It will add click event listeners to the tabs to handle showing and hiding the correct content panel.

6.  **Create the Album Manager UI Template (`template/ucp_album_manager.tpl`):**

    - This file now _only_ contains the form elements for managing the albums (the loop, text inputs, textareas, and checkboxes). It does not contain any surrounding `<fieldset>` or submit button, as it will be injected into the main profile form.

7.  **Submission Handling (within `commplus_setup_ucp_tabs`):**
    - The logic for saving the form data remains the same. It checks for `$_POST` data, iterates through submitted values, performs the security ownership check for each album, and updates the database accordingly.

#### `Accessibility (ARIA)`

The dynamically created tab interface must be fully accessible.

- The tab list (`<ul>`) will have `role="tablist"`.
- Each tab control (`<li>` or `<a>`) within the list will have `role="tab"` and an `aria-controls` attribute pointing to the ID of its corresponding panel.
- The active tab will have `aria-selected="true"`.
- Each tab content panel (`<div>`) will have `role="tabpanel"` and an `aria-labelledby` attribute pointing to the ID of its controlling tab.
- Inactive tab panels will be hidden using the `hidden` attribute or `display: none;`.

#### `Test` Plan

This plan uses a combination of **PHPUnit** for backend unit tests and **Cypress** for frontend end-to-end (E2E) tests to ensure full coverage.

**PHPUnit (Unit & Integration Tests - The "Inner Loop")**

1.  **Test Owner Albums Retrieval:** Verify that the function fetching albums for the UCP returns _only_ the albums owned by the specified user.
2.  **Test Save Action - Ownership Security:** Verify that the save logic explicitly rejects any attempt to modify an album by a user who is not its owner.
3.  **Test Save Action - Data Update:** Verify that submitting the form correctly updates the `name` and `description` in the database.
4.  **Test Privacy Toggle - Make Private:** Verify that checking the privacy box correctly changes the album's `status` to `private` and updates permissions.
5.  **Test Privacy Toggle - Make Public:** Verify that unchecking a previously private album's box correctly changes its `status` back to `public`.

**Cypress (End-to-End Test - The "Outer Loop")**

1.  **Full User Workflow Test:**
    - It logs in as an album owner.
    - It navigates to the profile page and confirms the "Profile" and "My Galleries" tabs are present.
    - It clicks the "My Galleries" tab.
    - It successfully changes an album's name and checks the "private" box.
    - It submits the form and verifies the success message.
    - It logs out and logs in as a different, non-admin user.
    - It confirms the modified album is no longer visible in the main gallery.
