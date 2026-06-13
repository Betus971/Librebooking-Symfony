# Librebooking — Open Source Strategy

This document outlines the architectural strategy for maintaining `Librebooking` as a generic, open-source resource reservation tool.

## 1. Core Principle: A Generic Foundation

`Librebooking` must remain a **generic core** suitable for any organization (companies, universities, associations) needing to manage physical resources (meeting rooms, vehicles, equipment). 

It should **never** include organization-specific business logic in its core repository.

## 2. Maintaining Organization-Specific Overlays

If an organization requires specific features that do not belong in the open-source core (e.g., custom security badges, specific SSO integrations, or internal visitor management), they must implement these via **custom extensions** rather than forking and modifying the core.

### How to extend Librebooking for specific needs:

- **Custom Bundles:** Create private Symfony Bundles to encapsulate specific features. For example, a custom SSO provider (e.g., `MyOrgSsoBundle`) can be injected via Composer without altering the core `Librebooking` authentication logic.
- **Event Listeners / Subscribers:** Hook into the core events (e.g., `ReservationCreatedEvent`, `ReservationApprovedEvent`) to trigger organization-specific actions (like sending data to an internal API).
- **Decorating Services:** Use Symfony's service decoration to override or extend core services (like availability checkers or mailers) if specific logic is needed.

### Example: The "Visitors" Module
An organization might need to track external visitors associated with a reservation, print access badges, and log their entry.
- **Incorrect approach:** Adding `Visitor` and `Badge` entities directly to the `Librebooking` open-source core.
- **Correct approach:** The organization creates a private `VisitorManagementBundle` that depends on `Librebooking`. This bundle adds its own database tables and injects its UI components into the core application using Twig blocks or extensions.

## 3. Benefits

- **No Divergent Forks:** By keeping the core clean and extending via standard Symfony mechanisms, organizations avoid maintaining complex, divergent forks that become impossible to update.
- **Easy Upgrades:** The core application can be updated independently of the specific overlays, ensuring security patches and new generic features are easy to deploy.
- **Community Contributions:** A generic core encourages a wider community to adopt and contribute to the project, improving stability and feature sets for everyone.
