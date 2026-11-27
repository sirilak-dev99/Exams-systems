(function () {
    'use strict';

    // ---------- องค์ประกอบ ----------
    var modal   = document.getElementById('programModal');
    var form    = document.getElementById('programForm');
    if (!modal || !form || !window.Api) {
        return;
    }
    var title     = document.getElementById('programModalTitle');
    var formError = document.getElementById('programFormError');
    var submitBtn = form.querySelector('button[type="submit"]');
    var fields    = { code: form.elements.code, name: form.elements.name, status: form.elements.status };
    var lastTrigger = null;

    // ---------- error ----------
    function setFieldError(name, message) {
        var input = fields[name];
        var box   = document.getElementById(input.id + 'Error');
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
        if (box) {
            box.textContent = message || '';
            box.hidden = !message;
        }
    }

    function setFormError(message) {
        formError.querySelector('b').textContent = message || '';
        formError.hidden = !message;
    }

    function clearErrors() {
        setFieldError('code', '');
        setFieldError('name', '');
        setFormError('');
    }

    // ---------- เปิด/ปิด ----------
    function openModal(trigger, program) {
        lastTrigger = trigger;
        clearErrors();
        form.reset();
        form.elements.id.value = program ? program.id : '';
        fields.code.value   = program ? program.code : '';
        fields.name.value   = program ? program.name : '';
        fields.status.value = program ? program.status : 'active';
        title.textContent   = program ? title.dataset.titleEdit : title.dataset.titleNew;

        modal.classList.add('is-open');
        document.body.classList.add('modal-open');
        fields.code.focus();
    }

    function closeModal() {
        if (submitBtn.getAttribute('aria-busy') === 'true') {
            return;
        }
        modal.classList.remove('is-open');
        document.body.classList.remove('modal-open');
        if (lastTrigger) {
            lastTrigger.focus();
        }
    }

    document.addEventListener('click', function (e) {
        var newBtn  = e.target.closest('[data-program-new]');
        var editBtn = e.target.closest('[data-program-edit]');
        if (newBtn) {
            openModal(newBtn, null);
        } else if (editBtn) {
            openModal(editBtn, {
                id: editBtn.dataset.id,
                code: editBtn.dataset.code,
                name: editBtn.dataset.name,
                status: editBtn.dataset.status
            });
        } else if (e.target.closest('[data-modal-close]') || e.target === modal) {
            closeModal();
        }
    });

    // ---------- คีย์บอร์ด ----------
    document.addEventListener('keydown', function (e) {
        if (!modal.classList.contains('is-open')) {
            return;
        }
        if (e.key === 'Escape') {
            closeModal();
            return;
        }
        if (e.key === 'Tab') {
            var items = modal.querySelectorAll('button, input:not([type="hidden"]), select');
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

    // ---------- ตรวจก่อนส่ง ----------
    function validate(values) {
        var ok = true;
        if (values.code === '') {
            setFieldError('code', 'กรุณากรอกรหัสหลักสูตร');
            ok = false;
        }
        if (values.name === '') {
            setFieldError('name', 'กรุณากรอกชื่อหลักสูตร');
            ok = false;
        }
        return ok;
    }

    // ---------- บันทึก ----------
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearErrors();

        var id = form.elements.id.value;
        var values = {
            code: fields.code.value.trim().toUpperCase(),
            name: fields.name.value.trim(),
            status: fields.status.value
        };
        if (!validate(values)) {
            (values.code === '' ? fields.code : fields.name).focus();
            return;
        }

        submitBtn.setAttribute('aria-busy', 'true');
        submitBtn.disabled = true;

        Api.post(id ? '/programs/' + encodeURIComponent(id) : '/programs', values).then(function (res) {
            if (res.ok) {
                window.location.reload();
                return;
            }

            submitBtn.removeAttribute('aria-busy');
            submitBtn.disabled = false;

            var code = res.error.code;
            if (code === 'DUPLICATE_CODE') {
                setFieldError('code', res.error.message);
                fields.code.focus();
            } else if (code === 'INVALID_STATUS') {
                setFormError(res.error.message);
                fields.status.focus();
            } else {
                setFormError(res.error.message);
            }
        });
    });
})();
