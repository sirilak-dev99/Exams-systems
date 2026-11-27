(function () {
    'use strict';

    if (!window.Api) {
        return;
    }

    // ---------- ข้อความ ----------
    var memberStatus = { enrolled: 'กำลังเรียน', dropped: 'นำออกแล้ว', completed: 'เรียนจบแล้ว' };
    var statusBadge  = { enrolled: 'active', dropped: 'archived', completed: 'published' };

    // ---------- ตัวช่วย ----------
    function $(id) {
        return document.getElementById(id);
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    }

    function option(value, label) {
        var o = el('option', '', label);
        o.value = value;
        return o;
    }

    function fillSelect(select, items, placeholder) {
        select.textContent = '';
        if (placeholder !== null) {
            select.appendChild(option('', placeholder));
        }
        items.forEach(function (i) { select.appendChild(option(i[0], i[1])); });
    }

    function thaiDate(value) {
        if (!value) {
            return '—';
        }
        var d = new Date(String(value).replace(' ', 'T'));
        return isNaN(d) ? value : d.toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function message(text, kind) {
        var box = $('studentList');
        box.textContent = '';
        if (kind === 'error') {
            var wrap  = el('div', 'card__body');
            var alert = el('div', 'alert alert--danger');
            alert.setAttribute('role', 'alert');
            alert.appendChild(el('b', '', text));
            wrap.appendChild(alert);
            box.appendChild(wrap);
            return;
        }
        var empty = el('div', 'empty');
        empty.appendChild(el('b', '', text));
        box.appendChild(empty);
    }

    // ---------- สถานะ ----------
    var term     = $('sTerm');
    var offering = $('sOffering');
    var section  = $('sSection');
    var search   = $('sSearch');
    var sections = [];
    var members  = [];
    var token    = 0;

    // ---------- ตาราง ----------
    function render() {
        if (!offering.value) {
            message('เลือกรายวิชาเพื่อดูรายชื่อนิสิต');
            return;
        }
        var q = search.value.trim().toLowerCase();
        var rows = members.filter(function (m) {
            return q === '' || (m.display_name || '').toLowerCase().indexOf(q) !== -1 || (m.student_code || '').indexOf(q) !== -1;
        });
        if (!rows.length) {
            message(members.length ? 'ไม่พบนิสิตตามคำค้น' : 'ยังไม่มีนิสิตในรายวิชานี้');
            return;
        }

        var sectionNo = {};
        sections.forEach(function (s) { sectionNo[s.id_sections] = s.section_no; });

        var wrap  = el('div', 'table-wrap');
        var table = el('table', 'table');
        var head  = el('tr');
        ['รหัสนิสิต', 'ชื่อ-นามสกุล', 'อีเมล', 'กลุ่ม', 'สถานะ', 'ลงทะเบียนเมื่อ'].forEach(function (label) {
            var th = el('th', '', label);
            th.scope = 'col';
            head.appendChild(th);
        });
        table.appendChild(el('thead')).appendChild(head);
        var body = table.appendChild(el('tbody'));
        rows.forEach(function (m) {
            var tr = el('tr');
            tr.appendChild(el('td', 'code nowrap', m.student_code || '—'));
            tr.appendChild(el('td', '', m.display_name));
            tr.appendChild(el('td', '', m.email || '—'));
            tr.appendChild(el('td', 'nowrap', sectionNo[m.section_id] || '—'));
            var st = el('td');
            st.appendChild(el('span', 'badge badge--' + (statusBadge[m.enrollment_status] || 'archived'), memberStatus[m.enrollment_status] || m.enrollment_status));
            tr.appendChild(st);
            tr.appendChild(el('td', 'nowrap', thaiDate(m.enrolled_at)));
            body.appendChild(tr);
        });
        wrap.appendChild(table);

        var box = $('studentList');
        box.textContent = '';
        box.appendChild(wrap);
        box.appendChild(el('div', 'table-foot', 'ทั้งหมด ' + rows.length + ' คน'));
    }

    // ---------- โหลดข้อมูล ----------
    function loadMembers() {
        var mine = ++token;
        var ids  = section.value ? [section.value] : sections.map(function (s) { return String(s.id_sections); });
        members = [];
        if (!ids.length) {
            message('รายวิชานี้ยังไม่มีกลุ่มเรียน');
            return;
        }
        message('กำลังโหลด…');
        Promise.all(ids.map(function (id) { return Api.get('/sections/' + encodeURIComponent(id) + '/members'); })).then(function (list) {
            if (mine !== token) {
                return;
            }
            var failed = list.filter(function (r) { return !r.ok; })[0];
            if (failed) {
                message(failed.error.message, 'error');
                return;
            }
            list.forEach(function (r) { members = members.concat(r.data.members || []); });
            members.sort(function (a, b) { return String(a.student_code || '').localeCompare(String(b.student_code || '')); });
            render();
        });
    }

    function loadSections() {
        var mine = ++token;
        sections = [];
        members  = [];
        fillSelect(section, [], 'ทุกกลุ่ม');
        section.disabled = true;
        if (!offering.value) {
            render();
            return;
        }
        message('กำลังโหลด…');
        Api.get('/course-offerings/' + encodeURIComponent(offering.value) + '/sections').then(function (res) {
            if (mine !== token) {
                return;
            }
            if (!res.ok) {
                message(res.error.message, 'error');
                return;
            }
            sections = res.data.sections || [];
            fillSelect(section, sections.map(function (s) { return [s.id_sections, 'กลุ่ม ' + s.section_no]; }), 'ทุกกลุ่ม');
            section.disabled = sections.length === 0;
            loadMembers();
        });
    }

    function loadOfferings() {
        var mine = ++token;
        sections = [];
        members  = [];
        fillSelect(offering, [], term.value ? 'กำลังโหลด…' : 'เลือกภาคการศึกษาก่อน');
        offering.disabled = true;
        fillSelect(section, [], 'ทุกกลุ่ม');
        section.disabled = true;
        render();
        if (!term.value) {
            return;
        }
        Api.get('/course-offerings?academic_terms_id=' + encodeURIComponent(term.value)).then(function (res) {
            if (mine !== token) {
                return;
            }
            if (!res.ok) {
                fillSelect(offering, [], 'โหลดรายวิชาไม่สำเร็จ');
                message(res.error.message, 'error');
                return;
            }
            var list = res.data.course_offerings || [];
            fillSelect(offering, list.map(function (o) { return [o.id_course_offerings, o.course_code + ' ' + o.course_name]; }), list.length ? 'เลือกรายวิชา' : 'ภาคนี้ยังไม่มีรายวิชาที่เปิดสอน');
            offering.disabled = list.length === 0;
        });
    }

    Api.get('/academic-terms').then(function (res) {
        if (!res.ok) {
            fillSelect(term, [], 'โหลดภาคการศึกษาไม่สำเร็จ');
            message(res.error.message, 'error');
            return;
        }
        var terms = (res.data.academic_terms || []).slice().sort(function (a, b) {
            return (b.academic_year - a.academic_year) || (b.term_no - a.term_no);
        });
        if (!terms.length) {
            fillSelect(term, [], 'ยังไม่มีภาคการศึกษา');
            return;
        }
        fillSelect(term, terms.map(function (t) { return [t.id_academic_terms, t.term_no + '/' + t.academic_year]; }), null);
        var active = terms.filter(function (t) { return t.status === 'active'; })[0] || terms[0];
        term.value = String(active.id_academic_terms);
        loadOfferings();
    });

    // ---------- เหตุการณ์ ----------
    term.addEventListener('change', loadOfferings);
    offering.addEventListener('change', loadSections);
    section.addEventListener('change', loadMembers);
    search.addEventListener('input', render);
})();
