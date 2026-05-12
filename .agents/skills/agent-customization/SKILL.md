# agent-customization SKILL

Purpose
-------
Provide a reusable, workspace-scoped skill for creating or updating VS Code agent customization files (SKILL.md, .agent.md, .instructions.md, .prompt.md, copilot-instructions.md, AGENTS.md). The skill guides an agent through extracting a repeatable workflow from conversation history, drafting a SKILL.md, saving it into the repository, and proposing follow-up clarifications.

When to use
-----------
- A user asks to create or modify agent customization files or to codify a conversational workflow into a reusable skill.
- The change affects instruction files listed above or introduces new agent behaviors, triggers, or tools.

Inputs
------
- Conversation history (agent should search for relevant instructions and patterns).
- Target scope: `workspace` or `personal` (ask if unspecified).
- Desired filename and path (default: `.agents/skills/<skill-name>/SKILL.md`).

Outputs
-------
- A drafted `SKILL.md` saved in the workspace at the requested path.
- A short list of clarifying questions to resolve ambiguous decisions.
- Example prompts and test cases to exercise the skill.

Step-by-step process
--------------------
1. Scan the workspace and conversation for existing agent rules, skills, and templates. Prioritize files in `.agents/` and repository root like `AGENTS.md`, `GEMINI.md`.
2. Extract the workflow: list of steps, decision points, quality criteria, and required tools/actions.
3. Ask the user two quick clarifying questions if scope or approval policies are unclear (scope, DB changes, auto-run workflows).
4. Draft `SKILL.md` using the template section below and include example prompts and expected outputs.
5. Save the file to the requested path and report the created file(s) and rationale.
6. Propose follow-up validations (peer review, run CI/workflow) and provide sample tests or checklist.

Decision points / branching logic
-------------------------------
- Scope: workspace vs personal — if workspace, commit path under `.agents/`; if personal, suggest user-local storage.
- Approval: any steps that would run database-migrating or destructive commands require explicit user approval.
- Automation: whether creating a SKILL.md should auto-register or kickoff a workflow (ask first).

Quality criteria and completion checks
-----------------------------------
- SKILL.md must include: purpose, triggers, inputs, outputs, step-by-step process, examples, and ambiguous points to confirm.
- Saved file path must follow `.agents/skills/<skill-name>/SKILL.md`.
- Provide at least two example prompts and one minimal test/checklist.

Template (use this when drafting)
--------------------------------
Title: <skill-name>
Short description: one sentence
Purpose: why this exists
When to use: triggers and examples
Inputs: list
Outputs: list
Process: numbered steps
Decision points: concise bullets
Quality criteria: checklist
Examples: 2–4 example prompts
Saved path: `.agents/skills/<skill-name>/SKILL.md`
Notes / Ambiguities: items to ask the user

Minimal example prompts
-----------------------
- "Create a SKILL.md that automates code review for PHP controllers, saving to `.agents/skills/php-controller-review/SKILL.md`."
- "Update AGENTS.md rules so that database-altering commands prompt for approval — draft changes in `.agents/skills/db-approval/SKILL.md`."

Follow-up questions the agent should ask (always include these when ambiguity exists)
-------------------------------------------------
1. Should this SKILL be workspace-scoped (committed) or personal (local only)?
2. Are database-destructive actions allowed to be automated, or must the agent always request manual approval?
3. Any naming conventions for skill folders or frontmatter metadata required by your workflows?

What this skill produces
------------------------
- A saved `SKILL.md` following the template above, plus a short checklist of what to test and example prompts to run.

Suggested next customizations
---------------------------
- Add a `frontmatter` convention (YAML) for metadata so skills can be discovered programmatically.
- Provide a small test harness or CI job that lints new SKILL.md files for required sections.

Examples of verification / tests
-------------------------------
- Verify file exists at `.agents/skills/<skill-name>/SKILL.md`.
- Confirm file includes `Purpose`, `When to use`, `Process`, and `Examples` headings.
- Manual: run one example prompt with the agent and confirm the created files and questions match expectations.

Responsibilities & permissions
-----------------------------
- The skill should never run destructive commands (drop/migrate DB) without explicit, in-band user approval.

Change log
----------
- v0.1 — Initial draft.
