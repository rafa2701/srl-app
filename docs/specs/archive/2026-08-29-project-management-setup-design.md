# Project Management Setup Design

## Goal
Establish a lightweight, AI-friendly project management structure for the SRL League System. This structure will enforce consistent rules, track progress, maintain a history of changes, and mandate a specification-driven development process.

## Architecture & Components

### 1. Project Rules (`AGENTS.md` and `.agents/rules/`)
- **`AGENTS.md` (Root)**: A central, lightweight file that all AI tools (Antigravity, Kilo Code, OpenCode) will read automatically. It will act as a router and contain the most critical project-wide directives (e.g., "Always update the CHANGELOG.md and TODO.md").
- **`.agents/rules/` (Directory)**: A modular directory containing domain-specific rules. 
    - `workflow.md`: Rules for updating the changelog, todo, and following spec-driven development.
    - (Future rules can be added here for frontend, database, etc.)
- **Benefit**: Keeps the context window small while providing flexibility for different AI tools.

### 2. Spec-Driven Development (`docs/specs/`)
- **Location**: `docs/specs/` will hold all design documents (like this one).
- **Process**: Before any creative work or feature implementation, a design spec must be written here and approved by the user.
- **Enforcement**: `AGENTS.md` will explicitly state that no implementation can begin without an approved spec in this directory.

### 3. Task Tracking (`TODO.md`)
- **Location**: `TODO.md` in the project root.
- **Format**: Simple Markdown task lists (`- [ ]`, `- [x]`).
- **Process**: Divided into logical sections (e.g., "Current Sprint", "Backlog"). Agents will be instructed to consult and update this file when starting or completing tasks.

### 4. Change History (`CHANGELOG.md`)
- **Location**: `CHANGELOG.md` in the project root.
- **Format**: Based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
- **Process**: Agents will be instructed to document additions, changes, and deprecations under the `[Unreleased]` section as part of their final steps in any task.

## Data Flow & Workflow
1. **Planning**: A new feature is requested. The AI writes a spec in `docs/specs/` and updates `TODO.md`.
2. **Execution**: The AI reads `AGENTS.md` which points it to specific rules in `.agents/rules/`.
3. **Completion**: The AI finishes the implementation, checks off the item in `TODO.md`, and logs the changes in `CHANGELOG.md`.

## Error Handling / Edge Cases
- If an AI tool does not auto-read `.agents/rules/`, the user or the `AGENTS.md` file will explicitly point the tool to the relevant rule file.
