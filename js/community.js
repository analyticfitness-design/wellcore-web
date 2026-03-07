/**
 * WellCore Community Module
 * Shared between cliente.html (audience='all') and rise-dashboard.html (audience='rise')
 * All user-generated content uses textContent to prevent XSS.
 */
var Community = (function() {
    var _container = null;
    var _audience = 'all';
    var _page = 1;
    var _loading = false;
    var _totalPages = 1;
    var _chatMode = false;
    var _pollTimer = null;
    var _lastMsgId = 0;
    var _clientId = 0;
    var _chatInitialized = false;
    var _unreadCount = 0;
    var _chatBadgeEl = null;

    var EMOJI_MAP = {
        fire:   'fa-fire',
        muscle: 'fa-dumbbell',
        clap:   'fa-hands-clapping',
        heart:  'fa-heart'
    };

    function getToken() {
        return localStorage.getItem('wc_token') || sessionStorage.getItem('wc_preview_token') || '';
    }

    function apiCall(method, url, body) {
        var opts = {
            method: method,
            headers: {
                'Authorization': 'Bearer ' + getToken(),
                'Content-Type': 'application/json'
            }
        };
        if (body) opts.body = JSON.stringify(body);
        return fetch(url, opts).then(function(r) { return r.json(); });
    }

    function timeAgo(dateStr) {
        var diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
        if (diff < 60) return 'ahora';
        if (diff < 3600) return Math.floor(diff / 60) + 'm';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd';
        return new Date(dateStr).toLocaleDateString('es-CO', { day: 'numeric', month: 'short' });
    }

    function fa(iconClass) {
        var i = document.createElement('i');
        i.className = 'fas ' + iconClass;
        return i;
    }

    function createAvatar(initial, size) {
        var el = document.createElement('div');
        var s = size || 36;
        el.style.cssText = 'width:' + s + 'px;height:' + s + 'px;border-radius:50%;background:rgba(227,30,36,0.15);border:1px solid rgba(227,30,36,0.4);display:flex;align-items:center;justify-content:center;font-size:' + (s * 0.4) + 'px;font-weight:700;color:#E31E24;flex-shrink:0;';
        el.textContent = initial || '?';
        return el;
    }

    function createReactionBar(postId, reactions) {
        var bar = document.createElement('div');
        bar.style.cssText = 'display:flex;gap:6px;flex-wrap:wrap;margin-top:12px;';
        bar.setAttribute('data-post-reactions', postId);

        var emojis = ['fire', 'muscle', 'clap', 'heart'];
        emojis.forEach(function(emoji) {
            var existing = null;
            (reactions || []).forEach(function(r) { if (r.emoji === emoji) existing = r; });

            var btn = document.createElement('button');
            btn.setAttribute('data-emoji', emoji);
            btn.setAttribute('data-post-id', postId);
            var active = existing && existing.user_reacted;
            btn.style.cssText = 'display:flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:12px;cursor:pointer;transition:all 0.2s;border:1px solid ' + (active ? 'rgba(227,30,36,0.5)' : 'rgba(255,255,255,0.1)') + ';background:' + (active ? 'rgba(227,30,36,0.15)' : 'rgba(255,255,255,0.03)') + ';color:' + (active ? '#E31E24' : 'rgba(255,255,255,0.4)') + ';';

            var icon = fa(EMOJI_MAP[emoji]);
            icon.style.fontSize = '11px';
            btn.appendChild(icon);

            var count = document.createElement('span');
            count.textContent = existing ? existing.count : '0';
            count.style.fontFamily = "'JetBrains Mono', monospace";
            btn.appendChild(count);

            btn.onclick = function() { toggleReaction(postId, emoji); };
            bar.appendChild(btn);
        });

        return bar;
    }

    function updateReactionBar(postId, reactions) {
        var bar = document.querySelector('[data-post-reactions="' + postId + '"]');
        if (!bar) return;
        var buttons = bar.querySelectorAll('button');
        buttons.forEach(function(btn) {
            var emoji = btn.getAttribute('data-emoji');
            var existing = null;
            (reactions || []).forEach(function(r) { if (r.emoji === emoji) existing = r; });
            var active = existing && existing.user_reacted;
            btn.style.borderColor = active ? 'rgba(227,30,36,0.5)' : 'rgba(255,255,255,0.1)';
            btn.style.background = active ? 'rgba(227,30,36,0.15)' : 'rgba(255,255,255,0.03)';
            btn.style.color = active ? '#E31E24' : 'rgba(255,255,255,0.4)';
            btn.querySelector('span').textContent = existing ? existing.count : '0';
        });
    }

    function renderPost(post) {
        var card = document.createElement('div');
        card.style.cssText = 'padding:16px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;margin-bottom:10px;';
        card.setAttribute('data-post-id', post.id);

        if (post.post_type === 'achievement') {
            card.style.borderColor = 'rgba(227,30,36,0.3)';
            card.style.background = 'rgba(227,30,36,0.05)';
        }

        // Header
        var header = document.createElement('div');
        header.style.cssText = 'display:flex;align-items:center;gap:10px;margin-bottom:10px;';
        header.appendChild(createAvatar(post.author_initial));

        var meta = document.createElement('div');
        meta.style.flex = '1';
        var nameEl = document.createElement('div');
        nameEl.style.cssText = 'font-weight:600;font-size:13px;color:#fff;';
        nameEl.textContent = post.author_name;
        var timeEl = document.createElement('div');
        timeEl.style.cssText = "color:rgba(255,255,255,0.35);font-size:11px;font-family:'JetBrains Mono',monospace;";
        timeEl.textContent = (post.author_plan ? post.author_plan.toUpperCase() + ' · ' : '') + timeAgo(post.created_at);
        meta.appendChild(nameEl);
        meta.appendChild(timeEl);
        header.appendChild(meta);
        card.appendChild(header);

        // Achievement badge
        if (post.post_type === 'achievement') {
            var badge = document.createElement('div');
            badge.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:4px;background:rgba(227,30,36,0.15);border:1px solid rgba(227,30,36,0.3);font-size:11px;font-weight:700;color:#E31E24;letter-spacing:1px;text-transform:uppercase;margin-bottom:8px;';
            badge.appendChild(fa('fa-trophy'));
            var badgeText = document.createElement('span');
            badgeText.textContent = ' LOGRO DESBLOQUEADO';
            badge.appendChild(badgeText);
            card.appendChild(badge);
        }

        // Content
        var body = document.createElement('p');
        body.style.cssText = 'color:rgba(255,255,255,0.7);font-size:13px;line-height:1.6;margin:0;';
        body.textContent = post.content;
        card.appendChild(body);

        // Reactions
        card.appendChild(createReactionBar(post.id, post.reactions));

        // Actions row
        var actions = document.createElement('div');
        actions.style.cssText = 'display:flex;align-items:center;gap:12px;margin-top:8px;';
        var replyBtn = document.createElement('button');
        replyBtn.style.cssText = 'background:none;border:none;color:rgba(255,255,255,0.3);font-size:12px;cursor:pointer;padding:0;display:flex;align-items:center;gap:4px;';
        var replyIcon = fa('fa-reply');
        replyIcon.style.fontSize = '11px';
        replyBtn.appendChild(replyIcon);
        var replyLabel = document.createElement('span');
        replyLabel.textContent = 'Responder';
        replyBtn.appendChild(replyLabel);
        replyBtn.onclick = function() { showReplyBox(post.id, card); };
        actions.appendChild(replyBtn);

        if (post.reply_count > 0) {
            var countEl = document.createElement('span');
            countEl.style.cssText = "color:rgba(255,255,255,0.25);font-size:11px;font-family:'JetBrains Mono',monospace;";
            countEl.textContent = post.reply_count + (post.reply_count === 1 ? ' respuesta' : ' respuestas');
            actions.appendChild(countEl);
        }
        card.appendChild(actions);

        // Replies
        if (post.replies && post.replies.length > 0) {
            var rc = document.createElement('div');
            rc.style.cssText = 'margin-top:10px;padding-left:16px;border-left:2px solid rgba(227,30,36,0.2);';
            rc.setAttribute('data-replies', post.id);
            post.replies.forEach(function(r) { rc.appendChild(renderReply(r)); });
            if (post.reply_count > 3) {
                var more = document.createElement('button');
                more.style.cssText = 'background:none;border:none;color:#E31E24;font-size:11px;cursor:pointer;padding:4px 0;';
                more.textContent = 'Ver las ' + post.reply_count + ' respuestas';
                rc.appendChild(more);
            }
            card.appendChild(rc);
        }

        return card;
    }

    function renderReply(reply) {
        var el = document.createElement('div');
        el.style.cssText = 'padding:8px 0;';
        var header = document.createElement('div');
        header.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:4px;';
        header.appendChild(createAvatar(reply.author_initial, 24));
        var name = document.createElement('span');
        name.style.cssText = 'font-weight:600;font-size:12px;color:rgba(255,255,255,0.8);';
        name.textContent = reply.author_name;
        header.appendChild(name);
        var time = document.createElement('span');
        time.style.cssText = "color:rgba(255,255,255,0.25);font-size:10px;font-family:'JetBrains Mono',monospace;";
        time.textContent = timeAgo(reply.created_at);
        header.appendChild(time);
        el.appendChild(header);
        var body = document.createElement('p');
        body.style.cssText = 'color:rgba(255,255,255,0.6);font-size:12px;line-height:1.5;margin:0;padding-left:32px;';
        body.textContent = reply.content;
        el.appendChild(body);
        return el;
    }

    function showReplyBox(postId, cardEl) {
        if (cardEl.querySelector('.reply-box')) return;
        var box = document.createElement('div');
        box.className = 'reply-box';
        box.style.cssText = 'margin-top:10px;display:flex;gap:8px;';
        var input = document.createElement('input');
        input.type = 'text';
        input.placeholder = 'Escribe una respuesta...';
        input.maxLength = 500;
        input.style.cssText = 'flex:1;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:6px;padding:8px 12px;color:#fff;font-size:12px;outline:none;';
        var sendBtn = document.createElement('button');
        sendBtn.style.cssText = 'background:#E31E24;border:none;border-radius:6px;padding:8px 14px;color:#fff;cursor:pointer;font-size:12px;font-weight:600;';
        sendBtn.textContent = 'Enviar';
        sendBtn.onclick = function() {
            var text = input.value.trim();
            if (!text) return;
            submitReply(postId, text, cardEl, box);
        };
        input.addEventListener('keypress', function(e) { if (e.key === 'Enter') sendBtn.click(); });
        box.appendChild(input);
        box.appendChild(sendBtn);
        cardEl.appendChild(box);
        input.focus();
    }

    function submitReply(postId, content, cardEl, replyBox) {
        apiCall('POST', '/api/community/posts.php', {
            content: content,
            parent_id: postId,
            audience: _audience
        }).then(function(data) {
            if (!data.ok) return;
            if (replyBox && replyBox.parentNode) replyBox.parentNode.removeChild(replyBox);
            var rc = cardEl.querySelector('[data-replies="' + postId + '"]');
            if (!rc) {
                rc = document.createElement('div');
                rc.style.cssText = 'margin-top:10px;padding-left:16px;border-left:2px solid rgba(227,30,36,0.2);';
                rc.setAttribute('data-replies', postId);
                cardEl.appendChild(rc);
            }
            rc.appendChild(renderReply(data.post));
        });
    }

    function toggleReaction(postId, emoji) {
        apiCall('POST', '/api/community/reactions.php', {
            post_id: postId,
            emoji: emoji
        }).then(function(data) {
            if (data.ok) updateReactionBar(postId, data.reactions);
        });
    }

    function renderComposer() {
        var composer = document.createElement('div');
        composer.style.cssText = 'padding:16px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.08);border-radius:10px;margin-bottom:16px;';
        var textarea = document.createElement('textarea');
        textarea.id = 'communityComposer';
        textarea.rows = 3;
        textarea.maxLength = 500;
        textarea.placeholder = 'Comparte tu progreso con la comunidad...';
        textarea.style.cssText = 'width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:12px;color:#fff;font-size:13px;resize:vertical;min-height:60px;outline:none;box-sizing:border-box;font-family:inherit;';
        var footer = document.createElement('div');
        footer.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-top:10px;';
        var counter = document.createElement('span');
        counter.style.cssText = "font-size:11px;color:rgba(255,255,255,0.25);font-family:'JetBrains Mono',monospace;";
        counter.textContent = '0/500';
        textarea.addEventListener('input', function() { counter.textContent = textarea.value.length + '/500'; });
        var btn = document.createElement('button');
        btn.style.cssText = 'background:#E31E24;border:none;border-radius:6px;padding:8px 20px;color:#fff;font-size:13px;font-weight:700;letter-spacing:1px;text-transform:uppercase;cursor:pointer;display:flex;align-items:center;gap:6px;';
        var btnIcon = fa('fa-paper-plane');
        btn.appendChild(btnIcon);
        var btnText = document.createElement('span');
        btnText.textContent = 'Publicar';
        btn.appendChild(btnText);
        btn.onclick = function() {
            var text = textarea.value.trim();
            if (!text) return;
            submitPost(text, textarea, counter);
        };
        footer.appendChild(counter);
        footer.appendChild(btn);
        composer.appendChild(textarea);
        composer.appendChild(footer);
        return composer;
    }

    function submitPost(content, textarea, counter) {
        apiCall('POST', '/api/community/posts.php', {
            content: content,
            post_type: 'text',
            audience: _audience
        }).then(function(data) {
            if (!data.ok) return;
            textarea.value = '';
            counter.textContent = '0/500';
            var feed = _container.querySelector('#communityFeedList');
            if (feed) feed.insertBefore(renderPost(data.post), feed.firstChild);
            apiCall('POST', '/api/community/check-achievements.php', { trigger: 'community_post' }).then(function(ach) {
                if (ach && ach.awarded && ach.awarded.length > 0 && typeof showToast === 'function') {
                    ach.awarded.forEach(function(a) { showToast('Logro desbloqueado: ' + a.title, 'success'); });
                }
            });
        });
    }

    function loadPosts(append) {
        if (_loading) return;
        _loading = true;
        apiCall('GET', '/api/community/posts.php?audience=' + _audience + '&page=' + _page + '&limit=20')
            .then(function(data) {
                _loading = false;
                _totalPages = data.total_pages || 1;
                var feed = _container.querySelector('#communityFeedList');
                if (!append) feed.innerHTML = '';
                if (data.posts && data.posts.length > 0) {
                    data.posts.forEach(function(post) { feed.appendChild(renderPost(post)); });
                } else if (!append) {
                    var empty = document.createElement('div');
                    empty.style.cssText = 'text-align:center;padding:40px 20px;color:rgba(255,255,255,0.3);';
                    var emptyIcon = fa('fa-comments');
                    emptyIcon.style.cssText = 'font-size:32px;margin-bottom:12px;display:block;opacity:0.3;';
                    empty.appendChild(emptyIcon);
                    var emptyText = document.createElement('div');
                    emptyText.textContent = 'Se el primero en publicar algo';
                    empty.appendChild(emptyText);
                    feed.appendChild(empty);
                }
                var loadMoreBtn = _container.querySelector('#communityLoadMore');
                if (loadMoreBtn) loadMoreBtn.style.display = _page < _totalPages ? 'block' : 'none';
            })
            .catch(function() { _loading = false; });
    }

    function loadAchievements(targetEl) {
        apiCall('GET', '/api/community/achievements.php').then(function(data) {
            if (!targetEl) return;
            targetEl.innerHTML = '';
            var header = document.createElement('div');
            header.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;';
            var title = document.createElement('div');
            title.style.cssText = 'font-weight:700;font-size:14px;color:#fff;';
            title.textContent = 'Logros';
            var progress = document.createElement('div');
            progress.style.cssText = "font-size:11px;color:rgba(255,255,255,0.35);font-family:'JetBrains Mono',monospace;";
            progress.textContent = data.total_earned + '/' + data.total_possible;
            header.appendChild(title);
            header.appendChild(progress);
            targetEl.appendChild(header);
            var grid = document.createElement('div');
            grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;';
            (data.earned || []).forEach(function(a) { grid.appendChild(renderBadge(a, false)); });
            (data.locked || []).forEach(function(a) { grid.appendChild(renderBadge(a, true)); });
            targetEl.appendChild(grid);
        });
    }

    function renderBadge(a, locked) {
        var el = document.createElement('div');
        el.style.cssText = 'padding:12px;border-radius:8px;text-align:center;border:1px solid ' + (locked ? 'rgba(255,255,255,0.06)' : 'rgba(227,30,36,0.3)') + ';background:' + (locked ? 'rgba(255,255,255,0.02)' : 'rgba(227,30,36,0.08)') + ';opacity:' + (locked ? '0.5' : '1') + ';';
        var icon = document.createElement('div');
        icon.style.cssText = 'font-size:20px;margin-bottom:6px;color:' + (locked ? 'rgba(255,255,255,0.2)' : '#E31E24') + ';';
        icon.appendChild(fa('fa-' + (a.icon || 'trophy')));
        el.appendChild(icon);
        var title = document.createElement('div');
        title.style.cssText = 'font-size:11px;font-weight:700;color:' + (locked ? 'rgba(255,255,255,0.3)' : '#fff') + ';line-height:1.3;';
        title.textContent = a.title;
        el.appendChild(title);
        if (locked) {
            var lock = document.createElement('div');
            lock.style.cssText = 'font-size:9px;color:rgba(255,255,255,0.2);margin-top:4px;';
            lock.appendChild(fa('fa-lock'));
            el.appendChild(lock);
        }
        return el;
    }

    /* ── Tab Switcher ─────────────────────────────────────── */

    function renderTabSwitcher() {
        var wrap = document.createElement('div');
        wrap.style.cssText = 'display:flex;gap:0;margin-bottom:16px;border-bottom:2px solid rgba(255,255,255,0.06);';

        var tabStyle = 'background:none;border:none;padding:12px 24px;font-size:13px;font-weight:700;letter-spacing:1px;text-transform:uppercase;cursor:pointer;transition:all 0.2s;position:relative;';

        var feedTab = document.createElement('button');
        feedTab.style.cssText = tabStyle + 'color:#E31E24;border-bottom:2px solid #E31E24;margin-bottom:-2px;';
        feedTab.appendChild(fa('fa-stream'));
        var feedLabel = document.createElement('span');
        feedLabel.textContent = ' Feed';
        feedTab.appendChild(feedLabel);

        var chatTab = document.createElement('button');
        chatTab.style.cssText = tabStyle + 'color:rgba(255,255,255,0.35);display:flex;align-items:center;gap:6px;';
        chatTab.appendChild(fa('fa-comments'));
        var chatLabel = document.createElement('span');
        chatLabel.textContent = ' Chat';
        chatTab.appendChild(chatLabel);

        _chatBadgeEl = document.createElement('span');
        _chatBadgeEl.style.cssText = 'display:none;background:#E31E24;color:#fff;font-size:9px;font-weight:700;min-width:16px;height:16px;border-radius:8px;padding:0 4px;text-align:center;line-height:16px;font-family:"JetBrains Mono",monospace;';
        chatTab.appendChild(_chatBadgeEl);

        feedTab.onclick = function() {
            _chatMode = false;
            var fw = _container.querySelector('#communityFeedWrap');
            var cw = _container.querySelector('#communityChatWrap');
            if (fw) fw.style.display = '';
            if (cw) cw.style.display = 'none';
            feedTab.style.color = '#E31E24';
            feedTab.style.borderBottom = '2px solid #E31E24';
            feedTab.style.marginBottom = '-2px';
            chatTab.style.color = 'rgba(255,255,255,0.35)';
            chatTab.style.borderBottom = 'none';
            chatTab.style.marginBottom = '0';
        };

        chatTab.onclick = function() {
            _chatMode = true;
            _unreadCount = 0;
            if (_chatBadgeEl) { _chatBadgeEl.style.display = 'none'; _chatBadgeEl.textContent = ''; }
            var fw = _container.querySelector('#communityFeedWrap');
            var cw = _container.querySelector('#communityChatWrap');
            if (fw) fw.style.display = 'none';
            if (cw) cw.style.display = '';
            if (!_chatInitialized) initChat();
            startPolling();
            chatTab.style.color = '#E31E24';
            chatTab.style.borderBottom = '2px solid #E31E24';
            chatTab.style.marginBottom = '-2px';
            feedTab.style.color = 'rgba(255,255,255,0.35)';
            feedTab.style.borderBottom = 'none';
            feedTab.style.marginBottom = '0';
        };

        wrap.appendChild(feedTab);
        wrap.appendChild(chatTab);
        return wrap;
    }

    /* ── Chat UI ──────────────────────────────────────────── */

    function renderChatUI() {
        var outer = document.createElement('div');
        outer.style.cssText = 'background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.08);border-radius:10px;overflow:hidden;';

        // Load older button
        var loadOlder = document.createElement('button');
        loadOlder.id = 'chatLoadOlder';
        loadOlder.style.cssText = 'width:100%;padding:8px;background:rgba(255,255,255,0.03);border:none;border-bottom:1px solid rgba(255,255,255,0.06);color:rgba(255,255,255,0.35);font-size:11px;cursor:pointer;display:none;';
        loadOlder.textContent = 'Cargar anteriores';
        loadOlder.onclick = function() { loadChatMessages('older'); };
        outer.appendChild(loadOlder);

        // Messages container
        var msgBox = document.createElement('div');
        msgBox.id = 'chatMessages';
        msgBox.style.cssText = 'height:400px;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:8px;scroll-behavior:smooth;';
        outer.appendChild(msgBox);

        // Input bar
        var inputBar = document.createElement('div');
        inputBar.style.cssText = 'display:flex;padding:10px;border-top:1px solid rgba(255,255,255,0.08);gap:8px;background:rgba(0,0,0,0.15);';

        var input = document.createElement('input');
        input.type = 'text';
        input.placeholder = 'Escribe un mensaje...';
        input.maxLength = 500;
        input.style.cssText = 'flex:1;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:10px 16px;color:#fff;font-size:13px;outline:none;';

        var sendBtn = document.createElement('button');
        sendBtn.style.cssText = 'width:36px;height:36px;border-radius:50%;background:#E31E24;border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;';
        sendBtn.appendChild(fa('fa-paper-plane'));

        var doSend = function() {
            var text = input.value.trim();
            if (!text) return;
            sendChatMessage(text, input);
        };

        sendBtn.onclick = doSend;
        input.addEventListener('keypress', function(e) { if (e.key === 'Enter') doSend(); });

        inputBar.appendChild(input);
        inputBar.appendChild(sendBtn);
        outer.appendChild(inputBar);

        return outer;
    }

    function isStaffRole(plan) {
        return plan === 'coach' || plan === 'admin' || plan === 'superadmin';
    }

    function renderChatMessage(msg, animate) {
        var isStaff = msg.user_type === 'admin' || isStaffRole(msg.author_plan);

        var wrap = document.createElement('div');
        wrap.style.cssText = 'display:flex;gap:8px;max-width:85%;opacity:1;transition:opacity 0.3s ease,transform 0.3s ease;';
        wrap.setAttribute('data-msg-id', msg.id);

        if (animate) {
            wrap.style.opacity = '0';
            wrap.style.transform = 'translateY(8px)';
            setTimeout(function() { wrap.style.opacity = '1'; wrap.style.transform = 'translateY(0)'; }, 30);
        }

        if (msg.is_mine) {
            wrap.style.alignSelf = 'flex-end';
            wrap.style.flexDirection = 'row-reverse';
        } else {
            wrap.style.alignSelf = 'flex-start';
        }

        // Avatar — golden ring for staff
        if (isStaff && !msg.is_mine) {
            var avatar = document.createElement('div');
            avatar.style.cssText = 'width:28px;height:28px;border-radius:50%;background:rgba(212,175,55,0.18);border:2px solid rgba(212,175,55,0.6);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#D4AF37;flex-shrink:0;';
            avatar.textContent = msg.author_initial || '?';
            wrap.appendChild(avatar);
        } else {
            wrap.appendChild(createAvatar(msg.author_initial, 28));
        }

        var bubble = document.createElement('div');
        if (msg.is_mine) {
            bubble.style.cssText = 'background:rgba(227,30,36,0.12);border:1px solid rgba(227,30,36,0.25);border-radius:12px 12px 2px 12px;padding:8px 12px;';
        } else if (isStaff) {
            bubble.style.cssText = 'background:rgba(212,175,55,0.06);border:1px solid rgba(212,175,55,0.25);border-radius:12px 12px 12px 2px;padding:8px 12px;';
        } else {
            bubble.style.cssText = 'background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px 12px 12px 2px;padding:8px 12px;';
        }

        // Header
        var hdr = document.createElement('div');
        hdr.style.cssText = 'display:flex;align-items:center;gap:6px;margin-bottom:4px;';

        var nameEl = document.createElement('span');
        nameEl.style.cssText = 'font-weight:600;font-size:11px;color:' + (isStaff ? '#D4AF37' : '#fff') + ';';
        nameEl.textContent = msg.author_name;
        hdr.appendChild(nameEl);

        if (isStaff) {
            var staffBadge = document.createElement('span');
            staffBadge.style.cssText = 'display:inline-flex;align-items:center;gap:3px;font-size:8px;font-weight:700;color:#D4AF37;background:rgba(212,175,55,0.12);border:1px solid rgba(212,175,55,0.25);padding:1px 6px;border-radius:3px;text-transform:uppercase;letter-spacing:0.5px;';
            var crownIcon = fa('fa-crown');
            crownIcon.style.fontSize = '7px';
            staffBadge.appendChild(crownIcon);
            var roleText = document.createElement('span');
            roleText.textContent = msg.author_plan === 'superadmin' ? 'CEO' : (msg.author_plan === 'coach' ? 'COACH' : 'STAFF');
            staffBadge.appendChild(roleText);
            hdr.appendChild(staffBadge);
        } else if (msg.author_plan) {
            var planBadge = document.createElement('span');
            planBadge.style.cssText = 'text-transform:uppercase;font-size:9px;color:rgba(227,30,36,0.7);';
            planBadge.textContent = msg.author_plan;
            hdr.appendChild(planBadge);
        }

        var timeEl = document.createElement('span');
        timeEl.style.cssText = "font-size:9px;color:rgba(255,255,255,0.25);font-family:'JetBrains Mono',monospace;";
        timeEl.textContent = timeAgo(msg.created_at);
        hdr.appendChild(timeEl);

        bubble.appendChild(hdr);

        // Message text
        var body = document.createElement('p');
        body.style.cssText = 'font-size:13px;line-height:1.5;color:rgba(255,255,255,0.75);margin:0;';
        body.textContent = msg.message;
        bubble.appendChild(body);

        // Chat reactions bar
        var reactRow = document.createElement('div');
        reactRow.style.cssText = 'display:flex;gap:4px;margin-top:6px;align-items:center;';
        reactRow.setAttribute('data-chat-reactions', msg.id);

        var chatEmojis = ['fire', 'muscle', 'heart'];
        chatEmojis.forEach(function(emoji) {
            var existing = null;
            (msg.reactions || []).forEach(function(r) { if (r.emoji === emoji) existing = r; });
            var rb = document.createElement('button');
            var active = existing && existing.user_reacted;
            rb.style.cssText = 'display:flex;align-items:center;gap:2px;padding:2px 6px;border-radius:10px;font-size:10px;cursor:pointer;transition:all 0.2s;border:1px solid ' + (active ? 'rgba(227,30,36,0.4)' : 'rgba(255,255,255,0.06)') + ';background:' + (active ? 'rgba(227,30,36,0.1)' : 'transparent') + ';color:' + (active ? '#E31E24' : 'rgba(255,255,255,0.25)') + ';';
            rb.setAttribute('data-emoji', emoji);
            var rIcon = fa(EMOJI_MAP[emoji]);
            rIcon.style.fontSize = '9px';
            rb.appendChild(rIcon);
            var rCount = document.createElement('span');
            rCount.style.cssText = "font-family:'JetBrains Mono',monospace;font-size:9px;";
            rCount.textContent = existing && existing.count > 0 ? existing.count : '';
            rb.appendChild(rCount);
            rb.onclick = function() { toggleChatReaction(msg.id, emoji); };
            reactRow.appendChild(rb);
        });

        bubble.appendChild(reactRow);

        // Report button (only for others' messages)
        if (!msg.is_mine) {
            var reportBtn = document.createElement('button');
            reportBtn.style.cssText = 'background:none;border:none;color:rgba(255,255,255,0.15);font-size:9px;cursor:pointer;padding:2px;float:right;';
            reportBtn.appendChild(fa('fa-flag'));
            reportBtn.onmouseenter = function() { reportBtn.style.color = 'rgba(227,30,36,0.5)'; };
            reportBtn.onmouseleave = function() { reportBtn.style.color = 'rgba(255,255,255,0.15)'; };
            reportBtn.onclick = function() { reportMessage(msg.id, wrap); };
            bubble.appendChild(reportBtn);
        }

        wrap.appendChild(bubble);
        return wrap;
    }

    function toggleChatReaction(msgId, emoji) {
        apiCall('POST', '/api/community/chat-reactions.php', {
            chat_message_id: msgId,
            emoji: emoji
        }).then(function(data) {
            if (!data.ok) return;
            var bar = document.querySelector('[data-chat-reactions="' + msgId + '"]');
            if (!bar) return;
            var buttons = bar.querySelectorAll('button');
            buttons.forEach(function(btn) {
                var e = btn.getAttribute('data-emoji');
                var existing = null;
                (data.reactions || []).forEach(function(r) { if (r.emoji === e) existing = r; });
                var active = existing && existing.user_reacted;
                btn.style.borderColor = active ? 'rgba(227,30,36,0.4)' : 'rgba(255,255,255,0.06)';
                btn.style.background = active ? 'rgba(227,30,36,0.1)' : 'transparent';
                btn.style.color = active ? '#E31E24' : 'rgba(255,255,255,0.25)';
                btn.querySelector('span').textContent = existing && existing.count > 0 ? existing.count : '';
            });
        });
    }

    function initChat() {
        var cw = _container.querySelector('#communityChatWrap');
        if (!cw) return;
        while (cw.firstChild) cw.removeChild(cw.firstChild);
        cw.appendChild(renderChatUI());
        loadChatMessages('init');
    }

    function loadChatMessages(mode) {
        var url = '/api/community/chat.php';
        if (mode === 'poll' && _lastMsgId) {
            url += '?after_id=' + _lastMsgId;
        } else if (mode === 'older') {
            var msgBox = document.getElementById('chatMessages');
            var first = msgBox ? msgBox.querySelector('[data-msg-id]') : null;
            if (first) {
                url += '?before_id=' + first.getAttribute('data-msg-id');
            }
        }

        apiCall('GET', url).then(function(data) {
            if (!data.ok) return;
            var msgBox = document.getElementById('chatMessages');
            if (!msgBox) return;

            if (mode === 'init') {
                while (msgBox.firstChild) msgBox.removeChild(msgBox.firstChild);
                (data.messages || []).forEach(function(msg) {
                    msgBox.appendChild(renderChatMessage(msg));
                });
                if (data.messages && data.messages.length > 0) {
                    _lastMsgId = data.messages[data.messages.length - 1].id;
                }
                msgBox.scrollTop = msgBox.scrollHeight;
                var loadOlderBtn = document.getElementById('chatLoadOlder');
                if (loadOlderBtn) loadOlderBtn.style.display = (data.messages && data.messages.length >= 50) ? 'block' : 'none';
                _chatInitialized = true;

            } else if (mode === 'poll') {
                if (data.messages && data.messages.length > 0) {
                    var wasAtBottom = (msgBox.scrollTop + msgBox.clientHeight >= msgBox.scrollHeight - 50);
                    data.messages.forEach(function(msg) {
                        msgBox.appendChild(renderChatMessage(msg, true));
                    });
                    _lastMsgId = data.messages[data.messages.length - 1].id;
                    if (wasAtBottom) msgBox.scrollTop = msgBox.scrollHeight;
                }

            } else if (mode === 'older') {
                var prevHeight = msgBox.scrollHeight;
                var prevScroll = msgBox.scrollTop;
                var msgs = data.messages || [];
                for (var i = msgs.length - 1; i >= 0; i--) {
                    msgBox.insertBefore(renderChatMessage(msgs[i]), msgBox.firstChild);
                }
                msgBox.scrollTop = prevScroll + (msgBox.scrollHeight - prevHeight);
                var loadOlderBtn2 = document.getElementById('chatLoadOlder');
                if (loadOlderBtn2) loadOlderBtn2.style.display = (msgs.length >= 50) ? 'block' : 'none';
            }
        });
    }

    function sendChatMessage(text, input) {
        apiCall('POST', '/api/community/chat.php', { message: text })
            .then(function(data) {
                if (data.ok) {
                    var msgBox = document.getElementById('chatMessages');
                    if (msgBox) {
                        msgBox.appendChild(renderChatMessage(data.message));
                        msgBox.scrollTop = msgBox.scrollHeight;
                    }
                    input.value = '';
                    if (data.message && data.message.id) _lastMsgId = data.message.id;
                } else {
                    if (data.code === 403 && typeof showToast === 'function') {
                        showToast('Tu cuenta ha sido suspendida del chat', 'error');
                    } else if (data.code === 429 && typeof showToast === 'function') {
                        showToast('Espera un momento', 'warning');
                    }
                }
            })
            .catch(function() {
                if (typeof showToast === 'function') showToast('Error de red', 'error');
            });
    }

    function reportMessage(msgId, bubbleEl) {
        apiCall('POST', '/api/community/report-chat.php', { chat_message_id: msgId })
            .then(function(data) {
                if (data.ok) {
                    if (typeof showToast === 'function') showToast('Mensaje reportado');
                    if (data.action === 'hidden_and_banned' && bubbleEl) {
                        bubbleEl.style.transition = 'opacity 0.3s';
                        bubbleEl.style.opacity = '0';
                        setTimeout(function() {
                            if (bubbleEl.parentNode) bubbleEl.parentNode.removeChild(bubbleEl);
                        }, 300);
                    }
                } else if (data.code === 409) {
                    if (typeof showToast === 'function') showToast('Ya reportaste este mensaje');
                }
            })
            .catch(function() {
                if (typeof showToast === 'function') showToast('Error de red', 'error');
            });
    }

    function startPolling() {
        stopPolling();
        _pollTimer = setInterval(function() {
            if (document.hidden) return;
            if (_chatMode) {
                loadChatMessages('poll');
            } else if (_lastMsgId) {
                // Background poll — only check for new messages for badge
                apiCall('GET', '/api/community/chat.php?after_id=' + _lastMsgId).then(function(data) {
                    if (data.ok && data.messages && data.messages.length > 0) {
                        _unreadCount += data.messages.length;
                        _lastMsgId = data.messages[data.messages.length - 1].id;
                        if (_chatBadgeEl) {
                            _chatBadgeEl.textContent = _unreadCount > 99 ? '99+' : _unreadCount;
                            _chatBadgeEl.style.display = 'inline-flex';
                        }
                    }
                });
            }
        }, 5000);
    }

    function stopPolling() {
        if (_pollTimer) { clearInterval(_pollTimer); _pollTimer = null; }
    }

    return {
        init: function(containerId, audience) {
            _container = document.getElementById(containerId);
            if (!_container) return;
            _audience = audience || 'all';
            _page = 1;
            _chatMode = false;
            _chatInitialized = false;
            while (_container.firstChild) _container.removeChild(_container.firstChild);

            // Tab switcher
            _container.appendChild(renderTabSwitcher());

            // Feed wrap
            var feedWrap = document.createElement('div');
            feedWrap.id = 'communityFeedWrap';

            var achievementsEl = document.createElement('div');
            achievementsEl.style.cssText = 'padding:16px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.08);border-radius:10px;margin-bottom:16px;';
            achievementsEl.id = 'communityAchievements';
            feedWrap.appendChild(achievementsEl);
            loadAchievements(achievementsEl);
            feedWrap.appendChild(renderComposer());
            var feedList = document.createElement('div');
            feedList.id = 'communityFeedList';
            feedWrap.appendChild(feedList);
            var loadMore = document.createElement('button');
            loadMore.id = 'communityLoadMore';
            loadMore.style.cssText = 'width:100%;padding:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:8px;color:rgba(255,255,255,0.4);font-size:13px;cursor:pointer;margin-top:8px;display:none;';
            loadMore.textContent = 'Cargar mas publicaciones';
            loadMore.onclick = function() { _page++; loadPosts(true); };
            feedWrap.appendChild(loadMore);
            _container.appendChild(feedWrap);

            // Chat wrap (hidden by default)
            var chatWrap = document.createElement('div');
            chatWrap.id = 'communityChatWrap';
            chatWrap.style.display = 'none';
            _container.appendChild(chatWrap);

            loadPosts(false);

            // Fetch latest chat message ID for unread badge tracking
            apiCall('GET', '/api/community/chat.php').then(function(data) {
                if (data.ok && data.messages && data.messages.length > 0) {
                    _lastMsgId = data.messages[data.messages.length - 1].id;
                }
                startPolling();
            }).catch(function() { startPolling(); });
        },
        refresh: function() {
            if (_chatMode) {
                loadChatMessages('init');
            } else {
                _page = 1; loadPosts(false);
            }
        },
        stopChat: function() { stopPolling(); }
    };
})();
