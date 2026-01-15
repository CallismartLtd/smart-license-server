# Smart License Server — Security Architecture / Blueprint

This security architecture defines a secure authorization context in which **principals** (authenticated actors) perform **actions** within the application.

---

## Principal

The **principal** is the currently authenticated actor in the application.

A principal may be:
- A **human user**
- A **service account**
- The **platform** (core system actors such as cron jobs)

**Important:** A principal does not own resources.

Resources are owned through an **Owner** entity, enabling principals to:
- Act on resources they own indirectly via an **Individual Owner** context
- Perform actions delegated by an **Individual Owner** or an **Organization**

---

## Actions

**Actions** represent discrete activities that may be performed within the application.

---

## Capability

A **capability** represents a canonical, system-defined permission that may be evaluated during authorization.

---

## Roles

**Roles** are named groupings of capabilities that are resolved for a principal within a specific owner context.

**Important:** 
- Roles are **not** assigned directly to principals
- Roles have **no meaning** outside an owner scope

---

## User

A **User** is a human principal who can authenticate and perform actions in the application.

A user may act:
- **For self**, within an **Individual Owner** context
- **On behalf of an Organization**, within an **Organization owner** context

---

## Service Account

A **Service Account** is a non-human principal that authenticates and performs actions in the application.

A service account must **always** act on behalf of:
- A **User** (Individual Owner), or
- An **Organization**

---

## Owner

An **Owner** is a non-authenticating entity that represents resource ownership context.

An owner:
- Does **not** authenticate
- Does **not** have roles or capabilities
- Serves as the **scope** within which a principal's roles and capabilities are evaluated

### Owner Types

There are three owner types:

#### 1. Platform Owner
Represents the core system context for canonical or automated actions.
- The platform owns all resources
- Its actions are governed by system-defined rules

#### 2. Individual Owner
Represents a human user who owns resources in the repository.

#### 3. Organization Owner
Represents a collection of one or more human users that collectively own resources and delegate authority through roles.

---

## Resource

The primary resource in this application is a **Hosted Application**.

Hosted applications may include:
- WordPress plugins
- WordPress themes
- Custom software

**Ownership Rule:** Each resource is owned by **exactly one** Owner.

---

## Architecture Summary
```
┌─────────────────────────────────────────────────────────────┐
│                        PRINCIPAL                             │
│  (Authenticated Actor: User, Service Account, or Platform)   │
└────────────────────┬────────────────────────────────────────┘
                     │
                     │ performs
                     ▼
              ┌─────────────┐
              │   ACTIONS   │
              └─────────────┘
                     │
                     │ authorized by
                     ▼
              ┌─────────────┐
              │ CAPABILITIES│
              └─────────────┘
                     │
                     │ grouped into
                     ▼
              ┌─────────────┐
              │    ROLES    │
              └─────────────┘
                     │
                     │ evaluated within
                     ▼
              ┌─────────────┐
              │    OWNER    │
              │   CONTEXT   │
              └─────────────┘
                     │
                     │ owns
                     ▼
              ┌─────────────┐
              │  RESOURCES  │
              │  (Hosted    │
              │Applications)│
              └─────────────┘
```

---

## Key Principles

1. **Separation of Identity and Ownership**
   - Principals authenticate and act
   - Owners provide context for authorization

2. **Context-Dependent Authorization**
   - Roles and capabilities only have meaning within an owner scope
   - The same principal may have different capabilities in different owner contexts

3. **Explicit Delegation**
   - Service accounts must always act on behalf of a user or organization
   - Organization members receive delegated authority through roles

4. **Single Ownership**
   - Each resource has exactly one owner
   - Ownership determines the authorization context for all actions on that resource