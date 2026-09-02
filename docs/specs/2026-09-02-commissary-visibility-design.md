# Design Spec: Commissary Visibility Controls

## Context & Goal
The client wants the ability to restrict access to the "Comisariato" (Protests) feature. Specifically, there should be an option in the SRL management settings (Gestion SRL) to toggle whether the Comisariato is "Public" or "Admin Only". 
When set to "Admin Only":
1. The Comisariato card on the homepage menu is hidden from non-admin users.
2. If a non-admin user attempts to directly access the Comisariato page (or individual protest records), they are automatically redirected to the homepage.

## Proposed Implementation

### 1. New Setting in Admin Panel
- **File**: `includes/admin-page.php`
- **Location**: Inside the "Configuración del Comisariato Virtual" tab.
- **Change**: Add a new setting `srl_commissary_visibility` with a dropdown:
  - `public`: Público (Cualquier usuario puede ver y enviar reclamos).
  - `admin_only`: Solo Administradores (El sistema de reclamos está oculto y bloqueado para pilotos/invitados).

### 2. Homepage Menu Card Logic
- **File**: `includes/shortcodes.php`
- **Location**: `srl_render_main_menu_shortcode()`
- **Change**: Before rendering the Comisariato `<a class="srl-menu-card">`, check the `srl_commissary_visibility` setting. If it is set to `admin_only` and `! current_user_can( 'manage_options' )`, we will simply not render that card in the grid.

### 3. Protection & Redirect Logic
- **File**: `srl-league-system.php` (or a dedicated hooks file, but `srl-league-system.php` handles initialization).
- **Location**: A new function hooked to `template_redirect`.
- **Change**: 
  - Check if `srl_commissary_visibility` is `admin_only`.
  - If so, check if the current user is NOT an admin (`! current_user_can('manage_options')`).
  - If both are true, check if the requested page is the Comisariato page (`is_page('comisariato')`), a single protest (`is_singular('srl_protest')`), or the protest archive (`is_post_type_archive('srl_protest')`).
  - If it's a match, use `wp_safe_redirect( home_url() ); exit;` to redirect the user to the homepage.

## Trade-offs & Recommendations
- **Redirect vs Error Message**: The user explicitly requested a redirect ("so if guest user attempt to see it, they get redirected"). This is slightly better for security (obscuring the existence of the page from guests) but provides less context to the user. This matches the requirements exactly.
- **Role Check**: Using `current_user_can('manage_options')` ensures only WordPress administrators can see it. If we want other roles (like Editors or custom Steward roles) to see it, we could change this capability. For now, "admin only" will be strictly mapped to `manage_options`.
