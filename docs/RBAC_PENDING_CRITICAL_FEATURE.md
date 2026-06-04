# Pending Critical Feature: Centralized RBAC

Role-Based Access Control is not complete and must be treated as a critical pending feature.

The repository contains partial role and visibility helpers, but they do not yet constitute a centralized, consistently enforced authorization system. Do not add temporary or page-specific permission logic as a substitute for the future RBAC architecture.

## Required Roles

- Owner
- Project Manager
- Field Manager
- Foreman
- Internal Worker
- External Worker

## Required Architecture

- Central backend authorization middleware
- One permission matrix and permission-key registry
- Route and API protection
- UI visibility derived from backend permissions
- Settings management interface
- Project-level access restrictions
- File and folder access restrictions
- Tool access restrictions
- Automated authorization tests for every protected capability

## Implementation Rule

All future authorization decisions must flow through the centralized RBAC layer. UI restrictions are only a usability measure and must never replace backend enforcement.
