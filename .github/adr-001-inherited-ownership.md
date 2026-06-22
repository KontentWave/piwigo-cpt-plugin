# ADR 001: Inherited ownership belongs in CPT, not a Community fork

## Status

Accepted

## Date

2026-06-18

## Context

Community currently models ownership as a direct user-to-album assignment through `categories.community_user` or legacy `categories.user_id`. In practice, galleries often use one Community-owned root album with several descendant subalbums. CPT needs a reliable way to let the same user manage those descendants in the profile/UCP editor and from the album-page privacy shortcut.

The same real-world tree assumption also affects Community's upload UX: if a user is effectively working inside one owned root tree, upload targets should not offer unrelated user roots. In this workspace that behavior is currently enforced by a small local Community integration patch, but the ownership rule and rationale still live with CPT.

Forking Community would make CPT depend on a second maintained customization layer, increase upgrade risk, and spread album-management rules across two plugins. The missing behavior is not a Community storage problem; it is CPT's ownership interpretation problem.

## Decision

CPT will extend ownership resolution internally.

The effective ownership rule is:

1. direct owner wins when the album has `community_user` or legacy `user_id`
2. if the album has no direct owner, CPT walks the ancestor chain using `uppercats` first and `id_uppercat` as a fallback
3. the first directly owned ancestor defines the descendant's effective owner
4. if any child album has an explicit direct owner, that owner blocks parent inheritance
5. if no explicit owner exists in the tree, CPT may still use the exclusive-contributor fallback

## Consequences

Positive:

- Community remains unmodified and upgradeable.
- All CPT surfaces share one ownership rule: UCP retrieval, server-side writes, and album-page toggle visibility.
- Permission synchronization can consistently preserve admin plus effective owner access for inherited descendants.
- PHPUnit coverage can isolate the behavior without needing a Community fork in the test environment.
- Related Community-side UX clamps can stay small and local because the higher-level ownership policy is still documented and tested from the CPT side.

Tradeoffs:

- CPT now owns a small amount of album-tree interpretation logic.
- Tree-aware retrieval and permission sync require additional tests and careful handling of explicit child-owner overrides.

## Follow-up

- Cypress smoke coverage now protects descendant visibility, descendant private guest blocking, explicit child-owner override, fallback limited-mode visibility, and the no-qualifying-albums hidden-state path. Broader scenario depth can still grow later from the feature files.
- Keep CPT documentation explicit about any local Community integration patches that enforce the same owned-tree rule outside CPT itself.
- Keep the local Community integration patches narrow: upload-target scoping should stay aligned to the effective owned tree, and photo-level privacy UI should remain hidden while CPT remains the album-level privacy authority for this deployment.
- Revisit only if Community later ships first-class inherited ownership semantics that CPT can trust directly.
