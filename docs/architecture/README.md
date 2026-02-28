# Architecture Decision Records

This directory contains Architecture Decision Records (ADRs) that document significant architectural decisions made in the quality-tools project.

## What are ADRs?

Architecture Decision Records capture important architectural decisions along with their context and consequences. They help future maintainers understand not just what decisions were made, but why they were made and what alternatives were considered.

## Template

New ADRs should be created using the template at: `docs/.templates/adr.md`

## Index of ADRs

| ADR | Title | Status | Related To |
|-----|-------|--------|------------|
| [0001](0001-context-aware-security-validation.md) | Context-Aware Security Validation | Accepted | Issue 022 |
| [0002](0002-security-at-entry-points.md) | Security at Entry Points | Accepted | Issue 022 |
| [0003](0003-code-duplication-elimination-through-refactoring.md) | Code Duplication Elimination Through Refactoring | Accepted | Issue 022 |
| [0004](0004-inheritance-based-security-propagation.md) | Inheritance-Based Security Propagation | Accepted | Issue 022 |

## Creating New ADRs

1. Copy the template from `docs/.templates/adr.md`
2. Name it with the next sequential number: `NNNN-descriptive-title.md`
3. Fill in all sections of the template
4. Add an entry to the index table above
5. Link to the ADR from relevant documentation

## ADR Lifecycle

- **Proposed**: Initial state when an ADR is created for discussion
- **Accepted**: The decision has been made and is being implemented
- **Deprecated**: The decision is no longer relevant or has been reversed
- **Superseded**: Replaced by a newer ADR (link to the new one)

## Best Practices

- Write ADRs at the time of decision, not retroactively
- Keep them concise but complete
- Be honest about trade-offs and negative consequences
- Document rejected alternatives and why they weren't chosen
- Never delete ADRs - mark them as deprecated or superseded instead