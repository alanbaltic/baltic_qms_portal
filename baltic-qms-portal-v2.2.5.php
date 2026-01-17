<?php
/**
 * Plugin Name: Baltic QMS Portal
 * Description: Staff-only QMS portal for recording MCS evidence (projects + records) with DOC export and print-to-PDF.
 * Version: 2.2.5
 * Author: Baltic Electric
 */

if (!defined('ABSPATH')) { exit; }

class BE_QMS_Portal {
  const VERSION = '2.2.5';
  const CPT_RECORD = 'be_qms_record';
  const CPT_PROJECT = 'be_qms_install'; // keep CPT key for backwards compatibility
  const TAX_RECORD_TYPE = 'be_qms_record_type';
  const META_PROJECT_LINK = '_be_qms_install_id';

  const CPT_EMPLOYEE = 'be_qms_employee';
  const CPT_TRAINING = 'be_qms_training';
  const META_EMPLOYEE_LINK = '_be_qms_employee_id';

  public static function init() {
    add_action('init', [__CLASS__, 'register_types']);
    add_action('init', [__CLASS__, 'maybe_handle_exports']);

    add_shortcode('be_qms_portal', [__CLASS__, 'shortcode_portal']);
    // Backwards compatible shortcodes
    add_shortcode('bqms_dashboard', [__CLASS__, 'shortcode_portal']);
    add_shortcode('bqms_portal', [__CLASS__, 'shortcode_portal']);

    add_action('admin_post_be_qms_save_record', [__CLASS__, 'handle_save_record']);
    add_action('admin_post_be_qms_save_project', [__CLASS__, 'handle_save_project']);
    add_action('admin_post_be_qms_save_employee', [__CLASS__, 'handle_save_employee']);
    add_action('admin_post_be_qms_save_training', [__CLASS__, 'handle_save_training']);
    add_action('admin_post_be_qms_save_profile', [__CLASS__, 'handle_save_profile']);
    add_action('admin_post_be_qms_delete', [__CLASS__, 'handle_delete']);

    // Light, scoped styles + datepicker
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

  private static function is_portal_page() {
    if (!is_singular()) return false;
    global $post;
    if (!$post) return false;
    $content = (string) $post->post_content;
    return (strpos($content, '[be_qms_portal') !== false) || (strpos($content, '[bqms_portal') !== false) || (strpos($content, '[bqms_dashboard') !== false);
  }

  public static function enqueue_assets() {
    if (!self::is_portal_page()) return;

    $css = <<<'CSS'
/* Baltic QMS Portal — Scoped to .be-qms-wrap */
.be-qms-wrap{
  --qms-bg:#020617;              /* slate-950 */
  --qms-panel:rgba(15,23,42,.60);/* slate-900/60 */
  --qms-panel2:rgba(2,6,23,.55);
  --qms-border:#1e293b;          /* slate-800 */
  --qms-border2:#334155;         /* slate-700 */
  --qms-text:#e2e8f0;            /* slate-200 */
  --qms-muted:#94a3b8;           /* slate-400 */
  --qms-emerald:#34d399;         /* emerald-400 */
  --qms-emerald2:#6ee7b7;        /* emerald-300 */
  --qms-amber:#fbbf24;           /* amber-400 */
  --qms-danger:#f87171;          /* red-400 */

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

.be-qms-title{ font-size:26px; font-weight:800; letter-spacing:-.02em; margin:0 0 10px 0; color:#f8fafc; }
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

.be-qms-btn-secondary{ background:rgba(2,6,23,.35); border-color:var(--qms-border); color:var(--qms-text) !important; box-shadow:none; }
.be-qms-btn-secondary:hover{ background:rgba(15,23,42,.65); border-color:var(--qms-border2); }

.be-qms-btn-danger{ background:rgba(248,113,113,.14); border-color:rgba(248,113,113,.45); color:#fecaca !important; box-shadow:none; }
.be-qms-btn-danger:hover{ background:rgba(248,113,113,.20); border-color:rgba(248,113,113,.65); }

.be-qms-btn:focus-visible, .be-qms-tab:focus-visible{ outline:none; box-shadow:0 0 0 4px rgba(251,191,36,.18); }

.be-qms-link{ color:var(--qms-emerald2); text-decoration:none; font-weight:700; }
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
.be-qms-col-12{ grid-column:span 12; }
@media(max-width:900px){ .be-qms-col-6{ grid-column:span 12; } }

/* Records layout */
.be-qms-split{ display:grid; grid-template-columns:260px 1fr; gap:14px; }
@media(max-width:900px){ .be-qms-split{ grid-template-columns:1fr; } }

.be-qms-side{
  background:rgba(2,6,23,.35);
  border:1px solid var(--qms-border);
  border-radius:16px;
  padding:10px;
}
.be-qms-side a{
  display:block;
  padding:10px 10px;
  border-radius:12px;
  text-decoration:none;
  color:var(--qms-text);
  border:1px solid transparent;
  font-weight:700;
}
.be-qms-side a:hover{ background:rgba(15,23,42,.55); border-color:var(--qms-border2); }
.be-qms-side a.is-active{ background:rgba(16,185,129,.14); border-color:rgba(52,211,153,.55); }

/* jQuery UI datepicker (minimal, readable on dark background) */
.ui-datepicker{ font-family:inherit; font-size:13px; background:#0b1220; border:1px solid #334155; color:#e2e8f0; padding:10px; border-radius:12px; }
.ui-datepicker a{ color:#e2e8f0; }
.ui-datepicker .ui-datepicker-header{ background:#0f172a; border:1px solid #334155; border-radius:10px; padding:6px; }
.ui-datepicker .ui-state-default{ background:#111827; border:1px solid #334155; color:#e2e8f0; border-radius:8px; text-align:center; }
.ui-datepicker .ui-state-hover{ background:#0f172a; border-color:#475569; }
.ui-datepicker .ui-state-active{ background:#10b981; border-color:#10b981; color:#020617; }
CSS;

    wp_register_style('be-qms-inline', false);
    wp_enqueue_style('be-qms-inline');
    wp_add_inline_style('be-qms-inline', $css);

    // Scripts (datepicker)
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-datepicker');
    $js = <<<'JS'
jQuery(function($){
  $('.be-qms-date').each(function(){
    try{ $(this).datepicker({ dateFormat: 'yy-mm-dd' }); }catch(e){}
  });
});
JS;
    wp_add_inline_script('jquery-ui-datepicker', $js);
  }

  private static function get_record_type_slug($record_id) {
    $terms = get_the_terms($record_id, self::TAX_RECORD_TYPE);
    if ($terms && !is_wp_error($terms)) {
      return $terms[0]->slug;
    }
    return '';
  }

  private static function record_type_definitions() {
    // Exact order requested (R01 - R11, skipping R10 as per screenshot)
    return [
      'r01_contracts'            => 'R01 Contracts Folder',
      'r02_capa'                 => 'R02 Corrective & Preventative Action Record',
      'r03_purchase_order'       => 'R03 Purchase Order',
      'r04_tool_calibration'     => 'R04 Tool Calibration',
      'r05_internal_review'      => 'R05 Internal Review Record',
      'r06_customer_complaints'  => 'R06 Customer Complaints',
      'r07_training_matrix'      => 'R07 Personal Skills & Training Matrix',
      'r08_approved_suppliers'   => 'R08 Approved Suppliers',
      'r09_approved_subcontract' => 'R09 Approved Subcontractors',
      'r11_company_documents'    => 'R11 Company Documents',
    ];
  }

  public static function register_types() {
    // Records
    register_post_type(self::CPT_RECORD, [
      'label' => 'QMS Records',
      'public' => false,
      'show_ui' => true,
      'menu_icon' => 'dashicons-clipboard',
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

    // Projects (kept as CPT key be_qms_install for backwards compat)
    register_post_type(self::CPT_PROJECT, [
      'labels' => [
        'name' => 'QMS Projects',
        'singular_name' => 'QMS Project',
        'add_new' => 'Add New Project',
        'add_new_item' => 'Add New Project',
        'edit_item' => 'Edit Project',
        'new_item' => 'New Project',
        'view_item' => 'View Project',
        'search_items' => 'Search Projects',
      ],
      'public' => false,
      'show_ui' => true,
      'menu_icon' => 'dashicons-portfolio',
      'supports' => ['title', 'author'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
    ]);

    // Employees (used for R07 Training Matrix)
    register_post_type(self::CPT_EMPLOYEE, [
      'labels' => [
        'name' => 'QMS Employees',
        'singular_name' => 'QMS Employee',
        'add_new' => 'Add Employee',
        'add_new_item' => 'Add New Employee',
        'edit_item' => 'Edit Employee',
        'new_item' => 'New Employee',
        'view_item' => 'View Employee',
        'search_items' => 'Search Employees',
      ],
      'public' => false,
      'show_ui' => true,
      'menu_icon' => 'dashicons-groups',
      'supports' => ['title', 'author'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
    ]);

    // Training records (child entries linked to an employee)
    register_post_type(self::CPT_TRAINING, [
      'labels' => [
        'name' => 'QMS Training Records',
        'singular_name' => 'QMS Training Record',
      ],
      'public' => false,
      'show_ui' => false,
      'supports' => ['title', 'author'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
    ]);

    // Ensure record-type terms exist
    foreach (self::record_type_definitions() as $slug => $name) {
      if (!term_exists($slug, self::TAX_RECORD_TYPE)) {
        wp_insert_term($name, self::TAX_RECORD_TYPE, ['slug' => $slug]);
      } else {
        $term = get_term_by('slug', $slug, self::TAX_RECORD_TYPE);
        if ($term && !is_wp_error($term) && $term->name !== $name) {
          wp_update_term((int)$term->term_id, self::TAX_RECORD_TYPE, ['name' => $name]);
        }
      }
    }

    // Soft migration: map old slugs to new slugs if old ones exist (keeps assignments)
    $legacy = [
      'internal_review'  => 'r05_internal_review',
      'contract_review'  => 'r01_contracts',
      'goods_in'         => 'r03_purchase_order',
      'tools'            => 'r04_tool_calibration',
      'training'         => 'r07_training_matrix',
      'complaints'       => 'r06_customer_complaints',
      'capa'             => 'r02_capa',
      'install_evidence' => 'r11_company_documents',
    ];
    foreach ($legacy as $old => $new) {
      $old_term = get_term_by('slug', $old, self::TAX_RECORD_TYPE);
      if ($old_term && !is_wp_error($old_term)) {
        $new_term = get_term_by('slug', $new, self::TAX_RECORD_TYPE);
        if (!$new_term || is_wp_error($new_term)) {
          $defs = self::record_type_definitions();
          wp_update_term((int)$old_term->term_id, self::TAX_RECORD_TYPE, [
            'slug' => $new,
            'name' => $defs[$new] ?? $old_term->name,
          ]);
        }
      }
    }
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

  private static function get_portal_action() {
    if (isset($_GET['be_action'])) return sanitize_key($_GET['be_action']);
    if (isset($_GET['action'])) return sanitize_key($_GET['action']);
    return '';
  }

  private static function normalize_view($view) {
    $view = sanitize_key((string)$view);
    // Backwards compatible alias
    if ($view === 'installations') return 'projects';
    return $view;
  }

  public static function shortcode_portal($atts = []) {
    self::require_staff();

    $view = self::normalize_view($_GET['view'] ?? 'records');

    // Tab order to match your screenshot
    $views = [
      'projects'    => 'Projects',
      'records'     => 'Records',
      'templates'   => 'Templates',
      'references'  => 'References',
      'assessment'  => 'How to pass assessment',
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

    if ($view === 'records') {
      self::render_records();
    } elseif ($view === 'projects') {
      self::render_projects();
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

  private static function render_templates() {
    echo '<div class="be-qms-card-inner">';
    echo '<h3>Templates (what you export / print)</h3>';
    echo '<p class="be-qms-muted">Create records in the portal, then export DOC or print to PDF for your assessor or your own filing.</p>';
    echo '<ul>';
    echo '<li><strong>R05 Internal Review Record</strong> – minutes + actions.</li>';
    echo '<li><strong>R01 Contracts Folder</strong> – contract review / scope / customer acceptance / variations.</li>';
    echo '<li><strong>R03 Purchase Order</strong> – goods-in checks / materials traceability where needed.</li>';
    echo '<li><strong>R04 Tool Calibration</strong> – tester/tool register + due dates.</li>';
    echo '<li><strong>R07 Personal Skills & Training Matrix</strong> – staff training, renewals, certs.</li>';
    echo '<li><strong>R06 Customer Complaints</strong> – log + outcome + any CAPA.</li>';
    echo '<li><strong>R02 CAPA</strong> – corrective/preventative actions (can be linked to projects).</li>';
    echo '</ul>';
    echo '</div>';
  }

  private static function render_references() {
    $manual_docx = esc_url(add_query_arg(['be_qms_ref'=>'manual_docx'], self::portal_url()));
    $manual_pdf  = esc_url(add_query_arg(['be_qms_ref'=>'manual_pdf'], self::portal_url()));

    $profile = get_option('be_qms_profile', []);
    if (!is_array($profile)) $profile = [];
    $defaults = [
      'company_name' => 'Baltic Electric Ltd',
      'responsible_person' => 'Alan Baltic',
      'address' => 'London, UK',
      'phone' => '',
      'email' => '',
      'company_reg' => '',
      'mcs_reg' => '',
      'consumer_code' => '',
    ];
    $profile = array_merge($defaults, $profile);

    echo '<div class="be-qms-card-inner">';
    echo '<h3>References & QMS framework</h3>';
    echo '<p class="be-qms-muted">Keep your profile up to date, then store evidence as Records (R01-R11) and link items to Projects where relevant.</p>';

    echo '<div class="be-qms-grid">';

    echo '<div class="be-qms-col-6">';
    echo '<h4 style="margin-top:0">Documented Procedure Manual</h4>';
    echo '<p class="be-qms-muted">Based on the template you uploaded, with Appendix A & C prefilled for Baltic Electric (placeholders where details are unknown).</p>';
    echo '<div class="be-qms-row">'
      .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$manual_docx.'">Download DOCX</a>'
      .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$manual_pdf.'" target="_blank">Open PDF</a>'
      .'</div>';
    echo '</div>';

    echo '<div class="be-qms-col-6">';
    echo '<h4 style="margin-top:0">Standards (official sources)</h4>';
    echo '<ul>';
    echo '<li><a class="be-qms-link" target="_blank" href="https://mcscertified.com/wp-content/uploads/2024/11/MCS-001-1-Issue-4.2_Final.pdf">MCS-001 (Issue 4.2)</a></li>';
    echo '<li><a class="be-qms-link" target="_blank" href="https://mcscertified.com/">MIS 3002 (Solar PV) – download from MCS Installer Resources</a></li>';
    echo '<li><a class="be-qms-link" target="_blank" href="https://mcscertified.com/">MIS 3012 (Battery) – download from MCS Installer Resources</a></li>';
    echo '</ul>';
    echo '</div>';

    echo '</div>';

    $save_url = esc_url(admin_url('admin-post.php'));
    echo '<hr style="margin:18px 0;border:0;border-top:1px solid rgba(30,41,59,.75)">';
    echo '<h4>Company profile (used for consistency)</h4>';
    echo '<form method="post" action="'.$save_url.'" class="be-qms-grid">';
    echo '<input type="hidden" name="action" value="be_qms_save_profile">';
    echo '<input type="hidden" name="_wpnonce" value="'.esc_attr(wp_create_nonce('be_qms_profile')).'">';

    $fields = [
      ['company_name','Company name'],
      ['responsible_person','Responsible person'],
      ['address','Address'],
      ['phone','Phone'],
      ['email','Email'],
      ['company_reg','Company reg no.'],
      ['mcs_reg','MCS reg no.'],
      ['consumer_code','Consumer code (e.g. HIES/RECC)'],
    ];

    foreach ($fields as $f) {
      [$key,$label] = $f;
      $val = $profile[$key] ?? '';
      echo '<div class="be-qms-col-6">';
      echo '<label style="display:block;font-size:12px" class="be-qms-muted">'.esc_html($label).'</label>';
      echo '<input class="be-qms-input" name="'.$key.'" value="'.esc_attr($val).'" />';
      echo '</div>';
    }

    echo '<div class="be-qms-col-12 be-qms-row" style="margin-top:10px">';
    echo '<button class="be-qms-btn" type="submit">Save profile</button>';
    echo '</div>';
    echo '</form>';

    echo '<hr style="margin:18px 0;border:0;border-top:1px solid rgba(30,41,59,.75)">';
    echo '<h4>MCS evidence map (quick)</h4>';
    echo '<table class="be-qms-table">';
    echo '<thead><tr><th>Requirement area</th><th>Where you evidence it in the portal</th></tr></thead><tbody>';
    echo '<tr><td>Internal review</td><td>Records → R05 Internal Review Record</td></tr>';
    echo '<tr><td>Contract review</td><td>Records → R01 Contracts Folder (use a record per job/contract)</td></tr>';
    echo '<tr><td>Goods-in checks</td><td>Records → R03 Purchase Order (link to a Project if it relates to one)</td></tr>';
    echo '<tr><td>Complaints handling</td><td>Records → R06 Customer Complaints (link to Project if applicable)</td></tr>';
    echo '<tr><td>Corrective / preventive action</td><td>Records → R02 CAPA (link to Project if applicable)</td></tr>';
    echo '<tr><td>Training / competence</td><td>Records → R07 Training Matrix</td></tr>';
    echo '<tr><td>Tools / calibration</td><td>Records → R04 Tool Calibration</td></tr>';
    echo '<tr><td>Assessment project file</td><td>Projects → evidence uploads + linked records</td></tr>';
    echo '</tbody></table>';

    echo '</div>';
  }

  private static function render_assessment() {
    echo '<div class="be-qms-card-inner">';
    echo '<h3>How to pass assessment (practical)</h3>';
    echo '<ol>';
    echo '<li>Create at least <strong>1 complete Project</strong> entry and upload evidence (design report, schematic, DNO, building regs, commissioning, photos).</li>';
    echo '<li>Create at least <strong>1 R05 Internal Review</strong> record (minutes + actions).</li>';
    echo '<li>Have <strong>R07 Training</strong> entries for you + any installers/subcontractors you use.</li>';
    echo '<li>Keep <strong>R04 Tools/Calibration</strong> up to date.</li>';
    echo '<li>Have a <strong>R06 Complaints</strong> log (even “No complaints to date” is fine — log it).</li>';
    echo '</ol>';
    echo '<p class="be-qms-muted">Tip: export key records to DOC or Print to PDF for your assessor pack.</p>';
    echo '</div>';
  }

  /* -------------------------------
   * Records
   * ------------------------------*/

  private static function render_records() {
    $action = self::get_portal_action();

    $defs = self::record_type_definitions();
    $type = isset($_GET['type']) ? sanitize_key($_GET['type']) : '';
    if (!$type || empty($defs[$type])) {
      $type = array_key_first($defs);
    }

    // Special record types with custom flows
    $is_r07 = ($type === 'r07_training_matrix');
    $is_r04 = ($type === 'r04_tool_calibration');
    $is_r06 = ($type === 'r06_customer_complaints');

    // --- Action pages (no sidebar) ---
    if ($is_r07 && in_array($action, ['new_employee','edit_employee','employee','add_skill','edit_skill'], true)) {
      self::render_r07_training_matrix($action);
      return;
    }

    if ($is_r04) {
      if ($action === 'new') {
        self::render_r04_tool_form(0);
        return;
      }
      if ($action === 'edit' && !empty($_GET['id'])) {
        self::render_r04_tool_form((int) $_GET['id']);
        return;
      }
      if ($action === 'view' && !empty($_GET['id'])) {
        self::render_r04_tool_view((int) $_GET['id']);
        return;
      }
    }

    if ($action === 'new') {
      if ($is_r06) {
        self::render_r06_customer_complaints_form(0);
        return;
      }
      self::render_record_form(0);
      return;
    }
    if ($action === 'edit' && !empty($_GET['id'])) {
      if ($is_r06) {
        self::render_r06_customer_complaints_form((int) $_GET['id']);
        return;
      }
      self::render_record_form((int) $_GET['id']);
      return;
    }
    if ($action === 'view' && !empty($_GET['id'])) {
      if ($is_r06) {
        self::render_r06_customer_complaints_view((int) $_GET['id']);
        return;
      }
      self::render_record_view((int) $_GET['id']);
      return;
    }

    // --- List page (with sidebar) ---
    echo '<div class="be-qms-split">';

    // Sidebar
    echo '<div class="be-qms-side">';
    echo '<div class="be-qms-side-title">Record types</div>';
    echo '<div class="be-qms-side-list">';
    foreach ($defs as $slug => $label) {
      $cls = 'be-qms-side-item' . ($slug === $type ? ' is-active' : '');
      $url = esc_url(add_query_arg(['view'=>'records','type'=>$slug], self::portal_url()));
      echo '<a class="' . esc_attr($cls) . '" href="' . $url . '">' . esc_html($label) . '</a>';
    }
    echo '</div>';
    echo '</div>';

    // Main
    echo '<div>';

    if ($is_r07) {
      // Custom list view for R07
      self::render_r07_training_matrix($action);
      echo '</div></div>';
      return;
    }

    if ($is_r04) {
      self::render_r04_tool_list();
      echo '</div></div>';
      return;
    }

    // Generic records list
    $label = $defs[$type] ?? 'Records';

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div><strong>' . esc_html($label) . '</strong> <span class="be-qms-muted">(records)</span></div>';
    echo '<div class="be-qms-row">';
    $new_url = esc_url(add_query_arg(['view'=>'records','type'=>$type,'be_action'=>'new'], self::portal_url()));
    echo '<a class="be-qms-btn" href="'.$new_url.'">Add New</a>';
    echo '</div>';
    echo '</div>';

    // List records
    $args = [
      'post_type' => self::CPT_RECORD,
      'post_status' => 'publish',
      'posts_per_page' => 100,
      'orderby' => 'date',
      'order' => 'DESC'
    ];
    if ($type) {
      $args['tax_query'] = [[
        'taxonomy' => self::TAX_RECORD_TYPE,
        'field' => 'slug',
        'terms' => [$type]
      ]];
    }
    $q = new WP_Query($args);

    echo '<table class="be-qms-table">';
    echo '<thead><tr><th>Date</th><th>Project</th><th>Title</th><th>Actions</th></tr></thead><tbody>';

    if (!$q->have_posts()) {
      echo '<tr><td colspan="4" class="be-qms-muted">No records yet. Click “Add New”.</td></tr>';
    } else {
      while ($q->have_posts()) {
        $q->the_post();
        $pid = get_the_ID();

        $view_url  = esc_url(add_query_arg(['view'=>'records','type'=>$type,'be_action'=>'view','id'=>$pid], self::portal_url()));
        $edit_url  = esc_url(add_query_arg(['view'=>'records','type'=>$type,'be_action'=>'edit','id'=>$pid], self::portal_url()));
        $doc_url   = esc_url(add_query_arg(['be_qms_export'=>'doc','post'=>$pid], self::portal_url()));
        $print_url = esc_url(add_query_arg(['be_qms_export'=>'print','post'=>$pid], self::portal_url()));
        $del_url   = esc_url(admin_url('admin-post.php?action=be_qms_delete&kind=record&id='.$pid.'&_wpnonce='.wp_create_nonce('be_qms_delete_'.$pid)));

        $linked_project = (int) get_post_meta($pid, self::META_PROJECT_LINK, true);

        echo '<tr>';
        echo '<td>'.esc_html(get_post_meta($pid, '_be_qms_record_date', true) ?: get_the_date('Y-m-d')).'</td>';

        if ($linked_project) {
          $pr_title = get_the_title($linked_project);
          $pr_url = esc_url(add_query_arg(['view'=>'projects','be_action'=>'view','id'=>$linked_project], self::portal_url()));
          echo '<td><a class="be-qms-link" href="'.$pr_url.'">'.esc_html($pr_title ?: ('Project #'.$linked_project)).'</a></td>';
        } else {
          echo '<td class="be-qms-muted">—</td>';
        }

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

    echo '</div></div>';
  }


  private static function render_record_form($id = 0) {
    $is_edit = $id > 0;

    $defs = self::record_type_definitions();

    $pref_type = isset($_GET['type']) ? sanitize_key($_GET['type']) : '';
    if (!$pref_type || !isset($defs[$pref_type])) {
      $pref_type = array_key_first($defs);
    }

    $pref_project = isset($_GET['project_id']) ? (int) $_GET['project_id'] : (int) ($_GET['install_id'] ?? 0);

    $title = '';
    $date = date('Y-m-d');
    $details = '';
    $actions = '';
    $selected_type = $pref_type;
    $linked_project = $pref_project;
    $existing_att_ids = [];

    if ($is_edit) {
      $p = get_post($id);
      if (!$p || $p->post_type !== self::CPT_RECORD) {
        echo '<div class="be-qms-muted">Record not found.</div>';
        return;
      }
      if (!current_user_can('edit_post', $id)) {
        wp_die('You do not have permission to edit this record.');
      }

      $title = $p->post_title;
      $date = get_post_meta($id, '_be_qms_record_date', true) ?: $date;
      $details = (string) get_post_meta($id, '_be_qms_details', true);
      $actions = (string) get_post_meta($id, '_be_qms_actions', true);
      $linked_project = (int) get_post_meta($id, self::META_PROJECT_LINK, true);
      $existing_att_ids = get_post_meta($id, '_be_qms_attachments', true);
      if (!is_array($existing_att_ids)) $existing_att_ids = [];

      $terms = get_the_terms($id, self::TAX_RECORD_TYPE);
      if ($terms && !is_wp_error($terms)) {
        $selected_type = $terms[0]->slug;
      }
    }

    $projects = get_posts([
      'post_type' => self::CPT_PROJECT,
      'post_status' => 'publish',
      'numberposts' => 200,
      'orderby' => 'date',
      'order' => 'DESC',
    ]);

    echo '<h3>'.($is_edit ? 'Edit record' : 'New record').'</h3>';
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data">';
    echo '<input type="hidden" name="action" value="be_qms_save_record" />';
    wp_nonce_field('be_qms_save_record');

    if ($is_edit) {
      echo '<input type="hidden" name="record_id" value="'.esc_attr($id).'" />';
    }

    echo '<div class="be-qms-grid">';

    echo '<div class="be-qms-col-6"><label><strong>Record type</strong><br/>';
    echo '<select class="be-qms-select" name="record_type" required>';
    foreach ($defs as $slug => $name) {
      $sel = ($slug === $selected_type) ? 'selected' : '';
      echo '<option '.$sel.' value="'.esc_attr($slug).'">'.esc_html($name).'</option>';
    }
    echo '</select></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Date</strong><br/>';
    echo '<input class="be-qms-input be-qms-date" type="text" name="record_date" value="'.esc_attr($date).'" required /></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Title</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="title" value="'.esc_attr($title).'" placeholder="e.g. Internal Review – Q1 2026" required /></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Linked project</strong> <span class="be-qms-muted">(optional)</span><br/>';
    echo '<select class="be-qms-select" name="project_id">';
    echo '<option value="0">— Company record (not tied to a job) —</option>';
    if (!empty($projects)) {
      foreach ($projects as $pr) {
        $sel = ($linked_project && (int)$linked_project === (int)$pr->ID) ? 'selected' : '';
        echo '<option '.$sel.' value="'.esc_attr($pr->ID).'">'.esc_html($pr->post_title).'</option>';
      }
    }
    echo '</select></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Details</strong><br/>';
    echo '<textarea class="be-qms-textarea" name="details" required>'.esc_textarea($details).'</textarea></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Actions / follow-ups</strong> <span class="be-qms-muted">(who / by when)</span><br/>';
    echo '<textarea class="be-qms-textarea" name="actions">'.esc_textarea($actions).'</textarea></label></div>';

    // Existing attachments
    echo '<div class="be-qms-col-12">';
    echo '<label><strong>Attachments</strong></label>';

    if ($is_edit && !empty($existing_att_ids)) {
      echo '<div class="be-qms-muted" style="margin:6px 0 8px 0">Tick any files you want to remove, and/or upload more below.</div>';
      echo '<ul style="margin:0;padding-left:18px">';
      foreach ($existing_att_ids as $aid) {
        $url = wp_get_attachment_url($aid);
        if (!$url) continue;
        $name = get_the_title($aid) ?: basename((string)$url);
        echo '<li style="margin:6px 0">'
          .'<label style="display:flex;gap:10px;align-items:center">'
          .'<input type="checkbox" name="remove_attachments[]" value="'.esc_attr((int)$aid).'">'
          .'<a class="be-qms-link" href="'.esc_url($url).'" target="_blank">'.esc_html($name).'</a>'
          .'</label>'
          .'</li>';
      }
      echo '</ul>';
    } else {
      echo '<div class="be-qms-muted" style="margin:6px 0 8px 0">None yet.</div>';
    }

    echo '<div style="margin-top:8px">';
    echo '<input type="file" name="attachments[]" multiple />';
    echo '</div>';
    echo '</div>';

    echo '</div>';

    echo '<div class="be-qms-row" style="margin-top:12px">';
    echo '<button class="be-qms-btn" type="submit">'.($is_edit ? 'Save changes' : 'Save record').'</button>';

    $back_type = isset($defs[$selected_type]) ? $selected_type : $pref_type;
    $back = esc_url(add_query_arg(['view'=>'records','type'=>$back_type], self::portal_url()));
    echo '<a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">Cancel</a>';
    echo '</div>';

    echo '</form>';
  }

  private static function render_r06_customer_complaints_form($id = 0) {
    $is_edit = $id > 0;

    $customer_name = '';
    $address = '';
    $complaint_date = date('Y-m-d');
    $contact_name = '';
    $contact_email = '';
    $contact_phone = '';
    $contact_mobile = '';
    $nature = '';
    $outcome = '';
    $immediate_action = '';
    $contacted_within_day = '';
    $contacted_reason = '';
    $actions_taken = '';
    $further_action = '';
    $customer_satisfied = '';
    $reported_by = '';
    $date_closed = '';
    $reported_title = '';
    $linked_project = 0;
    $existing_att_ids = [];

    if ($is_edit) {
      $p = get_post($id);
      if (!$p || $p->post_type !== self::CPT_RECORD) {
        echo '<div class="be-qms-muted">Record not found.</div>';
        return;
      }
      if (!current_user_can('edit_post', $id)) {
        wp_die('You do not have permission to edit this record.');
      }

      $customer_name = (string) get_post_meta($id, '_be_qms_r06_customer_name', true);
      $address = (string) get_post_meta($id, '_be_qms_r06_address', true);
      $complaint_date = get_post_meta($id, '_be_qms_r06_complaint_date', true) ?: $complaint_date;
      $contact_name = (string) get_post_meta($id, '_be_qms_r06_contact_name', true);
      $contact_email = (string) get_post_meta($id, '_be_qms_r06_contact_email', true);
      $contact_phone = (string) get_post_meta($id, '_be_qms_r06_contact_phone', true);
      $contact_mobile = (string) get_post_meta($id, '_be_qms_r06_contact_mobile', true);
      $nature = (string) get_post_meta($id, '_be_qms_r06_nature', true);
      $outcome = (string) get_post_meta($id, '_be_qms_r06_outcome', true);
      $immediate_action = (string) get_post_meta($id, '_be_qms_r06_immediate_action', true);
      $contacted_within_day = (string) get_post_meta($id, '_be_qms_r06_contacted_within_day', true);
      $contacted_reason = (string) get_post_meta($id, '_be_qms_r06_contacted_reason', true);
      $actions_taken = (string) get_post_meta($id, '_be_qms_r06_actions_taken', true);
      $further_action = (string) get_post_meta($id, '_be_qms_r06_further_action', true);
      $customer_satisfied = (string) get_post_meta($id, '_be_qms_r06_customer_satisfied', true);
      $reported_by = (string) get_post_meta($id, '_be_qms_r06_reported_by', true);
      $date_closed = (string) get_post_meta($id, '_be_qms_r06_date_closed', true);
      $reported_title = (string) get_post_meta($id, '_be_qms_r06_reported_title', true);
      $linked_project = (int) get_post_meta($id, self::META_PROJECT_LINK, true);
      $existing_att_ids = get_post_meta($id, '_be_qms_attachments', true);
      if (!is_array($existing_att_ids)) $existing_att_ids = [];
    }

    $projects = get_posts([
      'post_type' => self::CPT_PROJECT,
      'post_status' => 'publish',
      'numberposts' => 200,
      'orderby' => 'date',
      'order' => 'DESC',
    ]);

    echo '<h3>R06 - Customer Complaints</h3>';
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data">';
    echo '<input type="hidden" name="action" value="be_qms_save_record" />';
    echo '<input type="hidden" name="record_type" value="r06_customer_complaints" />';
    wp_nonce_field('be_qms_save_record');

    if ($is_edit) {
      echo '<input type="hidden" name="record_id" value="'.esc_attr($id).'" />';
    }

    echo '<div class="be-qms-grid">';

    echo '<div class="be-qms-col-6"><label><strong>Customer Name</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="customer_name" value="'.esc_attr($customer_name).'" required /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Address</strong><br/>';
    echo '<textarea class="be-qms-textarea" name="address">'.esc_textarea($address).'</textarea></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Date of Complaint</strong><br/>';
    echo '<input class="be-qms-input be-qms-date" type="text" name="complaint_date" value="'.esc_attr($complaint_date).'" required /></label></div>';
    echo '<div class="be-qms-col-6"></div>';

    echo '<div class="be-qms-col-6"><label><strong>Contact&#039;s Name</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="contact_name" value="'.esc_attr($contact_name).'" /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>E-mail</strong><br/>';
    echo '<input class="be-qms-input" type="email" name="contact_email" value="'.esc_attr($contact_email).'" /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Telephone</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="contact_phone" value="'.esc_attr($contact_phone).'" /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Mobile</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="contact_mobile" value="'.esc_attr($contact_mobile).'" /></label></div>';

    echo '<div class="be-qms-col-12"><hr style="margin:8px 0;border:0;border-top:1px solid rgba(30,41,59,.75)"></div>';

    echo '<div class="be-qms-col-12"><label><strong>Nature of Complaint</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="nature" value="'.esc_attr($nature).'" required /></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Outcome</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="outcome" value="'.esc_attr($outcome).'" /></label></div>';

    echo '<div class="be-qms-col-12"><hr style="margin:8px 0;border:0;border-top:1px solid rgba(30,41,59,.75)"></div>';

    echo '<div class="be-qms-col-12"><label><strong>Immediate Action Requested by Customer</strong><br/>';
    echo '<textarea class="be-qms-textarea" name="immediate_action">'.esc_textarea($immediate_action).'</textarea></label></div>';

    echo '<div class="be-qms-col-12"><hr style="margin:8px 0;border:0;border-top:1px solid rgba(30,41,59,.75)"></div>';

    $contacted_yes = ($contacted_within_day === 'yes') ? 'checked' : '';
    $contacted_no = ($contacted_within_day === 'no') ? 'checked' : '';
    echo '<div class="be-qms-col-12"><label><strong>Customer contacted within 1 working day and given information as to when the problem will be resolved?</strong></label>';
    echo '<div class="be-qms-row" style="gap:28px;margin-top:6px">';
    echo '<label><input type="radio" name="contacted_within_day" value="yes" '.$contacted_yes.'> Yes</label>';
    echo '<label><input type="radio" name="contacted_within_day" value="no" '.$contacted_no.'> No</label>';
    echo '</div></div>';

    echo '<div class="be-qms-col-12"><label><strong>If not, why not?</strong><br/>';
    echo '<textarea class="be-qms-textarea" name="contacted_reason">'.esc_textarea($contacted_reason).'</textarea></label></div>';

    echo '<div class="be-qms-col-12"><hr style="margin:8px 0;border:0;border-top:1px solid rgba(30,41,59,.75)"></div>';

    echo '<div class="be-qms-col-12"><label><strong>Actions taken to resolve complaint</strong><br/>';
    echo '<textarea class="be-qms-textarea" name="actions_taken">'.esc_textarea($actions_taken).'</textarea></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Further Action Required</strong><br/>';
    echo '<textarea class="be-qms-textarea" name="further_action">'.esc_textarea($further_action).'</textarea></label></div>';

    echo '<div class="be-qms-col-12"><hr style="margin:8px 0;border:0;border-top:1px solid rgba(30,41,59,.75)"></div>';

    $satisfied_yes = ($customer_satisfied === 'yes') ? 'checked' : '';
    $satisfied_no = ($customer_satisfied === 'no') ? 'checked' : '';
    echo '<div class="be-qms-col-12"><label><strong>Is Customer satisfied with result?</strong></label>';
    echo '<div class="be-qms-row" style="gap:28px;margin-top:6px">';
    echo '<label><input type="radio" name="customer_satisfied" value="yes" '.$satisfied_yes.'> Yes</label>';
    echo '<label><input type="radio" name="customer_satisfied" value="no" '.$satisfied_no.'> No</label>';
    echo '</div></div>';

    echo '<div class="be-qms-col-12"><hr style="margin:8px 0;border:0;border-top:1px solid rgba(30,41,59,.75)"></div>';

    echo '<div class="be-qms-col-6"><label><strong>Reported By</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="reported_by" value="'.esc_attr($reported_by).'" /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Date Closed</strong><br/>';
    echo '<input class="be-qms-input be-qms-date" type="text" name="date_closed" value="'.esc_attr($date_closed).'" /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Title</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="reported_title" value="'.esc_attr($reported_title).'" /></label></div>';
    echo '<div class="be-qms-col-6"></div>';

    echo '<div class="be-qms-col-12"><hr style="margin:8px 0;border:0;border-top:1px solid rgba(30,41,59,.75)"></div>';

    echo '<div class="be-qms-col-12"><label><strong>Linked project</strong> <span class="be-qms-muted">(optional)</span><br/>';
    echo '<select class="be-qms-select" name="project_id">';
    echo '<option value="0">— Company record (not tied to a job) —</option>';
    if (!empty($projects)) {
      foreach ($projects as $pr) {
        $sel = ($linked_project && (int)$linked_project === (int)$pr->ID) ? 'selected' : '';
        echo '<option '.$sel.' value="'.esc_attr($pr->ID).'">'.esc_html($pr->post_title).'</option>';
      }
    }
    echo '</select></label></div>';

    echo '<div class="be-qms-col-12">';
    echo '<label><strong>Attachments</strong></label>';

    if ($is_edit && !empty($existing_att_ids)) {
      echo '<div class="be-qms-muted" style="margin:6px 0 8px 0">Tick any files you want to remove, and/or upload more below.</div>';
      echo '<ul style="margin:0;padding-left:18px">';
      foreach ($existing_att_ids as $aid) {
        $url = wp_get_attachment_url($aid);
        if (!$url) continue;
        $name = get_the_title($aid) ?: basename((string)$url);
        echo '<li style="margin:6px 0">'
          .'<label style="display:flex;gap:10px;align-items:center">'
          .'<input type="checkbox" name="remove_attachments[]" value="'.esc_attr((int)$aid).'">'
          .'<a class="be-qms-link" href="'.esc_url($url).'" target="_blank">'.esc_html($name).'</a>'
          .'</label>'
          .'</li>';
      }
      echo '</ul>';
    } else {
      echo '<div class="be-qms-muted" style="margin:6px 0 8px 0">None yet.</div>';
    }

    echo '<div style="margin-top:8px">';
    echo '<input type="file" name="attachments[]" multiple />';
    echo '</div>';
    echo '</div>';

    echo '</div>';

    echo '<div class="be-qms-row" style="margin-top:12px">';
    echo '<button class="be-qms-btn" type="submit">'.($is_edit ? 'Save changes' : 'Save record').'</button>';
    $back = esc_url(add_query_arg(['view'=>'records','type'=>'r06_customer_complaints'], self::portal_url()));
    echo '<a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">Cancel</a>';
    echo '</div>';

    echo '</form>';
  }

  private static function render_r06_customer_complaints_view($id) {
    $p = get_post($id);
    if (!$p || $p->post_type !== self::CPT_RECORD) {
      echo '<div class="be-qms-muted">Record not found.</div>';
      return;
    }

    $customer_name = (string) get_post_meta($id, '_be_qms_r06_customer_name', true);
    $address = (string) get_post_meta($id, '_be_qms_r06_address', true);
    $complaint_date = (string) get_post_meta($id, '_be_qms_r06_complaint_date', true);
    $contact_name = (string) get_post_meta($id, '_be_qms_r06_contact_name', true);
    $contact_email = (string) get_post_meta($id, '_be_qms_r06_contact_email', true);
    $contact_phone = (string) get_post_meta($id, '_be_qms_r06_contact_phone', true);
    $contact_mobile = (string) get_post_meta($id, '_be_qms_r06_contact_mobile', true);
    $nature = (string) get_post_meta($id, '_be_qms_r06_nature', true);
    $outcome = (string) get_post_meta($id, '_be_qms_r06_outcome', true);
    $immediate_action = (string) get_post_meta($id, '_be_qms_r06_immediate_action', true);
    $contacted_within_day = (string) get_post_meta($id, '_be_qms_r06_contacted_within_day', true);
    $contacted_reason = (string) get_post_meta($id, '_be_qms_r06_contacted_reason', true);
    $actions_taken = (string) get_post_meta($id, '_be_qms_r06_actions_taken', true);
    $further_action = (string) get_post_meta($id, '_be_qms_r06_further_action', true);
    $customer_satisfied = (string) get_post_meta($id, '_be_qms_r06_customer_satisfied', true);
    $reported_by = (string) get_post_meta($id, '_be_qms_r06_reported_by', true);
    $date_closed = (string) get_post_meta($id, '_be_qms_r06_date_closed', true);
    $reported_title = (string) get_post_meta($id, '_be_qms_r06_reported_title', true);
    $linked_project = (int) get_post_meta($id, self::META_PROJECT_LINK, true);
    $att_ids = get_post_meta($id, '_be_qms_attachments', true);
    if (!is_array($att_ids)) $att_ids = [];

    $doc_url  = esc_url(add_query_arg(['be_qms_export'=>'doc','post'=>$id], self::portal_url()));
    $print_url= esc_url(add_query_arg(['be_qms_export'=>'print','post'=>$id], self::portal_url()));
    $edit_url = esc_url(add_query_arg(['view'=>'records','be_action'=>'edit','id'=>$id,'type'=>'r06_customer_complaints'], self::portal_url()));

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div><h3 style="margin:0">R06 - Customer Complaints</h3>';
    echo '<div class="be-qms-muted">'.esc_html($complaint_date ?: get_the_date('Y-m-d',$id)).'</div>';

    if ($linked_project) {
      $pt = get_the_title($linked_project);
      $purl = esc_url(add_query_arg(['view'=>'projects','be_action'=>'view','id'=>$linked_project], self::portal_url()));
      echo '<div class="be-qms-muted">Linked project: <a class="be-qms-link" href="'.$purl.'">'.esc_html($pt ?: ('Project #'.$linked_project)).'</a></div>';
    }

    echo '</div>';
    echo '<div class="be-qms-row">'
      .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$edit_url.'">Edit</a>'
      .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$doc_url.'">Download DOC</a>'
      .'<a class="be-qms-btn be-qms-btn-secondary" target="_blank" href="'.$print_url.'">Print / Save PDF</a>'
      .'</div>';
    echo '</div>';

    echo '<div class="be-qms-card-inner">';
    echo '<div class="be-qms-grid">';
    echo '<div class="be-qms-col-6"><strong>Customer Name</strong><br/>'.esc_html($customer_name).'</div>';
    echo '<div class="be-qms-col-6"><strong>Address</strong><br/>'.wpautop(esc_html($address)).'</div>';
    echo '<div class="be-qms-col-6"><strong>Date of Complaint</strong><br/>'.esc_html($complaint_date).'</div>';
    echo '<div class="be-qms-col-6"><strong>Contact&#039;s Name</strong><br/>'.esc_html($contact_name).'</div>';
    echo '<div class="be-qms-col-6"><strong>E-mail</strong><br/>'.esc_html($contact_email).'</div>';
    echo '<div class="be-qms-col-6"><strong>Telephone</strong><br/>'.esc_html($contact_phone).'</div>';
    echo '<div class="be-qms-col-6"><strong>Mobile</strong><br/>'.esc_html($contact_mobile).'</div>';
    echo '<div class="be-qms-col-12"><hr style="margin:12px 0;border:0;border-top:1px solid rgba(30,41,59,.75)"></div>';
    echo '<div class="be-qms-col-12"><strong>Nature of Complaint</strong><br/>'.esc_html($nature).'</div>';
    echo '<div class="be-qms-col-12"><strong>Outcome</strong><br/>'.esc_html($outcome).'</div>';
    echo '<div class="be-qms-col-12"><hr style="margin:12px 0;border:0;border-top:1px solid rgba(30,41,59,.75)"></div>';
    echo '<div class="be-qms-col-12"><strong>Immediate Action Requested by Customer</strong><br/>'.wpautop(esc_html($immediate_action)).'</div>';
    echo '<div class="be-qms-col-12"><hr style="margin:12px 0;border:0;border-top:1px solid rgba(30,41,59,.75)"></div>';
    echo '<div class="be-qms-col-12"><strong>Customer contacted within 1 working day?</strong><br/>'.esc_html($contacted_within_day ? ucfirst($contacted_within_day) : '—').'</div>';
    echo '<div class="be-qms-col-12"><strong>If not, why not?</strong><br/>'.wpautop(esc_html($contacted_reason)).'</div>';
    echo '<div class="be-qms-col-12"><hr style="margin:12px 0;border:0;border-top:1px solid rgba(30,41,59,.75)"></div>';
    echo '<div class="be-qms-col-12"><strong>Actions taken to resolve complaint</strong><br/>'.wpautop(esc_html($actions_taken)).'</div>';
    echo '<div class="be-qms-col-12"><strong>Further Action Required</strong><br/>'.wpautop(esc_html($further_action)).'</div>';
    echo '<div class="be-qms-col-12"><hr style="margin:12px 0;border:0;border-top:1px solid rgba(30,41,59,.75)"></div>';
    echo '<div class="be-qms-col-12"><strong>Is Customer satisfied with result?</strong><br/>'.esc_html($customer_satisfied ? ucfirst($customer_satisfied) : '—').'</div>';
    echo '<div class="be-qms-col-12"><hr style="margin:12px 0;border:0;border-top:1px solid rgba(30,41,59,.75)"></div>';
    echo '<div class="be-qms-col-6"><strong>Reported By</strong><br/>'.esc_html($reported_by).'</div>';
    echo '<div class="be-qms-col-6"><strong>Date Closed</strong><br/>'.esc_html($date_closed).'</div>';
    echo '<div class="be-qms-col-6"><strong>Title</strong><br/>'.esc_html($reported_title).'</div>';
    echo '</div>';

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

    $back = esc_url(add_query_arg(['view'=>'records','type'=>'r06_customer_complaints'], self::portal_url()));
    echo '<div class="be-qms-row" style="margin-top:12px"><a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">Return to Records</a></div>';
  }

  private static function render_record_view($id) {
    $p = get_post($id);
    if (!$p || $p->post_type !== self::CPT_RECORD) {
      echo '<div class="be-qms-muted">Record not found.</div>';
      return;
    }

    $terms = get_the_terms($id, self::TAX_RECORD_TYPE);
    $type_slug = ($terms && !is_wp_error($terms)) ? $terms[0]->slug : '';
    $defs = self::record_type_definitions();
    $type_name = ($type_slug && isset($defs[$type_slug])) ? $defs[$type_slug] : (($terms && !is_wp_error($terms)) ? $terms[0]->name : '-');

    $record_date = get_post_meta($id, '_be_qms_record_date', true) ?: get_the_date('Y-m-d',$id);
    $details = (string) get_post_meta($id, '_be_qms_details', true);
    $actions = (string) get_post_meta($id, '_be_qms_actions', true);
    $linked_project = (int) get_post_meta($id, self::META_PROJECT_LINK, true);
    $att_ids = get_post_meta($id, '_be_qms_attachments', true);
    if (!is_array($att_ids)) $att_ids = [];

    $doc_url  = esc_url(add_query_arg(['be_qms_export'=>'doc','post'=>$id], self::portal_url()));
    $print_url= esc_url(add_query_arg(['be_qms_export'=>'print','post'=>$id], self::portal_url()));
    $edit_url = esc_url(add_query_arg(['view'=>'records','be_action'=>'edit','id'=>$id,'type'=>$type_slug], self::portal_url()));

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div><h3 style="margin:0">'.esc_html($p->post_title).'</h3>';
    echo '<div class="be-qms-muted">'.esc_html($type_name).' • '.esc_html($record_date).'</div>';

    if ($linked_project) {
      $pt = get_the_title($linked_project);
      $purl = esc_url(add_query_arg(['view'=>'projects','be_action'=>'view','id'=>$linked_project], self::portal_url()));
      echo '<div class="be-qms-muted">Linked project: <a class="be-qms-link" href="'.$purl.'">'.esc_html($pt ?: ('Project #'.$linked_project)).'</a></div>';
    }

    echo '</div>';

    echo '<div class="be-qms-row">'
      .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$edit_url.'">Edit</a>'
      .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$doc_url.'">Download DOC</a>'
      .'<a class="be-qms-btn be-qms-btn-secondary" target="_blank" href="'.$print_url.'">Print / Save PDF</a>'
      .'</div>';
    echo '</div>';

    echo '<div class="be-qms-card-inner">';
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

    $back = esc_url(add_query_arg(['view'=>'records','type'=>($type_slug ?: array_key_first(self::record_type_definitions()))], self::portal_url()));
    echo '<div class="be-qms-row" style="margin-top:12px"><a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">Return to Records</a></div>';
  }

  // -------------------------
  // R04 Tool Calibration
  // -------------------------

  private static function render_r04_tool_list() {
    $add_url = esc_url(add_query_arg(['view'=>'records','type'=>'r04_tool_calibration','be_action'=>'new'], self::portal_url()));

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div><strong>R04 Tool Calibration</strong> <span class="be-qms-muted">(tools register)</span></div>';
    echo '<div class="be-qms-row"><a class="be-qms-btn" href="'.$add_url.'">Add New</a></div>';
    echo '</div>';

    $tools = self::query_r04_tools();

    echo '<table class="be-qms-table">';
    echo '<thead><tr><th>Item</th><th>Serial No</th><th>Next Due</th><th>Actions</th></tr></thead><tbody>';

    if (!$tools) {
      echo '<tr><td colspan="4" class="be-qms-muted">No tools logged yet. Click “Add New”.</td></tr>';
    } else {
      foreach ($tools as $tool) {
        $rid = (int) $tool->ID;
        $item = get_post_meta($rid, '_be_qms_tool_item', true) ?: get_the_title($rid);
        $serial = get_post_meta($rid, '_be_qms_tool_serial', true);
        $next_due = get_post_meta($rid, '_be_qms_tool_next_due', true);

        $view_url = esc_url(add_query_arg(['view'=>'records','type'=>'r04_tool_calibration','be_action'=>'view','id'=>$rid], self::portal_url()));
        $edit_url = esc_url(add_query_arg(['view'=>'records','type'=>'r04_tool_calibration','be_action'=>'edit','id'=>$rid], self::portal_url()));
        $del_url  = esc_url(admin_url('admin-post.php?action=be_qms_delete&kind=record&id='.$rid.'&_wpnonce='.wp_create_nonce('be_qms_delete_'.$rid)));

        echo '<tr>';
        echo '<td><a class="be-qms-link" href="'.$view_url.'">'.esc_html($item).'</a></td>';
        echo '<td>'.esc_html($serial ?: '—').'</td>';
        echo '<td>'.esc_html($next_due ?: '—').'</td>';
        echo '<td class="be-qms-row">'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$view_url.'">View</a>'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$edit_url.'">Edit</a>'
          .'<a class="be-qms-btn be-qms-btn-danger" href="'.$del_url.'" onclick="return confirm(\'Remove this tool record?\')">Remove</a>'
          .'</td>';
        echo '</tr>';
      }
    }

    echo '</tbody></table>';
  }

  private static function query_r04_tools() {
    $q = new WP_Query([
      'post_type' => self::CPT_RECORD,
      'post_status' => 'publish',
      'posts_per_page' => 200,
      'orderby' => 'date',
      'order' => 'DESC',
      'tax_query' => [[
        'taxonomy' => self::TAX_RECORD_TYPE,
        'field' => 'slug',
        'terms' => ['r04_tool_calibration'],
      ]],
    ]);
    return $q->have_posts() ? $q->posts : [];
  }

  private static function render_r04_tool_form($id) {
    $is_edit = $id > 0;
    $p = $is_edit ? get_post($id) : null;
    if ($is_edit && (!$p || $p->post_type !== self::CPT_RECORD)) {
      echo '<div class="be-qms-muted">Tool record not found.</div>';
      return;
    }

    $item = $is_edit ? (get_post_meta($id, '_be_qms_tool_item', true) ?: $p->post_title) : '';
    $serial = $is_edit ? get_post_meta($id, '_be_qms_tool_serial', true) : '';
    $description = $is_edit ? get_post_meta($id, '_be_qms_tool_description', true) : '';
    $requirements = $is_edit ? get_post_meta($id, '_be_qms_tool_requirements', true) : '';
    $date_purchased = $is_edit ? get_post_meta($id, '_be_qms_tool_date_purchased', true) : '';
    $date_calibrated = $is_edit ? get_post_meta($id, '_be_qms_tool_date_calibrated', true) : '';
    $next_due = $is_edit ? get_post_meta($id, '_be_qms_tool_next_due', true) : '';

    $existing_att_ids = $is_edit ? get_post_meta($id, '_be_qms_attachments', true) : [];
    if (!is_array($existing_att_ids)) $existing_att_ids = [];

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div><h3 style="margin:0">R04 - Tool Calibration</h3>';
    echo '<div class="be-qms-muted">'.($is_edit ? 'Edit tool record' : 'Add new tool record').'</div></div>';
    echo '</div>';

    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data" style="margin-top:12px">';
    echo '<input type="hidden" name="action" value="be_qms_save_record" />';
    echo '<input type="hidden" name="record_type" value="r04_tool_calibration" />';
    wp_nonce_field('be_qms_save_record');
    if ($is_edit) {
      echo '<input type="hidden" name="record_id" value="'.esc_attr($id).'" />';
    }

    echo '<div class="be-qms-grid">';

    echo '<div class="be-qms-col-6"><label><strong>Item of Equipment</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="tool_item" value="'.esc_attr($item).'" required /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Serial Number</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="tool_serial" value="'.esc_attr($serial).'" /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Calibration / Checking Requirements</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="tool_requirements" value="'.esc_attr($requirements).'" placeholder="e.g. Annual calibration" /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Description / Notes</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="tool_description" value="'.esc_attr($description).'" /></label></div>';

    echo '<div class="be-qms-col-4"><label><strong>Date Purchased</strong><br/>';
    echo '<input class="be-qms-input be-qms-date" type="text" name="tool_date_purchased" value="'.esc_attr($date_purchased).'" /></label></div>';

    echo '<div class="be-qms-col-4"><label><strong>Date Calibrated</strong><br/>';
    echo '<input class="be-qms-input be-qms-date" type="text" name="tool_date_calibrated" value="'.esc_attr($date_calibrated).'" /></label></div>';

    echo '<div class="be-qms-col-4"><label><strong>Next Calibration Date</strong><br/>';
    echo '<input class="be-qms-input be-qms-date" type="text" name="tool_next_due" value="'.esc_attr($next_due).'" /></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Upload evidence</strong> <span class="be-qms-muted">(optional)</span><br/>';
    echo '<input type="file" name="attachments[]" multiple /></label></div>';

    if ($is_edit) {
      echo '<div class="be-qms-col-12"><strong>Existing attachments</strong><br/>';
      if (!$existing_att_ids) {
        echo '<div class="be-qms-muted">None.</div>';
      } else {
        echo '<div class="be-qms-muted">Tick to remove on save:</div>';
        echo '<ul style="margin:8px 0 0 18px">';
        foreach ($existing_att_ids as $aid) {
          $url = wp_get_attachment_url($aid);
          $name = get_the_title($aid);
          if (!$url) continue;
          echo '<li><label style="display:flex;gap:10px;align-items:center">'
            .'<input type="checkbox" name="remove_attachments[]" value="'.esc_attr($aid).'">'
            .'<a class="be-qms-link" target="_blank" href="'.esc_url($url).'">'.esc_html($name ?: basename($url)).'</a>'
            .'</label></li>';
        }
        echo '</ul>';
      }
      echo '</div>';
    }

    echo '</div>'; // grid

    echo '<div class="be-qms-row" style="margin-top:12px">';
    echo '<button class="be-qms-btn" type="submit">'.($is_edit ? 'Save changes' : 'Save & Close').'</button>';
    $back = esc_url(add_query_arg(['view'=>'records','type'=>'r04_tool_calibration'], self::portal_url()));
    echo '<a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">Cancel</a>';
    echo '</div>';

    echo '</form>';
  }

  private static function render_r04_tool_view($id) {
    $p = get_post($id);
    if (!$p || $p->post_type !== self::CPT_RECORD) {
      echo '<div class="be-qms-muted">Tool record not found.</div>';
      return;
    }

    $item = get_post_meta($id, '_be_qms_tool_item', true) ?: $p->post_title;
    $serial = get_post_meta($id, '_be_qms_tool_serial', true);
    $description = get_post_meta($id, '_be_qms_tool_description', true);
    $requirements = get_post_meta($id, '_be_qms_tool_requirements', true);
    $date_purchased = get_post_meta($id, '_be_qms_tool_date_purchased', true);
    $date_calibrated = get_post_meta($id, '_be_qms_tool_date_calibrated', true);
    $next_due = get_post_meta($id, '_be_qms_tool_next_due', true);

    $att_ids = get_post_meta($id, '_be_qms_attachments', true);
    if (!is_array($att_ids)) $att_ids = [];

    $edit_url = esc_url(add_query_arg(['view'=>'records','type'=>'r04_tool_calibration','be_action'=>'edit','id'=>$id], self::portal_url()));

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div><h3 style="margin:0">R04 - Tool Calibration</h3>';
    echo '<div class="be-qms-muted">'.esc_html($item).'</div></div>';
    echo '<div class="be-qms-row"><a class="be-qms-btn be-qms-btn-secondary" href="'.$edit_url.'">Edit</a></div>';
    echo '</div>';

    echo '<div class="be-qms-card" style="margin-top:14px">';
    echo '<table class="be-qms-table">';
    echo '<tr><th>Item</th><td>'.esc_html($item).'</td></tr>';
    echo '<tr><th>Serial</th><td>'.esc_html($serial ?: '—').'</td></tr>';
    echo '<tr><th>Requirements</th><td>'.esc_html($requirements ?: '—').'</td></tr>';
    echo '<tr><th>Description</th><td>'.esc_html($description ?: '—').'</td></tr>';
    echo '<tr><th>Date Purchased</th><td>'.esc_html($date_purchased ?: '—').'</td></tr>';
    echo '<tr><th>Date Calibrated</th><td>'.esc_html($date_calibrated ?: '—').'</td></tr>';
    echo '<tr><th>Next Due</th><td>'.esc_html($next_due ?: '—').'</td></tr>';
    echo '</table>';

    echo '<h4 style="margin-top:14px">Attachments</h4>';
    if (!$att_ids) {
      echo '<div class="be-qms-muted">None.</div>';
    } else {
      echo '<ul>';
      foreach ($att_ids as $aid) {
        $url = wp_get_attachment_url($aid);
        $name = get_the_title($aid);
        if ($url) {
          echo '<li><a class="be-qms-link" target="_blank" href="'.esc_url($url).'">'.esc_html($name ?: basename($url)).'</a></li>';
        }
      }
      echo '</ul>';
    }
    echo '</div>';

    $back = esc_url(add_query_arg(['view'=>'records','type'=>'r04_tool_calibration'], self::portal_url()));
    echo '<div class="be-qms-row" style="margin-top:12px"><a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">Return to Records</a></div>';
  }

  // -------------------------
  // R07 Training Matrix
  // -------------------------

  private static function render_r07_training_matrix($action) {
    // Routes inside R07
    $sub = $action;
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $emp = isset($_GET['emp']) ? (int) $_GET['emp'] : 0;

    if ($sub === 'new_employee') {
      self::render_r07_employee_form(0);
      return;
    }
    if ($sub === 'edit_employee' && $id) {
      self::render_r07_employee_form($id);
      return;
    }
    if ($sub === 'employee' && $id) {
      self::render_r07_employee_view($id);
      return;
    }
    if ($sub === 'add_skill' && $emp) {
      self::render_r07_skill_form($emp, 0);
      return;
    }
    if ($sub === 'edit_skill' && $id && $emp) {
      self::render_r07_skill_form($emp, $id);
      return;
    }

    // Default: list employees
    self::render_r07_employee_list();
  }

  private static function render_r07_employee_list() {
    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div><strong>R07 Personal Skills &amp; Training Matrix</strong> <span class="be-qms-muted">(employee-first)</span></div>';
    $add = esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix','be_action'=>'new_employee'], self::portal_url()));
    echo '<a class="be-qms-btn" href="'.$add.'">Add New</a>';
    echo '</div>';

    $employees = get_posts([
      'post_type' => self::CPT_EMPLOYEE,
      'post_status' => 'publish',
      'numberposts' => 200,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    echo '<table class="be-qms-table">';
    echo '<thead><tr><th>Employee</th><th>Qualifications</th><th>Next renewal</th><th>Actions</th></tr></thead><tbody>';

    if (!$employees) {
      echo '<tr><td colspan="4" class="be-qms-muted">No employees yet. Click “Add New”.</td></tr>';
    } else {
      for ($i=0; $i<count($employees); $i++) {
        $e = $employees[$i];
        $eid = (int)$e->ID;

        // Find next renewal date for this employee
        $next = self::r07_get_next_renewal($eid);
        $qualifications = self::r07_get_employee_qualifications($eid);

        $view = esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix','be_action'=>'employee','id'=>$eid], self::portal_url()));
        $edit = esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix','be_action'=>'edit_employee','id'=>$eid], self::portal_url()));
        $del  = esc_url(admin_url('admin-post.php?action=be_qms_delete&kind=employee&id='.$eid.'&_wpnonce='.wp_create_nonce('be_qms_delete_'.$eid)));

        echo '<tr>';
        echo '<td><a class="be-qms-link" href="'.$view.'">'.esc_html($e->post_title).'</a></td>';
        echo '<td>'.$qualifications.'</td>';
        echo '<td>'.esc_html($next ?: '—').'</td>';
        echo '<td class="be-qms-row">'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$view.'">View</a>'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$edit.'">Edit</a>'
          .'<a class="be-qms-btn be-qms-btn-danger" href="'.$del.'" onclick="return confirm(\'Delete this employee and their training records?\')">Delete</a>'
          .'</td>';
        echo '</tr>';
      }
    }

    echo '</tbody></table>';
  }

  private static function r07_get_skill_count($employee_id) {
    $ids = get_posts([
      'post_type' => self::CPT_TRAINING,
      'post_status' => 'publish',
      'numberposts' => -1,
      'fields' => 'ids',
      'meta_key' => self::META_EMPLOYEE_LINK,
      'meta_value' => $employee_id,
    ]);
    return is_array($ids) ? count($ids) : 0;
  }

  private static function r07_get_employee_qualifications($employee_id) {
    $skills = get_posts([
      'post_type' => self::CPT_TRAINING,
      'post_status' => 'publish',
      'numberposts' => 200,
      'orderby' => 'date',
      'order' => 'DESC',
      'meta_key' => self::META_EMPLOYEE_LINK,
      'meta_value' => $employee_id,
    ]);

    if (!$skills) {
      return '<span class="be-qms-muted">—</span>';
    }

    $items = [];
    foreach ($skills as $skill) {
      $sid = (int) $skill->ID;
      $course = get_post_meta($sid, '_be_qms_training_course', true) ?: $skill->post_title;
      $renew = get_post_meta($sid, '_be_qms_training_renewal', true);
      $label = $renew ? ($course . ' (renew ' . $renew . ')') : $course;
      $items[] = esc_html($label);
    }

    $html = '<ul style="margin:0;padding-left:18px">';
    foreach ($items as $item) {
      $html .= '<li>'.$item.'</li>';
    }
    $html .= '</ul>';

    return $html;
  }

  private static function r07_get_next_renewal($employee_id) {
    $q = new WP_Query([
      'post_type' => self::CPT_TRAINING,
      'post_status' => 'publish',
      'posts_per_page' => 50,
      'orderby' => 'meta_value',
      'order' => 'ASC',
      'meta_key' => '_be_qms_training_renewal',
      'meta_query' => [[
        'key' => self::META_EMPLOYEE_LINK,
        'value' => $employee_id,
        'compare' => '=',
      ],[
        'key' => '_be_qms_training_renewal',
        'compare' => 'EXISTS',
      ]],
    ]);

    $best = '';
    if ($q->have_posts()) {
      while ($q->have_posts()) {
        $q->the_post();
        $rid = get_the_ID();
        $renew = get_post_meta($rid, '_be_qms_training_renewal', true);
        if ($renew && (!$best or $renew < $best)) {
          $best = $renew;
        }
      }
      wp_reset_postdata();
    }
    return $best;
  }

  private static function render_r07_employee_form($id) {
    $is_edit = $id > 0;
    $name = '';
    if ($is_edit) {
      $p = get_post($id);
      if (!$p || $p->post_type !== self::CPT_EMPLOYEE) {
        echo '<div class="be-qms-muted">Employee not found.</div>';
        return;
      }
      $name = $p->post_title;
    }

    echo '<h3>'.($is_edit ? 'Edit employee' : 'Add new employee').'</h3>';

    $save_url = esc_url(admin_url('admin-post.php'));
    echo '<form method="post" action="'.$save_url.'">';
    echo '<input type="hidden" name="action" value="be_qms_save_employee" />';
    wp_nonce_field('be_qms_save_employee');
    if ($is_edit) echo '<input type="hidden" name="id" value="'.esc_attr($id).'" />';

    echo '<div class="be-qms-grid">';
    echo '<div class="be-qms-col-12"><label><strong>Employee name</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="employee_name" value="'.esc_attr($name).'" required /></label></div>';
    echo '</div>';

    echo '<div class="be-qms-row" style="margin-top:12px">';
    echo '<button class="be-qms-btn" type="submit">Save</button>';
    $back = esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix'], self::portal_url()));
    echo '<a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">Cancel</a>';
    echo '</div>';
    echo '</form>';
  }

  private static function render_r07_employee_view($employee_id) {
    $p = get_post($employee_id);
    if (!$p || $p->post_type !== self::CPT_EMPLOYEE) {
      echo '<div class="be-qms-muted">Employee not found.</div>';
      return;
    }

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div><h3 style="margin:0">'.esc_html($p->post_title).'</h3><div class="be-qms-muted">R07 Training records</div></div>';
    $add = esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix','be_action'=>'add_skill','emp'=>$employee_id], self::portal_url()));
    echo '<a class="be-qms-btn" href="'.$add.'">Add New Skill Record</a>';
    echo '</div>';

    $skills = get_posts([
      'post_type' => self::CPT_TRAINING,
      'post_status' => 'publish',
      'numberposts' => 200,
      'orderby' => 'date',
      'order' => 'DESC',
      'meta_key' => self::META_EMPLOYEE_LINK,
      'meta_value' => $employee_id,
    ]);

    echo '<table class="be-qms-table">';
    echo '<thead><tr><th>Course</th><th>Date of course</th><th>Renewal date</th><th>Actions</th></tr></thead><tbody>';
    if (!$skills) {
      echo '<tr><td colspan="4" class="be-qms-muted">No skill records yet.</td></tr>';
    } else {
      foreach ($skills as $s) {
        $sid = (int)$s->ID;
        $course = get_post_meta($sid, '_be_qms_training_course', true);
        $date_course = get_post_meta($sid, '_be_qms_training_date', true);
        $renew = get_post_meta($sid, '_be_qms_training_renewal', true);
        $edit = esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix','be_action'=>'edit_skill','emp'=>$employee_id,'id'=>$sid], self::portal_url()));
        $del  = esc_url(admin_url('admin-post.php?action=be_qms_delete&kind=training&id='.$sid.'&_wpnonce='.wp_create_nonce('be_qms_delete_'.$sid)));
        echo '<tr>';
        echo '<td>'.esc_html($course ?: $s->post_title).'</td>';
        echo '<td>'.esc_html($date_course ?: '—').'</td>';
        echo '<td>'.esc_html($renew ?: '—').'</td>';
        echo '<td class="be-qms-row">'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$edit.'">Edit</a>'
          .'<a class="be-qms-btn be-qms-btn-danger" href="'.$del.'" onclick="return confirm(\'Delete this skill record?\')">Delete</a>'
          .'</td>';
        echo '</tr>';
      }
    }
    echo '</tbody></table>';

    $back = esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix'], self::portal_url()));
    echo '<div class="be-qms-row" style="margin-top:12px"><a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">← Return to R07</a></div>';
  }

  private static function render_r07_skill_form($employee_id, $skill_id) {
    $emp = get_post($employee_id);
    if (!$emp || $emp->post_type !== self::CPT_EMPLOYEE) {
      echo '<div class="be-qms-muted">Employee not found.</div>';
      return;
    }

    $is_edit = $skill_id > 0;
    $course = '';
    $date_course = '';
    $renew = '';
    $desc = '';
    $att_ids = [];

    if ($is_edit) {
      $p = get_post($skill_id);
      if (!$p || $p->post_type !== self::CPT_TRAINING) {
        echo '<div class="be-qms-muted">Training record not found.</div>';
        return;
      }
      $course = get_post_meta($skill_id, '_be_qms_training_course', true);
      $date_course = get_post_meta($skill_id, '_be_qms_training_date', true);
      $renew = get_post_meta($skill_id, '_be_qms_training_renewal', true);
      $desc = get_post_meta($skill_id, '_be_qms_training_desc', true);
      $att_ids = get_post_meta($skill_id, '_be_qms_attachments', true);
      if (!is_array($att_ids)) $att_ids = [];
    }

    echo '<h3>'.($is_edit ? 'Edit skill record' : 'Add new skill record').'</h3>';
    echo '<div class="be-qms-muted">Employee: '.esc_html($emp->post_title).'</div>';

    $save_url = esc_url(admin_url('admin-post.php'));
    echo '<form method="post" action="'.$save_url.'" enctype="multipart/form-data">';
    echo '<input type="hidden" name="action" value="be_qms_save_training" />';
    wp_nonce_field('be_qms_save_training');
    echo '<input type="hidden" name="employee_id" value="'.esc_attr($employee_id).'" />';
    if ($is_edit) echo '<input type="hidden" name="id" value="'.esc_attr($skill_id).'" />';

    echo '<div class="be-qms-grid">';
    echo '<div class="be-qms-col-6"><label><strong>Course name</strong><br/>';
    echo '<input class="be-qms-input" type="text" name="course_name" value="'.esc_attr($course).'" required /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Date of course</strong><br/>';
    echo '<input class="be-qms-input be-qms-date" type="text" name="date_course" value="'.esc_attr($date_course).'" placeholder="YYYY-MM-DD" /></label></div>';

    echo '<div class="be-qms-col-6"><label><strong>Renewal date</strong><br/>';
    echo '<input class="be-qms-input be-qms-date" type="text" name="renewal_date" value="'.esc_attr($renew).'" placeholder="YYYY-MM-DD" /></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Description / certificates</strong><br/>';
    echo '<textarea class="be-qms-textarea" name="description">'.esc_textarea($desc).'</textarea></label></div>';

    echo '<div class="be-qms-col-12"><label><strong>Attach certificates</strong> <span class="be-qms-muted">(optional)</span><br/>';
    echo '<input type="file" name="attachments[]" multiple /></label></div>';
    echo '</div>';

    if ($is_edit && $att_ids) {
      echo '<h4 style="margin-top:14px">Existing attachments</h4><ul>';
      foreach ($att_ids as $aid) {
        $url = wp_get_attachment_url($aid);
        if (!$url) continue;
        $name = get_the_title($aid) ?: basename($url);
        echo '<li><label><input type="checkbox" name="remove_attachments[]" value="'.esc_attr($aid).'" /> Remove</label> &nbsp; <a class="be-qms-link" target="_blank" href="'.esc_url($url).'">'.esc_html($name).'</a></li>';
      }
      echo '</ul>';
    }

    echo '<div class="be-qms-row" style="margin-top:12px">';
    echo '<button class="be-qms-btn" type="submit">Save</button>';
    $back = esc_url(add_query_arg(['view'=>'records','type'=>'r07_training_matrix','be_action'=>'employee','id'=>$employee_id], self::portal_url()));
    echo '<a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">Cancel</a>';
    echo '</div>';
    echo '</form>';
  }

  public static function handle_save_employee() {
    self::require_staff();
    check_admin_referer('be_qms_save_employee');

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $name = sanitize_text_field($_POST['employee_name'] ?? '');
    if (!$name) wp_die('Missing employee name.');

    if ($id) {
      $p = get_post($id);
      if (!$p || $p->post_type !== self::CPT_EMPLOYEE) wp_die('Not found');
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

    $url = add_query_arg(['view'=>'records','type'=>'r07_training_matrix','be_action'=>'employee','id'=>$eid], self::portal_url());
    wp_safe_redirect($url);
    exit;
  }

  public static function handle_save_training() {
    self::require_staff();
    check_admin_referer('be_qms_save_training');

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $employee_id = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
    $course = sanitize_text_field($_POST['course_name'] ?? '');
    $date_course = sanitize_text_field($_POST['date_course'] ?? '');
    $renew = sanitize_text_field($_POST['renewal_date'] ?? '');
    $desc = wp_kses_post($_POST['description'] ?? '');

    if (!$employee_id) wp_die('Missing employee.');
    if (!$course) wp_die('Missing course name.');

    $title = $course;

    if ($id) {
      $p = get_post($id);
      if (!$p || $p->post_type !== self::CPT_TRAINING) wp_die('Not found');
      wp_update_post(['ID'=>$id,'post_title'=>$title]);
      $tid = $id;
    } else {
      $tid = wp_insert_post([
        'post_type' => self::CPT_TRAINING,
        'post_status' => 'publish',
        'post_title' => $title,
      ], true);
      if (is_wp_error($tid)) wp_die('Failed to save training record.');
    }

    update_post_meta($tid, self::META_EMPLOYEE_LINK, $employee_id);
    update_post_meta($tid, '_be_qms_training_course', $course);
    update_post_meta($tid, '_be_qms_training_date', $date_course);
    update_post_meta($tid, '_be_qms_training_renewal', $renew);
    update_post_meta($tid, '_be_qms_training_desc', $desc);

    // Attachment handling (merge + removals)
    $existing = get_post_meta($tid, '_be_qms_attachments', true);
    if (!is_array($existing)) $existing = [];

    $remove = array_map('intval', (array) ($_POST['remove_attachments'] ?? []));
    if ($remove) {
      $existing = array_values(array_diff($existing, $remove));
    }

    $new = self::handle_uploads('attachments');
    if ($new) $existing = array_values(array_unique(array_merge($existing, $new)));

    update_post_meta($tid, '_be_qms_attachments', $existing);

    $url = add_query_arg(['view'=>'records','type'=>'r07_training_matrix','be_action'=>'employee','id'=>$employee_id], self::portal_url());
    wp_safe_redirect($url);
    exit;
  }

  public static function handle_save_record() {
    self::require_staff();
    check_admin_referer('be_qms_save_record');

    $record_id = isset($_POST['record_id']) ? (int) $_POST['record_id'] : 0;
    $is_edit = $record_id > 0;

    $type = isset($_POST['record_type']) ? sanitize_key($_POST['record_type']) : '';
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $date = isset($_POST['record_date']) ? sanitize_text_field($_POST['record_date']) : '';
    $details = isset($_POST['details']) ? wp_kses_post($_POST['details']) : '';
    $actions = isset($_POST['actions']) ? wp_kses_post($_POST['actions']) : '';
    $project_id = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;

    if (!$type) {
      wp_die('Missing required fields.');
    }

    // R04 Tool Calibration uses structured fields (no generic Details required)
    if ($type === 'r04_tool_calibration') {
      $tool_item = sanitize_text_field($_POST['tool_item'] ?? '');
      $tool_description = sanitize_textarea_field($_POST['tool_description'] ?? '');
      $tool_requirements = sanitize_textarea_field($_POST['tool_requirements'] ?? '');
      $tool_date_purchased = sanitize_text_field($_POST['tool_date_purchased'] ?? '');
      $tool_date_calibrated = sanitize_text_field($_POST['tool_date_calibrated'] ?? '');
      $tool_next_due = sanitize_text_field($_POST['tool_next_due'] ?? '');
      if (!$tool_item) wp_die('Missing required tool item.');
      if (!$title) {
        $title = 'R04 Tool – ' . $tool_item;
      }
      // Allow empty details/actions for this type
      $details = $tool_description;
      $actions = $tool_requirements;
      $date = $tool_next_due ?: ($tool_date_calibrated ?: ($tool_date_purchased ?: date('Y-m-d')));
    } elseif ($type === 'r06_customer_complaints') {
      $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
      $complaint_date = sanitize_text_field($_POST['complaint_date'] ?? '');
      $nature = sanitize_textarea_field($_POST['nature'] ?? '');
      if (!$customer_name || !$complaint_date || !$nature) {
        wp_die('Missing required fields.');
      }
      $title = $title ?: 'R06 Complaint – ' . $customer_name . ' (' . $complaint_date . ')';
      $date = $complaint_date;
      $details = $nature;
      $actions = sanitize_textarea_field($_POST['actions_taken'] ?? '');
    } else {
      if (!$title || !$date || !$details) {
        wp_die('Missing required fields.');
      }
    }

    if ($is_edit) {
      if (!current_user_can('edit_post', $record_id)) wp_die('No permission.');
      $updated = wp_update_post([
        'ID' => $record_id,
        'post_title' => $title,
      ], true);
      if (is_wp_error($updated)) wp_die('Failed to update record.');
      $pid = $record_id;
    } else {
      $pid = wp_insert_post([
        'post_type' => self::CPT_RECORD,
        'post_status' => 'publish',
        'post_title' => $title,
      ], true);
      if (is_wp_error($pid)) wp_die('Failed to save record.');
    }

    wp_set_object_terms($pid, [$type], self::TAX_RECORD_TYPE, false);
    update_post_meta($pid, '_be_qms_record_date', $date);
    update_post_meta($pid, '_be_qms_details', $details);
    update_post_meta($pid, '_be_qms_actions', $actions);

    if ($type === 'r04_tool_calibration') {
      update_post_meta($pid, '_be_qms_tool_item', sanitize_text_field($_POST['tool_item'] ?? ''));
      update_post_meta($pid, '_be_qms_tool_serial', sanitize_text_field($_POST['tool_serial'] ?? ''));
      update_post_meta($pid, '_be_qms_tool_description', sanitize_textarea_field($_POST['tool_description'] ?? ''));
      update_post_meta($pid, '_be_qms_tool_requirements', sanitize_textarea_field($_POST['tool_requirements'] ?? ''));
      update_post_meta($pid, '_be_qms_tool_date_purchased', sanitize_text_field($_POST['tool_date_purchased'] ?? ''));
      update_post_meta($pid, '_be_qms_tool_date_calibrated', sanitize_text_field($_POST['tool_date_calibrated'] ?? ''));
      update_post_meta($pid, '_be_qms_tool_next_due', sanitize_text_field($_POST['tool_next_due'] ?? ''));
    }

    if ($type === 'r06_customer_complaints') {
      update_post_meta($pid, '_be_qms_r06_customer_name', sanitize_text_field($_POST['customer_name'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_address', sanitize_textarea_field($_POST['address'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_complaint_date', sanitize_text_field($_POST['complaint_date'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_contact_name', sanitize_text_field($_POST['contact_name'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_contact_email', sanitize_email($_POST['contact_email'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_contact_phone', sanitize_text_field($_POST['contact_phone'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_contact_mobile', sanitize_text_field($_POST['contact_mobile'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_nature', sanitize_text_field($_POST['nature'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_outcome', sanitize_text_field($_POST['outcome'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_immediate_action', sanitize_textarea_field($_POST['immediate_action'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_contacted_within_day', sanitize_text_field($_POST['contacted_within_day'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_contacted_reason', sanitize_textarea_field($_POST['contacted_reason'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_actions_taken', sanitize_textarea_field($_POST['actions_taken'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_further_action', sanitize_textarea_field($_POST['further_action'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_customer_satisfied', sanitize_text_field($_POST['customer_satisfied'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_reported_by', sanitize_text_field($_POST['reported_by'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_date_closed', sanitize_text_field($_POST['date_closed'] ?? ''));
      update_post_meta($pid, '_be_qms_r06_reported_title', sanitize_text_field($_POST['reported_title'] ?? ''));
    }

    if ($project_id > 0) {
      update_post_meta($pid, self::META_PROJECT_LINK, $project_id);
    } else {
      delete_post_meta($pid, self::META_PROJECT_LINK);
    }

    $existing = get_post_meta($pid, '_be_qms_attachments', true);
    if (!is_array($existing)) $existing = [];

    $remove = isset($_POST['remove_attachments']) ? (array) $_POST['remove_attachments'] : [];
    $remove = array_map('intval', $remove);
    if ($remove) {
      $existing = array_values(array_diff(array_map('intval', $existing), $remove));
    }

    $new_att_ids = self::handle_uploads('attachments');
    if ($new_att_ids) {
      $existing = array_values(array_unique(array_merge($existing, $new_att_ids)));
    }

    update_post_meta($pid, '_be_qms_attachments', $existing);

    $url = add_query_arg(['view'=>'records','be_action'=>'view','id'=>$pid,'type'=>$type], self::portal_url());
    wp_safe_redirect($url);
    exit;
  }

  /* -------------------------------
   * Projects
   * ------------------------------*/

  private static function render_projects() {
    $action = self::get_portal_action();

    if ($action === 'new') {
      self::render_project_form(0);
      return;
    }
    if ($action === 'edit' && !empty($_GET['id'])) {
      self::render_project_form((int)$_GET['id']);
      return;
    }
    if ($action === 'view' && !empty($_GET['id'])) {
      self::render_project_view((int)$_GET['id']);
      return;
    }

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div><strong>Projects</strong> <span class="be-qms-muted">(your installation/job files)</span></div>';
    $new_url = esc_url(add_query_arg(['view'=>'projects','be_action'=>'new'], self::portal_url()));
    echo '<a class="be-qms-btn" href="'.$new_url.'">Add New</a>';
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

        $view_url = esc_url(add_query_arg(['view'=>'projects','be_action'=>'view','id'=>$pid], self::portal_url()));
        $edit_url = esc_url(add_query_arg(['view'=>'projects','be_action'=>'edit','id'=>$pid], self::portal_url()));
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

    $title = '';
    $customer = '';
    $address = '';
    $pv_kwp = '';
    $bess_kwh = '';
    $notes = '';
    $existing_att_ids = [];

    if ($is_edit) {
      $p = get_post($id);
      if (!$p || $p->post_type !== self::CPT_PROJECT) {
        echo '<div class="be-qms-muted">Project not found.</div>';
        return;
      }
      if (!current_user_can('edit_post', $id)) {
        wp_die('You do not have permission to edit this project.');
      }

      $title = $p->post_title;
      $customer = (string) get_post_meta($id, '_be_qms_customer', true);
      $address  = (string) get_post_meta($id, '_be_qms_address', true);
      $pv_kwp   = (string) get_post_meta($id, '_be_qms_pv_kwp', true);
      $bess_kwh = (string) get_post_meta($id, '_be_qms_bess_kwh', true);
      $notes    = (string) get_post_meta($id, '_be_qms_notes', true);
      $existing_att_ids = get_post_meta($id, '_be_qms_evidence', true);
      if (!is_array($existing_att_ids)) $existing_att_ids = [];
    }

    echo '<h3>'.($is_edit ? 'Edit project' : 'New project').'</h3>';
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data">';
    echo '<input type="hidden" name="action" value="be_qms_save_project" />';
    wp_nonce_field('be_qms_save_project');
    if ($is_edit) {
      echo '<input type="hidden" name="project_id" value="'.esc_attr($id).'" />';
    }

    echo '<div class="be-qms-grid">';

    echo '<div class="be-qms-col-12"><label><strong>Project name</strong><br/>';
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
    echo '<textarea class="be-qms-textarea" name="notes">'.esc_textarea($notes).'</textarea></label></div>';

    echo '<div class="be-qms-col-12">';
    echo '<label><strong>Evidence uploads</strong> <span class="be-qms-muted">(multiple files)</span></label>';

    if ($is_edit && !empty($existing_att_ids)) {
      echo '<div class="be-qms-muted" style="margin:6px 0 8px 0">Tick any files you want to remove, and/or upload more below.</div>';
      echo '<ul style="margin:0;padding-left:18px">';
      foreach ($existing_att_ids as $aid) {
        $url = wp_get_attachment_url($aid);
        if (!$url) continue;
        $name = get_the_title($aid) ?: basename((string)$url);
        echo '<li style="margin:6px 0">'
          .'<label style="display:flex;gap:10px;align-items:center">'
          .'<input type="checkbox" name="remove_evidence[]" value="'.esc_attr((int)$aid).'">'
          .'<a class="be-qms-link" href="'.esc_url($url).'" target="_blank">'.esc_html($name).'</a>'
          .'</label>'
          .'</li>';
      }
      echo '</ul>';
    } else {
      echo '<div class="be-qms-muted" style="margin:6px 0 8px 0">None yet.</div>';
    }

    echo '<div style="margin-top:8px"><input type="file" name="evidence[]" multiple /></div>';
    echo '</div>';

    echo '</div>';

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
    $add_record_url = esc_url(add_query_arg(['view'=>'records','be_action'=>'new','project_id'=>$id], self::portal_url()));
    $edit_url = esc_url(add_query_arg(['view'=>'projects','be_action'=>'edit','id'=>$id], self::portal_url()));

    echo '<div class="be-qms-row" style="justify-content:space-between">';
    echo '<div><h3 style="margin:0">'.esc_html($p->post_title).'</h3><div class="be-qms-muted">Project file (assessment/job)</div></div>';
    echo '<div class="be-qms-row">'
      .'<a class="be-qms-btn" href="'.$add_record_url.'">Add record for this project</a>'
      .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$edit_url.'">Edit project</a>'
      .'<a class="be-qms-btn be-qms-btn-secondary" target="_blank" href="'.$print_url.'">Print / Save PDF</a>'
      .'</div>';
    echo '</div>';

    echo '<div class="be-qms-card-inner">';
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

    // Linked records
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
      echo '<div class="be-qms-muted">No linked records yet. Use “Add record for this project”.</div>';
    } else {
      echo '<table class="be-qms-table" style="margin-top:10px">';
      echo '<thead><tr><th>Date</th><th>Type</th><th>Title</th><th>Actions</th></tr></thead><tbody>';
      while ($rq->have_posts()) {
        $rq->the_post();
        $rid = get_the_ID();
        $record_date = get_post_meta($rid, '_be_qms_record_date', true) ?: get_the_date('Y-m-d');
        $terms = get_the_terms($rid, self::TAX_RECORD_TYPE);
        $slug = ($terms && !is_wp_error($terms)) ? $terms[0]->slug : '';
        $defs = self::record_type_definitions();
        $type_name = ($slug && isset($defs[$slug])) ? $defs[$slug] : (($terms && !is_wp_error($terms)) ? $terms[0]->name : '-');

        $view_url = esc_url(add_query_arg(['view'=>'records','be_action'=>'view','id'=>$rid,'type'=>($slug ?: array_key_first($defs))], self::portal_url()));
        $edit_url = esc_url(add_query_arg(['view'=>'records','be_action'=>'edit','id'=>$rid,'type'=>($slug ?: array_key_first($defs))], self::portal_url()));
        $doc_url  = esc_url(add_query_arg(['be_qms_export'=>'doc','post'=>$rid], self::portal_url()));
        $print_url= esc_url(add_query_arg(['be_qms_export'=>'print','post'=>$rid], self::portal_url()));

        echo '<tr>';
        echo '<td>'.esc_html($record_date).'</td>';
        echo '<td>'.esc_html($type_name).'</td>';
        echo '<td><a class="be-qms-link" href="'.$view_url.'">'.esc_html(get_the_title()).'</a></td>';
        echo '<td class="be-qms-row">'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$view_url.'">View</a>'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$edit_url.'">Edit</a>'
          .'<a class="be-qms-btn be-qms-btn-secondary" href="'.$doc_url.'">DOC</a>'
          .'<a class="be-qms-btn be-qms-btn-secondary" target="_blank" href="'.$print_url.'">Print/PDF</a>'
          .'</td>';
        echo '</tr>';
      }
      wp_reset_postdata();
      echo '</tbody></table>';
    }

    echo '</div>';

    $back = esc_url(add_query_arg(['view'=>'projects'], self::portal_url()));
    echo '<div class="be-qms-row" style="margin-top:12px"><a class="be-qms-btn be-qms-btn-secondary" href="'.$back.'">Return to Projects</a></div>';
  }

  public static function handle_save_project() {
    self::require_staff();
    check_admin_referer('be_qms_save_project');

    $project_id = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;
    $is_edit = $project_id > 0;

    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    if (!$title) wp_die('Missing project name.');

    if ($is_edit) {
      if (!current_user_can('edit_post', $project_id)) wp_die('No permission.');
      $updated = wp_update_post([
        'ID' => $project_id,
        'post_title' => $title,
      ], true);
      if (is_wp_error($updated)) wp_die('Failed to update project.');
      $pid = $project_id;
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

    $existing = get_post_meta($pid, '_be_qms_evidence', true);
    if (!is_array($existing)) $existing = [];

    $remove = isset($_POST['remove_evidence']) ? (array) $_POST['remove_evidence'] : [];
    $remove = array_map('intval', $remove);
    if ($remove) {
      $existing = array_values(array_diff(array_map('intval', $existing), $remove));
    }

    $new_att_ids = self::handle_uploads('evidence');
    if ($new_att_ids) {
      $existing = array_values(array_unique(array_merge($existing, $new_att_ids)));
    }

    update_post_meta($pid, '_be_qms_evidence', $existing);

    $url = add_query_arg(['view'=>'projects','be_action'=>'view','id'=>$pid], self::portal_url());
    wp_safe_redirect($url);
    exit;
  }

  /* -------------------------------
   * Profile + Delete + Uploads
   * ------------------------------*/

  public static function handle_save_profile() {
    self::require_staff();
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'be_qms_profile')) {
      wp_die('Invalid nonce.');
    }
    $profile = [
      'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
      'responsible_person' => sanitize_text_field($_POST['responsible_person'] ?? ''),
      'address' => sanitize_text_field($_POST['address'] ?? ''),
      'phone' => sanitize_text_field($_POST['phone'] ?? ''),
      'email' => sanitize_email($_POST['email'] ?? ''),
      'company_reg' => sanitize_text_field($_POST['company_reg'] ?? ''),
      'mcs_reg' => sanitize_text_field($_POST['mcs_reg'] ?? ''),
      'consumer_code' => sanitize_text_field($_POST['consumer_code'] ?? ''),
    ];
    update_option('be_qms_profile', $profile, false);

    $url = add_query_arg(['view'=>'references','saved'=>'1'], self::portal_url());
    wp_safe_redirect($url);
    exit;
  }

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

    // Cascade: deleting an employee removes their training records
    if ($kind === 'employee') {
      $tq = new WP_Query([
        'post_type' => self::CPT_TRAINING,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_key' => self::META_EMPLOYEE_LINK,
        'meta_value' => $id,
      ]);
      if ($tq->have_posts()) {
        foreach ($tq->posts as $tid) {
          wp_delete_post((int)$tid, true);
        }
      }
    }

    wp_delete_post($id, true);

    $back_view = ($kind === 'project') ? 'projects' : 'records';
    $url = add_query_arg(['view'=>$back_view], self::portal_url());
    wp_safe_redirect($url);
    exit;
  }

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

  private static function portal_url() {
    $page = get_page_by_path('qms-portal');
    if ($page) return get_permalink($page);
    return home_url('/qms-portal/');
  }

  /* -------------------------------
   * Exports (DOC / Print) + bundled refs
   * ------------------------------*/

  public static function maybe_handle_exports() {
    if (!empty($_GET['be_qms_ref'])) {
      self::require_staff();
      $key = sanitize_key($_GET['be_qms_ref']);
      $map = [
        'manual_docx' => 'assets/documented-procedure-manual-baltic.docx',
        'manual_pdf'  => 'assets/documented-procedure-manual-baltic.pdf',
      ];
      if (empty($map[$key])) wp_die('Unknown reference file');
      $file = plugin_dir_path(__FILE__) . $map[$key];
      if (!file_exists($file)) wp_die('File missing');
      $mime = ($key === 'manual_pdf') ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
      header('Content-Type: ' . $mime);
      header('Content-Disposition: attachment; filename="' . basename($file) . '"');
      header('Content-Length: ' . filesize($file));
      readfile($file);
      exit;
    }

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
      $slug = ($terms && !is_wp_error($terms)) ? $terms[0]->slug : '';
      $defs = self::record_type_definitions();
      $type_name = ($slug && isset($defs[$slug])) ? $defs[$slug] : (($terms && !is_wp_error($terms)) ? $terms[0]->name : '-');

      $date = get_post_meta($p->ID, '_be_qms_record_date', true) ?: get_the_date('Y-m-d', $p);
      $details = get_post_meta($p->ID, '_be_qms_details', true);
      $actions = get_post_meta($p->ID, '_be_qms_actions', true);
      $linked_project = (int) get_post_meta($p->ID, self::META_PROJECT_LINK, true);
      $proj_bits = '';
      if ($linked_project) {
        $proj_bits = ' • Project: ' . esc_html(get_the_title($linked_project) ?: ('Project #'.$linked_project));
      }

      echo '<div class="muted">Type: '.esc_html($type_name).' • Date: '.esc_html($date).$proj_bits.'</div>';

      if ($slug === 'r06_customer_complaints') {
        $customer_name = get_post_meta($p->ID, '_be_qms_r06_customer_name', true);
        $address = get_post_meta($p->ID, '_be_qms_r06_address', true);
        $complaint_date = get_post_meta($p->ID, '_be_qms_r06_complaint_date', true);
        $contact_name = get_post_meta($p->ID, '_be_qms_r06_contact_name', true);
        $contact_email = get_post_meta($p->ID, '_be_qms_r06_contact_email', true);
        $contact_phone = get_post_meta($p->ID, '_be_qms_r06_contact_phone', true);
        $contact_mobile = get_post_meta($p->ID, '_be_qms_r06_contact_mobile', true);
        $nature = get_post_meta($p->ID, '_be_qms_r06_nature', true);
        $outcome = get_post_meta($p->ID, '_be_qms_r06_outcome', true);
        $immediate_action = get_post_meta($p->ID, '_be_qms_r06_immediate_action', true);
        $contacted_within_day = get_post_meta($p->ID, '_be_qms_r06_contacted_within_day', true);
        $contacted_reason = get_post_meta($p->ID, '_be_qms_r06_contacted_reason', true);
        $actions_taken = get_post_meta($p->ID, '_be_qms_r06_actions_taken', true);
        $further_action = get_post_meta($p->ID, '_be_qms_r06_further_action', true);
        $customer_satisfied = get_post_meta($p->ID, '_be_qms_r06_customer_satisfied', true);
        $reported_by = get_post_meta($p->ID, '_be_qms_r06_reported_by', true);
        $date_closed = get_post_meta($p->ID, '_be_qms_r06_date_closed', true);
        $reported_title = get_post_meta($p->ID, '_be_qms_r06_reported_title', true);

        echo '<table>';
        echo '<tr><th>Customer Name</th><td>'.esc_html($customer_name).'</td></tr>';
        echo '<tr><th>Address</th><td>'.wpautop(esc_html($address)).'</td></tr>';
        echo '<tr><th>Date of Complaint</th><td>'.esc_html($complaint_date).'</td></tr>';
        echo '<tr><th>Contact&#039;s Name</th><td>'.esc_html($contact_name).'</td></tr>';
        echo '<tr><th>E-mail</th><td>'.esc_html($contact_email).'</td></tr>';
        echo '<tr><th>Telephone</th><td>'.esc_html($contact_phone).'</td></tr>';
        echo '<tr><th>Mobile</th><td>'.esc_html($contact_mobile).'</td></tr>';
        echo '<tr><th>Nature of Complaint</th><td>'.esc_html($nature).'</td></tr>';
        echo '<tr><th>Outcome</th><td>'.esc_html($outcome).'</td></tr>';
        echo '<tr><th>Immediate Action Requested</th><td>'.wpautop(esc_html($immediate_action)).'</td></tr>';
        echo '<tr><th>Customer contacted within 1 working day?</th><td>'.esc_html($contacted_within_day).'</td></tr>';
        echo '<tr><th>If not, why not?</th><td>'.wpautop(esc_html($contacted_reason)).'</td></tr>';
        echo '<tr><th>Actions taken to resolve complaint</th><td>'.wpautop(esc_html($actions_taken)).'</td></tr>';
        echo '<tr><th>Further Action Required</th><td>'.wpautop(esc_html($further_action)).'</td></tr>';
        echo '<tr><th>Is Customer satisfied with result?</th><td>'.esc_html($customer_satisfied).'</td></tr>';
        echo '<tr><th>Reported By</th><td>'.esc_html($reported_by).'</td></tr>';
        echo '<tr><th>Date Closed</th><td>'.esc_html($date_closed).'</td></tr>';
        echo '<tr><th>Title</th><td>'.esc_html($reported_title).'</td></tr>';
        echo '</table>';
      } else {
        echo '<h2>Details</h2><div>'.wpautop(esc_html($details)).'</div>';
        if (!empty($actions)) {
          echo '<h2>Actions</h2><div>'.wpautop(esc_html($actions)).'</div>';
        }
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
      $slug = ($terms && !is_wp_error($terms)) ? $terms[0]->slug : '';
      $defs = self::record_type_definitions();
      $type_name = ($slug && isset($defs[$slug])) ? $defs[$slug] : (($terms && !is_wp_error($terms)) ? $terms[0]->name : '-');

      $date = get_post_meta($p->ID, '_be_qms_record_date', true) ?: get_the_date('Y-m-d', $p);
      $details = get_post_meta($p->ID, '_be_qms_details', true);
      $actions = get_post_meta($p->ID, '_be_qms_actions', true);

      echo '<div class="muted">Type: '.esc_html($type_name).' • Date: '.esc_html($date).'</div>';

      if ($slug === 'r06_customer_complaints') {
        $customer_name = get_post_meta($p->ID, '_be_qms_r06_customer_name', true);
        $address = get_post_meta($p->ID, '_be_qms_r06_address', true);
        $complaint_date = get_post_meta($p->ID, '_be_qms_r06_complaint_date', true);
        $contact_name = get_post_meta($p->ID, '_be_qms_r06_contact_name', true);
        $contact_email = get_post_meta($p->ID, '_be_qms_r06_contact_email', true);
        $contact_phone = get_post_meta($p->ID, '_be_qms_r06_contact_phone', true);
        $contact_mobile = get_post_meta($p->ID, '_be_qms_r06_contact_mobile', true);
        $nature = get_post_meta($p->ID, '_be_qms_r06_nature', true);
        $outcome = get_post_meta($p->ID, '_be_qms_r06_outcome', true);
        $immediate_action = get_post_meta($p->ID, '_be_qms_r06_immediate_action', true);
        $contacted_within_day = get_post_meta($p->ID, '_be_qms_r06_contacted_within_day', true);
        $contacted_reason = get_post_meta($p->ID, '_be_qms_r06_contacted_reason', true);
        $actions_taken = get_post_meta($p->ID, '_be_qms_r06_actions_taken', true);
        $further_action = get_post_meta($p->ID, '_be_qms_r06_further_action', true);
        $customer_satisfied = get_post_meta($p->ID, '_be_qms_r06_customer_satisfied', true);
        $reported_by = get_post_meta($p->ID, '_be_qms_r06_reported_by', true);
        $date_closed = get_post_meta($p->ID, '_be_qms_r06_date_closed', true);
        $reported_title = get_post_meta($p->ID, '_be_qms_r06_reported_title', true);

        echo '<h2>Customer Details</h2>';
        echo '<ul>';
        echo '<li><strong>Customer Name:</strong> '.esc_html($customer_name).'</li>';
        echo '<li><strong>Address:</strong> '.esc_html($address).'</li>';
        echo '<li><strong>Date of Complaint:</strong> '.esc_html($complaint_date).'</li>';
        echo '</ul>';
        echo '<h2>Contact</h2>';
        echo '<ul>';
        echo '<li><strong>Contact&#039;s Name:</strong> '.esc_html($contact_name).'</li>';
        echo '<li><strong>E-mail:</strong> '.esc_html($contact_email).'</li>';
        echo '<li><strong>Telephone:</strong> '.esc_html($contact_phone).'</li>';
        echo '<li><strong>Mobile:</strong> '.esc_html($contact_mobile).'</li>';
        echo '</ul>';
        echo '<h2>Complaint</h2>';
        echo '<ul>';
        echo '<li><strong>Nature of Complaint:</strong> '.esc_html($nature).'</li>';
        echo '<li><strong>Outcome:</strong> '.esc_html($outcome).'</li>';
        echo '</ul>';
        if (!empty($immediate_action)) {
          echo '<h2>Immediate Action Requested</h2><div>'.wpautop(esc_html($immediate_action)).'</div>';
        }
        echo '<h2>Contacted Within 1 Working Day</h2>';
        echo '<ul>';
        echo '<li><strong>Customer contacted within 1 working day?</strong> '.esc_html($contacted_within_day).'</li>';
        echo '<li><strong>If not, why not?</strong> '.esc_html($contacted_reason).'</li>';
        echo '</ul>';
        if (!empty($actions_taken)) {
          echo '<h2>Actions Taken</h2><div>'.wpautop(esc_html($actions_taken)).'</div>';
        }
        if (!empty($further_action)) {
          echo '<h2>Further Action Required</h2><div>'.wpautop(esc_html($further_action)).'</div>';
        }
        echo '<h2>Outcome Confirmation</h2>';
        echo '<ul>';
        echo '<li><strong>Is Customer satisfied with result?</strong> '.esc_html($customer_satisfied).'</li>';
        echo '<li><strong>Reported By:</strong> '.esc_html($reported_by).'</li>';
        echo '<li><strong>Date Closed:</strong> '.esc_html($date_closed).'</li>';
        echo '<li><strong>Title:</strong> '.esc_html($reported_title).'</li>';
        echo '</ul>';
      } else {
        echo '<h2>Details</h2><div>'.wpautop(esc_html($details)).'</div>';
        if (!empty($actions)) {
          echo '<h2>Actions</h2><div>'.wpautop(esc_html($actions)).'</div>';
        }
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
}

register_activation_hook(__FILE__, ['BE_QMS_Portal', 'on_activate']);
add_action('plugins_loaded', ['BE_QMS_Portal', 'init']);
