(function () {
    'use strict';

    if (!window.Api) {
        return;
    }

    // ---------- ข้อความ ----------
    var statusLabels = { active: 'ใช้งาน', inactive: 'ปิดใช้งาน', archived: 'จัดเก็บแล้ว' };

    // ---------- ตัวช่วย ----------
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
        var box = document.getElementById(input.id + 'Error');
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

    // ---------- หน้าต่าง ----------
    var openModal = null;
    var lastTrigger = null;
    var onClose = null;

    function showModal(modal, trigger, focusTarget, closeHandler) {
        openModal = modal;
        lastTrigger = trigger;
        onClose = closeHandler || null;
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
        if (onClose) {
            onClose();
        }
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
            var items = openModal.querySelectorAll('button:not([disabled]), input:not([type="hidden"]), select');
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

    // ---------- ฟอร์มหลักสูตร ----------
    var programModal = document.getElementById('programModal');
    var programForm  = document.getElementById('programForm');
    var programTitle = document.getElementById('programModalTitle');
    var programError = document.getElementById('programFormError');
    var pf = programForm ? programForm.elements : null;

    function openProgram(trigger, program) {
        programForm.reset();
        setFieldError(pf.code, '');
        setFieldError(pf.name, '');
        setAlert(programError, '');
        pf.id.value     = program ? program.id : '';
        pf.code.value   = program ? program.code : '';
        pf.name.value   = program ? program.name : '';
        pf.status.value = program ? program.status : 'active';
        programTitle.textContent = program ? programTitle.dataset.titleEdit : programTitle.dataset.titleNew;
        showModal(programModal, trigger, pf.code);
    }

    if (programForm) {
        programForm.addEventListener('submit', function (e) {
            e.preventDefault();
            setFieldError(pf.code, '');
            setFieldError(pf.name, '');
            setAlert(programError, '');

            var id = pf.id.value;
            var values = {
                code: pf.code.value.trim().toUpperCase(),
                name: pf.name.value.trim(),
                status: pf.status.value
            };
            if (values.code === '') {
                setFieldError(pf.code, 'กรุณากรอกรหัสหลักสูตร');
            }
            if (values.name === '') {
                setFieldError(pf.name, 'กรุณากรอกชื่อหลักสูตร');
            }
            if (values.code === '' || values.name === '') {
                (values.code === '' ? pf.code : pf.name).focus();
                return;
            }

            var submitBtn = programForm.querySelector('button[type="submit"]');
            setBusy(submitBtn, true);
            Api.post(id ? '/programs/' + encodeURIComponent(id) : '/programs', values).then(function (res) {
                if (res.ok) {
                    window.location.reload();
                    return;
                }
                setBusy(submitBtn, false);
                if (res.error.code === 'DUPLICATE_CODE') {
                    setFieldError(pf.code, res.error.message);
                    pf.code.focus();
                } else {
                    setAlert(programError, res.error.message);
                }
            });
        });
    }

    // ---------- ฉบับหลักสูตร ----------
    var versionModal = document.getElementById('versionModal');
    var versionForm  = document.getElementById('versionForm');
    var versionList  = document.getElementById('versionList');
    var versionSub   = document.getElementById('versionModalSub');
    var versionError = document.getElementById('versionFormError');
    var vf = versionForm ? versionForm.elements : null;
    var currentProgramId = null;
    var versionsChanged = false;

    function renderVersions(versions) {
        versionList.textContent = '';
        if (!versions.length) {
            var empty = el('div', 'empty');
            empty.appendChild(el('b', '', 'ยังไม่มีฉบับหลักสูตร'));
            versionList.appendChild(empty);
            return;
        }

        var wrap  = el('div', 'table-wrap');
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

        wrap.appendChild(table);
        versionList.appendChild(wrap);
    }

    function nextVersionNo(versions) {
        return versions.reduce(function (max, v) {
            return Math.max(max, parseInt(v.version_no, 10) || 0);
        }, 0) + 1;
    }

    function loadVersions() {
        versionList.textContent = '';
        versionList.appendChild(el('p', 'muted', 'กำลังโหลด…'));
        return Api.get('/programs/' + encodeURIComponent(currentProgramId) + '/versions').then(function (res) {
            if (!res.ok) {
                versionList.textContent = '';
                var alert = el('div', 'alert alert--danger');
                alert.setAttribute('role', 'alert');
                alert.appendChild(el('b', '', res.error.message));
                versionList.appendChild(alert);
                return;
            }
            var versions = res.data.versions || [];
            renderVersions(versions);
            vf.version_no.value = nextVersionNo(versions);
        });
    }

    function openVersions(trigger) {
        currentProgramId = trigger.dataset.id;
        versionsChanged = false;
        versionForm.reset();
        setFieldError(vf.version_no, '');
        setFieldError(vf.effective_to, '');
        setAlert(versionError, '');
        versionSub.textContent = trigger.dataset.code + ' · ' + trigger.dataset.name;
        showModal(versionModal, trigger, versionModal.querySelector('.modal__close'), function () {
            if (versionsChanged) {
                window.location.reload();
            }
        });
        loadVersions();
    }

    if (versionForm) {
        versionForm.addEventListener('submit', function (e) {
            e.preventDefault();
            setFieldError(vf.version_no, '');
            setFieldError(vf.effective_to, '');
            setAlert(versionError, '');

            var values = {
                version_no: parseInt(vf.version_no.value, 10),
                status: vf.status.value,
                effective_from: vf.effective_from.value,
                effective_to: vf.effective_to.value
            };
            if (!(values.version_no >= 1)) {
                setFieldError(vf.version_no, 'กรุณาระบุฉบับที่เป็นตัวเลขตั้งแต่ 1 ขึ้นไป');
                vf.version_no.focus();
                return;
            }
            if (values.effective_from && values.effective_to && values.effective_to < values.effective_from) {
                setFieldError(vf.effective_to, 'วันสิ้นสุดต้องไม่ก่อนวันเริ่มใช้');
                vf.effective_to.focus();
                return;
            }

            var submitBtn = versionForm.querySelector('button[type="submit"]');
            setBusy(submitBtn, true);
            Api.post('/programs/' + encodeURIComponent(currentProgramId) + '/versions', values).then(function (res) {
                setBusy(submitBtn, false);
                if (res.ok) {
                    versionsChanged = true;
                    versionForm.reset();
                    loadVersions().then(function () { vf.version_no.focus(); });
                    return;
                }
                if (res.error.code === 'DUPLICATE_VERSION') {
                    setFieldError(vf.version_no, 'ฉบับที่ ' + values.version_no + ' มีอยู่แล้วในหลักสูตรนี้');
                    vf.version_no.focus();
                } else if (res.error.code === 'INVALID_DATE_RANGE') {
                    setFieldError(vf.effective_to, 'วันสิ้นสุดต้องไม่ก่อนวันเริ่มใช้');
                    vf.effective_to.focus();
                } else {
                    setAlert(versionError, res.error.message);
                }
            });
        });
    }

    // ---------- ปุ่ม ----------
    document.addEventListener('click', function (e) {
        var newBtn     = e.target.closest('[data-program-new]');
        var editBtn    = e.target.closest('[data-program-edit]');
        var versionBtn = e.target.closest('[data-program-versions]');
        if (newBtn && programModal) {
            openProgram(newBtn, null);
        } else if (editBtn && programModal) {
            openProgram(editBtn, {
                id: editBtn.dataset.id,
                code: editBtn.dataset.code,
                name: editBtn.dataset.name,
                status: editBtn.dataset.status
            });
        } else if (versionBtn && versionModal) {
            openVersions(versionBtn);
        } else if (e.target.closest('[data-modal-close]') || (openModal && e.target === openModal)) {
            hideModal();
        }
    });
})();
