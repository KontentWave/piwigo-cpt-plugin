# AI Coding Agent Instructions: Core Privacy Toggle (CPT) Plugin

These instructions capture the essential, long-term conventions for this project.

## Project Overview

-   **Piwigo Version:** `15.6.0`
-   **Target PHP:** `8.1+`
-   **Critical Dependency:** This plugin is an extension for the Piwigo **`Community`** plugin. It requires `Community` to be installed and active, as it relies on its core feature of user-owned albums.
-   **Goal:** To safely and robustly extend Piwigo's User Control Panel (UCP).

## CONTEXT: GitHub metadata

-   The root `.github/` directory is a symlink to the active plugin’s `.github` folder (`plugins/core_privacy_toggle/.github`).
-   When editing files like workflows or issue templates, treat the symlinked files as if they were physically located at the repository root. Always verify you are modifying the plugin’s source files.

## Architectural Principles

We follow a strict separation of concerns to keep the codebase clean, secure, and maintainable.

-   **PHP (The Brain):** All business logic, data fetching, security validation, and database updates happen here. PHP is the single source of truth.
-   **Smarty Templates (The Skeleton):** Templates are for HTML structure and displaying data only. They should contain minimal logic (loops, conditionals) and never perform data manipulation.
-   **JavaScript (The Polish):** JS is used for **progressive enhancement**. The core functionality must work with JavaScript disabled. Use JS to create richer user experiences, like the tabbed interface, but not to deliver essential features.

## Core Conventions

These are the non-negotiable rules for writing code in this project.

-   **Prefixes:** All new global functions must be prefixed with `cpt_` to prevent conflicts with Piwigo core or other plugins. Constants should use `CPT_`.
-   **Security First:**
    -   **Always validate ownership on the server** before any database write. Never trust user-submitted IDs.
    -   Use prepared statements for all database queries to prevent SQL injection.
    -   Always escape output in templates (e.g., `{$VARIABLE|escape}`) to prevent XSS.
-   **Code Style:** Follow Piwigo core coding standards. Keep functions small and focused on a single task. Use early returns to reduce nesting.

## Quality & Testing

-   **Testing Strategy:** We use a dual approach for full coverage.
    -   **PHPUnit:** For all backend logic, especially security checks and database interactions (the "Inner Loop").
    -   **Cypress:** For end-to-end user workflow testing in a real browser (the "Outer Loop").
-   **Accessibility (ARIA):** All user interface components must be fully accessible. This includes using proper ARIA roles for dynamic widgets (like tabs), ensuring keyboard navigability, and maintaining focus management.

## Contribution Guidelines

-   Keep pull requests small and focused on one conceptual change.
-   Run all tests before submitting a pull request.
-   If a core architectural decision is made, update this document.

## Optional ARIA Reminder (High-Level)

- Use proper roles: `role="tablist"` for the container, `role="tab"` for each tab trigger, `role="tabpanel"` for content panels.
- Exactly one tab has `aria-selected="true"`; inactive tabs have `aria-selected="false"` (or omit) and `tabindex="-1"` if roving focus is used.
- Panels should reference their controlling tab via `aria-labelledby` and be hidden (e.g. `hidden` attribute) when inactive.
- Keyboard (baseline expectations): Left/Right (or Up/Down) navigate tabs, Home jumps first, End jumps last, Enter/Space activates.
- Progressive enhancement: UI still functional without JavaScript (tabs degrade to linear flow or are simply absent if dynamic).

## Minimal Testing Expectations (Phase-Agnostic Baseline)

Backend (PHPUnit):
- Ownership filtering returns only the authenticated user’s albums/data slices.
- Unauthorized update attempts are rejected (no side effects, clear error path/logging if applicable).
- Name & description updates persist correctly (UTF-8 & length edge cases included).
- Privacy state transitions (public ↔ private) update status & any required permission artifacts.

Frontend (Cypress or equivalent E2E):
- Enhanced UI elements (e.g., tabs) appear only when relevant data exists.
- Basic edit + submit round-trip reflects changed values after reload.
- Privacy change affects visibility/access from a non-owner session.
- Keyboard interaction on any dynamic widget meets minimal ARIA expectations (focus order, activation).

Non-Goals for Baseline (keep tests lean):
- No snapshot tests for full HTML (avoid brittleness).
- No timing-based assertions unless strictly required.

## Symlinks Note (Repeat for Emphasis)

-   Files shown at the repository root may actually reside in the plugin directory; treat them transparently. The `.github` directory will always map to the active plugin.