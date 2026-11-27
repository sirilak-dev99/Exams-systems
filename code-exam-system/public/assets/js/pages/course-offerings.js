(function () {
    'use strict';

    var panel = document.getElementById('groupsPanel');
    if (!panel || !window.Api) {
        return;
    }

    // ---------- ค่าพื้นฐาน ----------
    var offeringId = panel.dataset.offering;
    var canManage  = panel.dataset.canManage === '1';
    var roleLabels = { coordinator: 'ผู้ประสานงาน', teacher: 'ผู้สอน', assistant: 'ผู้ช่วยสอน' };
    var sections = [];
    var staffBySection = {};
    var membersBySection = {};
    var people = {};
    var formerRole = {};

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

    function option(value, label) {
        var o = el('option', '', label);
        o.value = value;
        return o;
    }

    function setBusy(btn, busy) {
        btn.disabled = busy;
        if (busy) {
            btn.setAttribute('aria-busy', 'true');
        } else {
            btn.removeAttribute('aria-busy');
        }
    }

    function setAlert(box, message) {
        box.querySelector('b').textContent = message || '';
        box.hidden = !message;
    }

    function setFieldError(input, message) {
        var box = $(input.id + 'Error');
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
        if (box) {
            box.textContent = message || '';
            box.hidden = !message;
        }
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
        if (lastTrigger && document.body.contains(lastTrigger)) {
            lastTrigger.focus();
        }
    }

    document.addEventListener('keydown', function (e) {
        if (openModal && e.key === 'Escape') {
            hideModal();
        }
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-modal-close]') || (openModal && e.target === openModal)) {
            hideModal();
        }
    });

    // ---------- รายชื่อผู้สอนในระบบ ----------
    function loadPeople() {
        return Api.get('/course-offerings').then(function (res) {
            var offerings = res.ok ? (res.data.course_offerings || []) : [];
            return Promise.all(offerings.map(function (o) {
                return Api.get('/course-offerings/' + encodeURIComponent(o.id_course_offerings) + '/sections');
            }));
        }).then(function (results) {
            var ids = [];
            results.forEach(function (r) {
                (r.ok ? (r.data.sections || []) : []).forEach(function (s) { ids.push(s.id_sections); });
            });
            return Promise.all(ids.map(function (id) {
                return Api.get('/sections/' + encodeURIComponent(id) + '/staff');
            }));
        }).then(function (results) {
            results.forEach(function (r) {
                (r.ok ? (r.data.staff || []) : []).forEach(function (s) {
                    people[s.user_id] = s.display_name;
                });
            });
        });
    }

    // ---------- โหลดกลุ่มเรียน ----------
    function load() {
        return Api.get('/course-offerings/' + encodeURIComponent(offeringId) + '/sections').then(function (res) {
            if (!res.ok) {
                setAlert($('groupsError'), res.error.message);
                $('groupsTable').textContent = '';
                return;
            }
            sections = (res.data.sections || []).slice().sort(function (a, b) {
                return String(a.section_no).localeCompare(String(b.section_no), 'th', { numeric: true });
            });
            return Promise.all(sections.map(function (s) {
                return Promise.all([
                    Api.get('/sections/' + encodeURIComponent(s.id_sections) + '/staff'),
                    Api.get('/sections/' + encodeURIComponent(s.id_sections) + '/members')
                ]).then(function (r) {
                    staffBySection[s.id_sections] = r[0].ok ? (r[0].data.staff || []) : [];
                    membersBySection[s.id_sections] = r[1].ok ? (r[1].data.members || []) : [];
                    staffBySection[s.id_sections].forEach(function (p) { people[p.user_id] = p.display_name; });
                });
            })).then(function () {
                render();
                renderCoordinator();
            });
        });
    }

    // ---------- ผู้ประสานงาน ----------
    function allStaff() {
        var list = [];
        sections.forEach(function (s) {
            (staffBySection[s.id_sections] || []).forEach(function (p) { list.push(p); });
        });
        return list;
    }

    function renderCoordinator() {
        var select = $('coordSelect');
        var staff = allStaff();
        var names = {};
        staff.forEach(function (p) { names[p.user_id] = p.display_name; });
        var current = staff.filter(function (p) { return p.staff_role === 'coordinator'; })[0];

        select.textContent = '';
        select.appendChild(option('', Object.keys(names).length ? 'ยังไม่กำหนด' : 'ยังไม่มีผู้สอนในวิชานี้'));
        Object.keys(names).forEach(function (id) {
            select.appendChild(option(id, names[id]));
        });
        select.value = current ? String(current.user_id) : '';
        select.dataset.current = select.value;
        select.disabled = !canManage || !Object.keys(names).length;
    }

    $('coordSelect').addEventListener('change', function () {
        var select = $('coordSelect');
        var chosen = select.value;
        var calls = [];
        var staff = allStaff();
        var chosenRows = staff.filter(function (p) { return String(p.user_id) === chosen; });
        var keepRow = chosenRows.filter(function (p) { return p.staff_role === 'coordinator'; })[0] || chosenRows[0] || null;
        staff.forEach(function (p) {
            if (p === keepRow) {
                if (p.staff_role !== 'coordinator') {
                    formerRole[p.id_section_staff] = p.staff_role;
                    calls.push(Api.post('/section-staff/' + encodeURIComponent(p.id_section_staff), { staff_role: 'coordinator' }));
                }
            } else if (p.staff_role === 'coordinator' && String(p.user_id) !== chosen) {
                calls.push(Api.post('/section-staff/' + encodeURIComponent(p.id_section_staff), { staff_role: formerRole[p.id_section_staff] || 'teacher' }));
                delete formerRole[p.id_section_staff];
            }
        });
        $('coordError').hidden = true;
        select.disabled = true;
        Promise.all(calls).then(function (results) {
            var failed = results.filter(function (r) { return !r.ok; })[0];
            if (failed) {
                $('coordError').textContent = failed.error.message;
                $('coordError').hidden = false;
            }
            load();
        });
    });

    // ---------- ตารางกลุ่มเรียน ----------
    function staffChip(p) {
        var chip = el('span', 'person-chip' + (p.staff_role === 'coordinator' ? ' person-chip--lead' : ''));
        chip.appendChild(el('span', '', p.display_name));
        if (p.staff_role !== 'teacher') {
            chip.appendChild(el('small', '', roleLabels[p.staff_role] || p.staff_role));
        }
        if (canManage) {
            var x = el('button', 'person-chip__remove', '✕');
            x.type = 'button';
            x.setAttribute('aria-label', 'นำ ' + p.display_name + ' ออกจากกลุ่ม');
            x.addEventListener('click', function () {
                if (!window.confirm('นำ ' + p.display_name + ' ออกจากกลุ่มเรียนนี้?')) {
                    return;
                }
                setBusy(x, true);
                Api.remove('/section-staff/' + encodeURIComponent(p.id_section_staff)).then(function (res) {
                    if (!res.ok) {
                        setBusy(x, false);
                        setAlert($('groupsError'), res.error.message);
                        return;
                    }
                    load();
                });
            });
            chip.appendChild(x);
        }
        return chip;
    }

    function addStaffSelect(s) {
        var current = {};
        (staffBySection[s.id_sections] || []).forEach(function (p) { current[p.user_id] = true; });
        var select = el('select', 'select select--inline');
        select.setAttribute('aria-label', 'เพิ่มผู้สอนในกลุ่ม ' + s.section_no);
        select.appendChild(option('', '+ เพิ่มผู้สอน'));
        Object.keys(people).filter(function (id) { return !current[id]; })
            .sort(function (a, b) { return people[a].localeCompare(people[b], 'th'); })
            .forEach(function (id) { select.appendChild(option(id, people[id])); });
        select.disabled = select.options.length === 1;
        select.addEventListener('change', function () {
            if (!select.value) {
                return;
            }
            select.disabled = true;
            Api.post('/sections/' + encodeURIComponent(s.id_sections) + '/staff', { user_id: parseInt(select.value, 10), staff_role: 'teacher' }).then(function (res) {
                if (!res.ok) {
                    setAlert($('groupsError'), res.error.message);
                }
                load();
            });
        });
        return select;
    }

    function render() {
        var box = $('groupsTable');
        setAlert($('groupsError'), '');
        box.textContent = '';
        if (!sections.length) {
            var empty = el('div', 'empty');
            empty.appendChild(el('b', '', 'ยังไม่มีกลุ่มเรียน'));
            empty.appendChild(el('span', 'muted', canManage ? 'กด "+ เพิ่มกลุ่มเรียน" เพื่อเริ่ม' : 'รอเจ้าหน้าที่เพิ่มกลุ่มเรียน'));
            box.appendChild(empty);
            return;
        }

        var table = el('table', 'table');
        var head = el('tr');
        [['กลุ่มเรียน', ''], ['ผู้สอน', ''], ['นิสิต', 'num'], ['', '']].forEach(function (c, i) {
            var th = el('th', c[1], c[0]);
            th.scope = 'col';
            if (i === 3) {
                th.appendChild(el('span', 'sr-only', 'จัดการ'));
            }
            head.appendChild(th);
        });
        table.appendChild(el('thead')).appendChild(head);
        var body = table.appendChild(el('tbody'));

        sections.forEach(function (s) {
            var tr = el('tr');
            tr.appendChild(el('td', 'nowrap', 'กลุ่ม ' + s.section_no)).style.fontWeight = '700';

            var staffCell = el('td');
            var wrap = el('div', 'person-chips');
            (staffBySection[s.id_sections] || []).forEach(function (p) { wrap.appendChild(staffChip(p)); });
            if (canManage) {
                wrap.appendChild(addStaffSelect(s));
            } else if (!wrap.children.length) {
                wrap.appendChild(el('span', 'muted', 'ยังไม่มีผู้สอน'));
            }
            staffCell.appendChild(wrap);
            tr.appendChild(staffCell);

            var active = (membersBySection[s.id_sections] || []).filter(function (m) { return m.enrollment_status !== 'dropped'; });
            var memberCell = el('td', 'num nowrap');
            memberCell.appendChild(document.createTextNode(active.length + ' คน · '));
            var view = el('button', 'link-btn', 'ดูรายชื่อ');
            view.type = 'button';
            view.setAttribute('aria-label', 'ดูรายชื่อนิสิตกลุ่ม ' + s.section_no);
            view.addEventListener('click', function () { openMembers(view, s); });
            memberCell.appendChild(view);
            tr.appendChild(memberCell);

            var actions = el('td', 'nowrap num');
            if (canManage) {
                var edit = el('button', 'link-btn', 'แก้ไข');
                edit.type = 'button';
                edit.setAttribute('aria-label', 'แก้ไขกลุ่ม ' + s.section_no);
                edit.addEventListener('click', function () { openSection(edit, s); });
                var del = el('button', 'link-btn link-btn--danger', 'ลบกลุ่ม');
                del.type = 'button';
                del.setAttribute('aria-label', 'ลบกลุ่ม ' + s.section_no);
                del.addEventListener('click', function () { removeSection(del, s); });
                actions.appendChild(edit);
                actions.appendChild(del);
            }
            tr.appendChild(actions);
            body.appendChild(tr);
        });
        box.appendChild(el('div', 'table-wrap')).appendChild(table);
    }

    // ---------- เพิ่ม/แก้ไข/ลบกลุ่ม ----------
    var sectionForm  = $('sectionForm');
    var sectionError = $('sectionFormError');

    function openSection(trigger, s) {
        sectionForm.reset();
        setAlert(sectionError, '');
        setFieldError($('sectionNo'), '');
        sectionForm.elements.id.value = s ? s.id_sections : '';
        var title = $('sectionModalTitle');
        title.textContent = s ? title.dataset.titleEdit : title.dataset.titleNew;
        if (s) {
            $('sectionNo').value = s.section_no;
        } else {
            var max = sections.reduce(function (m, x) { return Math.max(m, parseInt(x.section_no, 10) || 0); }, 0);
            $('sectionNo').value = String(max + 1).padStart(2, '0');
        }
        showModal($('sectionModal'), trigger, $('sectionNo'));
    }

    if ($('sectionAddBtn')) {
        $('sectionAddBtn').addEventListener('click', function () { openSection($('sectionAddBtn'), null); });
    }

    sectionForm.addEventListener('submit', function (e) {
        e.preventDefault();
        setAlert(sectionError, '');
        setFieldError($('sectionNo'), '');
        var no = $('sectionNo').value.trim();
        if (no === '') {
            setFieldError($('sectionNo'), 'กรุณากรอกเลขกลุ่ม');
            $('sectionNo').focus();
            return;
        }
        var id = sectionForm.elements.id.value;
        var btn = sectionForm.querySelector('button[type="submit"]');
        setBusy(btn, true);
        var path = id ? '/sections/' + encodeURIComponent(id) : '/course-offerings/' + encodeURIComponent(offeringId) + '/sections';
        Api.post(path, { section_no: no }).then(function (res) {
            setBusy(btn, false);
            if (res.ok) {
                hideModal();
                load();
                return;
            }
            if (res.status === 409 || res.status === 422) {
                setFieldError($('sectionNo'), res.error.message);
                $('sectionNo').focus();
            } else {
                setAlert(sectionError, res.error.message);
            }
        });
    });

    function removeSection(btn, s) {
        if (!window.confirm('ลบกลุ่ม ' + s.section_no + '?')) {
            return;
        }
        setBusy(btn, true);
        Api.remove('/sections/' + encodeURIComponent(s.id_sections)).then(function (res) {
            if (!res.ok) {
                setBusy(btn, false);
                setAlert($('groupsError'), res.error.message);
                return;
            }
            load();
        });
    }

    // ---------- รายชื่อนิสิต ----------
    var memberStatus = { enrolled: 'กำลังเรียน', dropped: 'นำออกแล้ว', completed: 'เรียนจบแล้ว' };

    function renderMembers(s) {
        var box = $('membersList');
        var list = membersBySection[s.id_sections] || [];
        box.textContent = '';
        if (!list.length) {
            var empty = el('div', 'empty empty--sm');
            empty.appendChild(el('b', '', 'ยังไม่มีนิสิตในกลุ่มนี้'));
            box.appendChild(empty);
            return;
        }
        var table = el('table', 'table');
        var head = el('tr');
        ['รหัสนิสิต', 'ชื่อ-สกุล', 'สถานะ', ''].forEach(function (label, i) {
            var th = el('th', '', label);
            th.scope = 'col';
            if (i === 3) {
                th.appendChild(el('span', 'sr-only', 'จัดการ'));
            }
            head.appendChild(th);
        });
        table.appendChild(el('thead')).appendChild(head);
        var body = table.appendChild(el('tbody'));
        list.forEach(function (m) {
            var tr = el('tr');
            tr.appendChild(el('td', 'code nowrap', m.student_code || '—'));
            tr.appendChild(el('td', '', m.display_name));
            var st = el('td');
            st.appendChild(el('span', 'badge badge--' + (m.enrollment_status === 'enrolled' ? 'active' : 'archived'), memberStatus[m.enrollment_status] || m.enrollment_status));
            tr.appendChild(st);
            var act = el('td', 'num nowrap');
            if (canManage && m.enrollment_status !== 'dropped') {
                var drop = el('button', 'link-btn link-btn--danger', 'นำออกจากกลุ่ม');
                drop.type = 'button';
                drop.setAttribute('aria-label', 'นำ ' + m.display_name + ' ออกจากกลุ่ม');
                drop.addEventListener('click', function () {
                    if (!window.confirm('นำ ' + m.display_name + ' ออกจากกลุ่ม ' + s.section_no + '?\nประวัติและผลสอบเดิมยังอยู่')) {
                        return;
                    }
                    setBusy(drop, true);
                    Api.post('/course-members/' + encodeURIComponent(m.id_course_members) + '/drop', {}).then(function (res) {
                        setBusy(drop, false);
                        if (!res.ok) {
                            setAlert($('membersError'), res.error.message);
                            return;
                        }
                        load().then(function () { renderMembers(s); });
                    });
                });
                act.appendChild(drop);
            }
            tr.appendChild(act);
            body.appendChild(tr);
        });
        box.appendChild(el('div', 'table-wrap')).appendChild(table);
    }

    function openMembers(trigger, s) {
        setAlert($('membersError'), '');
        $('membersModalTitle').textContent = 'รายชื่อนิสิต กลุ่ม ' + s.section_no;
        var active = (membersBySection[s.id_sections] || []).filter(function (m) { return m.enrollment_status !== 'dropped'; });
        $('membersModalSub').textContent = 'กำลังเรียน ' + active.length + ' คน';
        renderMembers(s);
        showModal($('membersModal'), trigger, $('membersModal').querySelector('.modal__close'));
    }

    // ---------- เริ่มต้น ----------
    load().then(function () {
        if (canManage) {
            loadPeople().then(render);
        }
    });
})();
