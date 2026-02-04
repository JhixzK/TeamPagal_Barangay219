<?php
/**
 * Bootstrap Verification Test
 * This file helps verify that Bootstrap is loading correctly
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bootstrap Test - E-Barangay System</title>
    
    <!-- Bootstrap CSS (Local) -->
    <link href="<?php echo ASSETS_URL; ?>css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-success">
                    <h4 class="alert-heading"><i class="bi bi-check-circle"></i> Bootstrap is Working!</h4>
                    <p>If you can see this styled alert box, Bootstrap CSS is loading correctly.</p>
                    <hr>
                    <p class="mb-0">Bootstrap CSS Path: <code><?php echo ASSETS_URL; ?>css/bootstrap.min.css</code></p>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Bootstrap Components Test</h5>
                    </div>
                    <div class="card-body">
                        <h6>Buttons:</h6>
                        <button class="btn btn-primary">Primary</button>
                        <button class="btn btn-success">Success</button>
                        <button class="btn btn-danger">Danger</button>
                        <button class="btn btn-warning">Warning</button>
                        
                        <h6 class="mt-3">Badges:</h6>
                        <span class="badge bg-primary">Primary</span>
                        <span class="badge bg-success">Success</span>
                        <span class="badge bg-danger">Danger</span>
                        
                        <h6 class="mt-3">Grid System:</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="p-3 bg-light border">Column 1</div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light border">Column 2</div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light border">Column 3</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button class="btn btn-info" onclick="testBootstrapJS()">
                        <i class="bi bi-play-circle"></i> Test Bootstrap JavaScript
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (Local) -->
    <script src="<?php echo ASSETS_URL; ?>js/bootstrap.bundle.min.js"></script>
    
    <script>
    function testBootstrapJS() {
        // Test if Bootstrap JS is loaded by trying to create a modal
        if (typeof bootstrap !== 'undefined') {
            alert('✅ Bootstrap JavaScript is loaded!\n\nBootstrap version: ' + (bootstrap.Tooltip ? '5.x' : 'Unknown'));
        } else {
            alert('❌ Bootstrap JavaScript is NOT loaded!\n\nCheck the path: <?php echo ASSETS_URL; ?>js/bootstrap.bundle.min.js');
        }
    }
    
    // Auto-test on load
    window.addEventListener('load', function() {
        setTimeout(function() {
            if (typeof bootstrap !== 'undefined') {
                console.log('✅ Bootstrap CSS and JS are both loaded successfully!');
            } else {
                console.error('❌ Bootstrap JS failed to load');
            }
        }, 1000);
    });
    </script>
</body>
</html>
