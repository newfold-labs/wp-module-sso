---
name: wp-module-sso
title: Integration
description: How the module registers and integrates.
updated: 2026-09-21
---

# Integration

The module registers with the Newfold Module Loader via bootstrap.php. The host plugin typically registers an SSO service. See [dependencies.md](dependencies.md).

## Redirect guard

Successful SSO pins the intended destination so other plugins cannot hijack it with a first-run onboarding redirect.

- During login, `SSO_Helpers::triggerSuccess()` registers a `wp_redirect` filter at `PHP_INT_MAX` **before** firing `wp_login`.
- The same URL is stored in a short-lived per-user transient (default 120 seconds; filter `newfold_sso_redirect_guard_ttl`).
- On the landing request, `guard_pending_redirect()` runs on `admin_init` at `PHP_INT_MIN` and re-pins `wp_redirect`. The transient is consumed only if that request was not rewritten away; a hijack that is forced back to the SSO URL keeps the guard for the next hop.

Pinned URLs are passed through `wp_validate_redirect()` so an off-site location cannot be forced.
