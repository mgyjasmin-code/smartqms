Act as a senior software engineer and refactoring specialist.

PROJECT
Project name: Smart QMS
Technology stack: PHP, JavaScript, HTML/CSS, MySQL, and XAMPP

CONTEXT
The Smart QMS codebase has become difficult to understand, manage, debug,
and maintain. Some files contain mixed responsibilities, repeated code,
tightly coupled logic, and overcomplicated workflows. I want to refactor
the existing project without rewriting it from scratch.

PRIMARY GOAL
Refactor the Smart QMS codebase to improve:

- Readability
- Modularity
- Maintainability
- Reusability
- Separation of concerns
- Testability
- Developer onboarding
- Overall code organization

PROGRAMMING PARADIGMS TO APPLY

1. Modular Programming
   - Organize the system into focused modules based on features or
     responsibilities.
   - Separate authentication, queue management, tickets, services,
     staff operations, admin operations, database access, validation,
     and shared utilities.
   - Each module and file should have a clear responsibility.
   - Avoid large files that handle multiple unrelated responsibilities.

2. Procedural Programming
   - Extract repeated and complicated operations into small, reusable,
     clearly named functions.
   - Each function should perform one clear task whenever practical.
   - Use consistent inputs, return values, and error handling.
   - Avoid deeply nested conditions, duplicated logic, hidden side effects,
     unnecessary global variables, and overly long functions.

3. Event-Driven Programming
   - Organize JavaScript behavior around user and system events.
   - Examples include form submissions, button clicks, confirmations,
     ticket updates, queue actions, and automatic status refresh.
   - Separate DOM/UI handling from validation, business logic, and
     network requests.
   - Prevent duplicate event listeners and tightly coupled UI code.

4. Declarative Programming
   - Use clear and focused SQL queries for database operations.
   - Centralize database access where appropriate.
   - Use prepared statements and parameter binding.
   - Avoid duplicated queries, SQL injection risks, and mixing large
     amounts of SQL with presentation code.

OOP CONSTRAINT
Do not convert the project to Object-Oriented Programming.
Do not introduce classes, inheritance, interfaces, or design patterns
that require OOP unless I explicitly approve them later.

IMPORTANT CONSTRAINTS

- Refactor the existing system; do not rewrite it from scratch.
- Preserve all working features and current business rules.
- Do not unnecessarily change the user interface.
- Do not change routes, URLs, form field names, API response formats,
  session keys, database schema, or user workflows unless necessary.
- If one of these must change, explain the reason and ask for approval first.
- Preserve role-based access for clients, staff, and administrators.
- Do not remove code until you verify that it is unused.
- Avoid unnecessary abstractions and overengineering.
- Do not install new dependencies unless clearly justified and approved.
- Preserve security controls and improve unsafe database handling when found.
- Work carefully with existing files and unrelated user changes.

SMART QMS FEATURES THAT MUST CONTINUE WORKING

- Login and registration
- Role-based authentication and redirection
- Client, staff, and administrator authorization
- Service selection
- Joining a queue
- Regular and Senior Citizen/PWD priority handling
- Ticket generation
- Prevention of duplicate active tickets
- Queue and ticket status display
- Predicted waiting-time display
- Staff service-window operations
- Call Next, Skip, and Complete actions
- Admin management functions
- Reports and other existing project features

REQUIRED WORKFLOW

Phase 1 — Codebase Audit

Before modifying files:

1. Inspect the repository structure and relevant configuration.
2. Identify application entry points and important workflows.
3. Trace dependencies between PHP, JavaScript, CSS, and SQL.
4. Identify:
   - Large or mixed-responsibility files
   - Repeated code
   - Long or complicated functions
   - Deeply nested conditions
   - Global state and hidden dependencies
   - Business logic mixed with HTML
   - SQL mixed throughout presentation files
   - Repeated event handlers
   - Inconsistent naming and error handling
   - Dead or apparently unused code
   - Security-sensitive code affected by refactoring
5. Run available tests and record the current baseline.
6. Do not modify the code during this audit phase.

Phase 2 — Refactoring Plan

Create a prioritized plan containing:

- Problem or code smell
- Affected files
- Proposed change
- Paradigm being applied
- Expected benefit
- Risk level
- Verification method
- Recommended implementation order

Base the proposed structure on the actual repository. Do not force an
imaginary architecture onto the project.

Phase 3 — Incremental Implementation

After presenting the audit and plan, refactor the project in small,
reviewable batches. Recommended order:

1. Shared configuration and database access
2. Shared validation and utility functions
3. Authentication and authorization
4. Client queue and ticket workflow
5. Staff and service-window workflow
6. Administrator features
7. JavaScript events and UI behavior
8. Cleanup of verified dead or duplicated code

For every batch:

1. State which files will be changed.
2. Explain what behavior must remain unchanged.
3. Make the smallest practical refactoring.
4. Check syntax and run relevant tests.
5. Manually verify critical workflows when automated tests are unavailable.
6. Review the diff for accidental behavior changes.
7. Report:
   - Files changed
   - What was improved
   - Paradigm applied
   - Tests performed
   - Results
   - Remaining risks or follow-up work

ACCEPTANCE CRITERIA

The refactoring is successful when:

- Existing Smart QMS functionality still works.
- Code is organized into understandable modules.
- Repeated logic is reduced.
- Functions have clear names and responsibilities.
- JavaScript events are organized and not duplicated.
- Database queries use safe and consistent handling.
- UI code, business logic, validation, and database operations are
  better separated.
- Client, staff, and administrator permissions remain enforced.
- The database remains compatible with existing data.
- Modified files pass applicable syntax checks and tests.
- No OOP implementation has been introduced.
- Documentation explains the updated structure and where future
  developers should add new functionality.

Start with Phase 1 only. Present the codebase audit and proposed
refactoring plan before changing any project files.
