(function () {
    'use strict';

    if (!window.Api) {
        return;
    }

    // ---------- ข้อความ ----------
    var typeLabels   = { multiple_choice: 'ปรนัย', true_false: 'ถูก/ผิด', short_answer: 'ตอบสั้น', essay: 'อัตนัย', matching: 'จับคู่' };
    var bloomLabels  = { remember: 'จำ', understand: 'เข้าใจ', apply: 'นำไปใช้', analyze: 'วิเคราะห์', evaluate: 'ประเมินค่า', create: 'สร้างสรรค์' };
    var statusLabels = { draft: 'ฉบับร่าง', published: 'เผยแพร่', archived: 'เก็บถาวร' };
    var icons = {
        eye: '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        pencil: '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        trash: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>'
    };

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

    function svg(name) {
        var ns = 'http://www.w3.org/2000/svg';
        var s = document.createElementNS(ns, 'svg');
        s.setAttribute('width', '20');
        s.setAttribute('height', '20');
        s.setAttribute('viewBox', '0 0 24 24');
        s.setAttribute('fill', 'none');
        s.setAttribute('stroke', 'currentColor');
        s.setAttribute('stroke-width', '2');
        s.setAttribute('stroke-linecap', 'round');
        s.setAttribute('stroke-linejoin', 'round');
        s.setAttribute('aria-hidden', 'true');
        s.innerHTML = icons[name];
        return s;
    }

    function code(id) {
        return 'Q-' + String(id).padStart(4, '0');
    }

    function bloomKey(value) {
        return String(value || '').toLowerCase();
    }

    function score(q) {
        if (q.manual_max_score !== null && q.manual_max_score !== undefined && q.manual_max_score !== '') {
            return String(Number(q.manual_max_score));
        }
        return '1';
    }

    // ---------- สถานะ ----------
    var questions = [];
    var courses   = {};
    var chapters  = {};
    var scenarios = {};
    var rowsBox   = $('qbRows');
    var filters   = {
        course: $('qbCourse'),
        chapter: $('qbChapter'),
        type: $('qbType'),
        bloom: $('qbBloom'),
        status: $('qbStatus')
    };
    var search = $('qbSearch');

    function emptyRow(text, kind) {
        rowsBox.textContent = '';
        var tr = el('tr');
        var td = el('td');
        td.colSpan = 9;
        if (kind === 'error') {
            var alert = el('div', 'alert alert--danger');
            alert.setAttribute('role', 'alert');
            alert.appendChild(el('b', '', text));
            td.appendChild(alert);
        } else {
            var empty = el('div', 'empty');
            empty.appendChild(el('b', '', text));
            td.appendChild(empty);
        }
        tr.appendChild(td);
        rowsBox.appendChild(tr);
    }

    // ---------- สรุป ----------
    function renderStats() {
        var count = { all: questions.length, draft: 0, published: 0, archived: 0 };
        questions.forEach(function (q) { count[q.status] = (count[q.status] || 0) + 1; });
        document.querySelectorAll('[data-stat]').forEach(function (n) {
            n.textContent = (count[n.dataset.stat] || 0).toLocaleString('th-TH');
        });
    }

    // ---------- ตาราง ----------
    function matches(q) {
        var term = search.value.trim().toLowerCase();
        return (!filters.course.value || String(q.courses_id) === filters.course.value)
            && (!filters.chapter.value || String(q.chapters_id) === filters.chapter.value)
            && (!filters.type.value || q.type === filters.type.value)
            && (!filters.bloom.value || bloomKey(q.bloom_levels_code) === filters.bloom.value)
            && (!filters.status.value || q.status === filters.status.value)
            && (term === '' || String(q.text || '').toLowerCase().indexOf(term) !== -1 || code(q.id_questions).toLowerCase().indexOf(term) !== -1);
    }

    function render() {
        var list = questions.filter(matches);
        $('qbAll').checked = false;
        if (!list.length) {
            emptyRow(questions.length ? 'ไม่พบข้อสอบตามตัวกรอง' : 'ยังไม่มีข้อสอบในคลัง');
            $('qbFoot').textContent = '';
            return;
        }

        var scenarioCount = {};
        questions.forEach(function (q) {
            if (q.scenario_id) {
                scenarioCount[q.scenario_id] = (scenarioCount[q.scenario_id] || 0) + 1;
            }
        });

        rowsBox.textContent = '';
        list.forEach(function (q) {
            var tr = el('tr');

            var check = el('td', 'qb-table__check');
            var box = el('input');
            box.type = 'checkbox';
            box.value = q.id_questions;
            box.setAttribute('aria-label', 'เลือก ' + code(q.id_questions));
            check.appendChild(box);
            tr.appendChild(check);

            tr.appendChild(el('td', 'qb-code nowrap', code(q.id_questions)));

            var text = el('td', 'qb-table__text');
            if (q.scenario_id) {
                var sc = scenarios[q.scenario_id];
                var scCode = sc ? String(sc.title).split(' ')[0] : 'S-' + q.scenario_id;
                text.appendChild(el('span', 'qb-scenario', 'สถานการณ์ ' + scCode + ' · ' + (q.scenario_sort_order || '?') + '/' + scenarioCount[q.scenario_id]));
            }
            text.appendChild(el('span', 'qb-clamp', q.text));
            tr.appendChild(text);

            var where = el('td', 'qb-table__where');
            var course = courses[q.courses_id];
            var chapter = chapters[q.chapters_id];
            where.appendChild(el('b', '', course ? course.code : '—'));
            where.appendChild(el('small', '', chapter ? 'บทที่ ' + chapter.chapters_no + ' ' + chapter.name : 'ไม่ระบุบท'));
            tr.appendChild(where);

            var type = el('td');
            type.appendChild(el('span', 'qb-pill qb-pill--' + q.type, typeLabels[q.type] || q.type));
            tr.appendChild(type);

            var bloom = el('td');
            var bk = bloomKey(q.bloom_levels_code);
            bloom.appendChild(el('span', 'qb-pill qb-bloom--' + bk, bloomLabels[bk] || q.bloom_levels_code));
            tr.appendChild(bloom);

            tr.appendChild(el('td', 'num', score(q)));

            var status = el('td');
            status.appendChild(el('span', 'badge badge--' + q.status, statusLabels[q.status] || q.status));
            tr.appendChild(status);

            var actions = el('td', 'qb-actions');
            var view = el('button', 'qb-act');
            view.type = 'button';
            view.dataset.view = q.id_questions;
            view.setAttribute('aria-label', 'ดู ' + code(q.id_questions));
            view.appendChild(svg('eye'));
            var edit = el('button', 'qb-act');
            edit.type = 'button';
            edit.disabled = true;
            edit.title = 'แก้ไข · เร็วๆ นี้';
            edit.setAttribute('aria-label', 'แก้ไข ' + code(q.id_questions) + ' (เร็วๆ นี้)');
            edit.appendChild(svg('pencil'));
            var del = el('button', 'qb-act qb-act--danger');
            del.type = 'button';
            del.dataset.delete = q.id_questions;
            del.setAttribute('aria-label', 'ลบ ' + code(q.id_questions));
            del.appendChild(svg('trash'));
            actions.appendChild(view);
            actions.appendChild(edit);
            actions.appendChild(del);
            tr.appendChild(actions);

            rowsBox.appendChild(tr);
        });
        $('qbFoot').textContent = 'แสดง ' + list.length.toLocaleString('th-TH') + ' จาก ' + questions.length.toLocaleString('th-TH') + ' ข้อ';
    }

    // ---------- ตัวกรองรายวิชาและบท ----------
    function fillCourseFilter() {
        var used = {};
        questions.forEach(function (q) { if (q.courses_id) { used[q.courses_id] = true; } });
        Object.keys(used).map(function (id) { return courses[id]; }).filter(Boolean)
            .sort(function (a, b) { return String(a.code).localeCompare(String(b.code)); })
            .forEach(function (c) { filters.course.appendChild(option(c.id_courses, c.code + ' ' + c.name)); });
    }

    function fillChapterFilter() {
        var sel = filters.chapter;
        sel.textContent = '';
        sel.appendChild(option('', 'ทั้งหมด'));
        var courseId = filters.course.value;
        var list = Object.keys(chapters).map(function (id) { return chapters[id]; })
            .filter(function (c) { return String(c.courses_id) === courseId; })
            .sort(function (a, b) { return a.chapters_no - b.chapters_no; });
        list.forEach(function (c) { sel.appendChild(option(c.id_chapters, 'บทที่ ' + c.chapters_no + ' ' + c.name)); });
        sel.disabled = !courseId || list.length === 0;
    }

    // ---------- โหลดข้อมูล ----------
    Promise.all([Api.get('/questions'), Api.get('/courses'), Api.get('/question-scenarios')]).then(function (r) {
        if (!r[0].ok) {
            emptyRow(r[0].error.message, 'error');
            return;
        }
        questions = r[0].data.questions || [];
        (r[1].ok ? r[1].data.courses || [] : []).forEach(function (c) { courses[c.id_courses] = c; });
        (r[2].ok ? r[2].data.scenarios || [] : []).forEach(function (s) { scenarios[s.id_question_scenarios] = s; });

        var courseIds = {};
        questions.forEach(function (q) { if (q.courses_id) { courseIds[q.courses_id] = true; } });
        return Promise.all(Object.keys(courseIds).map(function (id) {
            return Api.get('/courses/' + encodeURIComponent(id) + '/chapters');
        })).then(function (list) {
            list.forEach(function (res) {
                (res.ok ? res.data.chapters || [] : []).forEach(function (c) { chapters[c.id_chapters] = c; });
            });
            fillCourseFilter();
            renderStats();
            render();
        });
    });

    // ---------- เหตุการณ์ ----------
    filters.course.addEventListener('change', function () {
        fillChapterFilter();
        render();
    });
    ['chapter', 'type', 'bloom', 'status'].forEach(function (k) { filters[k].addEventListener('change', render); });
    search.addEventListener('input', render);

    $('qbAll').addEventListener('change', function () {
        var on = this.checked;
        rowsBox.querySelectorAll('input[type="checkbox"]').forEach(function (b) { b.checked = on; });
    });

    // ---------- ดูข้อสอบ ----------
    var modal = $('qbModal');
    var lastTrigger = null;

    function closeModal() {
        modal.classList.remove('is-open');
        document.body.classList.remove('modal-open');
        if (lastTrigger) {
            lastTrigger.focus();
        }
    }

    function showQuestion(id, trigger) {
        lastTrigger = trigger;
        var body = $('qbModalBody');
        $('qbModalTitle').textContent = code(id);
        $('qbModalSub').textContent = '';
        body.textContent = '';
        body.appendChild(el('p', 'muted', 'กำลังโหลด…'));
        modal.classList.add('is-open');
        document.body.classList.add('modal-open');
        modal.querySelector('.modal__close').focus();

        Api.get('/questions/' + encodeURIComponent(id)).then(function (res) {
            body.textContent = '';
            if (!res.ok) {
                var alert = el('div', 'alert alert--danger');
                alert.appendChild(el('b', '', res.error.message));
                body.appendChild(alert);
                return;
            }
            var q = res.data.question;
            var course = courses[q.courses_id];
            var chapter = chapters[q.chapters_id];
            $('qbModalSub').textContent = [
                course ? course.code + ' ' + course.name : null,
                chapter ? 'บทที่ ' + chapter.chapters_no : null,
                res.data.version ? 'เวอร์ชัน ' + res.data.version.version_no : null
            ].filter(Boolean).join(' · ');

            var meta = el('div', 'qb-view__meta');
            meta.appendChild(el('span', 'qb-pill qb-pill--' + q.type, typeLabels[q.type] || q.type));
            meta.appendChild(el('span', 'qb-pill qb-bloom--' + bloomKey(q.bloom_levels_code), bloomLabels[bloomKey(q.bloom_levels_code)] || q.bloom_levels_code));
            meta.appendChild(el('span', 'badge badge--' + q.status, statusLabels[q.status] || q.status));
            meta.appendChild(el('span', 'muted', 'คะแนน ' + score(q)));
            body.appendChild(meta);
            body.appendChild(el('p', 'qb-view__text', q.text));

            var options = res.data.options || [];
            if (options.length) {
                var ol = el('ol', 'qb-view__options');
                options.forEach(function (o) {
                    var li = el('li', Number(o.is_correct) === 1 && q.type !== 'matching' ? 'is-correct' : '');
                    li.appendChild(el('b', '', o.label));
                    li.appendChild(el('span', '', o.text));
                    if (Number(o.is_correct) === 1 && q.type !== 'matching') {
                        li.appendChild(el('small', '', 'คำตอบที่ถูก'));
                    }
                    ol.appendChild(li);
                });
                body.appendChild(ol);
            }
            if (q.explanation) {
                var ex = el('div', 'alert');
                var wrap = el('div');
                wrap.appendChild(el('b', '', 'คำอธิบายเฉลย'));
                wrap.appendChild(el('span', '', q.explanation));
                ex.appendChild(wrap);
                body.appendChild(ex);
            }
        });
    }

    rowsBox.addEventListener('click', function (e) {
        var view = e.target.closest('[data-view]');
        if (view) {
            showQuestion(view.dataset.view, view);
            return;
        }
        var del = e.target.closest('[data-delete]');
        if (del) {
            var id = del.dataset.delete;
            if (!window.confirm('ลบข้อสอบ ' + code(id) + '?\nข้อสอบที่ใช้ในชุดสอบแล้วจะลบไม่ได้')) {
                return;
            }
            del.disabled = true;
            Api.remove('/questions/' + encodeURIComponent(id)).then(function (res) {
                if (!res.ok) {
                    del.disabled = false;
                    window.alert(res.error.message);
                    return;
                }
                questions = questions.filter(function (q) { return String(q.id_questions) !== String(id); });
                renderStats();
                render();
            });
        }
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal || e.target.closest('[data-modal-close]')) {
            closeModal();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
})();
