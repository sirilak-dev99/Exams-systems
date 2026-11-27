(function () {
    'use strict';

    if (!window.Api) {
        return;
    }

    // ---------- ข้อความ ----------
    var statusLabels = { active: 'ใช้งาน', inactive: 'ปิดใช้งาน', archived: 'จัดเก็บแล้ว' };

    // ---------- ตัวช่วย ----------
    function $(id) {
        return document.getElementById(id);
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

    function thaiDate(value) {
        if (!value) {
            return '—';
        }
        var d = new Date(value + 'T00:00:00');
        return isNaN(d) ? value : d.toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function setBusy(btn, busy) {
        btn.disabled = busy;
        if (busy) {
            btn.setAttribute('aria-busy', 'true');
        } else {
            btn.removeAttribute('aria-busy');
        }
    }

    function setFieldError(input, message) {
        var box = $(input.id + 'Error');
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
        if (box) {
            box.textContent = message || '';
            box.hidden = !message;
        }
    }

    function setAlert(box, message) {
        box.querySelector('b').textContent = message || '';
        box.hidden = !message;
    }

    function clearForm(form, alertBox) {
        form.querySelectorAll('[aria-invalid]').forEach(function (input) {
            setFieldError(input, '');
        });
        setAlert(alertBox, '');
    }

    function messageBox(container, text, kind) {
        container.textContent = '';
        if (kind === 'error') {
            var alert = el('div', 'alert alert--danger');
            alert.setAttribute('role', 'alert');
            alert.appendChild(el('b', '', text));
            container.appendChild(alert);
        } else {
            var empty = el('div', 'empty');
            empty.appendChild(el('b', '', text));
            container.appendChild(empty);
        }
    }

    function option(value, label) {
        var o = el('option', '', label);
        o.value = value;
        return o;
    }

    // ---------- หน้าต่าง ----------
    var openModal = null;
    var lastTrigger = null;

    function showModal(modal, trigger, focusTarget) {
        openModal = modal;
        lastTrigger = trigger;
        modal.classList.add('is-open');
        document.body.classList.add('modal-open');
        (focusTarget || modal.querySelector('input, select, button')).focus();
    }

    function hideModal() {
        if (!openModal || openModal.querySelector('[aria-busy="true"]')) {
            return;
        }
        openModal.classList.remove('is-open');
        document.body.classList.remove('modal-open');
        openModal = null;
        if (lastTrigger) {
            lastTrigger.focus();
        }
    }

    document.addEventListener('keydown', function (e) {
        if (!openModal) {
            return;
        }
        if (e.key === 'Escape') {
            hideModal();
            return;
        }
        if (e.key === 'Tab') {
            var items = Array.prototype.filter.call(
                openModal.querySelectorAll('button:not([disabled]), input:not([type="hidden"]), select'),
                function (n) { return n.offsetParent !== null; }
            );
            var first = items[0];
            var last  = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-modal-close]') || (openModal && e.target === openModal)) {
            hideModal();
        }
    });

    // ---------- เพิ่มหลักสูตร ----------
    var programModal = $('programModal');
    var programForm  = $('programForm');
    var programError = $('programFormError');

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-program-new]');
        if (btn && programModal) {
            programForm.reset();
            clearForm(programForm, programError);
            showModal(programModal, btn, $('pName'));
        }
    });

    if (programForm) {
        programForm.addEventListener('submit', function (e) {
            e.preventDefault();
            clearForm(programForm, programError);
            var values = {
                code: $('pCode').value.trim().toUpperCase(),
                name: $('pName').value.trim(),
                status: $('pStatus').value
            };
            if (values.name === '') {
                setFieldError($('pName'), 'กรุณากรอกชื่อหลักสูตร');
            }
            if (values.code === '') {
                setFieldError($('pCode'), 'กรุณากรอกรหัสหลักสูตร');
            }
            if (values.name === '' || values.code === '') {
                (values.name === '' ? $('pName') : $('pCode')).focus();
                return;
            }

            var btn = programForm.querySelector('button[type="submit"]');
            setBusy(btn, true);
            Api.post('/programs', values).then(function (res) {
                if (res.ok) {
                    window.location.href = Api.url('/programs?program=' + res.data.program.id_programs);
                    return;
                }
                setBusy(btn, false);
                if (res.error.code === 'DUPLICATE_CODE') {
                    setFieldError($('pCode'), res.error.message);
                    $('pCode').focus();
                } else {
                    setAlert(programError, res.error.message);
                }
            });
        });
    }

    // ---------- หลักสูตรที่เลือก ----------
    var panel = $('programPanel');
    if (!panel) {
        return;
    }
    var programId = panel.dataset.id;

    // ---------- แท็บ ----------
    var tabs = Array.prototype.slice.call(panel.querySelectorAll('[role="tab"]'));

    function selectTab(tab) {
        tabs.forEach(function (t) {
            var on = t === tab;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.tabIndex = on ? 0 : -1;
            $(t.getAttribute('aria-controls')).hidden = !on;
        });
        tab.focus();
    }

    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () { selectTab(tab); });
        tab.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                e.preventDefault();
                selectTab(tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length]);
            }
        });
    });

    // ---------- ตั้งค่าหลักสูตร ----------
    var settingsForm  = $('settingsForm');
    var settingsError = $('settingsFormError');

    settingsForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearForm(settingsForm, settingsError);
        var values = {
            code: $('sCode').value.trim().toUpperCase(),
            name: $('sName').value.trim(),
            status: $('sStatus').value
        };
        if (values.name === '') {
            setFieldError($('sName'), 'กรุณากรอกชื่อหลักสูตร');
        }
        if (values.code === '') {
            setFieldError($('sCode'), 'กรุณากรอกรหัสหลักสูตร');
        }
        if (values.name === '' || values.code === '') {
            (values.name === '' ? $('sName') : $('sCode')).focus();
            return;
        }

        var btn = settingsForm.querySelector('button[type="submit"]');
        setBusy(btn, true);
        Api.post('/programs/' + encodeURIComponent(programId), values).then(function (res) {
            if (res.ok) {
                window.location.reload();
                return;
            }
            setBusy(btn, false);
            if (res.error.code === 'DUPLICATE_CODE') {
                setFieldError($('sCode'), res.error.message);
                $('sCode').focus();
            } else {
                setAlert(settingsError, res.error.message);
            }
        });
    });

    // ---------- ฉบับหลักสูตร ----------
    var versionForm  = $('versionForm');
    var versionError = $('versionFormError');
    var versions = [];

    function renderVersions() {
        var list = $('versionList');
        if (!versions.length) {
            messageBox(list, 'ยังไม่มีฉบับหลักสูตร');
            return;
        }
        list.textContent = '';
        var table = el('table', 'table');
        var head  = el('tr');
        ['ฉบับที่', 'เริ่มใช้', 'สิ้นสุด', 'สถานะ'].forEach(function (label) {
            var th = el('th', '', label);
            th.scope = 'col';
            head.appendChild(th);
        });
        table.appendChild(el('thead')).appendChild(head);
        var body = table.appendChild(el('tbody'));
        versions.forEach(function (v) {
            var tr = el('tr');
            tr.appendChild(el('td', 'nowrap', 'ฉบับที่ ' + v.version_no));
            tr.appendChild(el('td', 'nowrap', thaiDate(v.effective_from)));
            tr.appendChild(el('td', 'nowrap', thaiDate(v.effective_to)));
            var td = el('td');
            td.appendChild(el('span', 'badge badge--' + v.status, statusLabels[v.status] || v.status));
            tr.appendChild(td);
            body.appendChild(tr);
        });
        list.appendChild(el('div', 'table-wrap')).appendChild(table);
    }

    function currentVersion() {
        var id = $('sVersion').value;
        return versions.filter(function (v) { return String(v.id_program_versions) === id; })[0] || null;
    }

    function showProgramSub() {
        var v = currentVersion();
        $('programSub').textContent = 'รหัส ' + panel.dataset.code
            + (v ? ' · ฉบับที่ ' + v.version_no : ' · ยังไม่มีฉบับหลักสูตร')
            + ' · ' + (statusLabels[panel.dataset.status] || panel.dataset.status);
    }

    function fillVersionSelect(keepId) {
        var select = $('sVersion');
        var current = versions.filter(function (v) { return String(v.id_program_versions) === String(keepId); })[0]
            || versions.filter(function (v) { return v.status === 'active'; })[0]
            || versions[0];
        select.textContent = '';
        if (!versions.length) {
            select.appendChild(option('', 'ยังไม่มีฉบับ'));
        }
        versions.forEach(function (v) {
            var o = option(v.id_program_versions, 'ฉบับที่ ' + v.version_no + ' · ' + (statusLabels[v.status] || v.status));
            o.defaultSelected = v === current;
            select.appendChild(o);
        });
        select.disabled = !versions.length;
        if (current) {
            select.value = current.id_program_versions;
        }
        showProgramSub();
    }

    function rememberVersion() {
        var v = currentVersion();
        var params = new URLSearchParams(window.location.search);
        if (v) {
            params.set('version', v.id_program_versions);
        } else {
            params.delete('version');
        }
        window.history.replaceState(null, '', window.location.pathname + '?' + params.toString());
    }

    function loadVersions(keepId) {
        return Api.get('/programs/' + encodeURIComponent(programId) + '/versions').then(function (res) {
            if (!res.ok) {
                messageBox($('courseTable'), res.error.message, 'error');
                return false;
            }
            versions = res.data.versions || [];
            renderVersions();
            fillVersionSelect(keepId);
            $('vNo').value = versions.reduce(function (max, v) {
                return Math.max(max, parseInt(v.version_no, 10) || 0);
            }, 0) + 1;
            return true;
        });
    }

    $('versionAddBtn').addEventListener('click', function () {
        var next = $('vNo').value;
        versionForm.reset();
        clearForm(versionForm, versionError);
        $('vNo').value = next;
        showModal($('versionModal'), $('versionAddBtn'), $('vNo'));
    });

    versionForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearForm(versionForm, versionError);
        var values = {
            version_no: parseInt($('vNo').value, 10),
            status: $('vStatus').value,
            effective_from: $('vFrom').value,
            effective_to: $('vTo').value
        };
        if (!(values.version_no >= 1)) {
            setFieldError($('vNo'), 'กรุณาระบุฉบับที่เป็นตัวเลขตั้งแต่ 1 ขึ้นไป');
            $('vNo').focus();
            return;
        }
        if (values.effective_from && values.effective_to && values.effective_to < values.effective_from) {
            setFieldError($('vTo'), 'วันสิ้นสุดต้องไม่ก่อนวันเริ่มใช้');
            $('vTo').focus();
            return;
        }

        var btn = versionForm.querySelector('button[type="submit"]');
        setBusy(btn, true);
        Api.post('/programs/' + encodeURIComponent(programId) + '/versions', values).then(function (res) {
            setBusy(btn, false);
            if (res.ok) {
                hideModal();
                loadVersions(res.data.id_program_versions).then(function () {
                    rememberVersion();
                    loadCourses();
                });
                return;
            }
            if (res.error.code === 'DUPLICATE_VERSION') {
                setFieldError($('vNo'), 'ฉบับที่ ' + values.version_no + ' มีอยู่แล้วในหลักสูตรนี้');
                $('vNo').focus();
            } else if (res.error.code === 'INVALID_DATE_RANGE') {
                setFieldError($('vTo'), 'วันสิ้นสุดต้องไม่ก่อนวันเริ่มใช้');
                $('vTo').focus();
            } else {
                setAlert(versionError, res.error.message);
            }
        });
    });

    // ---------- รายวิชา ----------
    var allCourses = [];
    var courseById = {};
    var groups = null;
    var bindings = [];
    var questionCount = null;
    var offeringByBinding = {};
    var selectedTerm = null;

    function loadCatalog() {
        return Promise.all([Api.get('/courses'), Api.get('/course-groups'), Api.get('/questions')]).then(function (r) {
            allCourses = r[0].ok ? (r[0].data.courses || []) : [];
            courseById = {};
            allCourses.forEach(function (c) { courseById[c.id_courses] = c; });
            groups = r[1].ok ? (r[1].data.course_groups || []) : null;
            questionCount = null;
            if (r[2].ok) {
                questionCount = {};
                (r[2].data.questions || []).forEach(function (q) {
                    questionCount[q.courses_id] = (questionCount[q.courses_id] || 0) + 1;
                });
            }
        });
    }

    // ---------- ภาคการศึกษา ----------
    var termStatusLabels = { active: 'ภาคปัจจุบัน', upcoming: 'ภาคถัดไป', inactive: 'สิ้นสุดแล้ว', archived: 'สิ้นสุดแล้ว' };
    var termOrder = { active: 0, upcoming: 1, inactive: 2, archived: 2 };

    function loadOfferings() {
        offeringByBinding = {};
        if (!selectedTerm) {
            return Promise.resolve();
        }
        return Api.get('/course-offerings?academic_terms_id=' + encodeURIComponent(selectedTerm)).then(function (res) {
            (res.ok ? (res.data.course_offerings || []) : []).forEach(function (o) {
                offeringByBinding[o.program_version_courses_id] = o;
            });
        });
    }

    function syncTermLinks() {
        document.querySelectorAll('[data-program-card]').forEach(function (a) {
            var url = new URL(a.href);
            if (selectedTerm) {
                url.searchParams.set('term', selectedTerm);
            } else {
                url.searchParams.delete('term');
            }
            a.href = url.toString();
        });
        var params = new URLSearchParams(window.location.search);
        if (selectedTerm) {
            params.set('term', selectedTerm);
        }
        window.history.replaceState(null, '', window.location.pathname + '?' + params.toString());
    }

    function selectTerm(id) {
        selectedTerm = id;
        $('termPills').querySelectorAll('[role="radio"]').forEach(function (b) {
            var on = b.dataset.id === String(id);
            b.setAttribute('aria-checked', on ? 'true' : 'false');
            b.tabIndex = on ? 0 : -1;
        });
        syncTermLinks();
    }

    function loadTerms() {
        var box = $('termPills');
        return Api.get('/academic-terms').then(function (res) {
            box.textContent = '';
            var terms = res.ok ? (res.data.academic_terms || []) : [];
            if (!terms.length) {
                box.appendChild(el('span', 'muted', res.ok ? 'ยังไม่มีภาคการศึกษา' : res.error.message));
                return;
            }
            terms.sort(function (a, b) {
                return (termOrder[a.status] - termOrder[b.status])
                    || (b.academic_year - a.academic_year)
                    || (b.term_no - a.term_no);
            });
            terms.forEach(function (t) {
                var btn = el('button', 'pill', t.term_no + '/' + t.academic_year + ' · ' + (termStatusLabels[t.status] || t.status));
                btn.type = 'button';
                btn.setAttribute('role', 'radio');
                btn.dataset.id = t.id_academic_terms;
                btn.addEventListener('click', function () {
                    selectTerm(t.id_academic_terms);
                    loadOfferings().then(renderCourses);
                });
                box.appendChild(btn);
            });
            var wanted = box.dataset.term;
            var initial = terms.filter(function (t) { return String(t.id_academic_terms) === wanted; })[0]
                || terms.filter(function (t) { return t.status === 'active'; })[0]
                || terms[0];
            selectTerm(initial.id_academic_terms);
        });
    }

    $('termPills').addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') {
            return;
        }
        var radios = Array.prototype.slice.call($('termPills').querySelectorAll('[role="radio"]'));
        var i = radios.indexOf(document.activeElement);
        if (i === -1) {
            return;
        }
        e.preventDefault();
        var next = radios[(i + (e.key === 'ArrowRight' ? 1 : radios.length - 1)) % radios.length];
        next.click();
        next.focus();
    });

    // ---------- การ์ดหลักสูตร ----------
    function pickVersion(list) {
        return list.filter(function (v) { return v.status === 'active'; })[0] || list[0] || null;
    }

    function fillProgramCards() {
        document.querySelectorAll('[data-program-card]').forEach(function (card) {
            var id = card.dataset.programCard;
            var label = statusLabels[card.dataset.status] || card.dataset.status;
            Api.get('/programs/' + encodeURIComponent(id) + '/versions').then(function (res) {
                var v = res.ok ? pickVersion(res.data.versions || []) : null;
                if (!v) {
                    card.querySelector('small').textContent = 'ยังไม่มีฉบับ · ' + label;
                    return;
                }
                Api.get('/program-versions/' + encodeURIComponent(v.id_program_versions) + '/courses').then(function (r) {
                    var n = r.ok ? (r.data.courses || []).length : null;
                    card.querySelector('small').textContent = 'ฉบับที่ ' + v.version_no + ' · ' + label + (n === null ? '' : ' · ' + n + ' วิชา');
                });
            });
        });
    }

    function yearSemLabel(b) {
        var parts = [];
        if (b.year_level) {
            parts.push('ปี ' + b.year_level);
        }
        if (b.semester_no) {
            parts.push('ภาค ' + b.semester_no);
        }
        return parts.length ? parts.join(' · ') : '—';
    }

    function renderCourses() {
        var year = $('courseYear').value;
        var q = $('courseSearch').value.trim().toLowerCase();
        var rows = bindings.filter(function (b) {
            if (year !== '' && String(b.year_level || '') !== year) {
                return false;
            }
            return q === '' || (b.course_code + ' ' + b.course_name).toLowerCase().indexOf(q) !== -1;
        });

        $('courseTabCount').textContent = '(' + bindings.length + ')';
        $('courseCount').textContent = 'แสดง ' + rows.length + ' จาก ' + bindings.length + ' รายวิชา';

        var box = $('courseTable');
        if (!rows.length) {
            messageBox(box, bindings.length ? 'ไม่พบรายวิชาที่ตรงกับเงื่อนไข' : 'ยังไม่มีรายวิชาในฉบับนี้');
            return;
        }

        box.textContent = '';
        var table = el('table', 'table');
        var head  = el('tr');
        [['รายวิชา', ''], ['ชั้นปี · ภาค', ''], ['ผู้ประสานงาน', ''], ['กลุ่มเรียน', 'center'], ['ข้อสอบ', 'num'], ['', '']].forEach(function (col, i) {
            var th = el('th', col[1], col[0]);
            th.scope = 'col';
            if (i === 5) {
                th.appendChild(el('span', 'sr-only', 'จัดการ'));
            }
            head.appendChild(th);
        });
        table.appendChild(el('thead')).appendChild(head);

        var body = table.appendChild(el('tbody'));
        rows.forEach(function (b) {
            var course = courseById[b.courses_id] || {};
            var offering = offeringByBinding[b.id_program_version_courses] || null;
            var tr = el('tr');

            var nameCell = el('td', 'course-cell');
            nameCell.appendChild(el('b', '', b.course_name));
            nameCell.appendChild(el('small', '', b.course_code + (course.course_group_name ? ' · ' + course.course_group_name : '')));
            tr.appendChild(nameCell);
            tr.appendChild(el('td', 'nowrap', yearSemLabel(b)));

            var coordCell = el('td', '', offering ? 'กำลังโหลด…' : '—');
            if (offering) {
                coordCell.dataset.offering = offering.id_course_offerings;
            }
            tr.appendChild(coordCell);
            tr.appendChild(el('td', 'center', offering ? String(offering.sections_count) : '—'));
            tr.appendChild(el('td', 'num nowrap', questionCount === null ? '—' : (questionCount[b.courses_id] || 0) + ' ข้อ'));

            var actions = el('td', 'nowrap num');
            var groupsLink = el('a', 'btn btn--secondary btn--sm', 'กลุ่มเรียน · ผู้สอน');
            groupsLink.href = Api.url('/course-offerings?' + new URLSearchParams({
                courses_id: b.courses_id,
                academic_terms_id: selectedTerm || ''
            }).toString());
            groupsLink.setAttribute('aria-label', 'กลุ่มเรียนและผู้สอนของ ' + b.course_name);
            var edit = el('button', 'link-btn', 'แก้ไข');
            edit.type = 'button';
            edit.setAttribute('aria-label', 'แก้ไข ' + b.course_name);
            edit.addEventListener('click', function () { openCourse(edit, b); });
            actions.appendChild(groupsLink);
            actions.appendChild(edit);
            tr.appendChild(actions);

            body.appendChild(tr);
        });
        box.appendChild(el('div', 'table-wrap')).appendChild(table);
        fillCoordinators();
    }

    // ---------- ผู้ประสานงาน ----------
    var coordinatorCache = {};

    function loadCoordinator(offeringId) {
        if (!coordinatorCache[offeringId]) {
            coordinatorCache[offeringId] = Api.get('/course-offerings/' + encodeURIComponent(offeringId) + '/sections').then(function (res) {
                var sections = res.ok ? (res.data.sections || []) : [];
                return Promise.all(sections.map(function (s) {
                    return Api.get('/sections/' + encodeURIComponent(s.id_sections) + '/staff');
                }));
            }).then(function (results) {
                var staff = [];
                results.forEach(function (r) {
                    if (r.ok) {
                        staff = staff.concat(r.data.staff || []);
                    }
                });
                var pick = staff.filter(function (s) { return s.staff_role === 'coordinator'; })[0]
                    || staff.filter(function (s) { return s.staff_role === 'teacher'; })[0];
                return pick ? pick.display_name : '—';
            });
        }
        return coordinatorCache[offeringId];
    }

    function fillCoordinators() {
        $('courseTable').querySelectorAll('td[data-offering]').forEach(function (td) {
            loadCoordinator(td.dataset.offering).then(function (name) {
                td.textContent = name;
            });
        });
    }

    function fillYearFilter() {
        var select = $('courseYear');
        var keep = select.value;
        var years = {};
        bindings.forEach(function (b) {
            if (b.year_level) {
                years[b.year_level] = true;
            }
        });
        select.textContent = '';
        select.appendChild(option('', 'ทุกชั้นปี'));
        Object.keys(years).sort(function (a, b) { return a - b; }).forEach(function (y) {
            select.appendChild(option(y, 'ปี ' + y));
        });
        select.value = years[keep] ? keep : '';
    }

    function loadCourses() {
        var pvId = $('sVersion').value;
        var box = $('courseTable');
        if (!versions.length || !pvId) {
            bindings = [];
            $('courseAddBtn').disabled = true;
            $('courseTabCount').textContent = '(0)';
            $('courseCount').textContent = '';
            messageBox(box, 'ยังไม่มีฉบับหลักสูตร · เพิ่มฉบับที่แท็บ "ตั้งค่าหลักสูตร" ก่อน จึงจะเพิ่มรายวิชาได้');
            return Promise.resolve();
        }
        box.textContent = '';
        box.appendChild(el('p', 'muted', 'กำลังโหลด…'));
        return Api.get('/program-versions/' + encodeURIComponent(pvId) + '/courses').then(function (res) {
            if (!res.ok) {
                messageBox(box, res.error.message, 'error');
                return;
            }
            bindings = res.data.courses || [];
            $('courseAddBtn').disabled = false;
            fillYearFilter();
            renderCourses();
        });
    }

    $('sVersion').addEventListener('change', function () {
        showProgramSub();
        rememberVersion();
        loadCourses();
    });

    settingsForm.addEventListener('reset', function () {
        clearForm(settingsForm, settingsError);
        window.setTimeout(function () {
            showProgramSub();
            rememberVersion();
            loadCourses();
        }, 0);
    });
    $('courseYear').addEventListener('change', renderCourses);
    $('courseSearch').addEventListener('input', renderCourses);

    // ---------- ฟอร์มรายวิชา ----------
    var courseModal = $('courseModal');
    var courseForm  = $('courseForm');
    var courseError = $('courseFormError');
    var cf = courseForm.elements;

    function currentMode() {
        var checked = courseForm.querySelector('input[name="mode"]:checked');
        return checked ? checked.value : 'new';
    }

    function applyMode() {
        var editing = cf.binding_id.value !== '';
        var mode = editing ? 'new' : currentMode();
        $('courseModeGroup').hidden = editing || !groups;
        $('courseExistingField').hidden = mode !== 'existing';
        $('courseNewFields').hidden = mode === 'existing';
        $('courseGroupField').hidden = mode === 'existing';
        $('courseRemoveBtn').hidden = !editing;
    }

    $('courseRemoveBtn').addEventListener('click', function () {
        var b = bindings.filter(function (x) { return String(x.id_program_version_courses) === cf.binding_id.value; })[0];
        if (!b || !window.confirm('นำ "' + b.course_code + ' ' + b.course_name + '" ออกจากฉบับหลักสูตรนี้?\nรายวิชายังอยู่ในระบบ')) {
            return;
        }
        var btn = $('courseRemoveBtn');
        setBusy(btn, true);
        Api.remove('/program-version-courses/' + encodeURIComponent(b.id_program_version_courses)).then(function (res) {
            setBusy(btn, false);
            if (res.ok) {
                hideModal();
                loadCourses();
                fillProgramCards();
                return;
            }
            setAlert(courseError, res.error.message);
        });
    });

    courseForm.addEventListener('change', function (e) {
        if (e.target.name === 'mode') {
            applyMode();
        }
    });

    function fillGroupSelect(selected) {
        var select = $('cGroup');
        select.textContent = '';
        select.appendChild(option('', 'เลือกกลุ่มวิชา'));
        (groups || []).forEach(function (g) {
            select.appendChild(option(g.id_course_groups, g.name));
        });
        select.value = selected || '';
    }

    function fillExistingSelect() {
        var bound = {};
        bindings.forEach(function (b) { bound[b.courses_id] = true; });
        var select = $('cExisting');
        select.textContent = '';
        select.appendChild(option('', 'เลือกรายวิชา'));
        allCourses.filter(function (c) { return !bound[c.id_courses]; })
            .sort(function (a, b) { return String(a.code).localeCompare(String(b.code)); })
            .forEach(function (c) { select.appendChild(option(c.id_courses, c.code + ' ' + c.name)); });
    }

    function openCourse(trigger, binding) {
        courseForm.reset();
        clearForm(courseForm, courseError);
        var version = currentVersion();
        $('courseModalSub').textContent = panel.dataset.name + (version ? ' · ฉบับที่ ' + version.version_no : '');
        cf.binding_id.value = binding ? binding.id_program_version_courses : '';
        cf.course_id.value  = binding ? binding.courses_id : '';
        $('courseModalTitle').textContent = binding ? 'แก้ไขรายวิชา' : 'เพิ่มรายวิชา';

        var course = binding ? (courseById[binding.courses_id] || {}) : {};
        fillGroupSelect(course.course_groups_id);
        fillExistingSelect();
        if (!groups && !binding) {
            courseForm.querySelector('input[name="mode"][value="existing"]').checked = true;
        }
        $('cCode').value = binding ? binding.course_code : '';
        $('cName').value = binding ? binding.course_name : '';
        $('cYear').value = binding && binding.year_level ? binding.year_level : '';
        $('cSemester').value = binding && binding.semester_no ? binding.semester_no : '';
        applyMode();
        showModal(courseModal, trigger, currentMode() === 'existing' && !binding ? $('cExisting') : $('cCode'));
    }

    $('courseAddBtn').addEventListener('click', function () {
        openCourse($('courseAddBtn'), null);
    });

    function bindingValues() {
        return {
            year_level: $('cYear').value === '' ? null : parseInt($('cYear').value, 10),
            semester_no: $('cSemester').value === '' ? null : parseInt($('cSemester').value, 10)
        };
    }

    function showCourseError(res) {
        var code = res.error.code;
        if (code === 'DUPLICATE_COURSE_CODE') {
            setFieldError($('cCode'), res.error.message);
            $('cCode').focus();
        } else if (code === 'DUPLICATE_BINDING') {
            setFieldError($('cExisting'), res.error.message);
            $('cExisting').focus();
        } else if (code === 'GROUP_NOT_FOUND') {
            setFieldError($('cGroup'), res.error.message);
            $('cGroup').focus();
        } else {
            setAlert(courseError, res.error.message);
        }
    }

    function validateCourseFields() {
        var ok = true;
        [['cCode', 'กรุณากรอกรหัสวิชา'], ['cName', 'กรุณากรอกชื่อวิชา'], ['cGroup', 'กรุณาเลือกกลุ่มวิชา']].forEach(function (f) {
            var input = $(f[0]);
            if (input.value.trim() === '') {
                setFieldError(input, f[1]);
                if (ok) {
                    input.focus();
                }
                ok = false;
            }
        });
        return ok;
    }

    courseForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearForm(courseForm, courseError);

        var pvId = $('sVersion').value;
        var bindingId = cf.binding_id.value;
        var mode = bindingId ? 'edit' : currentMode();
        var courseValues = {
            code: $('cCode').value.trim().toUpperCase(),
            name: $('cName').value.trim(),
            course_groups_id: $('cGroup').value === '' ? null : parseInt($('cGroup').value, 10)
        };

        if (mode === 'existing' && $('cExisting').value === '') {
            setFieldError($('cExisting'), 'กรุณาเลือกรายวิชา');
            $('cExisting').focus();
            return;
        }
        if (mode !== 'existing' && !validateCourseFields()) {
            return;
        }

        var btn = courseForm.querySelector('button[type="submit"]');
        setBusy(btn, true);

        var done = function (res) {
            setBusy(btn, false);
            if (res.ok) {
                hideModal();
                loadCatalog().then(loadCourses);
                fillProgramCards();
                return;
            }
            showCourseError(res);
        };

        if (mode === 'edit') {
            Api.post('/courses/' + encodeURIComponent(cf.course_id.value), courseValues).then(function (res) {
                if (!res.ok) {
                    return done(res);
                }
                Api.post('/program-version-courses/' + encodeURIComponent(bindingId), bindingValues()).then(done);
            });
        } else if (mode === 'existing') {
            var payload = bindingValues();
            payload.courses_id = parseInt($('cExisting').value, 10);
            Api.post('/program-versions/' + encodeURIComponent(pvId) + '/courses', payload).then(done);
        } else {
            Api.post('/courses', courseValues).then(function (res) {
                if (!res.ok) {
                    return done(res);
                }
                var payload = bindingValues();
                payload.courses_id = res.data.course.id_courses;
                Api.post('/program-versions/' + encodeURIComponent(pvId) + '/courses', payload).then(function (bind) {
                    if (!bind.ok) {
                        bind.error.message = 'สร้างรายวิชาแล้ว แต่ยังผูกกับหลักสูตรไม่ได้ · ' + bind.error.message;
                    }
                    done(bind);
                });
            });
        }
    });

    // ---------- ลบหลักสูตร ----------
    $('programDeleteBtn').addEventListener('click', function () {
        var btn = $('programDeleteBtn');
        var box = $('deleteError');
        box.hidden = true;
        if (!window.confirm('ลบหลักสูตร "' + panel.dataset.name + '"?\nลบแล้วกู้คืนไม่ได้')) {
            return;
        }
        setBusy(btn, true);
        Api.remove('/programs/' + encodeURIComponent(programId)).then(function (res) {
            if (res.ok) {
                window.location.href = Api.url('/programs');
                return;
            }
            setBusy(btn, false);
            box.textContent = res.error.code === 'PROGRAM_IN_USE'
                ? 'ลบไม่ได้เพราะหลักสูตรนี้มีฉบับหลักสูตรแล้ว · ให้เปลี่ยนสถานะเป็น "ปิดใช้งาน" หรือ "จัดเก็บแล้ว" แทน'
                : res.error.message;
            box.hidden = false;
        });
    });

    // ---------- เริ่มต้น ----------
    Promise.all([
        loadVersions(new URLSearchParams(window.location.search).get('version')),
        loadCatalog(),
        loadTerms().then(loadOfferings)
    ]).then(function (r) {
        if (r[0]) {
            loadCourses();
        }
    });
    fillProgramCards();
})();
