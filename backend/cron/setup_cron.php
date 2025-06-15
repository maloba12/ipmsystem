<?php
/**
 * Cron Job Setup Script
 * 
 * This script sets up the necessary cron jobs for:
 * - Report processing
 * - System maintenance
 * 
 * Usage: php setup_cron.php
 */

class CronSetup {
    private $cron_file;
    private $scripts = [
        'report_processing' => [
            'script' => __DIR__ . '/process_reports.php',
            'schedule' => '* * * * *', // Every minute
            'description' => 'Process scheduled reports'
        ],
        'maintenance' => [
            'script' => __DIR__ . '/maintenance_scheduler.php',
            'schedule' => '0 * * * *', // Every hour
            'description' => 'Run system maintenance tasks'
        ]
    ];

    public function __construct() {
        $this->cron_file = tempnam(sys_get_temp_dir(), 'cron_');
    }

    public function setup() {
        try {
            // Get current crontab
            $current_crontab = $this->getCurrentCrontab();
            
            // Add new cron jobs
            $new_crontab = $this->addCronJobs($current_crontab);
            
            // Install new crontab
            $this->installCrontab($new_crontab);
            
            echo "Cron jobs have been set up successfully.\n";
        } catch (Exception $e) {
            echo "Error setting up cron jobs: " . $e->getMessage() . "\n";
        } finally {
            // Clean up temporary file
            if (file_exists($this->cron_file)) {
                unlink($this->cron_file);
            }
        }
    }

    private function getCurrentCrontab() {
        exec('crontab -l', $output, $return_var);
        if ($return_var !== 0) {
            throw new Exception('Failed to get current crontab');
        }
        return implode("\n", $output);
    }

    private function addCronJobs($current_crontab) {
        $new_crontab = $current_crontab;
        
        foreach ($this->scripts as $name => $config) {
            $cron_line = $this->formatCronLine($config);
            
            // Check if cron job already exists
            if (strpos($new_crontab, $cron_line) === false) {
                $new_crontab .= "\n" . $cron_line;
            }
        }
        
        return $new_crontab;
    }

    private function formatCronLine($config) {
        $php_path = PHP_BINARY;
        $log_file = dirname(__DIR__) . '/logs/' . basename($config['script'], '.php') . '.log';
        
        return sprintf(
            "%s %s %s %s >> %s 2>&1 # %s",
            $config['schedule'],
            $php_path,
            $config['script'],
            '>/dev/null',
            $log_file,
            $config['description']
        );
    }

    private function installCrontab($crontab) {
        // Write crontab to temporary file
        file_put_contents($this->cron_file, $crontab);
        
        // Install new crontab
        exec("crontab {$this->cron_file}", $output, $return_var);
        if ($return_var !== 0) {
            throw new Exception('Failed to install new crontab');
        }
    }
}

// Create necessary log directories
$log_dirs = [
    dirname(__DIR__) . '/logs',
    dirname(__DIR__) . '/reports/logs'
];

foreach ($log_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Run setup if script is executed directly
if (php_sapi_name() === 'cli') {
    $setup = new CronSetup();
    $setup->setup();
} 