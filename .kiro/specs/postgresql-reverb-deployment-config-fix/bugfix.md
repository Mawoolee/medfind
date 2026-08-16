# Bugfix Requirements Document

## Introduction

This bugfix makes MedFind's deployable configuration consistently select PostgreSQL and Laravel Reverb, and ensures operators can deploy the application behind Nginx over HTTPS without database ambiguity, disabled real-time broadcasting, insecure WebSocket connections, or disclosure of environment secrets. The fix aligns fresh-environment behavior with the intended PostgreSQL and Reverb runtime while preserving existing application data and working local behavior.

## Bug Analysis

### Current Behavior (Defect)

The current environment template and deployment guidance do not provide one consistent, production-ready path for PostgreSQL, Reverb, Nginx, and HTTPS.

1.1 WHEN a fresh environment is created using only the checked-in environment template, THE MedFind application SHALL select SQLite instead of PostgreSQL as its database connection.

1.2 WHEN a fresh environment is created using only the checked-in environment template, THE MedFind application SHALL contain multiple active broadcast connection declarations that either select different broadcasters or fail to identify Reverb as the single intended broadcaster.

1.3 WHEN MedFind is deployed without manually copying or reconstructing database and broadcasting settings from a working local environment, THE MedFind deployment SHALL lack a complete, consistent configuration for PostgreSQL or Reverb, causing at least one database-backed operation or real-time operation to fail.

1.4 WHEN an HTTPS client accesses a production deployment whose Reverb connection is configured with an insecure scheme or a host that is reachable only from the local environment, THE MedFind client SHALL fail to establish the Reverb WebSocket connection and SHALL not receive real-time updates or notifications through that connection.

1.5 WHEN an operator follows only the repository documentation to deploy MedFind in production, THE documentation SHALL provide local development startup instructions but SHALL not provide a complete Nginx and HTTPS procedure covering both Laravel HTTP traffic and Reverb WebSocket traffic.

1.6 WHEN a configuration example or deployment document is produced by copying values from a working environment file without replacing environment-specific values with placeholders, THE resulting example or document SHALL retain at least one non-placeholder database password, application key, Reverb secret, credential, or access token from that environment.

### Expected Behavior (Correct)

The deployable configuration and documentation must establish PostgreSQL and Reverb as the intended services while keeping production values configurable and secret.

2.1 WHEN a fresh environment is created from the checked-in environment template, THE system SHALL select PostgreSQL and include every PostgreSQL setting name referenced by the supplied deployment guidance, with each environment-specific or sensitive value represented by a non-secret placeholder explicitly requiring replacement before production use.

2.2 WHEN a fresh environment is created from the checked-in environment template, THE system SHALL contain exactly one active broadcast connection declaration, that declaration SHALL select Reverb, and no other active broadcast connection declaration SHALL be present.

2.3 WHEN MedFind is deployed with environment-specific values for every PostgreSQL and Reverb setting named by the supplied configuration guidance, THE system SHALL establish a PostgreSQL connection for database-backed features and a Reverb connection for real-time broadcasting without requiring any setting not identified in the checked-in environment template or deployment documentation.

2.4 WHEN an HTTPS client initiates real-time broadcasting in a production deployment, THE system SHALL complete a secure WebSocket connection to Reverb using the deployment's configured public host, the secure WebSocket scheme, and the port configured for the deployment's public TLS endpoint.

2.5 WHEN an operator follows the repository documentation to deploy MedFind in production, THE system SHALL provide ordered procedures for configuring Nginx to route Laravel HTTP traffic and proxy Reverb WebSocket traffic, enabling TLS for both traffic types, assigning every required production PostgreSQL and Reverb setting named by the checked-in environment template, starting each documented long-running application service, and verifying successful Laravel HTTP access, PostgreSQL connectivity, and secure Reverb WebSocket connectivity.

2.6 WHEN configuration examples or deployment documentation are created or updated, THE system SHALL represent passwords, application keys, Reverb credentials, TLS private-key values, and any other setting identified as sensitive by the supplied guidance using non-secret placeholders or variable names, and SHALL NOT contain any literal secret value copied from an environment file.

### Unchanged Behavior (Regression Prevention)

The configuration correction must not alter application data, functional behavior, or valid environment-specific customization beyond the affected deployment defaults and guidance.

3.1 WHEN MedFind starts with a configuration for a reachable PostgreSQL database containing existing application data and migration history, THE MedFind system SHALL retain and use the existing records, relationships, identifiers, and migration history without resetting, truncating, or reseeding the database.

3.2 WHEN developers start MedFind in a local environment with explicit PostgreSQL and Reverb host, port, and scheme settings, THE MedFind system SHALL use those settings for local HTTP and WebSocket operation.

3.3 WHEN automated tests start MedFind with an explicit isolated test database configuration, THE MedFind system SHALL use the test-specific database configuration without replacing any of its values with production PostgreSQL settings.

3.4 WHEN MedFind broadcasts an application event through Reverb, THE MedFind system SHALL preserve the event's existing channel, payload, authorization behavior, and client-visible real-time behavior.

3.5 WHEN a user accesses a non-real-time application feature, THE MedFind system SHALL preserve that feature's existing routes, responses, permissions, and user workflow.

3.6 WHEN an environment supplies deployment-specific hosts, ports, database credentials, certificates, or Reverb credentials through environment configuration, THE MedFind system SHALL use the supplied values without requiring hard-coded repository values.
