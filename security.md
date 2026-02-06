# Smart License Server — Security Architecture / Blueprint

This document defines the secure authorization context for the Smart License Server, detailing how **principals** interact with resources, owners, and roles.

---

## Principal

A **principal** is any **authenticated actor** in the system capable of performing actions.

A principal may be:

* **Human User**
* **Service Account** (non-human principal)
* **Platform** (system context, e.g., cron jobs)

**Key Notes:**

* Principals **do not directly own resources**.
* Resource ownership is expressed through the **Owner** entity.
* Principals may perform actions either **for themselves** or **on behalf of an Owner**.

---

## Owner

An **Owner** is a non-authenticating entity representing **resource ownership**.

**Characteristics:**

* Does **not authenticate**
* Does **not have roles or capabilities directly**
* Provides the **context** for role and capability evaluation
* Owns one or more resources (e.g., Hosted Applications)

### Owner Types

1. **Platform Owner**

   * Core system context
   * Owns system-level resources
   * Governs automated or canonical actions

2. **Individual Owner**

   * Represents a human user as a resource owner
   * Allows creation of service accounts for resource management

3. **Organization Owner**

   * Represents a group of human users collectively owning resources
   * Delegates authority through **roles assigned to members or service accounts**

---

## User

A **User** is a human principal who can authenticate and act in the system.

**Roles in the system:**

* May act **for self** via an Individual Owner
* May act **on behalf of an Organization** as a member

**Deletion considerations:**

* Removing a User deletes:

  * Their **individual role assignments**
  * Memberships in organizations
  * Their **Individual Owner record** (if any)
  * **Service Accounts** owned through their Individual Owner
  * **Role assignments of those Service Accounts**

---

## Service Account

A **Service Account** is a non-human principal for automated or API actions.

**Rules:**

* Must always act **on behalf of a User (Individual Owner)** or **Organization Owner**
* Can only perform actions permitted by the **roles assigned in that owner context**

**Deletion considerations:**

* Removing a Service Account deletes:

  * Its own **role assignments**
  * It does not affect the principal User or Organization

---

## Organization

An **Organization** is an Owner type representing a collective of users.

**Characteristics:**

* Owns resources collectively
* Delegates authority through **roles assigned to members or service accounts**
* Memberships are managed separately via the Organization Members table

**Deletion considerations:**

* Removing an Organization deletes:

  * Organization record
  * Organization memberships
  * **Direct role assignments** to the organization
  * Organization Owner record
  * **Service Accounts owned** by the organization
  * **Role assignments of those Service Accounts**

---

## Roles and Capabilities

### Roles

* Named groupings of **capabilities**
* Evaluated **within a specific Owner context**
* **Not assigned directly to principals**
* Provide contextual authority to both Users and Service Accounts

### Capabilities

* Canonical system-defined permissions
* Used to authorize actions
* Only meaningful within an **Owner scope**

---

## Resource

* Represents **Hosted Applications** (plugins, themes, software)
* Each resource is owned by **exactly one Owner**
* Ownership determines the **authorization context** for actions

---

## Key Principles

1. **Separation of Identity and Ownership**

   * Principals authenticate and act
   * Owners provide context for authorization

2. **Context-Dependent Authorization**

   * Roles and capabilities are evaluated **within an Owner scope**
   * A principal can have different capabilities across different owners

3. **Explicit Delegation**

   * Service Accounts must always act **on behalf of an owner**
   * Organization members receive delegated authority via roles

4. **Single Ownership**

   * Each resource is owned by **one Owner only**
   * Ownership dictates all authorization checks

5. **Cascading Deletion**

   * Deleting a principal or owner **removes all dependent records** (roles, memberships, service accounts) in a **single transaction where possible**
   * Ensures **data integrity** and prevents orphaned records

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                        PRINCIPAL                            │
│  (User, Service Account, or Platform)                       │
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
              │ (Individual │
              │ or Org)     │
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
