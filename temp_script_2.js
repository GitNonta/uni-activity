
    (function () {
        var THREADS_URL = '"BLADE"';
        var CSRF = document.querySelector('meta[name="csrf-token"]').content;
        var USER_ID = '"BLADE"';
        var MY_PHOTO = '"BLADE"';
        var MY_NAME = '"BLADE"';

        var panelOpen = false;
        var threads = [];
        var currentJobId = null;

        window.toggleChatWidget = function () { panelOpen ? closeChatWidget() : openChatWidget(); };
        window.closeChatWidget = function () {
            panelOpen = false;
            document.getElementById('chatFloatPanel').style.display = 'none';
            document.getElementById('chatFloatBtn').style.transform = '';
        };
        window.openChatWidget = function() {
            panelOpen = true;
            document.getElementById('chatFloatPanel').style.display = 'flex';
            document.getElementById('chatFloatBtn').style.transform = 'scale(1.1)';
            showListView();
            loadThreads();
        };

        function showListView() {
            currentJobId = null;
            document.getElementById('cfViewList').style.display = 'flex';
            document.getElementById('cfViewChat').style.display = 'none';
            document.getElementById('cfBackBtn').style.display = 'none';
            document.getElementById('cfHeaderTitle').innerHTML = '<svg style="width:16px;height:16px;display:inline;vertical-align:-3px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> ข้อความของฉัน';
        }
        window.cfBackToList = function () { showListView(); loadThreads(); };

        window.showChatView = function(jobId, jobTitle) {
            currentJobId = jobId;
            document.getElementById('cfViewList').style.display = 'none';
            document.getElementById('cfViewChat').style.display = 'flex';
            document.getElementById('cfBackBtn').style.display = 'inline-block';
            document.getElementById('cfHeaderTitle').textContent = jobTitle;
            loadMessages(jobId);
            fetch('/jobs/' + jobId + '/chat/read', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
            var idx = threads.findIndex(function(t){ return t.job_id == jobId; });
            if (idx >= 0) { threads[idx].unread = 0; recalcBadge(); }
        };

        function loadThreads() {
            fetch(THREADS_URL, { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
                .then(function(r){ return r.json(); })
                .then(function(data) {
                    threads = data.threads || [];
                    renderThreads();
                    updateBadge(data.total_unread || 0);
                });
        }

        function renderThreads() {
            var el = document.getElementById('cfListContent');
            if (!threads.length) {
                el.innerHTML = '<div style="padding:2rem 1rem;text-align:center;font-size:.83rem;color:#94a3b8;">ยังไม่มีข้อความ</div>';
                return;
            }
            el.innerHTML = threads.map(function(t) {
                var isUnread = (t.unread || 0) > 0;
                var preview = t.last_message ? (t.last_message.length > 32 ? t.last_message.slice(0,32)+'…' : t.last_message) : '<svg style="width:14px;height:14px;display:inline;vertical-align:-2px;margin-right:2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg> ไฟล์แนบ';
                var safeTitle = (t.job_title || 'งานกิจกรรม').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                return '<div onclick="showChatView(' + t.job_id + ',\'' + safeTitle + '\')" '
                    + 'style="display:flex;align-items:center;gap:.65rem;padding:.65rem .9rem;border-bottom:1px solid #f1f5f9;cursor:pointer;background:' + (isUnread?'#faf5ff':'#fff') + ';">'
                    + '<div style="width:34px;height:34px;border-radius:50%;background:#4f46e5;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;flex-shrink:0;">' + safeTitle.charAt(0).toUpperCase() + '</div>'
                    + '<div style="flex:1;min-width:0;">'
                    + '<div style="font-size:.82rem;font-weight:' + (isUnread?'700':'500') + ';white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#1e293b;">' + safeTitle + '</div>'
                    + '<div style="font-size:.7rem;color:' + (isUnread?'#1e293b':'#64748b') + ';white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + preview + '</div>'
                    + '</div>'
                    + (isUnread ? '<div style="min-width:18px;height:18px;border-radius:9px;background:#ef4444;color:#fff;font-size:.6rem;font-weight:700;line-height:18px;text-align:center;padding:0 4px;">' + t.unread + '</div>' : '')
                    + '</div>';
            }).join('');
        }
        window.showChatView = showChatView;

        function loadMessages(jobId) {
            var win = document.getElementById('cfChatWindow');
            win.innerHTML = '<div style="padding:1.5rem;text-align:center;font-size:.82rem;color:#94a3b8;">กำลังโหลด...</div>';
            fetch('/jobs/' + jobId + '/chat/messages', { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
                .then(function(r){ return r.json(); })
                .then(function(data) {
                    win.innerHTML = '';
                    var msgs = data.messages || [];
                    if (!Array.isArray(msgs)) msgs = Object.values(msgs);
                    msgs.forEach(function(m) { win.appendChild(buildBubble(m)); });
                    win.scrollTop = win.scrollHeight;
                });
        }

        function buildBubble(msg) {
            var mine = msg.user_id == USER_ID || (msg.user && msg.user.id == USER_ID);
            var isTemp = String(msg.id).startsWith('tmp-');
            var row = document.createElement('div');
            row.id = 'cf-msg-' + msg.id;
            row.style.cssText = 'display:flex;flex-direction:' + (mine?'row-reverse':'row') + ';align-items:flex-end;gap:.3rem;margin-bottom:.2rem;position:relative;';
            
            var col = document.createElement('div');
            col.style.cssText = 'display:flex;flex-direction:column;align-items:' + (mine?'flex-end':'flex-start') + ';max-width:75%;';
            
            var bubble = document.createElement('div');
            bubble.style.cssText = 'padding:.45rem .75rem;border-radius:' + (mine?'14px 4px 14px 14px':'4px 14px 14px 14px') + ';background:' + (mine?'#4f46e5':'#fff') + ';color:' + (mine?'#fff':'#1e293b') + ';font-size:.82rem;box-shadow:0 1px 2px rgba(0,0,0,.08);word-break:break-word;white-space:pre-wrap;';
            
            if (msg.message) {
                var p = document.createElement('p');
                p.style.margin = '0';
                p.textContent = msg.message;
                bubble.appendChild(p);
            }
            if (msg.is_edited) {
                var editedSpan = document.createElement('span');
                editedSpan.style.cssText = 'font-size:0.6rem;opacity:0.7;margin-left:5px;';
                editedSpan.textContent = '(แก้ไขแล้ว)';
                bubble.appendChild(editedSpan);
            }
            
            if (msg.attachments && msg.attachments.length) {
                msg.attachments.forEach(function(a) {
                    var attDiv = document.createElement('div');
                    attDiv.style.marginTop = '.3rem';
                    if ((a.mime_type || '').indexOf('image/') === 0) {
                        var img = document.createElement('img');
                        img.src = a.url;
                        img.style.cssText = 'max-width:100%;border-radius:8px;display:block;cursor:pointer;';
                        img.onclick = function() { window.open(a.url, '_blank'); };
                        attDiv.appendChild(img);
                    } else {
                        var link = document.createElement('a');
                        link.href = a.url;
                        link.target = '_blank';
                        link.style.cssText = 'font-size:.75rem;text-decoration:none;display:flex;align-items:center;gap:.2rem;color:' + (mine?'#c7d2fe':'#4f46e5');
                        link.innerHTML = '<svg style="width:14px;height:14px;display:inline;vertical-align:-2px;margin-right:2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg> ' + a.original_name;
                        attDiv.appendChild(link);
                    }
                    bubble.appendChild(attDiv);
                });
            }
            
            col.appendChild(bubble);

            if (!isTemp && mine) {
                var actions = document.createElement('div');
                actions.className = 'msg-actions';
                actions.style.cssText = 'display:flex; position:relative; flex-direction:row; z-index: 20; align-items:center; margin-bottom:18px; order: -1;';
                
                var moreBtn = document.createElement('button');
                moreBtn.innerHTML = '<svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>';
                moreBtn.style.cssText = 'background:transparent; border:none; cursor:pointer; padding:4px; color:#94a3b8; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:all .2s; margin:0 4px;';
                
                var menu = document.createElement('div');
                menu.className = 'msg-dropdown';
                menu.style.cssText = 'display:none; position:absolute; left:34px; bottom:-4px; background:#2d2d2d; color:#f8fafc; border-radius:12px; padding:6px 0; min-width:130px; box-shadow:0 4px 12px rgba(0,0,0,0.25); flex-direction:column; z-index:30;';
                
                var tail = document.createElement('div');
                tail.style.cssText = 'position:absolute; left:-4px; bottom:12px; width:10px; height:10px; background:#2d2d2d; transform:rotate(45deg); z-index:-1; border-radius:1px;';
                menu.appendChild(tail);

                var createItem = function(text, onClick) {
                    var item = document.createElement('div');
                    item.textContent = text;
                    item.style.cssText = 'padding:8px 16px; font-size:0.85rem; cursor:pointer; transition:background .15s; user-select:none; font-weight:500;';
                    item.onmouseover = function() { this.style.background = 'rgba(255,255,255,0.1)'; };
                    item.onmouseout = function() { this.style.background = 'transparent'; };
                    item.onclick = function(e) { e.stopPropagation(); onClick(); menu.style.display = 'none'; };
                    return item;
                };

                menu.appendChild(createItem('แก้ไข', function() { window.editStudentMessage(msg.id); }));
                menu.appendChild(createItem('ยกเลิกการส่ง', function() { window.deleteStudentMessage(msg.id); }));
                
                moreBtn.onclick = function(e) {
                    e.stopPropagation();
                    var isVis = menu.style.display === 'flex';
                    document.querySelectorAll('.msg-dropdown').forEach(function(el){ el.style.display='none'; });
                    if (!isVis) menu.style.display = 'flex';
                };
                
                row.addEventListener('mouseleave', function() {
                    menu.style.display = 'none';
                });

                actions.appendChild(menu);
                actions.appendChild(moreBtn);
                // we will append to row later
            }

            // Add time and status
            var timeStr = new Date(msg.created_at).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
            var statusDiv = document.createElement('div');
            statusDiv.style.cssText = 'display:flex;align-items:center;gap:.25rem;margin-top:.1rem;';
            statusDiv.innerHTML = '<span style="font-size:.6rem;color:#94a3b8;">' + timeStr + '</span>';
            
            if (mine) {
                var statusText = document.createElement('span');
                statusText.style.cssText = 'font-size:.6rem;color:' + (isTemp ? '#94a3b8' : '#6366f1') + ';';
                statusText.textContent = isTemp ? 'กำลังส่ง...' : '✓ ส่งแล้ว';
                statusDiv.appendChild(statusText);
            }
            col.appendChild(statusDiv);

            row.appendChild(col);
            
            // Insert actions before col if it's my message
            if (!isTemp && mine) {
                row.insertBefore(actions, col);
            }
            
            return row;
        }

        var cfFileInput = document.getElementById('cfFileInput');
        var cfPreview = document.getElementById('cfAttachPreview');
        cfFileInput.addEventListener('change', function() {
            cfPreview.innerHTML = '';
            if (!cfFileInput.files.length) { cfPreview.style.display = 'none'; return; }
            cfPreview.style.display = 'flex';
            Array.from(cfFileInput.files).forEach(function(f) {
                var chip = document.createElement('span');
                chip.style.cssText = 'background:#e0e7ff;color:#3730a3;border-radius:6px;padding:.2rem .55rem;font-size:.78rem;display:flex;align-items:center;gap:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px;';
                if (f.type.startsWith('image/')) {
                    chip.innerHTML = '<svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>' + f.name;
                } else {
                    chip.innerHTML = '<svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>' + f.name;
                }
                cfPreview.appendChild(chip);
            });
        });

        document.getElementById('cfChatForm').addEventListener('submit', function(e) {
            e.preventDefault();
            if (!currentJobId) return;
            var form = this;
            var msgInput = document.getElementById('cfMsgInput');
            var text = msgInput.value.trim();
            var fileInput = document.getElementById('cfFileInput');
            if (!text && fileInput.files.length === 0) return;

            var btn = document.getElementById('cfSendBtn');
            btn.disabled = true;

            if (currentEditId) {
                fetch('/chat/messages/' + currentEditId, {
                    method: 'PUT',
                    headers: { 
                        'X-CSRF-TOKEN': CSRF,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ message: text })
                }).then(function(r) { return r.json(); }).then(function(res) {
                    btn.disabled = false;
                    if (res.success) {
                        var el = document.getElementById('cf-msg-' + currentEditId);
                        if (el) {
                            var p = el.querySelector('p');
                            if (p) p.textContent = text;
                            if (!el.textContent.includes('(แก้ไขแล้ว)')) {
                                var editedSpan = document.createElement('span');
                                editedSpan.style.cssText = 'font-size:0.6rem;opacity:0.7;margin-left:5px;';
                                editedSpan.textContent = '(แก้ไขแล้ว)';
                                p.parentNode.appendChild(editedSpan);
                            }
                        }
                        
                        currentEditId = null;
                        msgInput.value = '';
                        btn.innerHTML = 'ส่ง';
                        btn.style.background = '#4f46e5';
                        var cancelBtn = document.getElementById('cfCancelEditBtn');
                        if (cancelBtn) cancelBtn.remove();
                        
                    } else if (res.message) {
                        alert(res.message);
                    }
                }).catch(function(err) {
                    btn.disabled = false;
                    console.error(err);
                });
                return;
            }

            var fd = new FormData(form);
            if (!fd.has('message')) fd.append('message', text);

            var btn = document.getElementById('cfSendBtn');
            btn.disabled = true;

            // Optimistic UI: Append message immediately
            var win = document.getElementById('cfChatWindow');
            var tempId = 'tmp-' + Date.now();
            var optimisticMsg = {
                id: tempId,
                user_id: USER_ID,
                message: text,
                attachments: [], // We can't easily preview local files here without more code, but we can show the text
                created_at: new Date().toISOString()
            };
            
            // If there are files, show a placeholder in optimistic UI
            if (fileInput.files.length > 0) {
                Array.from(fileInput.files).forEach(function(f) {
                    var isImg = f.type.startsWith('image/');
                    optimisticMsg.attachments.push({
                        original_name: f.name,
                        url: isImg ? URL.createObjectURL(f) : '#',
                        mime_type: f.type
                    });
                });
            }

            var bubble = buildBubble(optimisticMsg);
            bubble.style.opacity = '0.7'; // Sending state
            win.appendChild(bubble);
            win.scrollTop = win.scrollHeight;

            // Clear input
            msgInput.value = '';
            fileInput.value = '';
            document.getElementById('cfAttachPreview').innerHTML = '';
            document.getElementById('cfAttachPreview').style.display = 'none';

            fetch('/jobs/' + currentJobId + '/chat', { 
                method: 'POST', 
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, 
                body: fd 
            })
                .then(function(r) {
                    if (!r.ok) return r.json().then(function(err) { throw err; });
                    return r.json();
                })
                .then(function(data) {
                    btn.disabled = false;
                    // Replace optimistic bubble with real one
                    if (data.message) {
                        var realBubble = buildBubble(data.message);
                        bubble.parentNode.replaceChild(realBubble, bubble);
                    }
                    recalcBadge();
                })
                .catch(function(err) {
                    console.error('Chat Error:', err);
                    bubble.style.background = '#fee2e2'; // Error state
                    bubble.style.color = '#991b1b';
                    alert(err.error || (err.errors && err.errors.message ? err.errors.message[0] : null) || 'ไม่สามารถส่งข้อความได้');
                    btn.disabled = false;
                });
        });

        function updateBadge(count) {
            var badge = document.getElementById('chatFloatBadge');
            if (count > 0) { badge.textContent = count; badge.style.display = 'inline-block'; }
            else { badge.style.display = 'none'; }
        }
        function recalcBadge() { updateBadge(threads.reduce(function(s,t){ return s+(t.unread||0); }, 0)); }

        // Laravel Echo — ใช้ retry เพราะ app.js โหลด async (type=module)
        (function initStudentEcho() {
            if (!window.Echo) { setTimeout(initStudentEcho, 200); return; }
            // ฟังจากช่องส่วนตัวของนักศึกษา (สำหรับแจ้งเตือนรวม)
            window.Echo.private('chat.student.' + USER_ID)
                .listen('.MessageSent', function(e) {
                    if (e.user && e.user.id == USER_ID) return; // Skip optimistic duplicate

                    if (currentJobId == e.room_id || (e.room && currentJobId == e.room.job_id)) { 
                        var win = document.getElementById('cfChatWindow');
                        if (!document.getElementById('cf-msg-' + e.id)) {
                            win.appendChild(buildBubble(e));
                            win.scrollTop = win.scrollHeight;
                            // Auto mark-read if panel is open and on this room
                            if (panelOpen) {
                                fetch('/jobs/' + currentJobId + '/chat/read', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
                            }
                        }
                    } else {
                        loadThreads();
                    }
                })
                .listen('.MessageDeleted', function(e) {
                    var el = document.getElementById('cf-msg-' + e.id);
                    if (el) el.remove();
                })
                .listen('.MessageEdited', function(e) {
                    var el = document.getElementById('cf-msg-' + e.id);
                    if (el) {
                        var p = el.querySelector('p');
                        if (p) p.textContent = e.message;
                        if (!el.textContent.includes('(แก้ไขแล้ว)')) {
                            var editedSpan = document.createElement('span');
                            editedSpan.style.cssText = 'font-size:0.6rem;opacity:0.7;margin-left:5px;';
                            editedSpan.textContent = '(แก้ไขแล้ว)';
                            p.parentNode.appendChild(editedSpan);
                        }
                    }
                })
                .listen('.ChatDeleted', function(e) {
                    if (currentJobId == e.room_id) {
                        closeFloatChat();
                        loadThreads();
                    }
                });
        })();

        window.deleteStudentMessage = function(id) {
            if (!confirm('ต้องการลบข้อความนี้ใช่หรือไม่?')) return;
            fetch('/chat/messages/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF }
            }).then(function(r) { return r.json(); }).then(function(res) {
                if (res.success) {
                    var el = document.getElementById('cf-msg-' + id);
                    if (el) el.remove();
                }
            });
        };

        var currentEditId = null;

        window.editStudentMessage = function(id) {
            var el = document.getElementById('cf-msg-' + id);
            if (!el) return;
            var p = el.querySelector('p');
            if (!p) return;
            
            var currentText = p.textContent.replace('(แก้ไขแล้ว)', '').trim();
            var msgInput = document.getElementById('cfMsgInput');
            msgInput.value = currentText;
            msgInput.focus();
            
            currentEditId = id;
            
            var btn = document.getElementById('cfSendBtn');
            btn.innerHTML = '<svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> บันทึก';
            btn.style.background = '#10b981';
            
            if (!document.getElementById('cfCancelEditBtn')) {
                var cancelBtn = document.createElement('button');
                cancelBtn.id = 'cfCancelEditBtn';
                cancelBtn.type = 'button';
                cancelBtn.innerHTML = 'ยกเลิก';
                cancelBtn.style.cssText = 'background:#ef4444; color:#fff; border:none; border-radius:12px; padding:0 1rem; font-weight:500; font-size:.95rem; cursor:pointer; height:42px; margin-right:4px;';
                cancelBtn.onclick = function() {
                    currentEditId = null;
                    msgInput.value = '';
                    btn.innerHTML = 'ส่ง';
                    btn.style.background = '#4f46e5';
                    this.remove();
                };
                btn.parentNode.insertBefore(cancelBtn, btn);
            }
        };

        // โหลดข้อมูลล่าสุดตอนโหลดหน้าเว็บ เพื่ออัปเดตตัวเลขแจ้งเตือนที่ปุ่มแชท
        loadThreads();
    })();
    