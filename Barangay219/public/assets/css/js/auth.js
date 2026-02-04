// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    if (!loginForm) {
        console.error('Login form not found');
        return;
    }
    
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'login');
        
        // Get API URL from global variable (defined in HTML)
        const apiUrl = window.API_URL;
        if (!apiUrl) {
            console.error('API_URL is not defined. Please check your configuration.');
            showAlert('danger', 'Configuration error. Please refresh the page.');
            return;
        }
        
        // Debug logging
        console.log('API URL:', apiUrl);
        console.log('Full URL:', apiUrl + 'auth.php');
        
        if (!apiUrl || apiUrl.includes('<?php') || apiUrl.includes('%3C')) {
            console.error('Invalid API URL detected:', apiUrl);
            showAlert('danger', 'Configuration error. Please refresh the page.');
            return;
        }
        
        // Show loading state
        const submitButton = loginForm.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Logging in...';
        
        fetch(apiUrl + 'auth.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            // Check if response is OK
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            
            // Check content type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Non-JSON response:', text);
                    throw new Error('Server returned non-JSON response. Check API URL and server configuration.');
                });
            }
            
            return response.json();
        })
        .then(d => {
            console.log('Response data:', d);
            if (d.success) {
                showAlert('success', 'Login successful! Redirecting...');
                setTimeout(() => {
                    window.location.href = d.data.redirect;
                }, 500);
            } else {
                showAlert('danger', d.message || 'Login failed. Please check your credentials.');
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        })
        .catch(e => {
            console.error('Login error:', e);
            let errorMessage = 'Login error occurred. ';
            
            if (e.message.includes('Failed to fetch') || e.message.includes('NetworkError')) {
                errorMessage += 'Cannot connect to server. Please check:';
                errorMessage += '<ul style="margin: 10px 0; padding-left: 20px;">';
                errorMessage += '<li>Is Apache running in XAMPP?</li>';
                errorMessage += '<li>Is the API URL correct? (' + apiUrl + 'auth.php)</li>';
                errorMessage += '<li>Check browser console (F12) for more details</li>';
                errorMessage += '</ul>';
            } else if (e.message.includes('HTTP error')) {
                errorMessage += 'Server error: ' + e.message;
            } else {
                errorMessage += e.message || 'Please try again.';
            }
            
            showAlert('danger', errorMessage);
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        });
    });
});

function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}
