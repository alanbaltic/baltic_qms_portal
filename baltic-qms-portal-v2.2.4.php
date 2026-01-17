<?php
/**
 * Plugin Name: Baltic QMS Portal
 * Description: Staff-only QMS portal for recording MCS evidence (records + projects) with structured R07 (Training Matrix) and R04 (Tool Calibration), editable/reassignable records, and DOC/Print exports.
 * Version: 2.2.4
 * Author: Baltic Electric
 */

if (!defined('ABSPATH')) { exit; }

class BE_QMS_Portal {
  const VERSION = '2.2.4';

  const CPT_RECORD   = 'be_qms_record';
  const CPT_PROJECT  = 'be_qms_install'; // keep existing CPT slug for backwards compatibility
  const CPT_EMPLOYEE = 'be_qms_employee';

  const TAX_RECORD_TYPE = 'be_qms_record_type';

  const META_PROJECT_LINK = '_be_qms_install_id';
  const META_EMPLOYEE_ID  = '_be_qms_employee_id';

  // R04 meta
  const META_TOOL_ITEM        = '_be_qms_tool_item';
  const META_TOOL_SERIAL      = '_be_qms_tool_serial';
  const META_TOOL_REQ         = '_be_qms_tool_requirements';
  const META_TOOL_DATE_PUR    = '_be_qms_tool_date_purchased';
  const META_TOOL_DATE_CAL    = '_be_qms_tool_date_calibrated';
  const META_TOOL_DATE_NEXT   = '_be_qms_tool_next_due';
  const META_TOOL_NOTES       = '_be_qms_tool_notes';

  // R07 meta
  const META_SKILL_COURSE     = '_be_qms_skill_course';
  const META_SKILL_DATE       = '_be_qms_skill_date';
  const META_SKILL_RENEWAL    = '_be_qms_skill_renewal';
  const META_SKILL_DESC       = '_be_qms_skill_desc';

  const META_RECORD_DATE      = '_be_qms_record_date';
  const META_DETAILS          = '_be_qms_details';
  const META_ACTIONS          = '_be_qms_actions';
  const META_ATTACHMENTS      = '_be_qms_attachments';

  public static function init() {
    add_action('init', [__CLASS__, 'register_types']);
    add_action('init', [__CLASS__, 'maybe_handle_exports']);

    add_shortcode('be_qms_portal', [__CLASS__, 'shortcode_portal']);
    add_shortcode('bqms_dashboard', [__CLASS__, 'shortcode_portal']);
    add_shortcode('bqms_portal', [__CLASS__, 'shortcode_portal']);

    add_action('admin_post_be_qms_save_record', [__CLASS__, 'handle_save_record']);
    add_action('admin_post_be_qms_save_project', [__CLASS__, 'handle_save_project']);

    // R07
    add_action('admin_post_be_qms_save_employee', [__CLASS__, 'handle_save_employee']);
    add_action('admin_post_be_qms_save_skill', [__CLASS__, 'handle_save_skill']);

    // R04
    add_action('admin_post_be_qms_save_tool', [__CLASS__, 'handle_save_tool']);

    add_action('admin_post_be_qms_delete', [__CLASS__, 'handle_delete']);

    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
  }

  public static function on_activate() {
    self::register_types();
    flush_rewrite_rules();

    // Create /qms-portal if it doesn't exist.
    $slug = 'qms-portal';
    $existing = get_page_by_path($slug);
    if (!$existing) {
      wp_insert_post([
        'post_title'   => 'QMS Portal',
        'post_name'    => $slug,
        'post_status'  => 'private',
        'post_type'    => 'page',
        'post_content' => '[be_qms_portal]',
      ]);
    }
  }

  /**
   * Record types in the exact Easy-MCS order screenshot.
   */
  private static function record_type_map() {
    return [
      'r01_contracts_folder'     => 'R01 Contracts Folder',
      'r02_capa_record'          => 'R02 Corrective & Preventative Action Record',
      'r03_purchase_order'       => 'R03 Purchase Order',
      'r04_tool_calibration'     => 'R04 Tool Calibration',
      'r05_internal_review'      => 'R05 Internal Review Record',
      'r06_customer_complaints'  => 'R06 Customer Complaints',
      'r07_training_matrix'      => 'R07 Personal Skills & Training Matrix',
      'r08_approved_suppliers'   => 'R08 Approved Suppliers',
      'r09_approved_subcontractors' => 'R09 Approved Subcontractors',
      'r11_company_documents'    => 'R11 Company Documents',
    ];
  }

  public static function register_types() {
    // Records
    register_post_type(self::CPT_RECORD, [
      'label' => 'QMS Records',
      'public' => false,
      'show_ui' => true,
      'menu_icon' => 'clipboard',
      'supports' => ['title', 'author'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
    ]);

    // Projects (installation evidence)
    register_post_type(self::CPT_PROJECT, [
      'label' => 'QMS Projects',
      'public' => false,
      'show_ui' => true,
      'menu_icon' => 'forms',
      'supports' => ['title', 'author'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
    ]);

    // Employees
    register_post_type(self::CPT_EMPLOYEE, [
      'label' => 'QMS Employees',
      'public' => false,
      'show_ui' => true,
      'menu_icon' => 'groups',
      'supports' => ['title', 'author'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
    ]);

    register_taxonomy(self::TAX_RECORD_TYPE, [self::CPT_RECORD], [
      'label' => 'Record Types',
      'public' => false,
      'show_ui' => true,
      'hierarchical' => false,
    ]);

    // Ensure terms
    foreach (self::record_type_map() as $slug => $name) {
      if (!term_exists($slug, self::TAX_RECORD_TYPE)) {
        wp_insert_term($name, self::TAX_RECORD_TYPE, ['slug' => $slug]);
      }
    }
  }

  public static function enqueue_assets() {
    if (!is_singular()) return;
    global $post;
    if (!$post || strpos((string)$post->post_content, '[be_qms_portal') === false) return;

    wp_enqueue_script('jquery');

    $css = <<<'CSS'
.be-qms-wrap{
  --qms-bg:#020617;
  --qms-panel:rgba(15,23,42,.60);
  --qms-panel2:rgba(2,6,23,.55);
  --qms-border:#1e293b;
  --qms-border2:#334155;
  --qms-text:#e2e8f0;
  --qms-muted:#94a3b8;
  --qms-emerald:#34d399;
  --qms-emerald2:#6ee7b7;
  --qms-amber:#fbbf24;
  --qms-danger:#f87171;

  max-width:1200px;
  margin:0 auto;
  padding:18px 14px;
  color:var(--qms-text);
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
}
.be-qms-wrap *{ box-sizing:border-box; }

.be-qms-card{
  background:var(--qms-panel);
  border:1px solid var(--qms-border);
  border-radius:18px;
  padding:16px;
  margin:14px 0;
  box-shadow:0 18px 60px rgba(16,185,129,.10);
  backdrop-filter: blur(10px);
}

.be-qms-title{
  font-size:26px;
  font-weight:800;
  letter-spacing:-.02em;
  margin:0 0 10px 0;
  color:#f8fafc;
}

.be-qms-muted{ color:var(--qms-muted); font-size:13px; }

.be-qms-nav{ display:flex; flex-wrap:wrap; gap:10px; margin:10px 0 16px 0; }

.be-qms-tab{
  display:inline-block;
  padding:10px 14px;
  border-radius:999px;
  border:1px solid var(--qms-border);
  background:rgba(2,6,23,.35);
  color:var(--qms-text);
  text-decoration:none;
  font-weight:700;
  font-size:13px;
  transition: background .18s ease, border-color .18s ease, transform .18s ease, box-shadow .18s ease;
}
.be-qms-tab:hover{ background:rgba(15,23,42,.65); border-color:var(--qms-border2); transform: translateY(-1px); }
.be-qms-tab.is-active{ background:rgba(16,185,129,.14); border-color:rgba(52,211,153,.55); box-shadow:0 0 0 4px rgba(16,185,129,.12); }

.be-qms-layout{ display:grid; grid-template-columns: 280px 1fr; gap:14px; align-items:start; }
@media(max-width:900px){ .be-qms-layout{ grid-template-columns:1fr; } }

.be-qms-side{
  background:rgba(2,6,23,.28);
  border:1px solid var(--qms-border);
  border-radius:16px;
  padding:10px;
}

.be-qms-side a{
  display:flex;
  justify-content:space-between;
  gap:10px;
  padding:10px 12px;
  border-radius:12px;
  color:var(--qms-text);
  text-decoration:none;
  border:1px solid transparent;
  font-weight:700;
  font-size:13px;
}
.be-qms-side a:hover{ background:rgba(15,23,42,.55); border-color:var(--qms-border2); }
.be-qms-side a.is-active{ background:rgba(16,185,129,.14); border-color:rgba(52,211,153,.55); }

.be-qms-row{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }

.be-qms-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  padding:10px 14px;
  border-radius:999px;
  border:1px solid transparent;
  background:var(--qms-emerald);
  color:#020617 !important;
  text-decoration:none;
  font-weight:800;
  font-size:13px;
  cursor:pointer;
  line-height:1;
  box-shadow:0 14px 40px rgba(16,185,129,.16);
  transition: transform .18s ease, background .18s ease, border-color .18s ease, box-shadow .18s ease;
}
.be-qms-btn:hover{ background:var(--qms-emerald2); transform: translateY(-1px); }
.be-qms-btn:active{ transform: translateY(0); }

.be-qms-btn-secondary{
  background:rgba(2,6,23,.35);
  border-color:var(--qms-border);
  color:var(--qms-text) !important;
  box-shadow:none;
}
.be-qms-btn-secondary:hover{ background:rgba(15,23,42,.65); border-color:var(--qms-border2); }

.be-qms-btn-danger{
  background:rgba(248,113,113,.14);
  border-color:rgba(248,113,113,.45);
  color:#fecaca !important;
  box-shadow:none;
}
.be-qms-btn-danger:hover{ background:rgba(248,113,113,.20); border-color:rgba(248,113,113,.65); }

.be-qms-link{ color:var(--qms-emerald2); text-decoration:none; font-weight:800; }
.be-qms-link:hover{ text-decoration:underline; }

.be-qms-table{ width:100%; border-collapse:collapse; margin-top:10px; font-size:13px; }
.be-qms-table th, .be-qms-table td{ padding:10px 10px; border-top:1px solid rgba(30,41,59,.75); vertical-align:top; }
.be-qms-table th{ color:#f8fafc; font-weight:800; font-size:12px; letter-spacing:.02em; text-transform:uppercase; }
.be-qms-table tr:hover td{ background:rgba(15,23,42,.35); }

.be-qms-input, .be-qms-textarea, .be-qms-select{
  width:100%;
  padding:10px 12px;
  border-radius:12px;
  border:1px solid var(--qms-border2);
  background:var(--qms-panel2);
  color:var(--qms-text);
  outline:none;
  transition:border-color .18s ease, box-shadow .18s ease, background .18s ease;
}
.be-qms-input::placeholder, .be-qms-textarea::placeholder{ color: rgba(148,163,184,.60); }
.be-qms-input:focus, .be-qms-textarea:focus, .be-qms-select:focus{ border-color:rgba(52,211,153,.70); box-shadow:0 0 0 4px rgba(16,185,129,.18); background:rgba(2,6,23,.70); }
.be-qms-textarea{ min-height:110px; resize:vertical; }

.be-qms-grid{ display:grid; grid-template-columns:repeat(12,1fr); gap:10px; }
.be-qms-col-6{ grid-column:span 6; }
.be-qms-col-4{ grid-column:span 4; }
.be-qms-col-3{ grid-column:span 3; }
.be-qms-col-12{ grid-column:span 12; }
@media(max-width:800px){ .be-qms-col-6,.be-qms-col-4,.be-qms-col-3{ grid-column:span 12; } }

.be-qms-pill{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:6px 10px;
  border-radius:999px;
  border:1px solid var(--qms-border);
  background:rgba(2,6,23,.35);
  font-size:12px;
  font-weight:800;
}
CSS;

    wp_register_style('be-qms-inline', false);
    wp_enqueue_style('be-qms-inline');
    wp_add_inline_style('be-qms-inline', $css);
  }

  private static function require_staff() {
    if (!is_user_logged_in()) {
      auth_redirect();
      exit;
    }
    if (!current_user_can('edit_posts')) {
      wp_die('You do not have permission to access the QMS Portal.');
    }
  }

  private static function portal_url() {
    $page = get_page_by_path('qms-portal');
    if ($page) return get_permalink($page);
    return home_url('/qms-portal/');
  }

  private static function get_portal_action() {
    if (isset($_GET['be_action'])) return sanitize_key($_GET['be_action']);
    if (isset($_GET['action'])) return sanitize_key($_GET['action']);
    return '';
  }

  public static function shortcode_portal($atts = []) {
    self::require_staff();

    $view = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'records';
    $views = [
      'projects'   => 'Projects',
      'records'    => 'Records',
      'templates'  => 'Templates',
      'references' => 'References',
      'assessment' => 'How to pass assessment',
    ];
    if (!isset($views[$view])) $view = 'records';

    ob_start();

    echo '<div class="be-qms-wrap">';
    echo '<div class="be-qms-card">';
    echo '<div class="be-qms-title">Baltic Electric – QMS Portal</div>';
    echo '<div class="be-qms-muted">Staff-only. Record your MCS evidence here and export when needed.</div>';

    echo '<div class="be-qms-nav">';
    foreach ($views as $k => $label) {
      $cls = 'be-qms-tab' . ($k === $view ? ' is-active' : '');
      $url = esc_url(add_query_arg(['view' => $k], self::portal_url()));
      echo '<a class="' . esc_attr($cls) . '" href="' . $url . '">' . esc_html($label) . '</a>';
    }
    echo '</div>';

    if ($view === 'projects') {
      self::render_projects();
    } elseif ($view === 'records') {
      self::render_records();
    } elseif ($view === 'templates') {
      self::render_templates();
    } elseif ($view === 'references') {
      self::render_references();
    } else {
      self::render_assessment();
    }

    echo '</div></div>';

    return ob_get_clean();
  }

  // ------------------ Templates / References / Assessment ------------------

  private static function render_templates() {
    echo '<div class="be-qms-muted">Use the portal to create records (editable and reassignable). Export to DOC or Print to PDF when needed.</div>';
    echo '<ul style="margin-top:10px">';
    echo '<li><strong>R05 Internal Review Record</strong> – minutes + actions.</li>';
    echo '<li><strong>R02 CAPA</strong> – corrective / preventative actions.</li>';
    echo '<li><strong>R06 Customer Complaints</strong> – log + linked project if applicable.</li>';
    echo '<li><strong>R04 Tool Calibration</strong> – structured tool log.</li>';
    echo '<li><strong>R07 Training Matrix</strong> – employee-first training records.</li>';
    echo '</ul>';
  }

  private static function render_references() {
    echo '<div class="be-qms-muted">Keep standards handy, and use Records/Projects to evidence what you do.</div>';
    echo '<ul style="margin-top:10px">';
    echo '<li><a class="be-qms-link" target="_blank" href="https://mcscertified.com/wp-content/uploads/2024/11/MCS-001-1-Issue-4.2_Final.pdf">MCS-001 (Issue 4.2)</a></li>';
    echo '<li><a class="be-qms-link" target="_blank" href="https://mcscertified.com/">MCS Installer Resources (MIS 3002 / MIS 3012)</a></li>';
    echo '</ul>';

    echo '<hr style="margin:18px 0;border:0;border-top:1px solid rgba(30,41,59,.75)">';
    echo '<div class="be-qms-muted">Tip: Your assessor mainly wants to see a working system: 1 project with evidence + a handful of live records (internal review, training, tools, complaints/CAPA).</div>';
  }

  private static function render_assessment() {
    echo '<ol>';
    echo '<li>Create at least <strong>1 Project</strong> and upload evidence (design report, schematic, DNO, building regs cert, commissioning, photos).</li>';
    echo '<li>Complete <strong>R05 Internal Review Record</strong> (minutes + actions).</li>';
    echo '<li>Complete <strong>R07 Training Matrix</strong> for you + any staff/subbies.</li>';
    echo '<li>Complete <strong>R04 Tool Calibration</strong> for test gear.</li>';
    echo '<li>Have <strong>R06 Complaints</strong> + <strong>R02 CAPA</strong> logs (even “none to date” entries).</li>';
    echo '</ol>';
  }

  // ------------------ Projects (formerly Installations) ------------------

  private static function render_projects() {
    $action = self::get_portal_action();

    if ($action === 'new_project') {
      self::render_project_form();
      return;
    }

    if (($action === 'view_project' || $action === 'edit_project') && !empty($_GET['id'])) {
      $id = (int)$_GET['id'];
      if ($action === 'edit_project') {
        self::render_project_form($id);
      } else {
        self::render_project_view($id);
      }
      return;
    }

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div><strong>Projects</strong> <span class="be-qms-muted">(assessment evidence files)</span></div>';
    $new_url = esc_url(add_query_arg(['view'=>'projects','be_action'=>'new_project'], self::portal_url()));
    echo '<a class="be-qms-btn" href="'.$new_url.'">+ New project</a>';
    echo '</div>';

    $q = new WP_Query([
      'post_type' => self::CPT_PROJECT,
      'post_status' => 'publish',
      'posts_per_page' => 50,
      'orderby' => 'date',
      'order' => 'DESC'
    ]);

    echo '<table class="be-qms-table">';
    echo '<thead><tr><th>Date</th><th>Project</th><th>Customer</th><th>Actions</th></tr></thead><tbody>';

    if (!$q->have_posts()) {
      echo '<tr><td colspan="4" class="be-qms-muted">No projects yet. Create one for your assessment pack.</td></tr>';
    } else {
      while ($q->have_posts()) {
        $q->the_post();
        $pid = get_the_ID();
        $customer = get_post_meta($pid, '_be_qms_customer', true);
        $view_url = esc_url(add_query_arg(['view'=>'projects','be_action'=>'view_project','id'=>$pid], self::portal_url()));
        $edit_url = esc_url(add_query_arg(['view'=>'projects','be_action'=>'edit_project','id'=>$pid], self::portal_url()));
        $print_url= esc_url(add_query_arg(['be_qms_export'=>'print','post'=>$pid], self::portal_url()));
        $del_url  = esc_url(admin_url('admin-post.php?action=be_qms_delete&kind=project&id='.$pid.'&_wpnonce='.wp_create_nonce('be_qms_delete_'.$pid)));

        echo '<tr>';
        echo '<td>'.esc_html(get_the_date('Y-m-d')).'</td>';
        echo '<td><a class="be-qms-link" href="'.$view_url.'">'.esc_html(get_the_title()).'</a></td>';
        echo '<td>'.esc_html($customer ?: '-').'</td>';
        echo '<td class="be-qms-row">'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$view_url.'">View</a>'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$edit_url.'">Edit</a>'
          .'<a class="be-qms-btn be-qms-btn-secondary" target="_blank" href="'.$print_url.'">Print/PDF</a>'
          .'<a class="be-qms-btn be-qms-btn-danger" href="'.$del_url.'" onclick="return confirm(\'Delete this project?\')">Delete</a>'
          .'</td>';
        echo '</tr>';
      }
      wp_reset_postdata();
    }

    echo '</tbody></table>';
  }

  private static function render_project_form($id = 0) {
    $is_edit = $id > 0;
    $p = $is_edit ? get_post($id) : null;
    if ($is_edit && (!$p || $p->post_type !== self::CPT_PROJECT)) {
      echo '<div class="be-qms-muted">Project not found.</div>';
      return;
    }

    $title = $is_edit ? $p->post_title : '';
    $customer = $is_edit ? get_post_meta($id, '_be_qms_customer', true) : '';
    $address  = $is_edit ? get_post_meta($id, '_be_qms_address', true) : '';
    $pv_kwp   = $is_edit ? get_post_meta($id, '_be_qms_pv_kwp', true) : '';
    $bess_kwh = $is_edit ? get_post_meta($id, '_be_qms_bess_kwh', true) : '';
    $notes    = $is_edit ? get_post_meta($id, '_be_qms_notes', true) : '';
    $att_ids  = $is_edit ? get_post_meta($id, '_be_qms_evidence', true) : [];
    if (!is_array($att_ids)) $att_ids = [];

    echo '<h3>'.($is_edit ? 'Edit project' : 'New project').'</h3>';
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data">';
    echo '<input type="hidden" name="action" value="be_qms_save_project" />';
    if ($is_edit) echo '<input type="hidden" name="project_id" value="'.esc_attr($id).'" />';
    wp_nonce_field('be_qms_save_project');

    echo '<div class="be-qms-grid">';
    echo '<div class="be-qms-col-12"><label><strong>Project name / title</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="title" value="'.esc_attr($title).'" placeholder="e.g. PV + Battery – 12 Example Road – Jan 2026" required /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Customer name</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="customer" value="'.esc_attr($customer).'" /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Site address</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="address" value="'.esc_attr($address).'" /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>PV size (kWp)</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="pv_kwp" value="'.esc_attr($pv_kwp).'" /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Battery size (kWh)</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="bess_kwh" value="'.esc_attr($bess_kwh).'" /></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Notes</strong><br/>';
    echo '<textarea class="be-qms-textarea" name="notes" placeholder="Anything useful for the assessor…">'.esc_textarea($notes).'</textarea></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Upload evidence</strong> <span class="be-qms-muted">(multiple files allowed)</span><br/>';
    echo '<input type="file" name="evidence[]" multiple /></label></div>';

    if ($is_edit) {
      echo '<div class="be-qms-col-12">';
      echo '<strong>Existing evidence</strong><br/>';
      if (!$att_ids) {
        echo '<div class="be-qms-muted">None yet.</div>';
      } else {
        echo '<ul style="margin:8px 0 0 18px">';
        foreach ($att_ids as $aid) {
          $url = wp_get_attachment_url($aid);
          $name = get_the_title($aid);
          if ($url) echo '<li><a class="be-qms-link" target="_blank" href="'.esc_url($url).'">'.esc_html($name ?: basename($url)).'</a></li>';
        }
        echo '</ul>';
      }
      echo '</div>';
    }

    echo '</div>'; // grid

    echo '<div class="be-qms-row" style="margin-top:12px">';
    echo '<button class="be-qms-btn" type="submit">'.($is_edit ? 'Save changes' : 'Save project').'</button>';
    $back = esc_url(add_query_arg(['view'=>'projects'], self::portal_url()));
    echo '<a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">Cancel</a>';
    echo '</div>';

    echo '</form>';
  }

  private static function render_project_view($id) {
    $p = get_post($id);
    if (!$p || $p->post_type !== self::CPT_PROJECT) {
      echo '<div class="be-qms-muted">Project not found.</div>';
      return;
    }

    $customer = get_post_meta($id, '_be_qms_customer', true);
    $address  = get_post_meta($id, '_be_qms_address', true);
    $pv_kwp   = get_post_meta($id, '_be_qms_pv_kwp', true);
    $bess_kwh = get_post_meta($id, '_be_qms_bess_kwh', true);
    $notes    = get_post_meta($id, '_be_qms_notes', true);
    $att_ids  = get_post_meta($id, '_be_qms_evidence', true);
    if (!is_array($att_ids)) $att_ids = [];

    $print_url= esc_url(add_query_arg(['be_qms_export'=>'print','post'=>$id], self::portal_url()));

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div><h3 style="margin:0">'.esc_html($p->post_title).'</h3><div class="be-qms-muted">Project evidence file</div></div>';
    echo '<div class="be-qms-row">'
      .'<a class="be-qms-btn be-qms-btn-secondary" target="_blank" href="'.$print_url.'">Print / Save PDF</a>'
      .'</div>';
    echo '</div>';

    echo '<div class="be-qms-card" style="margin-top:14px">';
    echo '<div class="be-qms-grid">';
    echo '<div class="be-qms-col-6"><strong>Customer</strong><br/>'.esc_html($customer ?: '-').'</div>';
    echo '<div class="be-qms-col-6"><strong>Address</strong><br/>'.esc_html($address ?: '-').'</div>';
    echo '<div class="be-qms-col-6"><strong>PV (kWp)</strong><br/>'.esc_html($pv_kwp ?: '-').'</div>';
    echo '<div class="be-qms-col-6"><strong>Battery (kWh)</strong><br/>'.esc_html($bess_kwh ?: '-').'</div>';
    echo '</div>';

    if (!empty($notes)) {
      echo '<h4 style="margin-top:14px">Notes</h4>';
      echo '<div>'.wpautop(esc_html($notes)).'</div>';
    }

    echo '<h4 style="margin-top:14px">Evidence uploads</h4>';
    if (!$att_ids) {
      echo '<div class="be-qms-muted">None uploaded yet.</div>';
    } else {
      echo '<ul>';
      foreach ($att_ids as $aid) {
        $url = wp_get_attachment_url($aid);
        $name = get_the_title($aid);
        if ($url) {
          echo '<li><a class="be-qms-link" href="'.esc_url($url).'" target="_blank">'.esc_html($name ?: basename($url)).'</a></li>';
        }
      }
      echo '</ul>';
    }

    echo '<h4 style="margin-top:14px">Linked records</h4>';
    $rq = new WP_Query([
      'post_type' => self::CPT_RECORD,
      'post_status' => 'publish',
      'posts_per_page' => 50,
      'meta_key' => self::META_PROJECT_LINK,
      'meta_value' => $id,
      'orderby' => 'date',
      'order' => 'DESC',
    ]);
    if (!$rq->have_posts()) {
      echo '<div class="be-qms-muted">No linked records yet. Create/edit a record and select this project in “Linked project”.</div>';
    } else {
      echo '<table class="be-qms-table" style="margin-top:10px">';
      echo '<thead><tr><th>Date</th><th>Type</th><th>Title</th><th>Actions</th></tr></thead><tbody>';
      while ($rq->have_posts()) {
        $rq->the_post();
        $rid = get_the_ID();
        $terms = get_the_terms($rid, self::TAX_RECORD_TYPE);
        $type_name = $terms && !is_wp_error($terms) ? $terms[0]->name : '-';
        $view_url = esc_url(add_query_arg(['view'=>'records','be_action'=>'view','id'=>$rid], self::portal_url()));
        $edit_url = esc_url(add_query_arg(['view'=>'records','be_action'=>'edit','id'=>$rid], self::portal_url()));
        $doc_url  = esc_url(add_query_arg(['be_qms_export'=>'doc','post'=>$rid], self::portal_url()));
        $print_url2= esc_url(add_query_arg(['be_qms_export'=>'print','post'=>$rid], self::portal_url()));
        echo '<tr>';
        echo '<td>'.esc_html(get_post_meta($rid, self::META_RECORD_DATE, true) ?: get_the_date('Y-m-d')).'</td>';
        echo '<td>'.esc_html($type_name).'</td>';
        echo '<td><a class="be-qms-link" href="'.$view_url.'">'.esc_html(get_the_title()).'</a></td>';
        echo '<td class="be-qms-row">'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$view_url.'">View</a>'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$edit_url.'">Edit</a>'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$doc_url.'">DOC</a>'
          .'<a class="be-qms-btn be-qms-btn-secondary" target="_blank" href="'.$print_url2.'">Print/PDF</a>'
          .'</td>';
        echo '</tr>';
      }
      wp_reset_postdata();
      echo '</tbody></table>';
    }

    echo '</div>'; // card

    $back = esc_url(add_query_arg(['view'=>'projects'], self::portal_url()));
    echo '<div class="be-qms-row" style="margin-top:12px"><a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">← Back to projects</a></div>';
  }

  public static function handle_save_project() {
    self::require_staff();
    check_admin_referer('be_qms_save_project');

    $id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    if (!$title) wp_die('Missing title.');

    if ($id > 0) {
      $p = get_post($id);
      if (!$p || $p->post_type !== self::CPT_PROJECT) wp_die('Project not found.');
      wp_update_post(['ID'=>$id,'post_title'=>$title]);
      $pid = $id;
    } else {
      $pid = wp_insert_post([
        'post_type' => self::CPT_PROJECT,
        'post_status' => 'publish',
        'post_title' => $title,
      ], true);
      if (is_wp_error($pid)) wp_die('Failed to save project.');
    }

    update_post_meta($pid, '_be_qms_customer', sanitize_text_field($_POST['customer'] ?? ''));
    update_post_meta($pid, '_be_qms_address', sanitize_text_field($_POST['address'] ?? ''));
    update_post_meta($pid, '_be_qms_pv_kwp', sanitize_text_field($_POST['pv_kwp'] ?? ''));
    update_post_meta($pid, '_be_qms_bess_kwh', sanitize_text_field($_POST['bess_kwh'] ?? ''));
    update_post_meta($pid, '_be_qms_notes', wp_kses_post($_POST['notes'] ?? ''));

    // Merge evidence uploads (do not wipe existing)
    $existing = get_post_meta($pid, '_be_qms_evidence', true);
    if (!is_array($existing)) $existing = [];
    $new = self::handle_uploads('evidence');
    $merged = array_values(array_unique(array_merge($existing, $new ?: [])));
    update_post_meta($pid, '_be_qms_evidence', $merged);

    $url = add_query_arg(['view'=>'projects','be_action'=>'view_project','id'=>$pid], self::portal_url());
    wp_safe_redirect($url);
    exit;
  }

  // ------------------ Records ------------------

  private static function render_records() {
    $type = isset($_GET['type']) ? sanitize_key($_GET['type']) : 'r05_internal_review';
    $valid = self::record_type_map();
    if (!isset($valid[$type])) {
      // Default to R05
      $type = 'r05_internal_review';
    }

    $action = self::get_portal_action();

    // Special routes (R07 and R04)
    if ($type === 'r07_training_matrix') {
      self::render_r07($action);
      return;
    }
    if ($type === 'r04_tool_calibration') {
      self::render_r04($action);
      return;
    }

    // Generic view/edit/new routes for everything else
    if ($action === 'new') {
      self::render_record_form(0, $type);
      return;
    }
    if (($action === 'view' || $action === 'edit') && !empty($_GET['id'])) {
      $id = (int)$_GET['id'];
      if ($action === 'edit') self::render_record_form($id, $type);
      else self::render_record_view($id);
      return;
    }

    // Layout
    echo '<div class="be-qms-layout">';
    echo '<div class="be-qms-side">';
    foreach (self::record_type_map() as $slug => $label) {
      $url = esc_url(add_query_arg(['view'=>'records','type'=>$slug], self::portal_url()));
      $cls = ($slug === $type) ? 'is-active' : '';
      echo '<a class="'.$cls.'" href="'.$url.'"><span>'.esc_html($label).'</span></a>';
    }
    echo '</div>';

    echo '<div>';

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div>'
      .'<div class="be-qms-pill">'.esc_html(self::record_type_map()[$type]).'</div>'
      .'<div class="be-qms-muted" style="margin-top:6px">Editable records • can be assigned to projects • attachments can be added later</div>'
      .'</div>';

    $new_url = esc_url(add_query_arg(['view'=>'records','type'=>$type,'be_action'=>'new'], self::portal_url()));
    echo '<a class="be-qms-btn" href="'.$new_url.'">Add New</a>';
    echo '</div>';

    // Records list
    $args = [
      'post_type' => self::CPT_RECORD,
      'post_status' => 'publish',
      'posts_per_page' => 100,
      'orderby' => 'date',
      'order' => 'DESC',
      'tax_query' => [[
        'taxonomy' => self::TAX_RECORD_TYPE,
        'field' => 'slug',
        'terms' => [$type],
      ]]
    ];

    $q = new WP_Query($args);

    echo '<table class="be-qms-table">';
    echo '<thead><tr><th>Date</th><th>Project</th><th>Title</th><th>Actions</th></tr></thead><tbody>';

    if (!$q->have_posts()) {
      echo '<tr><td colspan="4" class="be-qms-muted">No records yet. Click “Add New”.</td></tr>';
    } else {
      while ($q->have_posts()) {
        $q->the_post();
        $rid = get_the_ID();
        $date = get_post_meta($rid, self::META_RECORD_DATE, true) ?: get_the_date('Y-m-d');
        $proj = (int)get_post_meta($rid, self::META_PROJECT_LINK, true);
        $proj_label = $proj ? (get_the_title($proj) ?: ('Project #'.$proj)) : '—';
        $proj_url = $proj ? esc_url(add_query_arg(['view'=>'projects','be_action'=>'view_project','id'=>$proj], self::portal_url())) : '';

        $view_url = esc_url(add_query_arg(['view'=>'records','type'=>$type,'be_action'=>'view','id'=>$rid], self::portal_url()));
        $edit_url = esc_url(add_query_arg(['view'=>'records','type'=>$type,'be_action'=>'edit','id'=>$rid], self::portal_url()));
        $doc_url  = esc_url(add_query_arg(['be_qms_export'=>'doc','post'=>$rid], self::portal_url()));
        $print_url= esc_url(add_query_arg(['be_qms_export'=>'print','post'=>$rid], self::portal_url()));
        $del_url  = esc_url(admin_url('admin-post.php?action=be_qms_delete&kind=record&id='.$rid.'&_wpnonce='.wp_create_nonce('be_qms_delete_'.$rid)));

        echo '<tr>';
        echo '<td>'.esc_html($date).'</td>';
        echo '<td>'.($proj_url ? '<a class="be-qms-link" href="'.$proj_url.'">'.esc_html($proj_label).'</a>' : '<span class="be-qms-muted">'.esc_html($proj_label).'</span>').'</td>';
        echo '<td><a class="be-qms-link" href="'.$view_url.'">'.esc_html(get_the_title()).'</a></td>';
        echo '<td class="be-qms-row">'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$view_url.'">View</a>'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$edit_url.'">Edit</a>'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$doc_url.'">DOC</a>'
          .'<a class="be-qms-btn be-qms-btn-secondary" target="_blank" href="'.$print_url.'">Print/PDF</a>'
          .'<a class="be-qms-btn be-qms-btn-danger" href="'.$del_url.'" onclick="return confirm(\'Delete this record?\')">Delete</a>'
          .'</td>';
        echo '</tr>';
      }
      wp_reset_postdata();
    }

    echo '</tbody></table>';

    echo '</div>'; // main
    echo '</div>'; // layout
  }

  private static function project_select_html($selected_id = 0) {
    $projects = get_posts([
      'post_type' => self::CPT_PROJECT,
      'post_status' => 'publish',
      'numberposts' => 200,
      'orderby' => 'date',
      'order' => 'DESC',
    ]);

    $html = '<select class="be-qms-select" name="project_id">';
    $html .= '<option value="0">— Company record (not tied to a job) —</option>';
    foreach ($projects as $p) {
      $sel = ((int)$selected_id === (int)$p->ID) ? 'selected' : '';
      $html .= '<option '.$sel.' value="'.esc_attr($p->ID).'">'.esc_html($p->post_title).'</option>';
    }
    $html .= '</select>';
    return $html;
  }

  private static function render_record_form($id = 0, $type_slug = '') {
    $is_edit = $id > 0;
    $p = $is_edit ? get_post($id) : null;

    if ($is_edit && (!$p || $p->post_type !== self::CPT_RECORD)) {
      echo '<div class="be-qms-muted">Record not found.</div>';
      return;
    }

    $type_slug = $type_slug ?: ($is_edit ? self::get_record_type_slug($id) : '');
    if (!$type_slug) $type_slug = 'r05_internal_review';

    // Prevent editing R07/R04 from generic form
    if ($type_slug === 'r07_training_matrix' || $type_slug === 'r04_tool_calibration') {
      echo '<div class="be-qms-muted">This record type uses a structured form. Use the Add/Edit buttons within that section.</div>';
      $back = esc_url(add_query_arg(['view'=>'records','type'=>$type_slug], self::portal_url()));
      echo '<div class="be-qms-row" style="margin-top:12px"><a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">← Back</a></div>';
      return;
    }

    $title = $is_edit ? $p->post_title : '';
    $date = $is_edit ? (get_post_meta($id, self::META_RECORD_DATE, true) ?: date('Y-m-d')) : date('Y-m-d');
    $details = $is_edit ? get_post_meta($id, self::META_DETAILS, true) : '';
    $actions = $is_edit ? get_post_meta($id, self::META_ACTIONS, true) : '';
    $project_id = $is_edit ? (int)get_post_meta($id, self::META_PROJECT_LINK, true) : 0;

    $att_ids = $is_edit ? get_post_meta($id, self::META_ATTACHMENTS, true) : [];
    if (!is_array($att_ids)) $att_ids = [];

    echo '<h3>'.($is_edit ? 'Edit record' : 'New record').'</h3>';
    echo '<div class="be-qms-pill" style="margin-bottom:12px">'.esc_html(self::record_type_map()[$type_slug] ?? $type_slug).'</div>';

    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data">';
    echo '<input type="hidden" name="action" value="be_qms_save_record" />';
    if ($is_edit) echo '<input type="hidden" name="record_id" value="'.esc_attr($id).'" />';
    echo '<input type="hidden" name="record_type" value="'.esc_attr($type_slug).'" />';
    wp_nonce_field('be_qms_save_record');

    echo '<div class="be-qms-grid">';

    echo '<div class="be-qms-col-6"><label><strong>Date</strong><br/>';
    echo '<input class="be-qms-input" type="date" name="record_date" value="'.esc_attr($date).'" required /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Linked project</strong> <span class="be-qms-muted">(optional)</span><br/>';
    echo self::project_select_html($project_id);
    echo '</label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Title</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="title" value="'.esc_attr($title).'" placeholder="e.g. Internal Review – Q1 2026" required /></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Details</strong><br/>';
    echo '<textarea class="be-qms-textarea" name="details" required>'.esc_textarea($details).'</textarea></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Actions / follow-ups</strong> <span class="be-qms-muted">(who / by when)</span><br/>';
    echo '<textarea class="be-qms-textarea" name="actions">'.esc_textarea($actions).'</textarea></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Upload attachments</strong> <span class="be-qms-muted">(optional)</span><br/>';
    echo '<input type="file" name="attachments[]" multiple /></label></div>';

    if ($is_edit) {
      echo '<div class="be-qms-col-12"><strong>Existing attachments</strong><br/>';
      if (!$att_ids) {
        echo '<div class="be-qms-muted">None.</div>';
      } else {
        echo '<div class="be-qms-muted">Tick to remove on save:</div>';
        echo '<ul style="margin:8px 0 0 18px">';
        foreach ($att_ids as $aid) {
          $url = wp_get_attachment_url($aid);
          $name = get_the_title($aid);
          if (!$url) continue;
          echo '<li><label style="display:flex;gap:10px;align-items:center">'
            .'<input type="checkbox" name="remove_attachments[]" value="'.esc_attr($aid).'" />'
            .'<a class="be-qms-link" target="_blank" href="'.esc_url($url).'">'.esc_html($name ?: basename($url)).'</a>'
            .'</label></li>';
        }
        echo '</ul>';
      }
      echo '</div>';
    }

    echo '</div>'; // grid

    echo '<div class="be-qms-row" style="margin-top:12px">';
    echo '<button class="be-qms-btn" type="submit">Save record</button>';
    $back = esc_url(add_query_arg(['view'=>'records','type'=>$type_slug], self::portal_url()));
    echo '<a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">Cancel</a>';
    echo '</div>';

    echo '</form>';
  }

  private static function get_record_type_slug($record_id) {
    $terms = get_the_terms($record_id, self::TAX_RECORD_TYPE);
    if ($terms && !is_wp_error($terms)) return $terms[0]->slug;
    return '';
  }

  private static function render_record_view($id) {
    $p = get_post($id);
    if (!$p || $p->post_type !== self::CPT_RECORD) {
      echo '<div class="be-qms-muted">Record not found.</div>';
      return;
    }

    $type_slug = self::get_record_type_slug($id);
    $type_label = self::record_type_map()[$type_slug] ?? ($type_slug ?: '-');

    // If someone tries to view R04/R07 via generic view, send them back
    if ($type_slug === 'r07_training_matrix') {
      $back = esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix'], self::portal_url()));
      echo '<div class="be-qms-muted">R07 uses a structured view. Use the R07 section.</div>';
      echo '<div class="be-qms-row" style="margin-top:12px"><a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">Go to R07</a></div>';
      return;
    }
    if ($type_slug === 'r04_tool_calibration') {
      $back = esc_url(add_query_arg(['view'=>'records','type'=>'r04_tool_calibration'], self::portal_url()));
      echo '<div class="be-qms-muted">R04 uses a structured view. Use the R04 section.</div>';
      echo '<div class="be-qms-row" style="margin-top:12px"><a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">Go to R04</a></div>';
      return;
    }

    $record_date = get_post_meta($id, self::META_RECORD_DATE, true) ?: get_the_date('Y-m-d', $id);
    $details = get_post_meta($id, self::META_DETAILS, true);
    $actions = get_post_meta($id, self::META_ACTIONS, true);
    $project_id = (int)get_post_meta($id, self::META_PROJECT_LINK, true);

    $att_ids = get_post_meta($id, self::META_ATTACHMENTS, true);
    if (!is_array($att_ids)) $att_ids = [];

    $edit_url = esc_url(add_query_arg(['view'=>'records','type'=>$type_slug,'be_action'=>'edit','id'=>$id], self::portal_url()));
    $doc_url  = esc_url(add_query_arg(['be_qms_export'=>'doc','post'=>$id], self::portal_url()));
    $print_url= esc_url(add_query_arg(['be_qms_export'=>'print','post'=>$id], self::portal_url()));

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div><h3 style="margin:0">'.esc_html($p->post_title).'</h3>';
    echo '<div class="be-qms-muted">'.esc_html($type_label).' • '.esc_html($record_date).'</div>';
    if ($project_id) {
      $proj_url = esc_url(add_query_arg(['view'=>'projects','be_action'=>'view_project','id'=>$project_id], self::portal_url()));
      echo '<div class="be-qms-muted">Linked project: <a class="be-qms-link" href="'.$proj_url.'">'.esc_html(get_the_title($project_id) ?: ('Project #'.$project_id)).'</a></div>';
    }
    echo '</div>';
    echo '<div class="be-qms-row">'
      .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$edit_url.'">Edit</a>'
      .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$doc_url.'">Download DOC</a>'
      .'<a class="be-qms-btn be-qms-btn-secondary" target="_blank" href="'.$print_url.'">Print / Save PDF</a>'
      .'</div>';
    echo '</div>';

    echo '<div class="be-qms-card" style="margin-top:14px">';
    echo '<h4>Details</h4>';
    echo '<div>'.wpautop(esc_html($details)).'</div>';
    if (!empty($actions)) {
      echo '<h4 style="margin-top:14px">Actions</h4>';
      echo '<div>'.wpautop(esc_html($actions)).'</div>';
    }

    echo '<h4 style="margin-top:14px">Attachments</h4>';
    if (!$att_ids) {
      echo '<div class="be-qms-muted">None.</div>';
    } else {
      echo '<ul>';
      foreach ($att_ids as $aid) {
        $url = wp_get_attachment_url($aid);
        $name = get_the_title($aid);
        if ($url) {
          echo '<li><a class="be-qms-link" href="'.esc_url($url).'" target="_blank">'.esc_html($name ?: basename($url)).'</a></li>';
        }
      }
      echo '</ul>';
    }
    echo '</div>';

    $back = esc_url(add_query_arg(['view'=>'records','type'=>$type_slug], self::portal_url()));
    echo '<div class="be-qms-row" style="margin-top:12px"><a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">← Back to records</a></div>';
  }

  public static function handle_save_record() {
    self::require_staff();
    check_admin_referer('be_qms_save_record');

    $id = isset($_POST['record_id']) ? (int)$_POST['record_id'] : 0;
    $type = isset($_POST['record_type']) ? sanitize_key($_POST['record_type']) : '';
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $date = isset($_POST['record_date']) ? sanitize_text_field($_POST['record_date']) : '';
    $details = isset($_POST['details']) ? wp_kses_post($_POST['details']) : '';
    $actions = isset($_POST['actions']) ? wp_kses_post($_POST['actions']) : '';
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

    if (!$type || !$title || !$date || !$details) {
      wp_die('Missing required fields.');
    }

    // Prevent saving R07/R04 via generic handler
    if ($type === 'r07_training_matrix' || $type === 'r04_tool_calibration') {
      wp_die('This record type must be saved via its structured form.');
    }

    if ($id > 0) {
      $p = get_post($id);
      if (!$p || $p->post_type !== self::CPT_RECORD) wp_die('Record not found.');
      wp_update_post(['ID'=>$id,'post_title'=>$title]);
      $pid = $id;
    } else {
      $pid = wp_insert_post([
        'post_type' => self::CPT_RECORD,
        'post_status' => 'publish',
        'post_title' => $title,
      ], true);
      if (is_wp_error($pid)) wp_die('Failed to save record.');
    }

    wp_set_object_terms($pid, [$type], self::TAX_RECORD_TYPE, false);

    update_post_meta($pid, self::META_RECORD_DATE, $date);
    update_post_meta($pid, self::META_DETAILS, $details);
    update_post_meta($pid, self::META_ACTIONS, $actions);

    if ($project_id > 0) update_post_meta($pid, self::META_PROJECT_LINK, $project_id);
    else delete_post_meta($pid, self::META_PROJECT_LINK);

    // Attachments merge + remove
    $existing = get_post_meta($pid, self::META_ATTACHMENTS, true);
    if (!is_array($existing)) $existing = [];

    $to_remove = isset($_POST['remove_attachments']) && is_array($_POST['remove_attachments']) ? array_map('intval', $_POST['remove_attachments']) : [];
    if ($to_remove) {
      $existing = array_values(array_diff($existing, $to_remove));
    }

    $new = self::handle_uploads('attachments');
    $merged = array_values(array_unique(array_merge($existing, $new ?: [])));
    update_post_meta($pid, self::META_ATTACHMENTS, $merged);

    $url = add_query_arg(['view'=>'records','type'=>$type,'be_action'=>'view','id'=>$pid], self::portal_url());
    wp_safe_redirect($url);
    exit;
  }

  // ------------------ R07 Training Matrix (employee-first, Easy-MCS style) ------------------

  private static function render_r07($action) {
    $type = 'r07_training_matrix';

    // Sidebar layout wrapper
    echo '<div class="be-qms-layout">';
    echo '<div class="be-qms-side">';
    foreach (self::record_type_map() as $slug => $label) {
      $url = esc_url(add_query_arg(['view'=>'records','type'=>$slug], self::portal_url()));
      $cls = ($slug === $type) ? 'is-active' : '';
      echo '<a class="'.$cls.'" href="'.$url.'"><span>'.esc_html($label).'</span></a>';
    }
    echo '</div>';

    echo '<div>';

    // Routes
    if ($action === 'employee_new') {
      self::render_employee_form();
      echo '</div></div>'; // close layout
      return;
    }

    if (($action === 'employee_view' || $action === 'employee_edit') && !empty($_GET['employee_id'])) {
      $eid = (int)$_GET['employee_id'];
      if ($action === 'employee_edit') self::render_employee_form($eid);
      else self::render_employee_view($eid);
      echo '</div></div>';
      return;
    }

    if (($action === 'skill_new') && !empty($_GET['employee_id'])) {
      self::render_skill_form(0, (int)$_GET['employee_id']);
      echo '</div></div>';
      return;
    }

    if (($action === 'skill_edit') && !empty($_GET['skill_id'])) {
      self::render_skill_form((int)$_GET['skill_id'], 0);
      echo '</div></div>';
      return;
    }

    // Easy-MCS style list screen: employee + course rows
    $add_emp_url = esc_url(add_query_arg(['view'=>'records','type'=>$type,'be_action'=>'employee_new'], self::portal_url()));
    $print_url = esc_url(add_query_arg(['be_qms_export'=>'print_r07'], self::portal_url()));

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div>'
      .'<h3 style="margin:0">Personal Skills & Training Matrix</h3>'
      .'<div class="be-qms-muted">Your existing employees/courses are displayed below. Click “View” to manage an employee’s skill records.</div>'
      .'</div>';
    echo '<div class="be-qms-row">'
      .'<a class="be-qms-btn" href="'.$add_emp_url.'">Add New</a>'
      .'<a class="be-qms-btn be-qms-btn-secondary" target="_blank" href="'.$print_url.'">Print Log</a>'
      .'</div>';
    echo '</div>';

    $skills = self::query_skills();

    echo '<table class="be-qms-table" style="margin-top:12px">';
    echo '<thead><tr><th>Employee Name</th><th>Course Name</th><th>Renewal Date</th><th>Options</th></tr></thead><tbody>';

    if (!$skills) {
      echo '<tr><td colspan="4" class="be-qms-muted">No training records yet. Click “Add New” to create an employee first.</td></tr>';
    } else {
      $last_emp = 0;
      foreach ($skills as $s) {
        $sid = (int)$s->ID;
        $eid = (int)get_post_meta($sid, self::META_EMPLOYEE_ID, true);
        $emp_name = $eid ? get_the_title($eid) : '';
        $course = get_post_meta($sid, self::META_SKILL_COURSE, true);
        $renewal = get_post_meta($sid, self::META_SKILL_RENEWAL, true);

        $emp_cell = '';
        if ($eid !== $last_emp) {
          $emp_view = esc_url(add_query_arg(['view'=>'records','type'=>$type,'be_action'=>'employee_view','employee_id'=>$eid], self::portal_url()));
          $emp_edit = esc_url(add_query_arg(['view'=>'records','type'=>$type,'be_action'=>'employee_edit','employee_id'=>$eid], self::portal_url()));
          $emp_del  = esc_url(admin_url('admin-post.php?action=be_qms_delete&kind=employee&id='.$eid.'&_wpnonce='.wp_create_nonce('be_qms_delete_'.$eid)));
          $emp_cell = '<a class="be-qms-link" href="'.$emp_view.'">'.esc_html($emp_name).'</a>'
            .'<div class="be-qms-muted" style="margin-top:6px">'
            .'<a class="be-qms-link" href="'.$emp_edit.'" style="font-weight:700">Edit</a>'
            .' &nbsp;·&nbsp; '
            .'<a class="be-qms-link" href="'.$emp_del.'" style="color:#fecaca" onclick="return confirm(\'Remove employee (and keep their course records)?\')">Remove</a>'
            .'</div>';
        }

        $skill_edit = esc_url(add_query_arg(['view'=>'records','type'=>$type,'be_action'=>'skill_edit','skill_id'=>$sid], self::portal_url()));
        $skill_del  = esc_url(admin_url('admin-post.php?action=be_qms_delete&kind=record&id='.$sid.'&_wpnonce='.wp_create_nonce('be_qms_delete_'.$sid)));

        echo '<tr>';
        echo '<td>'.($emp_cell ?: '<span class="be-qms-muted">&nbsp;</span>').'</td>';
        echo '<td>'.esc_html($course ?: get_the_title($sid)).'</td>';
        echo '<td>'.esc_html($renewal ?: '-').'</td>';
        echo '<td class="be-qms-row">'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$skill_edit.'">Edit</a>'
          .'<a class="be-qms-btn be-qms-btn-danger" href="'.$skill_del.'" onclick="return confirm(\'Remove this skill record?\')">Remove</a>'
          .'</td>';
        echo '</tr>';

        $last_emp = $eid;
      }
    }

    echo '</tbody></table>';

    echo '</div></div>'; // close layout
  }

  private static function query_skills($employee_id = 0) {
    $tax_query = [[
      'taxonomy' => self::TAX_RECORD_TYPE,
      'field' => 'slug',
      'terms' => ['r07_training_matrix'],
    ]];

    $meta_query = [];
    if ($employee_id > 0) {
      $meta_query[] = [
        'key' => self::META_EMPLOYEE_ID,
        'value' => $employee_id,
        'compare' => '=',
      ];
    }

    $args = [
      'post_type' => self::CPT_RECORD,
      'post_status' => 'publish',
      'posts_per_page' => 500,
      'orderby' => 'title',
      'order' => 'ASC',
      'tax_query' => $tax_query,
    ];

    if (!empty($meta_query)) {
      $args['meta_query'] = $meta_query;
    }

    $q = new WP_Query($args);
    if (!$q->have_posts()) return [];
    return $q->posts;
  }

  private static function render_employee_form($employee_id = 0) {
    $is_edit = $employee_id > 0;
    $p = $is_edit ? get_post($employee_id) : null;
    if ($is_edit && (!$p || $p->post_type !== self::CPT_EMPLOYEE)) {
      echo '<div class="be-qms-muted">Employee not found.</div>';
      return;
    }

    $name = $is_edit ? $p->post_title : '';

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div><h3 style="margin:0">'.($is_edit ? 'Edit Employee' : 'Add New Employee').'</h3>';
    echo '<div class="be-qms-muted">Employee-first flow (matches Easy-MCS).</div></div>';
    echo '</div>';

    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
    echo '<input type="hidden" name="action" value="be_qms_save_employee" />';
    if ($is_edit) echo '<input type="hidden" name="employee_id" value="'.esc_attr($employee_id).'" />';
    wp_nonce_field('be_qms_save_employee');

    echo '<div class="be-qms-grid" style="margin-top:12px">';
    echo '<div class="be-qms-col-12"><label><strong>Employee name</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="employee_name" value="'.esc_attr($name).'" placeholder="e.g. Alan Baltic" required /></label></div>';
    echo '</div>';

    echo '<div class="be-qms-row" style="margin-top:12px">';
    echo '<button class="be-qms-btn" type="submit">'.($is_edit ? 'Save & Close' : 'Save').'</button>';
    $back = esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix'], self::portal_url()));
    echo '<a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">Cancel</a>';
    echo '</div>';

    echo '</form>';
  }

  private static function render_employee_view($employee_id) {
    $p = get_post($employee_id);
    if (!$p || $p->post_type !== self::CPT_EMPLOYEE) {
      echo '<div class="be-qms-muted">Employee not found.</div>';
      return;
    }

    $add_skill_url = esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix','be_action'=>'skill_new','employee_id'=>$employee_id], self::portal_url()));
    $print_url = esc_url(add_query_arg(['be_qms_export'=>'print_r07','employee_id'=>$employee_id], self::portal_url()));
    $return_url = esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix'], self::portal_url()));

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div>'
      .'<h3 style="margin:0">R07 - Personal Skills & Training Matrix</h3>'
      .'<div class="be-qms-muted">Employee: <strong>'.esc_html($p->post_title).'</strong></div>'
      .'</div>';

    echo '<div class="be-qms-row">'
      .'<a class="be-qms-btn" href="'.$add_skill_url.'">Add New</a>'
      .'<a class="be-qms-btn be-qms-btn-secondary" target="_blank" href="'.$print_url.'">Print Log</a>'
      .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$return_url.'">Return to Records</a>'
      .'</div>';
    echo '</div>';

    $skills = self::query_skills($employee_id);

    echo '<table class="be-qms-table" style="margin-top:12px">';
    echo '<thead><tr><th>Course Name</th><th>Date of Course</th><th>Renewal Date</th><th>Options</th></tr></thead><tbody>';

    if (!$skills) {
      echo '<tr><td colspan="4" class="be-qms-muted">No skill records yet. Click “Add New”.</td></tr>';
    } else {
      foreach ($skills as $s) {
        $sid = (int)$s->ID;
        $course = get_post_meta($sid, self::META_SKILL_COURSE, true);
        $course_date = get_post_meta($sid, self::META_SKILL_DATE, true);
        $renewal = get_post_meta($sid, self::META_SKILL_RENEWAL, true);

        $edit_url = esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix','be_action'=>'skill_edit','skill_id'=>$sid], self::portal_url()));
        $del_url  = esc_url(admin_url('admin-post.php?action=be_qms_delete&kind=record&id='.$sid.'&_wpnonce='.wp_create_nonce('be_qms_delete_'.$sid)));

        echo '<tr>';
        echo '<td>'.esc_html($course ?: get_the_title($sid)).'</td>';
        echo '<td>'.esc_html($course_date ?: '-').'</td>';
        echo '<td>'.esc_html($renewal ?: '-').'</td>';
        echo '<td class="be-qms-row">'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$edit_url.'">Edit</a>'
          .'<a class="be-qms-btn be-qms-btn-danger" href="'.$del_url.'" onclick="return confirm(\'Remove this skill record?\')">Remove</a>'
          .'</td>';
        echo '</tr>';
      }
    }

    echo '</tbody></table>';
  }

  private static function render_skill_form($skill_id = 0, $employee_id = 0) {
    $is_edit = $skill_id > 0;

    $p = $is_edit ? get_post($skill_id) : null;
    if ($is_edit && (!$p || $p->post_type !== self::CPT_RECORD)) {
      echo '<div class="be-qms-muted">Skill record not found.</div>';
      return;
    }

    if ($is_edit) {
      $employee_id = (int)get_post_meta($skill_id, self::META_EMPLOYEE_ID, true);
    }

    $emp = $employee_id ? get_post($employee_id) : null;
    if (!$emp || $emp->post_type !== self::CPT_EMPLOYEE) {
      echo '<div class="be-qms-muted">Employee not found. Create an employee first.</div>';
      $back = esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix','be_action'=>'employee_new'], self::portal_url()));
      echo '<div class="be-qms-row" style="margin-top:12px"><a class="be-qms-btn" href="'.$back.'">Add New Employee</a></div>';
      return;
    }

    $course = $is_edit ? get_post_meta($skill_id, self::META_SKILL_COURSE, true) : '';
    $course_date = $is_edit ? get_post_meta($skill_id, self::META_SKILL_DATE, true) : '';
    $renewal = $is_edit ? get_post_meta($skill_id, self::META_SKILL_RENEWAL, true) : '';
    $desc = $is_edit ? get_post_meta($skill_id, self::META_SKILL_DESC, true) : '';

    $att_ids = $is_edit ? get_post_meta($skill_id, self::META_ATTACHMENTS, true) : [];
    if (!is_array($att_ids)) $att_ids = [];

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div>'
      .'<h3 style="margin:0">R07 - Personal Skills & Training Matrix</h3>'
      .'<div class="be-qms-muted">Employee: <strong>'.esc_html($emp->post_title).'</strong></div>'
      .'</div>';

    echo '<div class="be-qms-row">'
      .'<a class="be-qms-btn be-qms-btn-secondary" href="'.esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix','be_action'=>'employee_view','employee_id'=>$employee_id], self::portal_url())).'">Return</a>'
      .'</div>';
    echo '</div>';

    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data" style="margin-top:12px">';
    echo '<input type="hidden" name="action" value="be_qms_save_skill" />';
    echo '<input type="hidden" name="employee_id" value="'.esc_attr($employee_id).'" />';
    if ($is_edit) echo '<input type="hidden" name="skill_id" value="'.esc_attr($skill_id).'" />';
    wp_nonce_field('be_qms_save_skill');

    echo '<div class="be-qms-grid">';

    echo '<div class="be-qms-col-6"><label><strong>Course Name</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="course_name" value="'.esc_attr($course).'" placeholder="e.g. BPEC Solar PV" required /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Date of Course</strong><br/>';
    echo '<input class="be-qms-input" type="date" name="course_date" value="'.esc_attr($course_date).'" /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Renewal Date</strong><br/>';
    echo '<input class="be-qms-input" type="date" name="renewal_date" value="'.esc_attr($renewal).'" /></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Description of Course and Certificates Attained</strong><br/>';
    echo '<textarea class="be-qms-textarea" name="course_desc">'.esc_textarea($desc).'</textarea></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Upload certificates</strong> <span class="be-qms-muted">(optional)</span><br/>';
    echo '<input type="file" name="attachments[]" multiple /></label></div>';

    if ($is_edit) {
      echo '<div class="be-qms-col-12"><strong>Existing attachments</strong><br/>';
      if (!$att_ids) {
        echo '<div class="be-qms-muted">None.</div>';
      } else {
        echo '<div class="be-qms-muted">Tick to remove on save:</div>';
        echo '<ul style="margin:8px 0 0 18px">';
        foreach ($att_ids as $aid) {
          $url = wp_get_attachment_url($aid);
          $name = get_the_title($aid);
          if (!$url) continue;
          echo '<li><label style="display:flex;gap:10px;align-items:center">'
            .'<input type="checkbox" name="remove_attachments[]" value="'.esc_attr($aid).'" />'
            .'<a class="be-qms-link" target="_blank" href="'.esc_url($url).'">'.esc_html($name ?: basename($url)).'</a>'
            .'</label></li>';
        }
        echo '</ul>';
      }
      echo '</div>';
    }

    echo '</div>'; // grid

    echo '<div class="be-qms-row" style="margin-top:12px">';
    echo '<button class="be-qms-btn" type="submit">'.($is_edit ? 'Save & Close' : 'Save').'</button>';
    echo '<a class="be-qms-btn be-qms-btn-secondary" href="'.esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix','be_action'=>'employee_view','employee_id'=>$employee_id], self::portal_url())).'">Cancel</a>';
    echo '</div>';

    echo '</form>';
  }

  public static function handle_save_employee() {
    self::require_staff();
    check_admin_referer('be_qms_save_employee');

    $id = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
    $name = isset($_POST['employee_name']) ? sanitize_text_field($_POST['employee_name']) : '';
    if (!$name) wp_die('Missing employee name.');

    if ($id > 0) {
      $p = get_post($id);
      if (!$p || $p->post_type !== self::CPT_EMPLOYEE) wp_die('Employee not found.');
      wp_update_post(['ID'=>$id,'post_title'=>$name]);
      $eid = $id;
    } else {
      $eid = wp_insert_post([
        'post_type' => self::CPT_EMPLOYEE,
        'post_status' => 'publish',
        'post_title' => $name,
      ], true);
      if (is_wp_error($eid)) wp_die('Failed to save employee.');
    }

    // After save, go to employee view
    $url = add_query_arg(['view'=>'records','type'=>'r07_training_matrix','be_action'=>'employee_view','employee_id'=>$eid], self::portal_url());
    wp_safe_redirect($url);
    exit;
  }

  public static function handle_save_skill() {
    self::require_staff();
    check_admin_referer('be_qms_save_skill');

    $skill_id = isset($_POST['skill_id']) ? (int)$_POST['skill_id'] : 0;
    $employee_id = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;

    $course = isset($_POST['course_name']) ? sanitize_text_field($_POST['course_name']) : '';
    $course_date = isset($_POST['course_date']) ? sanitize_text_field($_POST['course_date']) : '';
    $renewal = isset($_POST['renewal_date']) ? sanitize_text_field($_POST['renewal_date']) : '';
    $desc = isset($_POST['course_desc']) ? wp_kses_post($_POST['course_desc']) : '';

    if (!$employee_id || !$course) wp_die('Missing required fields.');

    // Create/update record
    $title = $course;

    if ($skill_id > 0) {
      $p = get_post($skill_id);
      if (!$p || $p->post_type !== self::CPT_RECORD) wp_die('Skill record not found.');
      wp_update_post(['ID'=>$skill_id,'post_title'=>$title]);
      $rid = $skill_id;
    } else {
      $rid = wp_insert_post([
        'post_type' => self::CPT_RECORD,
        'post_status' => 'publish',
        'post_title' => $title,
      ], true);
      if (is_wp_error($rid)) wp_die('Failed to save skill record.');
    }

    wp_set_object_terms($rid, ['r07_training_matrix'], self::TAX_RECORD_TYPE, false);

    update_post_meta($rid, self::META_EMPLOYEE_ID, $employee_id);
    update_post_meta($rid, self::META_SKILL_COURSE, $course);
    update_post_meta($rid, self::META_SKILL_DATE, $course_date);
    update_post_meta($rid, self::META_SKILL_RENEWAL, $renewal);
    update_post_meta($rid, self::META_SKILL_DESC, $desc);

    // Keep META_RECORD_DATE for sorting/consistency
    update_post_meta($rid, self::META_RECORD_DATE, $course_date ?: date('Y-m-d'));

    // Attachments merge + remove
    $existing = get_post_meta($rid, self::META_ATTACHMENTS, true);
    if (!is_array($existing)) $existing = [];

    $to_remove = isset($_POST['remove_attachments']) && is_array($_POST['remove_attachments']) ? array_map('intval', $_POST['remove_attachments']) : [];
    if ($to_remove) {
      $existing = array_values(array_diff($existing, $to_remove));
    }

    $new = self::handle_uploads('attachments');
    $merged = array_values(array_unique(array_merge($existing, $new ?: [])));
    update_post_meta($rid, self::META_ATTACHMENTS, $merged);

    $url = add_query_arg(['view'=>'records','type'=>'r07_training_matrix','be_action'=>'employee_view','employee_id'=>$employee_id], self::portal_url());
    wp_safe_redirect($url);
    exit;
  }

  // ------------------ R04 Tool Calibration (structured) ------------------

  private static function render_r04($action) {
    $type = 'r04_tool_calibration';

    echo '<div class="be-qms-layout">';
    echo '<div class="be-qms-side">';
    foreach (self::record_type_map() as $slug => $label) {
      $url = esc_url(add_query_arg(['view'=>'records','type'=>$slug], self::portal_url()));
      $cls = ($slug === $type) ? 'is-active' : '';
      echo '<a class="'.$cls.'" href="'.$url.'"><span>'.esc_html($label).'</span></a>';
    }
    echo '</div>';

    echo '<div>';

    if ($action === 'tool_new') {
      self::render_tool_form();
      echo '</div></div>';
      return;
    }
    if (($action === 'tool_edit' || $action === 'tool_view') && !empty($_GET['tool_id'])) {
      $tid = (int)$_GET['tool_id'];
      if ($action === 'tool_edit') self::render_tool_form($tid);
      else self::render_tool_view($tid);
      echo '</div></div>';
      return;
    }

    $add_url = esc_url(add_query_arg(['view'=>'records','type'=>$type,'be_action'=>'tool_new'], self::portal_url()));
    $print_url = esc_url(add_query_arg(['be_qms_export'=>'print_r04'], self::portal_url()));

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div>'
      .'<h3 style="margin:0">R04 - Tool Calibration</h3>'
      .'<div class="be-qms-muted">Structured tool log (matches Easy-MCS fields).</div>'
      .'</div>';
    echo '<div class="be-qms-row">'
      .'<a class="be-qms-btn" href="'.$add_url.'">Add New</a>'
      .'<a class="be-qms-btn be-qms-btn-secondary" target="_blank" href="'.$print_url.'">Print Log</a>'
      .'</div>';
    echo '</div>';

    $tools = self::query_tools();

    echo '<table class="be-qms-table" style="margin-top:12px">';
    echo '<thead><tr><th>Item</th><th>Serial No</th><th>Next Due</th><th>Options</th></tr></thead><tbody>';

    if (!$tools) {
      echo '<tr><td colspan="4" class="be-qms-muted">No tools logged yet. Click “Add New”.</td></tr>';
    } else {
      foreach ($tools as $t) {
        $rid = (int)$t->ID;
        $item = get_post_meta($rid, self::META_TOOL_ITEM, true) ?: get_the_title($rid);
        $serial = get_post_meta($rid, self::META_TOOL_SERIAL, true);
        $next = get_post_meta($rid, self::META_TOOL_DATE_NEXT, true);

        $view_url = esc_url(add_query_arg(['view'=>'records','type'=>$type,'be_action'=>'tool_view','tool_id'=>$rid], self::portal_url()));
        $edit_url = esc_url(add_query_arg(['view'=>'records','type'=>$type,'be_action'=>'tool_edit','tool_id'=>$rid], self::portal_url()));
        $del_url  = esc_url(admin_url('admin-post.php?action=be_qms_delete&kind=record&id='.$rid.'&_wpnonce='.wp_create_nonce('be_qms_delete_'.$rid)));

        echo '<tr>';
        echo '<td><a class="be-qms-link" href="'.$view_url.'">'.esc_html($item).'</a></td>';
        echo '<td>'.esc_html($serial ?: '-').'</td>';
        echo '<td>'.esc_html($next ?: '-').'</td>';
        echo '<td class="be-qms-row">'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$view_url.'">View</a>'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$edit_url.'">Edit</a>'
          .'<a class="be-qms-btn be-qms-btn-danger" href="'.$del_url.'" onclick="return confirm(\'Remove this tool record?\')">Remove</a>'
          .'</td>';
        echo '</tr>';
      }
    }

    echo '</tbody></table>';

    echo '</div></div>';
  }

  private static function query_tools() {
    $q = new WP_Query([
      'post_type' => self::CPT_RECORD,
      'post_status' => 'publish',
      'posts_per_page' => 500,
      'orderby' => 'date',
      'order' => 'DESC',
      'tax_query' => [[
        'taxonomy' => self::TAX_RECORD_TYPE,
        'field' => 'slug',
        'terms' => ['r04_tool_calibration'],
      ]]
    ]);
    if (!$q->have_posts()) return [];
    return $q->posts;
  }

  private static function render_tool_form($tool_id = 0) {
    $is_edit = $tool_id > 0;
    $p = $is_edit ? get_post($tool_id) : null;
    if ($is_edit && (!$p || $p->post_type !== self::CPT_RECORD)) {
      echo '<div class="be-qms-muted">Tool record not found.</div>';
      return;
    }

    $item = $is_edit ? (get_post_meta($tool_id, self::META_TOOL_ITEM, true) ?: $p->post_title) : '';
    $serial = $is_edit ? get_post_meta($tool_id, self::META_TOOL_SERIAL, true) : '';
    $req = $is_edit ? get_post_meta($tool_id, self::META_TOOL_REQ, true) : '';
    $date_p = $is_edit ? get_post_meta($tool_id, self::META_TOOL_DATE_PUR, true) : '';
    $date_c = $is_edit ? get_post_meta($tool_id, self::META_TOOL_DATE_CAL, true) : '';
    $date_n = $is_edit ? get_post_meta($tool_id, self::META_TOOL_DATE_NEXT, true) : '';
    $notes = $is_edit ? get_post_meta($tool_id, self::META_TOOL_NOTES, true) : '';

    $att_ids = $is_edit ? get_post_meta($tool_id, self::META_ATTACHMENTS, true) : [];
    if (!is_array($att_ids)) $att_ids = [];

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div>'
      .'<h3 style="margin:0">R04 - Tool Calibration</h3>'
      .'<div class="be-qms-muted">'.($is_edit ? 'Edit tool record' : 'Add new tool record').'</div>'
      .'</div>';
    echo '</div>';

    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data" style="margin-top:12px">';
    echo '<input type="hidden" name="action" value="be_qms_save_tool" />';
    if ($is_edit) echo '<input type="hidden" name="tool_id" value="'.esc_attr($tool_id).'" />';
    wp_nonce_field('be_qms_save_tool');

    echo '<div class="be-qms-grid">';

    echo '<div class="be-qms-col-6"><label><strong>Item of Equipment</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="tool_item" value="'.esc_attr($item).'" required /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Serial Number</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="tool_serial" value="'.esc_attr($serial).'" /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Calibration / Checking Requirements</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="tool_req" value="'.esc_attr($req).'" placeholder="e.g. Annual calibration" /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Description / Notes</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="tool_notes" value="'.esc_attr($notes).'" /></label></div>';

    echo '<div class="be-qms-col-4"><label><strong>Date Purchased</strong><br/>';
    echo '<input class="be-qms-input" type="date" name="date_purchased" value="'.esc_attr($date_p).'" /></label></div>';

    echo '<div class="be-qms-col-4"><label><strong>Date Calibrated</strong><br/>';
    echo '<input class="be-qms-input" type="date" name="date_calibrated" value="'.esc_attr($date_c).'" /></label></div>';

    echo '<div class="be-qms-col-4"><label><strong>Next Calibration Date</strong><br/>';
    echo '<input class="be-qms-input" type="date" name="date_next" value="'.esc_attr($date_n).'" /></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Upload evidence</strong> <span class="be-qms-muted">(optional)</span><br/>';
    echo '<input type="file" name="attachments[]" multiple /></label></div>';

    if ($is_edit) {
      echo '<div class="be-qms-col-12"><strong>Existing attachments</strong><br/>';
      if (!$att_ids) {
        echo '<div class="be-qms-muted">None.</div>';
      } else {
        echo '<div class="be-qms-muted">Tick to remove on save:</div>';
        echo '<ul style="margin:8px 0 0 18px">';
        foreach ($att_ids as $aid) {
          $url = wp_get_attachment_url($aid);
          $name = get_the_title($aid);
          if (!$url) continue;
          echo '<li><label style="display:flex;gap:10px;align-items:center">'
            .'<input type="checkbox" name="remove_attachments[]" value="'.esc_attr($aid).'" />'
            .'<a class="be-qms-link" target="_blank" href="'.esc_url($url).'">'.esc_html($name ?: basename($url)).'</a>'
            .'</label></li>';
        }
        echo '</ul>';
      }
      echo '</div>';
    }

    echo '</div>'; // grid

    echo '<div class="be-qms-row" style="margin-top:12px">';
    echo '<button class="be-qms-btn" type="submit">Save & Close</button>';
    $back = esc_url(add_query_arg(['view'=>'records','type'=>'r04_tool_calibration'], self::portal_url()));
    echo '<a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">Cancel</a>';
    echo '</div>';

    echo '</form>';
  }

  private static function render_tool_view($tool_id) {
    $p = get_post($tool_id);
    if (!$p || $p->post_type !== self::CPT_RECORD) {
      echo '<div class="be-qms-muted">Tool record not found.</div>';
      return;
    }

    $item = get_post_meta($tool_id, self::META_TOOL_ITEM, true) ?: $p->post_title;
    $serial = get_post_meta($tool_id, self::META_TOOL_SERIAL, true);
    $req = get_post_meta($tool_id, self::META_TOOL_REQ, true);
    $date_p = get_post_meta($tool_id, self::META_TOOL_DATE_PUR, true);
    $date_c = get_post_meta($tool_id, self::META_TOOL_DATE_CAL, true);
    $date_n = get_post_meta($tool_id, self::META_TOOL_DATE_NEXT, true);
    $notes = get_post_meta($tool_id, self::META_TOOL_NOTES, true);

    $att_ids = get_post_meta($tool_id, self::META_ATTACHMENTS, true);
    if (!is_array($att_ids)) $att_ids = [];

    $edit_url = esc_url(add_query_arg(['view'=>'records','type'=>'r04_tool_calibration','be_action'=>'tool_edit','tool_id'=>$tool_id], self::portal_url()));

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div>'
      .'<h3 style="margin:0">R04 - Tool Calibration</h3>'
      .'<div class="be-qms-muted">'.esc_html($item).'</div>'
      .'</div>';
    echo '<div class="be-qms-row">'
      .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$edit_url.'">Edit</a>'
      .'</div>';
    echo '</div>';

    echo '<div class="be-qms-card" style="margin-top:14px">';
    echo '<table class="be-qms-table">';
    echo '<tr><th>Item</th><td>'.esc_html($item).'</td></tr>';
    echo '<tr><th>Serial</th><td>'.esc_html($serial ?: '-').'</td></tr>';
    echo '<tr><th>Requirements</th><td>'.esc_html($req ?: '-').'</td></tr>';
    echo '<tr><th>Date Purchased</th><td>'.esc_html($date_p ?: '-').'</td></tr>';
    echo '<tr><th>Date Calibrated</th><td>'.esc_html($date_c ?: '-').'</td></tr>';
    echo '<tr><th>Next Due</th><td>'.esc_html($date_n ?: '-').'</td></tr>';
    echo '<tr><th>Notes</th><td>'.esc_html($notes ?: '-').'</td></tr>';
    echo '</table>';

    echo '<h4 style="margin-top:14px">Attachments</h4>';
    if (!$att_ids) {
      echo '<div class="be-qms-muted">None.</div>';
    } else {
      echo '<ul>';
      foreach ($att_ids as $aid) {
        $url = wp_get_attachment_url($aid);
        $name = get_the_title($aid);
        if ($url) echo '<li><a class="be-qms-link" target="_blank" href="'.esc_url($url).'">'.esc_html($name ?: basename($url)).'</a></li>';
      }
      echo '</ul>';
    }
    echo '</div>';

    $back = esc_url(add_query_arg(['view'=>'records','type'=>'r04_tool_calibration'], self::portal_url()));
    echo '<div class="be-qms-row" style="margin-top:12px"><a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">← Back to records</a></div>';
  }

  public static function handle_save_tool() {
    self::require_staff();
    check_admin_referer('be_qms_save_tool');

    $id = isset($_POST['tool_id']) ? (int)$_POST['tool_id'] : 0;

    $item = isset($_POST['tool_item']) ? sanitize_text_field($_POST['tool_item']) : '';
    if (!$item) wp_die('Missing tool item.');

    $serial = sanitize_text_field($_POST['tool_serial'] ?? '');
    $req = sanitize_text_field($_POST['tool_req'] ?? '');
    $notes = sanitize_text_field($_POST['tool_notes'] ?? '');

    $date_p = sanitize_text_field($_POST['date_purchased'] ?? '');
    $date_c = sanitize_text_field($_POST['date_calibrated'] ?? '');
    $date_n = sanitize_text_field($_POST['date_next'] ?? '');

    if ($id > 0) {
      $p = get_post($id);
      if (!$p || $p->post_type !== self::CPT_RECORD) wp_die('Tool record not found.');
      wp_update_post(['ID'=>$id,'post_title'=>$item]);
      $rid = $id;
    } else {
      $rid = wp_insert_post([
        'post_type' => self::CPT_RECORD,
        'post_status' => 'publish',
        'post_title' => $item,
      ], true);
      if (is_wp_error($rid)) wp_die('Failed to save tool record.');
    }

    wp_set_object_terms($rid, ['r04_tool_calibration'], self::TAX_RECORD_TYPE, false);

    update_post_meta($rid, self::META_TOOL_ITEM, $item);
    update_post_meta($rid, self::META_TOOL_SERIAL, $serial);
    update_post_meta($rid, self::META_TOOL_REQ, $req);
    update_post_meta($rid, self::META_TOOL_DATE_PUR, $date_p);
    update_post_meta($rid, self::META_TOOL_DATE_CAL, $date_c);
    update_post_meta($rid, self::META_TOOL_DATE_NEXT, $date_n);
    update_post_meta($rid, self::META_TOOL_NOTES, $notes);

    // Use next due (or calibrated date) as record date for sorting
    $sort_date = $date_n ?: ($date_c ?: date('Y-m-d'));
    update_post_meta($rid, self::META_RECORD_DATE, $sort_date);

    // Attachments merge + remove
    $existing = get_post_meta($rid, self::META_ATTACHMENTS, true);
    if (!is_array($existing)) $existing = [];

    $to_remove = isset($_POST['remove_attachments']) && is_array($_POST['remove_attachments']) ? array_map('intval', $_POST['remove_attachments']) : [];
    if ($to_remove) {
      $existing = array_values(array_diff($existing, $to_remove));
    }

    $new = self::handle_uploads('attachments');
    $merged = array_values(array_unique(array_merge($existing, $new ?: [])));
    update_post_meta($rid, self::META_ATTACHMENTS, $merged);

    $url = add_query_arg(['view'=>'records','type'=>'r04_tool_calibration'], self::portal_url());
    wp_safe_redirect($url);
    exit;
  }

  // ------------------ Delete ------------------

  public static function handle_delete() {
    self::require_staff();

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $kind = isset($_GET['kind']) ? sanitize_key($_GET['kind']) : '';
    if (!$id) wp_die('Missing ID.');

    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'be_qms_delete_'.$id)) {
      wp_die('Invalid nonce.');
    }

    $p = get_post($id);
    if (!$p) wp_die('Not found.');

    if ($kind === 'record' && $p->post_type !== self::CPT_RECORD) wp_die('Wrong type.');
    if ($kind === 'project' && $p->post_type !== self::CPT_PROJECT) wp_die('Wrong type.');
    if ($kind === 'employee' && $p->post_type !== self::CPT_EMPLOYEE) wp_die('Wrong type.');

    wp_delete_post($id, true);

    $back_view = 'records';
    $args = ['view'=>'records'];

    if ($kind === 'project') {
      $back_view = 'projects';
      $args = ['view'=>'projects'];
    }

    if ($kind === 'employee') {
      $args = ['view'=>'records','type'=>'r07_training_matrix'];
    }

    $url = add_query_arg($args, self::portal_url());
    wp_safe_redirect($url);
    exit;
  }

  // ------------------ Exports ------------------

  public static function maybe_handle_exports() {
    // Print logs
    if (!empty($_GET['be_qms_export']) && in_array($_GET['be_qms_export'], ['print_r07','print_r04'], true)) {
      self::require_staff();
      $mode = sanitize_key($_GET['be_qms_export']);
      if ($mode === 'print_r07') {
        self::export_print_r07(isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0);
        exit;
      }
      if ($mode === 'print_r04') {
        self::export_print_r04();
        exit;
      }
    }

    // Existing DOC/print export for individual posts
    if (empty($_GET['be_qms_export']) || empty($_GET['post'])) return;

    self::require_staff();

    $mode = sanitize_key($_GET['be_qms_export']);
    $id = (int)$_GET['post'];
    $p = get_post($id);
    if (!$p) wp_die('Not found');

    if (!in_array($p->post_type, [self::CPT_RECORD, self::CPT_PROJECT], true)) {
      wp_die('Invalid post type');
    }

    if ($mode === 'doc') {
      self::export_doc($p);
      exit;
    }
    if ($mode === 'print') {
      self::export_print($p);
      exit;
    }
  }

  private static function export_print_r07($employee_id = 0) {
    $title = $employee_id ? ('Training Matrix – ' . (get_the_title($employee_id) ?: 'Employee')) : 'Training Matrix – All Employees';
    $skills = $employee_id ? self::query_skills($employee_id) : self::query_skills();

    echo '<!doctype html><html><head><meta charset="utf-8"><title>'.esc_html($title).'</title>';
    echo '<style>body{font-family:Arial,sans-serif;padding:30px;color:#111;} h1{margin:0 0 10px 0;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ddd;padding:8px;font-size:12px;} th{background:#f3f4f6;text-align:left;} .muted{color:#666;font-size:12px;margin-bottom:18px;} @media print{button{display:none}}</style>';
    echo '</head><body>';
    echo '<button onclick="window.print()" style="padding:10px 14px;border-radius:10px;border:1px solid #ddd;background:#f8fafc;cursor:pointer">Print / Save as PDF</button>';
    echo '<h1>'.esc_html($title).'</h1>';
    echo '<div class="muted">Generated by Baltic QMS Portal v'.esc_html(self::VERSION).'</div>';

    echo '<table><thead><tr><th>Employee</th><th>Course</th><th>Course Date</th><th>Renewal</th></tr></thead><tbody>';

    if (!$skills) {
      echo '<tr><td colspan="4">No records.</td></tr>';
    } else {
      foreach ($skills as $s) {
        $sid = (int)$s->ID;
        $eid = (int)get_post_meta($sid, self::META_EMPLOYEE_ID, true);
        $emp = $eid ? get_the_title($eid) : '-';
        $course = get_post_meta($sid, self::META_SKILL_COURSE, true) ?: get_the_title($sid);
        $course_date = get_post_meta($sid, self::META_SKILL_DATE, true);
        $renewal = get_post_meta($sid, self::META_SKILL_RENEWAL, true);
        echo '<tr>';
        echo '<td>'.esc_html($emp).'</td>';
        echo '<td>'.esc_html($course).'</td>';
        echo '<td>'.esc_html($course_date).'</td>';
        echo '<td>'.esc_html($renewal).'</td>';
        echo '</tr>';
      }
    }

    echo '</tbody></table>';
    echo '</body></html>';
  }

  private static function export_print_r04() {
    $tools = self::query_tools();

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Tool Calibration Log</title>';
    echo '<style>body{font-family:Arial,sans-serif;padding:30px;color:#111;} h1{margin:0 0 10px 0;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ddd;padding:8px;font-size:12px;} th{background:#f3f4f6;text-align:left;} .muted{color:#666;font-size:12px;margin-bottom:18px;} @media print{button{display:none}}</style>';
    echo '</head><body>';
    echo '<button onclick="window.print()" style="padding:10px 14px;border-radius:10px;border:1px solid #ddd;background:#f8fafc;cursor:pointer">Print / Save as PDF</button>';
    echo '<h1>Tool Calibration Log</h1>';
    echo '<div class="muted">Generated by Baltic QMS Portal v'.esc_html(self::VERSION).'</div>';

    echo '<table><thead><tr><th>Item</th><th>Serial</th><th>Requirements</th><th>Date Purchased</th><th>Date Calibrated</th><th>Next Due</th></tr></thead><tbody>';

    if (!$tools) {
      echo '<tr><td colspan="6">No tools.</td></tr>';
    } else {
      foreach ($tools as $t) {
        $rid = (int)$t->ID;
        $item = get_post_meta($rid, self::META_TOOL_ITEM, true) ?: get_the_title($rid);
        $serial = get_post_meta($rid, self::META_TOOL_SERIAL, true);
        $req = get_post_meta($rid, self::META_TOOL_REQ, true);
        $dp = get_post_meta($rid, self::META_TOOL_DATE_PUR, true);
        $dc = get_post_meta($rid, self::META_TOOL_DATE_CAL, true);
        $dn = get_post_meta($rid, self::META_TOOL_DATE_NEXT, true);
        echo '<tr>';
        echo '<td>'.esc_html($item).'</td>';
        echo '<td>'.esc_html($serial).'</td>';
        echo '<td>'.esc_html($req).'</td>';
        echo '<td>'.esc_html($dp).'</td>';
        echo '<td>'.esc_html($dc).'</td>';
        echo '<td>'.esc_html($dn).'</td>';
        echo '</tr>';
      }
    }

    echo '</tbody></table>';
    echo '</body></html>';
  }

  private static function export_doc($p) {
    $filename = sanitize_file_name($p->post_title) . '.doc';

    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    echo '<html><head><meta charset="utf-8"><title>'.esc_html($p->post_title).'</title>';
    echo '<style>body{font-family:Arial, sans-serif;font-size:11pt;} h1{font-size:18pt;} h2{font-size:13pt;margin-top:18px;} .muted{color:#666;} table{width:100%;border-collapse:collapse;} td,th{border:1px solid #ddd;padding:8px;vertical-align:top;}</style>';
    echo '</head><body>';
    echo '<h1>'.esc_html($p->post_title).'</h1>';

    if ($p->post_type === self::CPT_RECORD) {
      $terms = get_the_terms($p->ID, self::TAX_RECORD_TYPE);
      $type_name = $terms && !is_wp_error($terms) ? $terms[0]->name : '-';
      $date = get_post_meta($p->ID, self::META_RECORD_DATE, true) ?: get_the_date('Y-m-d', $p);
      $details = get_post_meta($p->ID, self::META_DETAILS, true);
      $actions = get_post_meta($p->ID, self::META_ACTIONS, true);

      echo '<div class="muted">Type: '.esc_html($type_name).' • Date: '.esc_html($date).'</div>';
      echo '<h2>Details</h2><div>'.wpautop(esc_html($details)).'</div>';
      if (!empty($actions)) {
        echo '<h2>Actions</h2><div>'.wpautop(esc_html($actions)).'</div>';
      }
    } else {
      $customer = get_post_meta($p->ID, '_be_qms_customer', true);
      $address  = get_post_meta($p->ID, '_be_qms_address', true);
      $pv_kwp   = get_post_meta($p->ID, '_be_qms_pv_kwp', true);
      $bess_kwh = get_post_meta($p->ID, '_be_qms_bess_kwh', true);
      $notes    = get_post_meta($p->ID, '_be_qms_notes', true);

      echo '<table>';
      echo '<tr><th>Customer</th><td>'.esc_html($customer).'</td></tr>';
      echo '<tr><th>Address</th><td>'.esc_html($address).'</td></tr>';
      echo '<tr><th>PV (kWp)</th><td>'.esc_html($pv_kwp).'</td></tr>';
      echo '<tr><th>Battery (kWh)</th><td>'.esc_html($bess_kwh).'</td></tr>';
      echo '</table>';
      if (!empty($notes)) {
        echo '<h2>Notes</h2><div>'.wpautop(esc_html($notes)).'</div>';
      }
    }

    echo '<div class="muted" style="margin-top:24px">Generated by Baltic QMS Portal v'.esc_html(self::VERSION).'</div>';
    echo '</body></html>';
  }

  private static function export_print($p) {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>'.esc_html($p->post_title).'</title>';
    echo '<style>body{font-family:Arial,sans-serif;padding:30px;color:#111;} h1{margin:0 0 8px 0;} .muted{color:#666;font-size:12px;} h2{margin-top:18px;} @media print{button{display:none}}</style>';
    echo '</head><body>';
    echo '<button onclick="window.print()" style="padding:10px 14px;border-radius:10px;border:1px solid #ddd;background:#f8fafc;cursor:pointer">Print / Save as PDF</button>';
    echo '<h1>'.esc_html($p->post_title).'</h1>';

    if ($p->post_type === self::CPT_RECORD) {
      $terms = get_the_terms($p->ID, self::TAX_RECORD_TYPE);
      $type_name = $terms && !is_wp_error($terms) ? $terms[0]->name : '-';
      $date = get_post_meta($p->ID, self::META_RECORD_DATE, true) ?: get_the_date('Y-m-d', $p);
      $details = get_post_meta($p->ID, self::META_DETAILS, true);
      $actions = get_post_meta($p->ID, self::META_ACTIONS, true);

      echo '<div class="muted">Type: '.esc_html($type_name).' • Date: '.esc_html($date).'</div>';
      echo '<h2>Details</h2><div>'.wpautop(esc_html($details)).'</div>';
      if (!empty($actions)) {
        echo '<h2>Actions</h2><div>'.wpautop(esc_html($actions)).'</div>';
      }
    } else {
      $customer = get_post_meta($p->ID, '_be_qms_customer', true);
      $address  = get_post_meta($p->ID, '_be_qms_address', true);
      $pv_kwp   = get_post_meta($p->ID, '_be_qms_pv_kwp', true);
      $bess_kwh = get_post_meta($p->ID, '_be_qms_bess_kwh', true);
      $notes    = get_post_meta($p->ID, '_be_qms_notes', true);

      echo '<div class="muted">Project evidence</div>';
      echo '<h2>Summary</h2>';
      echo '<ul>';
      echo '<li><strong>Customer:</strong> '.esc_html($customer).'</li>';
      echo '<li><strong>Address:</strong> '.esc_html($address).'</li>';
      echo '<li><strong>PV (kWp):</strong> '.esc_html($pv_kwp).'</li>';
      echo '<li><strong>Battery (kWh):</strong> '.esc_html($bess_kwh).'</li>';
      echo '</ul>';
      if (!empty($notes)) {
        echo '<h2>Notes</h2><div>'.wpautop(esc_html($notes)).'</div>';
      }

      $att_ids  = get_post_meta($p->ID, '_be_qms_evidence', true);
      if (!is_array($att_ids)) $att_ids = [];
      echo '<h2>Evidence list</h2>';
      if (!$att_ids) {
        echo '<div class="muted">No evidence files uploaded.</div>';
      } else {
        echo '<ol>';
        foreach ($att_ids as $aid) {
          $url = wp_get_attachment_url($aid);
          $name = get_the_title($aid);
          echo '<li>'.esc_html($name ?: basename((string)$url)).'</li>';
        }
        echo '</ol>';
      }
    }

    echo '<div class="muted" style="margin-top:24px">Generated by Baltic QMS Portal v'.esc_html(self::VERSION).'</div>';
    echo '</body></html>';
  }

  // ------------------ Upload helper ------------------

  private static function handle_uploads($field) {
    if (empty($_FILES[$field]) || empty($_FILES[$field]['name'])) return [];

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $files = $_FILES[$field];
    $att_ids = [];

    $count = is_array($files['name']) ? count($files['name']) : 0;
    if ($count < 1) return [];

    for ($i=0; $i<$count; $i++) {
      if (empty($files['name'][$i])) continue;

      $file_array = [
        'name' => $files['name'][$i],
        'type' => $files['type'][$i],
        'tmp_name' => $files['tmp_name'][$i],
        'error' => $files['error'][$i],
        'size' => $files['size'][$i],
      ];

      $tmp = $_FILES;
      $_FILES = [$field => $file_array];
      $att_id = media_handle_upload($field, 0);
      $_FILES = $tmp;

      if (!is_wp_error($att_id)) {
        $att_ids[] = (int)$att_id;
      }
    }

    return $att_ids;
  }
}

register_activation_hook(__FILE__, ['BE_QMS_Portal', 'on_activate']);
add_action('plugins_loaded', ['BE_QMS_Portal', 'init']);
