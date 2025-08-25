<?php
/*
Plugin Name: Delta Backup
Description: Advanced file backup and management system for WordPress
Version: 1.0.0
Author: Alireza Fatemi
Author URI: https://alirezafatemi.ir
Plugin URI: https://github.com/deveguru
*/

if (!defined('ABSPATH')) {
    exit;
}

class DeltaBackupPlugin {
    private $backup_dir;
    private $backup_url;
    
    public function __construct() {
        $upload_dir_info = wp_upload_dir();
        $this->backup_dir = $upload_dir_info['basedir'] . '/Delta-Force-Backup/';
        $this->backup_url = $upload_dir_info['baseurl'] . '/Delta-Force-Backup/';
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_dfb_get_files', array($this, 'ajax_get_files'));
        add_action('wp_ajax_dfb_create_backup', array($this, 'ajax_create_backup'));
        add_action('wp_ajax_dfb_get_backups', array($this, 'ajax_get_backups'));
        add_action('wp_ajax_dfb_download_backup', array($this, 'ajax_download_backup'));
        add_action('wp_ajax_dfb_send_email', array($this, 'ajax_send_email'));
        add_action('wp_ajax_dfb_delete_backup', array($this, 'ajax_delete_backup'));
        add_action('dfb_scheduled_backup', array($this, 'run_scheduled_backup'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
        }
        if (!file_exists($this->backup_dir . 'index.php')) {
            @file_put_contents($this->backup_dir . 'index.php', '<?php // Silence is golden.');
        }
        if (!file_exists($this->backup_dir . '.htaccess')) {
            @file_put_contents($this->backup_dir . '.htaccess', 'Options -Indexes');
        }
    }
    
    public function activate() {
        $this->init();
        if (!wp_next_scheduled('dfb_scheduled_backup')) {
            wp_schedule_event(time(), 'hourly', 'dfb_scheduled_backup');
        }
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('dfb_scheduled_backup');
    }
    
    public function add_admin_menu() {
        add_menu_page('Delta Backup', 'Delta Backup', 'manage_options', 'delta-backup', array($this, 'admin_page'), 'dashicons-backup', 30);
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Delta Backup</h1>
            <div class="dfb-container">
                <div class="dfb-tabs">
                    <button class="dfb-tab-btn active" data-tab="file-manager">File Manager</button>
                    <button class="dfb-tab-btn" data-tab="backups">Backups</button>
                </div>
                <div id="file-manager" class="dfb-tab-content active">
                    <div class="dfb-toolbar">
                        <div class="dfb-path"><span>Path: </span><span id="current-path">/</span></div>
                        <div class="dfb-selection-info"><span id="selection-count">0 files selected</span><button id="create-backup-btn" class="dfb-btn dfb-btn-primary" style="display:none;">Create Backup</button></div>
                    </div>
                    <div id="file-list" class="dfb-file-list"><div class="dfb-loading"><div class="dfb-spinner"></div></div></div>
                </div>
                <div id="backups" class="dfb-tab-content">
                    <div class="dfb-backup-header"><h3>Backup Management</h3><button id="refresh-backups" class="dfb-btn dfb-btn-secondary">Refresh</button></div>
                    <div id="backup-list" class="dfb-backup-list"><div class="dfb-loading"><div class="dfb-spinner"></div></div></div>
                </div>
            </div>
        </div>
        <div id="backup-modal" class="dfb-modal">
            <div class="dfb-modal-content">
                <div class="dfb-modal-header"><h3>Create Backup</h3><span class="dfb-close">&times;</span></div>
                <div class="dfb-modal-body">
                    <div class="dfb-form-group"><label>Backup Name:</label><input type="text" id="backup-name" class="dfb-input" placeholder="Enter backup name"></div>
                    <div class="dfb-form-group">
                        <label>Repeat Every:</label>
                        <select id="backup-interval" class="dfb-select">
                            <option value="once">Once (No Repeat)</option><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div id="selected-files-preview" class="dfb-selected-files"><h4>Selected Files:</h4><ul id="selected-files-list"></ul></div>
                </div>
                <div class="dfb-modal-footer"><button id="cancel-backup" class="dfb-btn dfb-btn-secondary">Cancel</button><button id="finish-backup" class="dfb-btn dfb-btn-primary">Finish</button></div>
            </div>
        </div>
        <style>
        .dfb-container{background:#fff;border:1px solid #ccd0d4;border-radius:4px;margin-top:20px}.dfb-tabs{display:flex;border-bottom:1px solid #ccd0d4;background:#f9f9f9}.dfb-tab-btn{padding:15px 25px;border:none;background:transparent;cursor:pointer;font-size:14px;font-weight:500;color:#555;transition:all .3s}.dfb-tab-btn:hover{background:#e9e9e9}.dfb-tab-btn.active{background:#fff;color:#0073aa;border-bottom:2px solid #0073aa}.dfb-tab-content{display:none;padding:20px}.dfb-tab-content.active{display:block}.dfb-toolbar{display:flex;justify-content:space-between;align-items:center;padding:15px;background:#f8f9fa;border:1px solid #dee2e6;border-radius:4px;margin-bottom:20px}.dfb-path{font-weight:500;color:#495057}.dfb-selection-info{display:flex;align-items:center;gap:15px}#selection-count{font-weight:500;color:#0073aa}.dfb-file-list{border:1px solid #dee2e6;border-radius:4px;max-height:500px;overflow-y:auto}.dfb-file-item{display:flex;align-items:center;padding:12px 15px;border-bottom:1px solid #f0f0f0;cursor:pointer;transition:all .2s}.dfb-file-item:hover{background:#f8f9fa}.dfb-file-item.selected{background:#e3f2fd;border-color:#2196f3}.dfb-file-item:last-child{border-bottom:none}.dfb-file-icon{margin-right:12px;font-size:18px}.dfb-folder{color:#ffc107}.dfb-file{color:#6c757d}.dfb-file-info{flex:1}.dfb-file-name{font-weight:500;color:#212529}.dfb-file-details{font-size:12px;color:#6c757d;margin-top:2px}.dfb-backup-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.dfb-backup-list{border:1px solid #dee2e6;border-radius:4px;max-height:500px;overflow-y:auto}.dfb-backup-item{display:flex;justify-content:space-between;align-items:center;padding:15px;border-bottom:1px solid #f0f0f0}.dfb-backup-item:last-child{border-bottom:none}.dfb-backup-info h4{margin:0 0 5px;color:#212529}.dfb-backup-meta{font-size:12px;color:#6c757d}.dfb-backup-actions{display:flex;gap:8px}.dfb-btn{padding:8px 16px;border:none;border-radius:4px;cursor:pointer;font-size:13px;font-weight:500;text-decoration:none;display:inline-block;transition:all .2s}.dfb-btn-primary{background:#0073aa;color:#fff}.dfb-btn-primary:hover{background:#005a87}.dfb-btn-secondary{background:#6c757d;color:#fff}.dfb-btn-secondary:hover{background:#545b62}.dfb-btn-success{background:#28a745;color:#fff}.dfb-btn-success:hover{background:#218838}.dfb-btn-warning{background:#ffc107;color:#212529}.dfb-btn-warning:hover{background:#e0a800}.dfb-btn-danger{background:#dc3545;color:#fff}.dfb-btn-danger:hover{background:#c82333}.dfb-btn-sm{padding:6px 12px;font-size:12px}.dfb-modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background-color:rgba(0,0,0,.5)}.dfb-modal-content{background-color:#fefefe;margin:5% auto;border-radius:8px;width:600px;max-width:90%;box-shadow:0 4px 20px rgba(0,0,0,.15)}.dfb-modal-header{display:flex;justify-content:space-between;align-items:center;padding:20px;border-bottom:1px solid #dee2e6}.dfb-modal-header h3{margin:0;color:#212529}.dfb-close{color:#aaa;font-size:28px;font-weight:700;cursor:pointer}.dfb-close:hover{color:#000}.dfb-modal-body{padding:20px}.dfb-form-group{margin-bottom:20px}.dfb-form-group label{display:block;margin-bottom:8px;font-weight:500;color:#212529}.dfb-input,.dfb-select{width:100%;padding:10px;border:1px solid #ced4da;border-radius:4px;font-size:14px}.dfb-input:focus,.dfb-select:focus{outline:0;border-color:#0073aa;box-shadow:0 0 0 2px rgba(0,115,170,.2)}.dfb-selected-files{background:#f8f9fa;border:1px solid #dee2e6;border-radius:4px;padding:15px}.dfb-selected-files h4{margin:0 0 10px;color:#212529}.dfb-selected-files ul{margin:0;padding-left:20px;max-height:150px;overflow-y:auto}.dfb-selected-files li{margin-bottom:5px;color:#495057}.dfb-modal-footer{display:flex;justify-content:flex-end;gap:10px;padding:20px;border-top:1px solid #dee2e6}.dfb-loading{display:flex;justify-content:center;align-items:center;padding:40px}.dfb-spinner{border:4px solid #f3f3f3;border-top:4px solid #0073aa;border-radius:50%;width:40px;height:40px;animation:spin 1s linear infinite}@keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}.dfb-empty{text-align:center;padding:40px;color:#6c757d}
        </style>
        <script>
        jQuery(document).ready(function($){let selectedFiles=[],currentPath="";$(".dfb-tab-btn").click(function(){$(".dfb-tab-btn").removeClass("active"),$(".dfb-tab-content").removeClass("active"),$(this).addClass("active"),$("#"+$(this).data("tab")).addClass("active"),"file-manager"===$(this).data("tab")?loadFiles(""): "backups"===$(this).data("tab")&&loadBackups()});function loadFiles(t){currentPath=t,$("#current-path").text(t||"/"),$("#file-list").html('<div class="dfb-loading"><div class="dfb-spinner"></div></div>'),$.ajax({url:ajaxurl,type:"POST",data:{action:"dfb_get_files",path:t,nonce:'<?php echo wp_create_nonce("dfb_nonce"); ?>'},success:function(e){e.success?displayFiles(e.data.files,t):$("#file-list").html('<div class="dfb-empty">Error loading files</div>')}})}function displayFiles(e,t){let a="";if(t){let e=t.split("/").slice(0,-1).join("/");a+='<div class="dfb-file-item" data-path="'+e+'" data-type="parent">',a+='<span class="dfb-file-icon dfb-folder">📁</span>',a+='<div class="dfb-file-info">',a+='<div class="dfb-file-name">..</div>',a+="</div>",a+="</div>"}e.forEach(function(e){a+='<div class="dfb-file-item" data-path="'+e.path+'" data-type="'+e.type+'">',a+='<span class="dfb-file-icon '+("dir"===e.type?'dfb-folder">📁':'dfb-file">📄')+"</span>",a+='<div class="dfb-file-info">',a+='<div class="dfb-file-name">'+e.name+"</div>",a+='<div class="dfb-file-details">'+e.size+" | "+e.modified+"</div>",a+="</div>",a+="</div>"}),$("#file-list").html(a||'<div class="dfb-empty">No files found</div>')}function updateSelection(){selectedFiles=[],$(".dfb-file-item.selected").each(function(){"file"===$(this).data("type")&&selectedFiles.push({path:$(this).data("path"),name:$(this).find(".dfb-file-name").text()})});let e=selectedFiles.length;$("#selection-count").text(e+" file"+(1!==e?"s":"")+" selected"),e>0?$("#create-backup-btn").show():$("#create-backup-btn").hide()}function loadBackups(){$("#backup-list").html('<div class="dfb-loading"><div class="dfb-spinner"></div></div>'),$.ajax({url:ajaxurl,type:"POST",data:{action:"dfb_get_backups",nonce:'<?php echo wp_create_nonce("dfb_nonce"); ?>'},success:function(e){e.success?displayBackups(e.data):$("#backup-list").html('<div class="dfb-empty">Error loading backups</div>')}})}function displayBackups(e){if(0===e.length)return void $("#backup-list").html('<div class="dfb-empty">No backups found</div>');let t="";e.forEach(function(e){t+='<div class="dfb-backup-item">',t+='<div class="dfb-backup-info">',t+="<h4>"+e.name+"</h4>",t+='<div class="dfb-backup-meta">Created: '+e.date+" | Size: "+e.size,e.interval&&"once"!==e.interval&&(t+=" | Repeat: "+e.interval),t+="</div>",t+="</div>",t+='<div class="dfb-backup-actions">',t+='<button class="dfb-btn dfb-btn-success dfb-btn-sm download-backup" data-file="'+e.file+'">Download</button>',t+='<button class="dfb-btn dfb-btn-warning dfb-btn-sm email-backup" data-file="'+e.file+'">Email Link</button>',t+='<button class="dfb-btn dfb-btn-danger dfb-btn-sm delete-backup" data-file="'+e.file+'">Delete</button>',t+="</div>",t+="</div>"}),$("#backup-list").html(t)}$(document).on("click",".dfb-file-item",function(){"dir"===$(this).data("type")||"parent"===$(this).data("type")?loadFiles($(this).data("path")):($(this).toggleClass("selected"),updateSelection())}),$("#create-backup-btn").click(function(){if(0!==selectedFiles.length){$("#backup-name").val(""),$("#backup-interval").val("once");let e="";selectedFiles.forEach(function(t){e+="<li>"+t.name+"</li>"}),$("#selected-files-list").html(e),$("#backup-modal").show()}}),$(".dfb-close, #cancel-backup").click(function(){$("#backup-modal").hide()}),$("#finish-backup").click(function(){let e=$("#backup-name").val().trim(),t=$("#backup-interval").val();e?( $(this).prop("disabled",!0).text("Creating..."),$.ajax({url:ajaxurl,type:"POST",data:{action:"dfb_create_backup",name:e,interval:t,files:selectedFiles,nonce:'<?php echo wp_create_nonce("dfb_nonce"); ?>'},success:function(e){e.success?(alert("Backup created successfully!"),$("#backup-modal").hide(),selectedFiles=[],$(".dfb-file-item").removeClass("selected"),updateSelection()):alert("Error: "+e.data)},complete:function(){$("#finish-backup").prop("disabled",!1).text("Finish")}})):alert("Please enter a backup name")}),$(document).on("click",".download-backup",function(){let e=$(this).data("file");window.open(ajaxurl+"?action=dfb_download_backup&file="+encodeURIComponent(e)+"&nonce="+'<?php echo wp_create_nonce("dfb_nonce"); ?>',"_blank")}),$(document).on("click",".email-backup",function(){let e=$(this).data("file"),t=prompt("Enter email address:");if(t){let a=$(this);a.prop("disabled",!0).text("Sending..."),$.ajax({url:ajaxurl,type:"POST",data:{action:"dfb_send_email",file:e,email:t,nonce:'<?php echo wp_create_nonce("dfb_nonce"); ?>'},success:function(e){alert(e.success?"Email sent successfully!":"Error: "+e.data)},complete:function(){a.prop("disabled",!1).text("Email Link")}}) }else{ e.stopPropagation()}}),$(document).on("click",".delete-backup",function(){if(confirm("Are you sure you want to delete this backup?")){let e=$(this).data("file");$.ajax({url:ajaxurl,type:"POST",data:{action:"dfb_delete_backup",file:e,nonce:'<?php echo wp_create_nonce("dfb_nonce"); ?>'},success:function(e){e.success?loadBackups():alert("Error: "+e.data)}})}}),$("#refresh-backups").click(function(){loadBackups()}),loadFiles("")});
        </script>
        <?php
    }
    
    public function ajax_get_files() {
        check_ajax_referer('dfb_nonce', 'nonce');
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';
        $full_path = realpath(ABSPATH . ltrim($path, '/'));
        if (!$full_path || strpos($full_path, realpath(ABSPATH)) !== 0 || !is_dir($full_path)) {
            wp_send_json_error('Invalid directory specified.');
        }
        $files = array();
        $items = scandir($full_path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $item_path = $full_path . DIRECTORY_SEPARATOR . $item;
            $relative_path = $path ? $path . '/' . $item : $item;
            $files[] = array('name' => $item, 'path' => $relative_path, 'type' => is_dir($item_path) ? 'dir' : 'file', 'size' => is_file($item_path) ? $this->format_bytes(filesize($item_path)) : '-', 'modified' => date('Y-m-d H:i:s', filemtime($item_path)));
        }
        usort($files, function($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'dir' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });
        wp_send_json_success(array('files' => $files));
    }
    
    public function ajax_create_backup() {
        check_ajax_referer('dfb_nonce', 'nonce');
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $interval = isset($_POST['interval']) ? sanitize_text_field($_POST['interval']) : 'once';
        $files = isset($_POST['files']) ? $_POST['files'] : array();
        if (empty($name) || empty($files) || !is_array($files)) {
            wp_send_json_error('Missing required data.');
        }
        $date = date('Y-m-d_H-i-s');
        $backup_filename = sanitize_file_name($name) . '_' . $date . '.zip';
        $backup_path = $this->backup_dir . $backup_filename;
        if (!class_exists('ZipArchive')) {
            wp_send_json_error('ZipArchive extension is not available.');
        }
        $zip = new ZipArchive();
        if ($zip->open($backup_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            wp_send_json_error('Cannot create backup file.');
        }
        foreach ($files as $file) {
            $file_path_rel = isset($file['path']) ? ltrim($file['path'], '/') : '';
            if (empty($file_path_rel)) continue;
            $file_path_abs = realpath(ABSPATH . $file_path_rel);
            if ($file_path_abs && strpos($file_path_abs, realpath(ABSPATH)) === 0 && is_file($file_path_abs)) {
                $zip->addFile($file_path_abs, basename($file_path_abs));
            }
        }
        $zip->close();
        if ($interval !== 'once') {
            $backup_data = array('name' => $name, 'interval' => $interval, 'files' => $files, 'last_run' => time());
            $scheduled_backups = get_option('dfb_scheduled_backups', array());
            $scheduled_backups[$name] = $backup_data;
            update_option('dfb_scheduled_backups', $scheduled_backups);
        }
        wp_send_json_success('Backup created successfully.');
    }
    
    public function run_scheduled_backup() {
        $scheduled_backups = get_option('dfb_scheduled_backups', array());
        foreach ($scheduled_backups as $name => $backup_data) {
            $interval = $backup_data['interval'];
            $last_run = $backup_data['last_run'];
            $current_time = time();
            $should_run = false;
            switch ($interval) {
                case 'daily': $should_run = ($current_time - $last_run) >= 86400; break;
                case 'weekly': $should_run = ($current_time - $last_run) >= 604800; break;
                case 'monthly': $should_run = ($current_time - $last_run) >= 2592000; break;
            }
            if ($should_run) {
                $this->create_scheduled_backup($backup_data);
                $backup_data['last_run'] = $current_time;
                $scheduled_backups[$name] = $backup_data;
                update_option('dfb_scheduled_backups', $scheduled_backups);
            }
        }
    }
    
    private function create_scheduled_backup($backup_data) {
        $name = $backup_data['name'];
        $files = $backup_data['files'];
        $date = date('Y-m-d_H-i-s');
        $backup_filename = sanitize_file_name($name) . '_auto_' . $date . '.zip';
        $backup_path = $this->backup_dir . $backup_filename;
        if (!class_exists('ZipArchive') || !is_array($files)) {
            return false;
        }
        $zip = new ZipArchive();
        if ($zip->open($backup_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return false;
        }
        foreach ($files as $file) {
            $file_path_rel = isset($file['path']) ? ltrim($file['path'], '/') : '';
            if (empty($file_path_rel)) continue;
            $file_path_abs = realpath(ABSPATH . $file_path_rel);
            if ($file_path_abs && strpos($file_path_abs, realpath(ABSPATH)) === 0 && is_file($file_path_abs)) {
                $zip->addFile($file_path_abs, basename($file_path_abs));
            }
        }
        $zip->close();
        return true;
    }
    
    public function ajax_get_backups() {
        check_ajax_referer('dfb_nonce', 'nonce');
        $backups = array();
        $scheduled_backups = get_option('dfb_scheduled_backups', array());
        if (is_dir($this->backup_dir)) {
            $files = glob($this->backup_dir . '*.zip');
            if ($files === false) $files = [];
            foreach ($files as $file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                $basename = basename($file);
                $backup_name = preg_replace('/_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}(_auto)?$/', '', $filename);
                $interval = 'once';
                if (isset($scheduled_backups[$backup_name])) {
                    $interval = $scheduled_backups[$backup_name]['interval'];
                }
                $backups[] = array('name' => $filename, 'file' => $basename, 'date' => date('Y-m-d H:i:s', filemtime($file)), 'size' => $this->format_bytes(filesize($file)), 'interval' => $interval);
            }
            usort($backups, function($a, $b) { return strcmp($b['date'], $a['date']); });
        }
        wp_send_json_success($backups);
    }
    
    public function ajax_download_backup() {
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'dfb_nonce') || !current_user_can('manage_options')) {
            wp_die('Security check failed.');
        }
        $file = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
        $file_path = $this->backup_dir . $file;
        if (!file_exists($file_path) || strpos(realpath($file_path), realpath($this->backup_dir)) !== 0) {
            wp_die('File not found or access denied.');
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        readfile($file_path);
        exit;
    }
    
    public function ajax_send_email() {
        check_ajax_referer('dfb_nonce', 'nonce');
        $file = isset($_POST['file']) ? sanitize_file_name($_POST['file']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $file_path = $this->backup_dir . $file;
        if (!file_exists($file_path)) {
            wp_send_json_error('File not found');
        }
        if (!is_email($email)) {
            wp_send_json_error('Invalid email address');
        }
        
        $download_url = $this->backup_url . rawurlencode($file);
        
        $subject = 'Delta Backup Download Link';
        $message = "Your backup file is ready for download:\n\n";
        $message .= "File: " . esc_html($file) . "\n";
        $message .= "Download Link: " . esc_url($download_url) . "\n\n";
        $message .= "This is a direct download link.";
        $sent = wp_mail($email, $subject, $message);
        if ($sent) {
            wp_send_json_success('Email sent successfully');
        } else {
            wp_send_json_error('Failed to send email');
        }
    }
    
    public function ajax_delete_backup() {
        check_ajax_referer('dfb_nonce', 'nonce');
        $file = isset($_POST['file']) ? sanitize_file_name($_POST['file']) : '';
        $file_path = $this->backup_dir . $file;
        if (file_exists($file_path) && strpos(realpath($file_path), realpath($this->backup_dir)) === 0) {
            if (unlink($file_path)) {
                wp_send_json_success('Backup deleted successfully');
            }
        }
        wp_send_json_error('Failed to delete backup');
    }
    
    private function format_bytes($size, $precision = 2) {
        if ($size <= 0) return '0 B';
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $i = floor(log($size, 1024));
        return round($size / pow(1024, $i), $precision) . ' ' . $units[$i];
    }
}

new DeltaBackupPlugin();
?>
