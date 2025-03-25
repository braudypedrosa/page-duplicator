<?php
/**
 * Handles logging for the Page Duplicator plugin
 */
class PageDuplicatorLogger {
    /**
     * Log file path
     */
    private $log_file;
    
    /**
     * Constructor
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/page-duplicator-logs.txt';
    }
    
    /**
     * Log an event
     *
     * @param string $message Log message
     * @param string $level Log level (info, error, warning)
     */
    public function log($message, $level = 'info') {
        if (!file_exists($this->log_file)) {
            touch($this->log_file);
        }
        
        $timestamp = current_time('mysql');
        $log_entry = sprintf(
            "[%s] [%s] %s\n",
            $timestamp,
            strtoupper($level),
            $message
        );
        
        file_put_contents($this->log_file, $log_entry, FILE_APPEND);
        
        // If it's an error, also send an admin notification
        if ($level === 'error') {
            $this->notify_admin($message);
        }
    }
    
    /**
     * Send notification to admin
     *
     * @param string $message Error message
     */
    private function notify_admin($message) {
        $admin_email = get_option('admin_email');
        $subject = __('Page Duplicator Error', 'page-duplicator');
        $body = sprintf(
            __('An error occurred in the Page Duplicator plugin:\n\n%s', 'page-duplicator'),
            $message
        );
        
        wp_mail($admin_email, $subject, $body);
    }
} 