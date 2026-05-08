# AGENTS

## Project Intent
- Maintain one shared operational context for all agents working in this repository.
- Keep updates factual, concise, and directly actionable.

## Read First
1. AGENTS.md
2. PROJECT_STATUS.md
3. TASKS.md
4. docs/decisions.md
5. docs/handoffs/HANDOFF_TEMPLATE.md

## Working Rules
- Prioritize repository evidence over assumptions.
- Label uncertain information as Assumption, Unknown, or Placeholder.
- Avoid duplicate context across files.
- Keep task status and project status synchronized.

## Context Check Before Work
- Confirm Current Objective and Current Phase in PROJECT_STATUS.md.
- Confirm active items in TASKS.md (Doing, Blocked).
- Review latest decision in docs/decisions.md if change affects architecture or workflow.

## Definition of Done
- Relevant task state updated in TASKS.md.
- PROJECT_STATUS.md updated when progress, blockers, or scope changes.
- docs/decisions.md updated when a durable decision is made.
- Handoff record prepared when work is transferred.

## Finishing Ritual
1. Move tasks to correct status section.
2. Update Done, In Progress, and Blockers/Risks in PROJECT_STATUS.md.
3. Log decision if long-term impact exists.
4. Prepare handoff note from template when needed.

## Required Output Format
- Summary: what changed.
- Files touched: exact paths.
- Validation: checks performed and result.
- Open items: blockers, assumptions, or unknowns.

## Update Rules
- Update only impacted sections; do not rewrite whole files.
- Keep wording stable and status labels consistent.
- Remove stale or duplicated entries immediately.