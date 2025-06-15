<?php
/**
 * Report Routes
 * 
 * This file defines all the API routes for the reporting system.
 */

// Initialize report controller
$report_controller = new ReportController($db);

// Generate report
$router->post('/reports/generate', function($request) use ($report_controller) {
    return $report_controller->generateReport($request);
});

// Schedule report
$router->post('/reports/schedule', function($request) use ($report_controller) {
    return $report_controller->scheduleReport($request);
});

// Update scheduled report
$router->put('/reports/schedule/:id', function($request, $params) use ($report_controller) {
    $request['report_id'] = $params['id'];
    return $report_controller->updateScheduledReport($request);
});

// Delete scheduled report
$router->delete('/reports/schedule/:id', function($request, $params) use ($report_controller) {
    $request['report_id'] = $params['id'];
    return $report_controller->deleteScheduledReport($request);
});

// Get scheduled reports
$router->get('/reports/schedule', function($request) use ($report_controller) {
    return $report_controller->getScheduledReports($request);
});

// Get report templates
$router->get('/reports/templates', function() use ($report_controller) {
    return $report_controller->getReportTemplates();
});

// Get report recipients
$router->get('/reports/recipients', function() use ($report_controller) {
    return $report_controller->getReportRecipients();
});

// Get report recipient groups
$router->get('/reports/recipient-groups', function() use ($report_controller) {
    return $report_controller->getReportRecipientGroups();
}); 