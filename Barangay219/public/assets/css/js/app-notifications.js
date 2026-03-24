(function () {
    'use strict';

    var api = typeof window.API_URL !== 'undefined' ? window.API_URL : '';
    if (!api) return;

    var bell = document.getElementById('appNotificationsToggle');
    var badge = document.getElementById('appNotificationsBadge');
    var listEl = document.getElementById('appNotificationsList');
    var markAllBtn = document.getElementById('appNotificationsMarkAll');
    var clearAllBtn = document.getElementById('appNotificationsClearAll');

    function esc(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function formatRelativeTime(raw) {
        if (!raw) return '';
        var d = new Date(String(raw).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        var now = Date.now();
        var sec = Math.floor((now - d.getTime()) / 1000);
        if (sec < 45) return 'Just now';
        if (sec < 3600) return Math.floor(sec / 60) + ' min ago';
        if (sec < 86400) return Math.floor(sec / 3600) + ' hr ago';
        if (sec < 604800) return Math.floor(sec / 86400) + ' days ago';
        var opt = { month: 'short', day: 'numeric' };
        if (d.getFullYear() !== new Date().getFullYear()) {
            opt.year = 'numeric';
        }
        return d.toLocaleDateString(undefined, opt);
    }

    function notificationIcon(eventType, notifyType) {
        var t = (eventType || '').toLowerCase();
        var ty = (notifyType || '').toLowerCase();
        if (t.indexOf('reject') !== -1 || ty === 'danger') return { icon: 'bi-x-circle-fill', tone: 'danger' };
        if (t.indexOf('released') !== -1 || t.indexOf('issue') !== -1) return { icon: 'bi-patch-check-fill', tone: 'success' };
        if (t.indexOf('ready') !== -1 || t.indexOf('pickup') !== -1) return { icon: 'bi-box-seam-fill', tone: 'primary' };
        if (t.indexOf('approv') !== -1) return { icon: 'bi-check-circle-fill', tone: 'success' };
        if (t.indexOf('submit') !== -1 || t.indexOf('submitted') !== -1) return { icon: 'bi-send-fill', tone: 'primary' };
        if (t.indexOf('complaint') !== -1) return { icon: 'bi-chat-left-text-fill', tone: 'secondary' };
        if (t.indexOf('announcement') !== -1) return { icon: 'bi-megaphone-fill', tone: 'warning' };
        if (t.indexOf('registration') !== -1 || t.indexOf('resident_application') !== -1) return { icon: 'bi-person-check-fill', tone: 'primary' };
        if (t.indexOf('certificate') !== -1) return { icon: 'bi-file-earmark-text-fill', tone: 'primary' };
        if (ty === 'success') return { icon: 'bi-check-circle-fill', tone: 'success' };
        if (ty === 'warning') return { icon: 'bi-exclamation-triangle-fill', tone: 'warning' };
        return { icon: 'bi-bell-fill', tone: 'secondary' };
    }

    function fetchJson(url, opts) {
        return fetch(url, Object.assign({ credentials: 'same-origin' }, opts || {})).then(function (r) {
            var ct = (r.headers.get('content-type') || '').toLowerCase();
            if (!ct.includes('application/json')) {
                return r.text().then(function (text) {
                    throw new Error(text ? text.slice(0, 120) : 'Non-JSON response');
                });
            }
            return r.json().then(function (j) {
                if (!r.ok && j && typeof j === 'object' && !('success' in j)) {
                    throw new Error('HTTP ' + r.status);
                }
                return j;
            });
        });
    }

    function setBadge(n) {
        if (!badge) return;
        if (n > 0) {
            badge.textContent = n > 99 ? '99+' : String(n);
            badge.classList.remove('d-none');
            if (bell) bell.classList.add('app-notifications-btn--unread');
        } else {
            badge.classList.add('d-none');
            if (bell) bell.classList.remove('app-notifications-btn--unread');
        }
    }

    function renderList(rows) {
        if (!listEl) return;
        if (!rows || !rows.length) {
            listEl.innerHTML = '<li class="app-notifications-empty" role="listitem"><i class="bi bi-inbox" aria-hidden="true"></i><span>No notifications yet</span></li>';
            return;
        }
        listEl.innerHTML = rows.map(function (row) {
            var link = row.link_url ? esc(row.link_url) : '#';
            var title = esc(row.title || 'Notice');
            var msg = esc(row.message || '').replace(/\n/g, '<br>');
            var unread = !parseInt(row.is_read, 10);
            var meta = notificationIcon(row.event_type || '', row.type || '');
            var timeStr = formatRelativeTime(row.created_at || '');
            var itemCls = 'app-notification-item' + (unread ? ' is-unread' : ' is-read');
            var iconCls = 'app-notification-icon app-notification-icon--' + meta.tone;
            var nid = esc(String(row.id));
            return (
                '<li class="app-notification-row" role="listitem">' +
                '<div class="app-notification-card">' +
                '<a class="' + itemCls + '" href="' + link + '" data-id="' + nid + '">' +
                '<span class="' + iconCls + '" aria-hidden="true"><i class="bi ' + meta.icon + '"></i></span>' +
                '<span class="app-notification-main">' +
                '<span class="app-notification-title">' + title + '</span>' +
                '<span class="app-notification-msg">' + msg + '</span>' +
                (timeStr ? '<span class="app-notification-time"><i class="bi bi-clock"></i> ' + esc(timeStr) + '</span>' : '') +
                '</span>' +
                (unread ? '<span class="app-notification-dot" aria-hidden="true"></span>' : '') +
                '</a>' +
                '<button type="button" class="app-notification-delete" data-id="' + nid + '" title="Delete" aria-label="Delete notification">' +
                '<i class="bi bi-trash3" aria-hidden="true"></i></button>' +
                '</div></li>'
            );
        }).join('');
    }

    function loadUnreadCount() {
        fetchJson(api + 'notifications.php?action=unread_count')
            .then(function (j) {
                if (j && j.success && j.data) setBadge(j.data.count || 0);
            })
            .catch(function () {});
    }

    function loadList() {
        fetchJson(api + 'notifications.php?action=list&limit=20')
            .then(function (j) {
                if (!j) {
                    throw new Error('Empty response');
                }
                if (!j.success) {
                    throw new Error(j.message || 'Request failed');
                }
                var data = j.data || { notifications: [], total_unread: 0 };
                renderList(data.notifications || []);
                setBadge(data.total_unread != null ? data.total_unread : 0);
            })
            .catch(function (err) {
                if (listEl) {
                    var hint = err && err.message ? esc(String(err.message)) : '';
                    listEl.innerHTML = '<li class="app-notifications-empty app-notifications-empty--error" role="listitem">Could not load notifications.'
                        + (hint ? '<br><span class="small">' + hint + '</span>' : '')
                        + '</li>';
                }
            });
    }

    function markRead(id) {
        var fd = new FormData();
        fd.append('action', 'mark_read');
        fd.append('id', id);
        return fetchJson(api + 'notifications.php', { method: 'POST', body: fd });
    }

    function markAllRead() {
        var fd = new FormData();
        fd.append('action', 'mark_all_read');
        return fetchJson(api + 'notifications.php', { method: 'POST', body: fd });
    }

    function clearAll() {
        var fd = new FormData();
        fd.append('action', 'clear_all');
        return fetchJson(api + 'notifications.php', { method: 'POST', body: fd });
    }

    function deleteOne(id) {
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        return fetchJson(api + 'notifications.php', { method: 'POST', body: fd });
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest('.app-notification-item');
        if (!a || !listEl || !listEl.contains(a)) return;
        var id = a.getAttribute('data-id');
        if (!id) return;
        markRead(id).then(function () {
            loadUnreadCount();
        }).catch(function () {});
    });

    if (listEl) {
        listEl.addEventListener('click', function (e) {
            var btn = e.target.closest('.app-notification-delete');
            if (!btn || !listEl.contains(btn)) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var delId = btn.getAttribute('data-id');
            if (!delId) {
                return;
            }
            deleteOne(delId).then(function (j) {
                if (!j || !j.success) {
                    return;
                }
                var row = btn.closest('.app-notification-row');
                if (row) {
                    row.remove();
                }
                loadUnreadCount();
                if (listEl && !listEl.querySelector('.app-notification-row')) {
                    renderList([]);
                }
            }).catch(function () {});
        });
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            markAllRead().then(function () {
                loadList();
            }).catch(function () {});
        });
    }

    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!window.confirm('Remove all notifications from this list? This cannot be undone.')) {
                return;
            }
            clearAll().then(function (j) {
                if (j && j.success) {
                    setBadge(0);
                    renderList([]);
                }
            }).catch(function () {});
        });
    }

    if (bell) {
        bell.addEventListener('show.bs.dropdown', function () {
            loadList();
        });
    }

    loadUnreadCount();
    setInterval(loadUnreadCount, 60000);
})();
