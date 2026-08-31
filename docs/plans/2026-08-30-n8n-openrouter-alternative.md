# N8N OpenRouter Alternative Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an OpenRouter alternative model node to the n8n workflow and update documentation.

**Architecture:** We will insert a disconnected `@n8n/n8n-nodes-langchain.lmChatOpenAi` node in the workflow JSON and add instructions for setting it up in the README.

**Tech Stack:** JSON (n8n workflow), Markdown

## Global Constraints

- Do not modify the existing `Google Gemini (Strict)` connections.
- The new node must be named exactly `OpenRouter Model (Alternative)`.
- The node type must be exactly `@n8n/n8n-nodes-langchain.lmChatOpenAi`.

---

### Task 1: Add OpenRouter Node to Workflow JSON

**Files:**
- Modify: `docs/n8n/virtual-commissary-workflow.json`

**Interfaces:**
- Consumes: N/A
- Produces: A new node in the JSON array of the workflow.

- [ ] **Step 1: Write the minimal implementation**

```json
    {
      "parameters": {
        "modelName": "minimax/minimax-01",
        "options": {
          "baseURL": "https://openrouter.ai/api/v1"
        }
      },
      "type": "@n8n/n8n-nodes-langchain.lmChatOpenAi",
      "typeVersion": 1,
      "position": [
        480,
        700
      ],
      "id": "openrouter-model-alternative",
      "name": "OpenRouter Model (Alternative)",
      "credentials": {
        "openAiApi": {
          "id": "YOUR_CREDENTIAL_ID",
          "name": "OpenRouter API"
        }
      }
    }
```
*(Insert this object into the `nodes` array in `docs/n8n/virtual-commissary-workflow.json`, keeping the JSON structurally valid)*

- [ ] **Step 2: Verify JSON Validity**

Run: `node -e "require('./docs/n8n/virtual-commissary-workflow.json')"`
Expected: No output (which means valid JSON).

- [ ] **Step 3: Commit**

```bash
git add docs/n8n/virtual-commissary-workflow.json
git commit -m "feat: add openrouter node to n8n workflow"
```

---

### Task 2: Update Documentation

**Files:**
- Modify: `docs/n8n/README.md`

**Interfaces:**
- Consumes: N/A
- Produces: Updated markdown documentation.

- [ ] **Step 1: Write the minimal implementation**

Append this section to the bottom of `docs/n8n/README.md`:

```markdown
## Using Alternative Models (OpenRouter)

If you prefer to use other models (like Minimax, Qwen, Nemotron, etc.), the workflow includes a disconnected **OpenRouter Model (Alternative)** node.

### Setup Instructions

1. **Create an OpenRouter Account:**
   Go to [openrouter.ai](https://openrouter.ai) and create an account. Generate an API Key in your account settings.
2. **Add Credentials in n8n:**
   In n8n, go to **Credentials > Add Credential** and search for **OpenAI Custom API**. Create one named "OpenRouter API" and paste your OpenRouter API key.
3. **Configure the Node:**
   Open the workflow and double-click the **OpenRouter Model (Alternative)** node. Ensure the **Credential to connect with** is set to your new "OpenRouter API".
   Change the **Model Name** to your preferred model string (e.g., `minimax/minimax-01` or `qwen/qwen-2.5-72b-instruct`).
4. **Switch Models:**
   By default, the `Google Gemini (Strict)` node is connected to the 4 analysis chains. To use OpenRouter instead, delete the connection lines from the Gemini node and draw new lines from the **OpenRouter Model (Alternative)** node to the `ai_languageModel` input of the 4 chain nodes.
```

- [ ] **Step 2: Verify File Exists and Contains Updates**

Run: `Get-Content docs\n8n\README.md -Tail 10`
Expected: Shows the newly added markdown instructions.

- [ ] **Step 3: Commit**

```bash
git add docs/n8n/README.md
git commit -m "docs: add instructions for openrouter alternative model"
```
