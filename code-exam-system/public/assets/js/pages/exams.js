(function () {
    'use strict';

    if (!window.Api) {
        return;
    }

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

    function setBusy(btn, busy) {
        btn.disabled = busy;
        if (busy) {
            btn.setAttribute('aria-busy', 'true');
        } else {
            btn.removeAttribute('aria-busy');
        }
    }

    function setFieldError(input, text) {
        var box = $(input.id + 'Error');
        input.setAttribute('aria-invalid', text ? 'true' : 'false');
        if (box) {
            box.textContent = text || '';
            box.hidden = !text;
        }
    }

    function setAlert(box, text) {
        box.querySelector('b').textContent = text || '';
        box.hidden = !text;
    }

    function toSqlDateTime(value) {
        return value ? value.replace('T', ' ') + ':00' : '';
    }

    // ---------- หน้าต่าง ----------
    var modal   = $('examModal');
    var form    = $('examForm');
    var alertEl = $('examFormError');
    var newBtn  = $('examNewBtn');

    function openModal() {
        modal.classList.add('is-open');
        document.body.classList.add('modal-open');
        $('eOffering').focus();
    }

    function closeModal() {
        if (form.querySelector('[aria-busy="true"]')) {
            return;
        }
        modal.classList.remove('is-open');
        document.body.classList.remove('modal-open');
        newBtn.focus();
    }

    newBtn.addEventListener('click', openModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal || e.target.closest('[data-modal-close]')) {
            closeModal();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (!modal.classList.contains('is-open')) {
            return;
        }
        if (e.key === 'Escape') {
            closeModal();
            return;
        }
        if (e.key === 'Tab') {
            var items = Array.prototype.filter.call(
                modal.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled])'),
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

    // ---------- รายวิชาที่เปิดสอน ----------
    var offeringSel = $('eOffering');

    Api.get('/course-offerings').then(function (res) {
        offeringSel.textContent = '';
        if (!res.ok) {
            offeringSel.appendChild(option('', 'โหลดรายวิชาไม่สำเร็จ'));
            setFieldError(offeringSel, res.error.message);
            return;
        }
        var list = res.data.course_offerings || [];
        var byId = {};
        list.forEach(function (o) { byId[o.id_course_offerings] = o; });

        document.querySelectorAll('[data-offering]').forEach(function (td) {
            var o = byId[td.dataset.offering];
            if (o) {
                td.textContent = o.course_code + ' ' + o.course_name;
                td.appendChild(el('small', 'muted block', 'ภาค ' + o.term_no + '/' + o.academic_year));
            }
        });

        var open = list.filter(function (o) { return o.status === 'open'; });
        offeringSel.appendChild(option('', open.length ? 'เลือกรายวิชา' : 'ยังไม่มีรายวิชาที่เปิดสอนอยู่'));
        open.forEach(function (o) {
            offeringSel.appendChild(option(o.id_course_offerings, o.course_code + ' ' + o.course_name + ' · ' + o.term_no + '/' + o.academic_year));
        });

        if (newBtn.dataset.open === '1') {
            openModal();
        }
    });

    // ---------- กลุ่มเรียน ----------
    var sectionBox  = $('eSections');
    var sectionList = $('eSectionList');

    offeringSel.addEventListener('change', function () {
        setFieldError(offeringSel, '');
        sectionList.textContent = '';
        sectionBox.hidden = true;
        var id = offeringSel.value;
        if (!id) {
            return;
        }
        Api.get('/course-offerings/' + encodeURIComponent(id) + '/sections').then(function (res) {
            if (offeringSel.value !== id || !res.ok) {
                return;
            }
            (res.data.sections || []).forEach(function (s) {
                var label = el('label', 'choice');
                var input = el('input');
                input.type = 'checkbox';
                input.name = 'section_ids';
                input.value = s.id_sections;
                input.checked = true;
                label.appendChild(input);
                label.appendChild(el('span', '', 'กลุ่ม ' + s.section_no));
                sectionList.appendChild(label);
            });
            sectionBox.hidden = sectionList.children.length === 0;
        });
    });

    // ---------- รหัสเข้าสอบ ----------
    $('eCode').addEventListener('change', function () {
        $('eTtlField').hidden = !this.checked;
        $('eResumeField').hidden = !this.checked;
    });

    // ---------- บันทึก ----------
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        form.querySelectorAll('[aria-invalid="true"]').forEach(function (n) { setFieldError(n, ''); });
        setAlert(alertEl, '');

        var duration = Number($('eDuration').value);
        var attempts = Number($('eAttempts').value);
        var values = {
            course_offerings_id: offeringSel.value,
            section_ids: Array.prototype.map.call(sectionList.querySelectorAll('input:checked'), function (n) { return Number(n.value); }),
            title: $('eTitle').value.trim(),
            kind: form.querySelector('input[name="kind"]:checked').value,
            mode: form.querySelector('input[name="mode"]:checked').value,
            duration_min: duration,
            max_attempts: attempts,
            grace_period_min: Math.max(0, Number($('eGrace').value) || 0),
            open_at: toSqlDateTime($('eOpen').value),
            close_at: toSqlDateTime($('eClose').value),
            show_answer: $('eShowAnswer').value,
            status: $('eStatus').value,
            shuffle_questions: $('eShuffle').checked ? 1 : 0,
            require_exam_code: $('eCode').checked ? 1 : 0,
            access_code_ttl_min: Math.max(1, Number($('eTtl').value) || 10),
            require_code_on_resume: $('eResume').checked ? 1 : 0,
            require_location_consent: $('eLocation').checked ? 1 : 0
        };

        var invalid = [];
        function fail(input, text) {
            setFieldError(input, text);
            invalid.push(input);
        }
        if (!values.course_offerings_id) {
            fail(offeringSel, 'กรุณาเลือกรายวิชา');
        }
        if (values.title === '') {
            fail($('eTitle'), 'กรุณากรอกชื่อชุดสอบ');
        }
        if (!values.open_at) {
            fail($('eOpen'), 'กรุณาเลือกเวลาเปิดสอบ');
        }
        if (!values.close_at) {
            fail($('eClose'), 'กรุณาเลือกเวลาปิดสอบ');
        } else if (values.open_at && values.close_at <= values.open_at) {
            fail($('eClose'), 'เวลาปิดสอบต้องหลังเวลาเปิดสอบ');
        }
        if (!Number.isInteger(duration) || duration < 1) {
            fail($('eDuration'), 'ต้องเป็นจำนวนเต็มตั้งแต่ 1 นาที');
        }
        if (!Number.isInteger(attempts) || attempts < 1) {
            fail($('eAttempts'), 'ต้องเป็นจำนวนเต็มตั้งแต่ 1 ครั้ง');
        }
        if (invalid.length) {
            invalid[0].focus();
            return;
        }

        var btn = form.querySelector('button[type="submit"]');
        setBusy(btn, true);
        Api.post('/exams', values).then(function (res) {
            if (res.ok) {
                window.location.href = Api.url('/exams');
                return;
            }
            setBusy(btn, false);
            if (res.error.code === 'INVALID_TIME_WINDOW') {
                setFieldError($('eClose'), res.error.message);
                $('eClose').focus();
            } else {
                setAlert(alertEl, res.error.message);
                alertEl.scrollIntoView({ block: 'nearest' });
            }
        });
    });
})();
