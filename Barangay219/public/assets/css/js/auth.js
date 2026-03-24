// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    const invalidCredentialsMessage = 'Invalid username or password';
    const loginForm = document.getElementById('loginForm');
    const passwordInput = document.getElementById('password');
    const passwordGroup = passwordInput ? passwordInput.closest('.password-field') : null;
    const stepPassword = document.getElementById('loginStepPassword');
    const step2fa = document.getElementById('loginStep2fa');
    const login2faOtp = document.getElementById('login2faOtp');
    const login2faHint = document.getElementById('login2faHint');
    const login2faVerifyBtn = document.getElementById('login2faVerifyBtn');
    const login2faResendBtn = document.getElementById('login2faResendBtn');
    const login2faBackBtn = document.getElementById('login2faBackBtn');

    let challengeToken = null;

    if (!loginForm) {
        console.error('Login form not found');
        return;
    }

    function clearInlinePasswordError() {
        if (passwordInput) {
            passwordInput.classList.remove('is-invalid');
            passwordInput.removeAttribute('aria-invalid');
        }
        if (passwordGroup) {
            passwordGroup.classList.remove('password-invalid');
        }
    }

    function showInlinePasswordError(message) {
        void message;
        if (passwordInput) {
            passwordInput.classList.add('is-invalid');
            passwordInput.setAttribute('aria-invalid', 'true');
            passwordInput.focus();
            passwordInput.select();
        }
        if (passwordGroup) {
            passwordGroup.classList.add('password-invalid');
        }
    }

    function showPasswordStep() {
        challengeToken = null;
        if (stepPassword) {
            stepPassword.classList.remove('d-none');
        }
        if (step2fa) {
            step2fa.classList.add('d-none');
        }
        if (login2faOtp) {
            login2faOtp.value = '';
        }
    }

    function show2faStep(token, message) {
        challengeToken = token;
        if (stepPassword) {
            stepPassword.classList.add('d-none');
        }
        if (step2fa) {
            step2fa.classList.remove('d-none');
        }
        if (login2faHint) {
            login2faHint.textContent = message || 'Enter the 6-digit code sent to your email.';
        }
        if (login2faOtp) {
            login2faOtp.focus();
        }
    }

    function setSubmitLoading(isLoading, button, originalHtml) {
        if (!button) {
            return;
        }
        button.disabled = isLoading;
        if (isLoading) {
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Please wait...';
        } else {
            button.innerHTML = originalHtml;
        }
    }

    function finishLoginSuccess(d) {
        clearInlinePasswordError();
        showAlert('success', 'Login successful! Redirecting...');
        setTimeout(function() {
            window.location.href = d.data.redirect;
        }, 500);
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', clearInlinePasswordError);
    }

    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        clearInlinePasswordError();
        showAlert('', '');
        const formData = new FormData(this);
        formData.append('action', 'login');

        const apiUrl = window.API_URL;
        if (!apiUrl) {
            console.error('API_URL is not defined. Please check your configuration.');
            showAlert('danger', 'Configuration error. Please refresh the page.');
            return;
        }

        if (!apiUrl || apiUrl.includes('<?php') || apiUrl.includes('%3C')) {
            console.error('Invalid API URL detected:', apiUrl);
            showAlert('danger', 'Configuration error. Please refresh the page.');
            return;
        }

        const submitButton = loginForm.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Logging in...';

        fetch(apiUrl + 'auth.php', {
            method: 'POST',
            body: formData
        })
            .then(function(response) {
                const ct = response.headers.get('content-type');
                if (!response.ok) {
                    if (ct && ct.includes('application/json')) {
                        return response.json().then(function(j) {
                            const err = new Error('HTTP_' + response.status);
                            err.payload = j;
                            err.status = response.status;
                            throw err;
                        });
                    }
                    throw new Error('HTTP error! status: ' + response.status);
                }
                if (!ct || !ct.includes('application/json')) {
                    return response.text().then(function(text) {
                        console.error('Non-JSON response:', text);
                        throw new Error('Server returned non-JSON response.');
                    });
                }
                return response.json();
            })
            .then(function(d) {
                if (d.success && d.data && d.data.step === 'email_2fa' && d.data.challenge_token) {
                    show2faStep(d.data.challenge_token, d.message || d.data.message);
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                    showAlert('success', d.message || 'Check your email for the verification code.');
                    return;
                }
                if (d.success) {
                    clearInlinePasswordError();
                    finishLoginSuccess(d);
                    return;
                }
                const message = d.message || 'Login failed. Please check your credentials.';
                if (message.toLowerCase().includes('invalid username or password') || message.toLowerCase().includes('wrong username/password')) {
                    clearInlinePasswordError();
                    showAlert('danger', invalidCredentialsMessage);
                } else {
                    showAlert('danger', message);
                }
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            })
            .catch(function(e) {
                console.error('Login error:', e);
                if (e.payload && e.payload.message) {
                    showAlert('danger', e.payload.message);
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                    return;
                }
                let errorMessage = 'Login error occurred. ';
                if (e.message && (e.message.includes('Failed to fetch') || e.message.includes('NetworkError'))) {
                    errorMessage += 'Cannot connect to server. Please check your connection and try again.';
                } else if (e.message && e.message.includes('HTTP_401')) {
                    errorMessage = invalidCredentialsMessage;
                } else {
                    errorMessage += (e.message || 'Please try again.');
                }
                if (errorMessage === invalidCredentialsMessage) {
                    clearInlinePasswordError();
                }
                showAlert('danger', errorMessage);
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
    });

    function post2faAction(action, extraFields, onSuccess) {
        const apiUrl = window.API_URL;
        if (!apiUrl || !challengeToken) {
            showAlert('danger', 'Session expired. Please sign in again.');
            showPasswordStep();
            return;
        }
        const fd = new FormData();
        fd.append('action', action);
        fd.append('challenge_token', challengeToken);
        if (extraFields) {
            Object.keys(extraFields).forEach(function(k) {
                fd.append(k, extraFields[k]);
            });
        }

        return fetch(apiUrl + 'auth.php', { method: 'POST', body: fd })
            .then(function(response) {
                return response.json().then(function(j) {
                    return { ok: response.ok, status: response.status, json: j };
                });
            })
            .then(function(result) {
                const d = result.json;
                if (d.success && onSuccess) {
                    onSuccess(d);
                    return;
                }
                if (!d.success) {
                    showAlert('danger', d.message || 'Request failed.');
                }
            })
            .catch(function(err) {
                console.error(err);
                showAlert('danger', 'Network error. Please try again.');
            });
    }

    if (login2faVerifyBtn) {
        login2faVerifyBtn.addEventListener('click', function() {
            const otp = (login2faOtp && login2faOtp.value) ? login2faOtp.value.replace(/\D/g, '') : '';
            if (otp.length !== 6) {
                showAlert('danger', 'Enter the 6-digit code from your email.');
                return;
            }
            const orig = login2faVerifyBtn.innerHTML;
            setSubmitLoading(true, login2faVerifyBtn, orig);
            post2faAction('verify_login_2fa', { otp: otp }, function(d) {
                finishLoginSuccess(d);
            }).finally(function() {
                setSubmitLoading(false, login2faVerifyBtn, orig);
            });
        });
    }

    if (login2faResendBtn) {
        login2faResendBtn.addEventListener('click', function() {
            const orig = login2faResendBtn.innerHTML;
            login2faResendBtn.disabled = true;
            login2faResendBtn.innerHTML = 'Sending...';
            post2faAction('resend_login_2fa', {}, function(d) {
                if (d.data && d.data.challenge_token) {
                    challengeToken = d.data.challenge_token;
                }
                showAlert('success', d.message || 'New code sent.');
            }).finally(function() {
                login2faResendBtn.disabled = false;
                login2faResendBtn.innerHTML = orig;
            });
        });
    }

    if (login2faBackBtn) {
        login2faBackBtn.addEventListener('click', function() {
            showAlert('', '');
            showPasswordStep();
            if (passwordInput) {
                passwordInput.focus();
            }
        });
    }

    if (login2faOtp) {
        login2faOtp.addEventListener('keydown', function(ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                if (login2faVerifyBtn) {
                    login2faVerifyBtn.click();
                }
            }
        });
    }
});

function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    if (!container) {
        return;
    }
    if (!type || !message) {
        container.innerHTML = '';
        return;
    }
    container.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
        message +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
