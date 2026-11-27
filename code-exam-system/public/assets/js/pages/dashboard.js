(function () {
    'use strict';

    if (!window.Api) {
        return;
    }

    // ---------- ตัวช่วย ----------
    function q(selector) {
        return document.querySelector(selector);
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function setTile(key, value, sub) {
        var v = q('[data-tile-value="' + key + '"]');
        var s = q('[data-tile-sub="' + key + '"]');
        if (v && value !== null) {
            v.textContent = Number(value).toLocaleString('th-TH');
        }
        if (s && sub) {
            s.textContent = sub;
        }
    }

    function timeLabel(value) {
        var d = new Date(String(value).replace(' ', 'T'));
        if (isNaN(d)) {
            return '';
        }
        var today = new Date();
        if (d.toDateString() === today.toDateString()) {
            return d.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
        }
        var y = new Date(today);
        y.setDate(today.getDate() - 1);
        if (d.toDateString() === y.toDateString()) {
            return 'เมื่อวาน';
        }
        return d.toLocaleDateString('th-TH', { day: 'numeric', month: 'short' });
    }

    // ---------- ภาคการศึกษาปัจจุบัน ----------
    var termSlot = q('[data-current-term]');
    var termChip = q('[data-term-chip]');
    var activeTerm = null;

    var termReady = (termSlot || termChip) ? Api.get('/academic-terms').then(function (res) {
        var terms = res.ok ? (res.data.academic_terms || []) : [];
        activeTerm = terms.filter(function (t) { return t.status === 'active'; })[0] || null;
        if (!activeTerm) {
            return;
        }
        var label = activeTerm.term_no + '/' + activeTerm.academic_year;
        if (termSlot && termSlot.textContent.trim() === '') {
            termSlot.textContent = ' · ภาคการศึกษา ' + label;
        }
        if (termChip) {
            q('[data-term-chip-text]').textContent = 'ภาคการศึกษา ' + label + ' (ปัจจุบัน)';
            termChip.hidden = false;
        }
    }) : Promise.resolve();

    if (!q('[data-tile-value]')) {
        return;
    }

    // ---------- ตัวเลขสรุป ----------
    Api.get('/programs').then(function (res) {
        if (!res.ok) {
            return;
        }
        var list = res.data.programs || [];
        var active = list.filter(function (p) { return p.status === 'active'; }).length;
        setTile('programs', list.length, 'ใช้งานอยู่ ' + active + ' หลักสูตร');
    });

    Promise.all([Api.get('/courses'), termReady]).then(function (r) {
        if (!r[0].ok) {
            return;
        }
        var total = (r[0].data.courses || []).length;
        setTile('courses', total, null);
        if (!activeTerm) {
            return;
        }
        Api.get('/course-offerings?academic_terms_id=' + encodeURIComponent(activeTerm.id_academic_terms)).then(function (res) {
            if (!res.ok) {
                return;
            }
            var ids = {};
            (res.data.course_offerings || []).forEach(function (o) { ids[o.courses_id] = true; });
            setTile('courses', total, 'เปิดสอนภาคนี้ ' + Object.keys(ids).length + ' วิชา');
        });
    });

    Api.get('/exams').then(function (res) {
        if (!res.ok) {
            return;
        }
        var list = res.data.exams || res.data.items || [];
        if (!Array.isArray(list)) {
            return;
        }
        var drafts = list.filter(function (e) { return e.status === 'draft'; }).length;
        setTile('exams', list.length, 'ฉบับร่าง ' + drafts + ' ชุด');
    });

    // ---------- กิจกรรมล่าสุด ----------
    var card = q('#activityCard');
    if (card && !card.querySelector('.dash-list')) {
        Api.get('/audit-logs?limit=5').then(function (res) {
            var logs = res.ok ? (res.data.logs || []) : [];
            if (!logs.length) {
                return;
            }
            var empty = card.querySelector('.empty');
            if (empty) {
                empty.remove();
            }
            var list = el('ul', 'dash-list dash-list--log');
            logs.slice(0, 5).forEach(function (l) {
                var li = el('li');
                li.appendChild(el('span', 'dash-list__time', timeLabel(l.created_at)));
                var text = el('span');
                text.appendChild(el('b', '', l.actor_name || 'ระบบ'));
                text.appendChild(document.createTextNode(' ' + (l.reason || l.action)));
                li.appendChild(text);
                list.appendChild(li);
            });
            card.appendChild(list);
        });
    }
})();
