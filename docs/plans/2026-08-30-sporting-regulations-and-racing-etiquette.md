# Sporting Regulations & Virtual Racing Etiquette Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert the official SRL Sporting Regulations PDF into structured Markdown, create a comprehensive Virtual Racing Etiquette framework based on international sim racing standards, produce a merged master rulebook for AI Stewards, and document everything in accordance with project rules.

**Architecture:** Create three modular documents in `docs/` (`reglamento-deportivo-srl.md`, `codigo-etiqueta-carreras.md`, `reglamento-completo-srl.md`). The unified file serves as the input for WordPress option `srl_rulebook_markdown` consumed by the n8n AI Commissary.

**Tech Stack:** Markdown (GitHub Flavored), Git, PHP/WordPress (API consumer).

## Global Constraints
- Faithful 1:1 reproduction of all 24 chapters, 45 articles, and sanction tables from `Reglamento Deportivo SIM RACING LATINOAMERICA (6).pdf`.
- Clear Spanish language phrasing for all sporting and technical terms.
- Follow workspace rules in `AGENTS.md` (spec archiving, TODO tracking, CHANGELOG updates).

---

### Task 1: Create `docs/reglamento-deportivo-srl.md`

**Files:**
- Create: `docs/reglamento-deportivo-srl.md`

**Interfaces:**
- Consumes: Content extracted from `Reglamento Deportivo SIM RACING LATINOAMERICA (6).pdf`
- Produces: Complete official league sporting regulations in markdown.

- [ ] **Step 1: Write `docs/reglamento-deportivo-srl.md`**
Write all 24 chapters, 45 articles, and sanction tables (R01–R02, T01, A01–A03, E01, P01–P05, C01–C31, S01–S03) matching the official PDF exactly.

- [ ] **Step 2: Verify content completeness**
Check that all chapters and sanction codes match the PDF source.

- [ ] **Step 3: Commit**
```bash
git add docs/reglamento-deportivo-srl.md
git commit -m "docs: add official SRL sporting regulations markdown"
```

---

### Task 2: Create `docs/codigo-etiqueta-carreras.md`

**Files:**
- Create: `docs/codigo-etiqueta-carreras.md`

**Interfaces:**
- Consumes: Virtual racing etiquette standards (FIA ISC Appendix L, iRacing, SRO Esports).
- Produces: Detailed technical driving etiquette and stewarding guidelines in Spanish.

- [ ] **Step 1: Write `docs/codigo-etiqueta-carreras.md`**
Define the 6 core pillars:
1. Vortex of Danger (Vórtice de Peligro)
2. Corner Rights & Overlap Criteria (Derechos en Curva y Solapamiento)
3. Moving Under Braking (Movimientos en Frenada)
4. Safe Rejoin Protocol (Reincorporaciones Seguras)
5. Blue Flags & Lapped Traffic (Banderas Azules)
6. Stewarding & AI Evaluation Framework (Criterios de Comisariato e IA: Culpable Predominante, Carga de la Prueba)

- [ ] **Step 2: Verify clarity and completeness**
Ensure definitions provide unambiguous metrics (e.g. axle overlap, blind spot zone, brake zone line locking).

- [ ] **Step 3: Commit**
```bash
git add docs/codigo-etiqueta-carreras.md
git commit -m "docs: add virtual racing etiquette and stewarding criteria"
```

---

### Task 3: Create `docs/reglamento-completo-srl.md`

**Files:**
- Create: `docs/reglamento-completo-srl.md`

**Interfaces:**
- Consumes: `docs/reglamento-deportivo-srl.md` and `docs/codigo-etiqueta-carreras.md`
- Produces: Merged master rulebook for AI Stewards and website publication.

- [ ] **Step 1: Write `docs/reglamento-completo-srl.md`**
Combine the sporting regulations with the virtual racing etiquette appendix under a unified document with table of contents.

- [ ] **Step 2: Verify consistency**
Ensure cross-references between the sporting regulations and the etiquette appendix are seamless.

- [ ] **Step 3: Commit**
```bash
git add docs/reglamento-completo-srl.md
git commit -m "docs: add complete merged SRL rulebook for AI commissary"
```

---

### Task 4: Update Documentation, TODO, and CHANGELOG

**Files:**
- Modify: `TODO.md`
- Modify: `CHANGELOG.md`
- Move: `docs/specs/2026-08-30-sporting-regulations-and-racing-etiquette-design.md` -> `docs/specs/archive/2026-08-30-sporting-regulations-and-racing-etiquette-design.md`

- [ ] **Step 1: Update `TODO.md`**
Add and mark as completed the sporting regulations and racing etiquette tasks.

- [ ] **Step 2: Update `CHANGELOG.md`**
Record additions in the `[Unreleased]` section.

- [ ] **Step 3: Archive Spec**
Move `docs/specs/2026-08-30-sporting-regulations-and-racing-etiquette-design.md` to `docs/specs/archive/`.

- [ ] **Step 4: Commit**
```bash
git add TODO.md CHANGELOG.md docs/specs/
git commit -m "chore: archive spec, update TODO and changelog for rulebook markdown docs"
```
