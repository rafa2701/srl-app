# N8N OpenRouter Alternative Model Support

## Goal
Modify the `virtual-commissary-workflow.json` to include an OpenRouter model node as an alternative to the default Gemini AI node. This will allow the user to easily switch to alternative models (like Minimax, Qwen, Nemotron) if desired, while retaining Gemini as the default connected model due to its generous free tier. The setup instructions will also be updated to explain how to configure and use the OpenRouter alternative.

## Approach

### 1. Workflow Modification (`docs/n8n/virtual-commissary-workflow.json`)
- Add a new node of type `@n8n/n8n-nodes-langchain.lmChatOpenAi`.
- **Name**: `OpenRouter Model (Alternative)`
- **Configuration**:
  - Point to the OpenRouter API endpoint (`https://openrouter.ai/api/v1`).
  - Expect standard OpenAI credentials (which will be populated with the OpenRouter API key).
  - Set a placeholder model name like `minimax/minimax-01` or `qwen/qwen-2.5-72b-instruct` that can be easily edited.
- **Placement**: Place it visually near the existing `Google Gemini (Strict)` node, but leave it disconnected from the 4 analysis chains so it doesn't run by default.

### 2. Documentation Updates (`docs/n8n/README.md`)
- Add a new section: **Using Alternative Models (OpenRouter)**.
- Include step-by-step instructions for:
  - Creating an OpenRouter account and generating an API key.
  - Adding a new "OpenAI Custom API" credential in n8n.
  - Updating the "OpenRouter Model (Alternative)" node settings with the chosen model string.
  - Switching the visual connections in the n8n canvas (detaching Gemini and attaching OpenRouter to the 4 chains).

## Spec Self-Review
- Are there any TBDs? No.
- Internal consistency? Yes, the approach matches the goal.
- Scope? Simple and focused on a single workflow JSON update and README addition.
- Ambiguity? Clear instructions on what node to add and what docs to write.

## Verification
- Confirm the new node appears correctly in the workflow JSON schema.
- Confirm the README contains the necessary Markdown instructions.
