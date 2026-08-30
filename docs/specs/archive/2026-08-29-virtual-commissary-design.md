# Virtual Commissary System Design Specification

## 1. Executive Summary
The Virtual Commissary feature provides an AI-assisted system for judging sim racing incidents. To avoid straining the cheap shared hosting environment of the WordPress site, the heavy lifting of AI processing will be offloaded to a self-hosted n8n instance via Webhooks.

The system features three distinct AI personas (Strict, Lax, Balanced) that analyze footage and incident descriptions, followed by a "Chief Steward" that provides a final consensus on blame percentage and recommended sanctions based on the league's Rulebook.

## 2. Architecture Overview
- **Frontend (WordPress):** Drivers submit protest forms containing evidence URLs (YouTube, Twitch, Discord, or Cloudflare R2).
- **Backend (WordPress):** Admins review protests and trigger the AI analysis via a button.
- **Processing (n8n Homelab):** n8n receives the webhook from WP, fetches the latest Rulebook from WP, processes 4 parallel/sequential Gemini 1.5 Pro calls, and sends the final JSON verdict back to WP via a REST API callback.
- **AI Model:** Google Gemini 1.5 Pro (Multimodal).

## 3. Data Model & Storage
### Custom Post Type: `srl_protest`
- **Fields:**
  - Protesting Driver (User ID)
  - Accused Driver (User ID)
  - Race / Event (Post ID)
  - Lap / Timecode
  - Incident Description
  - Evidence URLs (Text array)
  - AI Status (`pending`, `processing`, `completed`, `failed`)
  - AI Verdict Data (JSON string storing the 3 persona arguments and the final consensus)

## 4. User Flows
### Driver (Frontend)
1. Driver accesses the protest form via shortcode `[srl_protest_form]`.
2. Fills out incident details and pastes links to video evidence (e.g., Discord video links).
3. Submits the form. A new `srl_protest` post is created with status "pending".

### Human Commissary (Backend)
1. Admin opens the WP dashboard and goes to the Protests list.
2. Opens a specific protest to review the driver's submission.
3. Clicks **"Send to Virtual Commissary"**.
4. The post status updates to "processing" and fires a webhook payload to n8n.
5. (Later) Receives a notification/sees the updated protest once the AI completes the analysis.

## 5. Rules & Admin Management
### Settings Page
A new WordPress admin page: **"Virtual Commissary Settings"**.
- Contains a large textarea to paste the League Rulebook and Racing Etiquette guidelines (in Markdown).
- This text is saved in WP options.

### API Endpoint
- A custom WP REST API endpoint (e.g., `/wp-json/srl/v1/rulebook`).
- Output: The markdown text of the rulebook.
- Security: Protected via a simple API key check (since n8n needs to access it).

## 6. n8n AI Workflow
1. **Trigger:** Webhook received from WP (contains incident details + video URLs).
2. **Context:** HTTP GET request to WP to fetch the Rulebook.
3. **Burden of Proof Evaluation:** All AI prompts will be instructed to dismiss ambiguous protests if the protester provided insufficient evidence (e.g., missing angles).
4. **Parallel Processing:**
   - **Persona A (Strict):** Adheres to the absolute letter of the rulebook. No tolerances.
   - **Persona B (Lax):** "Turismo Carretera" mindset. Highly tolerant of minor contact (rubbing is racing).
   - **Persona C (Balanced):** Objective middle ground looking at corner rights and divebomb metrics.
5. **Consensus (Chief Steward):** Evaluates the 3 arguments and outputs a final JSON structure containing percentages of blame and the recommended penalty.
6. **Callback:** HTTP POST request back to a WP endpoint (e.g., `/wp-json/srl/v1/protest-update`) to save the verdict into the `srl_protest` post meta and change status to "completed".
