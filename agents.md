# AGENTS.md

## Project
PatriotContracts

## Purpose
PatriotContracts is a government contracting intelligence platform built to ingest, normalize, search, and display federal contract opportunities and awards.

This repository is a live product, not a prototype. Preserve working functionality unless a change is explicitly required.

---

## Agent Role
You are an implementation agent working inside an existing production-minded repository.

Your job is to:
1. inspect the current codebase
2. follow the task queue in `AI_TASKS.md`
3. make the smallest safe changes necessary
4. preserve existing behavior whenever possible
5. commit changes in small, logically grouped units

---

## Core Rules

### Preserve what works
- Do not redesign the site unless explicitly instructed
- Do not rebuild working systems just because another design seems cleaner
- Do not rewrite stable files for style only
- Do not replace working logic with new abstractions unless clearly necessary

### Minimize scope
- Prefer surgical fixes over refactors
- Reuse existing files, functions, and patterns
- Do not move files, rename files, or change routes unless absolutely necessary
- Do not introduce new frameworks or unnecessary dependencies

### Protect business-critical systems
Unless explicitly instructed, do not modify:
- authentication flow
- membership logic
- Stripe / billing behavior
- API access control
- public routes
- visual design / layout

### Backend safety
- Use prepared statements
- Avoid duplicate logic
- Avoid NULL overwriting good data during ingestion
- Prefer additive migrations over destructive schema changes
- Preserve backward compatibility where practical

### Frontend safety
- Keep page structure and output as close to current behavior as possible
- Do not change styling unless required by a backend-driven fix
- Do not redesign the UI

---

## Approved Priorities
Focus on these areas unless told otherwise:
1. missing or empty listing fields
2. contract detail page data quality
3. ingestion reliability
4. safe upserts
5. search performance
6. logging and validation
7. indexing and query cleanup

---

## Required Workflow

### Phase 1: Inspect first
Before making broad changes:
- inspect relevant files
- determine exact cause of issue
- identify the smallest safe fix

### Phase 2: Implement minimally
- modify only files required for the task
- keep behavior stable
- avoid unrelated cleanup

### Phase 3: Validate
After changes:
- verify affected logic paths
- ensure no obvious regressions
- check for syntax/runtime issues where possible

### Phase 4: Record progress
- update `AI_TASKS.md`
- mark completed items clearly
- add short notes about what changed

### Phase 5: Commit
Create small focused commits, such as:
- `fix: prevent null overwrites in contract normalization`
- `perf: add indexes for listing search filters`
- `fix: restore agency/vendor fallback mapping on detail page`

Do not combine unrelated changes into one commit.

---

## File Handling Rules
- Touch as few files as possible
- If a function can be extended instead of rewritten, extend it
- If a query can be fixed instead of replaced, fix it
- If indexing solves the issue, do not rebuild the schema
- Only create new tables/services if the current structure cannot reasonably support the task

---

## Migration Rules
- Put schema changes in migration files
- Avoid destructive changes unless explicitly required
- Do not drop columns/tables unless instructed
- Prefer backward-compatible additions

---

## Logging Rules
When working on ingestion:
- log records fetched
- log records inserted
- log records updated
- log records skipped
- log validation failures
- never silently swallow critical data problems

---

## What Not To Do
- do not “modernize” the whole codebase
- do not convert the app to another framework
- do not restyle the site
- do not refactor unrelated modules
- do not replace stable code just to be clever

---

## Definition of Success
Success means:
- current site still works
- changes are minimal and understandable
- data quality improves
- performance improves where targeted
- commit history stays clean
- no unnecessary rebuilding