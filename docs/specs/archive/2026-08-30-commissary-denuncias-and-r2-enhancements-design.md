# Design Specification: Commissary "Denuncias" Wording, Active Championship Filtering, Searchable Drivers & Cloudflare R2 Uploads

## 1. Executive Summary
This specification defines improvements to the Virtual Commissary feature in the SRL League System plugin:
1. **Terminology Alignment:** Update terminology from *"Protestas"* to *"Denuncias"* throughout the admin panel, CPT definitions, shortcode UI, and user notifications.
2. **Championship Filtering:** Limit the championship dropdown in `[srl_protest_form]` to active championships from the current racing season/year (`_srl_status != 'completed'` and current calendar year).
3. **Searchable Driver Dropdowns:** Implement lightweight, interactive comboboxes with instant typing and search filtering for both Protesting and Accused drivers.
4. **Direct Video Evidence Upload with Cloudflare R2:** Enable direct file uploading of incident footage from the form, supporting direct upload to Cloudflare R2 (S3-compatible API) with seamless local WordPress media fallback, complete with admin configuration and setup documentation.

---

## 2. Terminology Changes (*"Protestas"* ➔ *"Denuncias"*)
- **CPT Labeling:**
  - Plural: *Denuncias*
  - Singular: *Denuncia*
  - Admin Menu: *Comisariato (Denuncias)*
- **Post Titles:**
  - Format: `Denuncia: [Piloto Demandante] vs [Piloto Acusado] ([Evento])`
- **Shortcode & Public Text:**
  - Form Title: *Formulario de Denuncias*
  - Submit Button: *Enviar Denuncia al Comisariato*
  - Success Feedback: *¡Denuncia registrada con éxito!*
- **Homepage Main Menu:**
  - Card Text: *Comisariato — Envía y consulta denuncias e incidentes analizados con IA.*

---

## 3. Form Championship Filtering
In `[srl_protest_form]`:
- Query `srl_championship` posts where:
  - `post_status` = `'publish'`
  - `_srl_status` != `'completed'`
  - `date_query`: After or within `date('Y')-01-01`
- Fallback: If no active championships exist in the current calendar year, query all non-completed championships to prevent empty dropdowns.

---

## 4. Searchable Driver Selector (Combobox UI)
- Replace static `<select>` elements for Demandante and Acusado with a responsive autocomplete component:
  - **Input field:** Filter options on `input` event with instant client-side matching.
  - **Dropdown list:** Shows matched drivers, selectable via click or Enter key.
  - **Selection state:** Shows selected driver tag with a clear button (✕) to quickly reset.
  - **Hidden Input:** Transmits `driver_id` for form processing.

---

## 5. Direct Video Evidence Upload & Cloudflare R2 Storage
### Admin Settings
In **Gestión SRL** ➔ **Comisariato Virtual AI**:
- `srl_r2_enabled`: Checkbox toggle.
- `srl_r2_account_id`: Cloudflare Account ID.
- `srl_r2_access_key_id`: S3 Access Key ID.
- `srl_r2_secret_access_key`: S3 Secret Access Key.
- `srl_r2_bucket_name`: Bucket name.
- `srl_r2_public_url`: Public domain or R2 custom URL (e.g. `https://media.simracinglatinoamerica.com` or `https://pub-xxx.r2.dev`).

### Upload Workflow
1. User drags & drops or clicks to select a video/image (`.mp4, .webm, .mov, .avi, .mkv, .png, .jpg, .jpeg`).
2. AJAX endpoint `srl_upload_evidence_file` receives file with nonce authentication.
3. If R2 is enabled and configured:
   - File is streamed to Cloudflare R2 using AWS S3 Signature Version 4 via `wp_remote_request` (no heavy external Composer dependencies needed).
   - Generates and returns public CDN URL.
4. If R2 is disabled/unconfigured or if R2 upload fails:
   - Falls back gracefully to WordPress native upload (`wp_handle_upload`), returning the media library URL.
5. Form automatically appends the returned URL to the evidence URLs textarea and updates UI with a thumbnail/file badge.

---

## 6. Documentation & Setup Guides
- Create `docs/cloudflare-r2-setup.md` covering:
  - Creating a Cloudflare R2 bucket.
  - Generating S3-compatible API tokens (Read/Write).
  - Enabling public access or custom domain.
  - Configuring CORS rules in Cloudflare R2 dashboard for web uploads.
  - Entering credentials into WordPress Gestión SRL settings.
