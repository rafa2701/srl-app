# SRL Virtual Commissary — n8n & Gemini AI Homelab Setup Guide

This guide explains how to connect your self-hosted **n8n** instance with the **SRL League System** WordPress plugin to power the AI Racing Steward Virtual Commissary.

---

## Architecture Overview

```
[ Driver Form ] ──► [ WP Post: srl_protest ]
                             │
                  (Admin clicks "Send to AI")
                             │
                             ▼
                   [ n8n Webhook Trigger ]
                             │
        ┌────────────────────┼────────────────────┐
        ▼                    ▼                    ▼
[ Persona A: Strict ] [ Persona B: Lax ] [ Persona C: Balanced ]
  (Gemini 1.5 Pro)      (Gemini 1.5 Pro)   (Gemini 1.5 Pro)
        └────────────────────┬────────────────────┘
                             ▼
                 [ Chief Steward Consensus ]
                      (Gemini 1.5 Pro)
                             │
                             ▼
                [ REST Callback to WP ]
          POST /wp-json/srl/v1/protest-update
```

---

## 1. Prerequisites

1. **Self-hosted n8n** (version 1.0+ recommended via Docker).
2. **Google AI Studio API Key** (for Google Gemini 1.5 Pro multimodal model).
3. **SRL League System WordPress Plugin** installed and activated.

---

## 2. WordPress Configuration

1. In WordPress WP-Admin, navigate to **Gestión SRL** ➔ Tab **Comisariato Virtual AI**.
2. Set your **Reglamento Deportivo** in Markdown format.
3. Note or generate your **API Secret Key** (e.g. `srl_sec_...`).
4. Set the **URL del Webhook de n8n** to:
   ```
   https://tu-instancia-n8n.com/webhook/srl-virtual-commissary
   ```
5. Click **Guardar Configuración**.

---

## 3. n8n Workflow Import

1. Open your n8n Dashboard.
2. Click **Add workflow** ➔ **Import from File**.
3. Select `docs/n8n/virtual-commissary-workflow.json`.
4. In n8n **Credentials**, create or link your **Google Gemini(PaLM) API** account credential with your Google AI Studio API key.
5. In the Gemini Chat Model node, make sure the credential is selected.
6. Activate the workflow (**Active: ON**).

---

## 4. Testing the Flow

1. On the frontend, submit a test protest via `[srl_protest_form]`.
2. In WP-Admin ➔ **Comisariato (Protestas)**, open the new protest.
3. Click **"Enviar a Comisariato Virtual (n8n)"**.
4. Check your n8n Executions log:
   - The webhook receives the incident details + video URLs.
   - It fetches the latest Rulebook from WordPress (`GET /wp-json/srl/v1/rulebook`).
   - The 3 Gemini personas evaluate the incident in parallel.
   - The Chief Steward generates the consensus blame percentage and penalty.
   - It posts the verdict callback to `/wp-json/srl/v1/protest-update`.
5. Refresh the WordPress protest page to see the interactive verdict cards, blame percentage bar, and Chief Steward sanction.

---

## 5. Using Alternative Models (OpenRouter)

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
