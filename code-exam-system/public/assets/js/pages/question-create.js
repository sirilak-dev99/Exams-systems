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

    // ---------- สถานะ ----------
    var form       = $('questionForm');
    var courseSel  = $('qCourse');
    var chapterSel = $('qChapter');
    var list       = $('qOptions');
    var labels     = 'กขคงจฉชซฌญ';
    var withOptions = { multiple_choice: true, true_false: true, matching: true };

    function currentType() {
        return form.querySelector('input[name="type"]:checked').value;
    }

    // ---------- รายวิชาและบทเรียน ----------
    Api.get('/courses').then(function (res) {
        courseSel.textContent = '';
        if (!res.ok) {
            courseSel.appendChild(option('', 'โหลดรายวิชาไม่สำเร็จ'));
            setFieldError(courseSel, res.error.message);
            return;
        }
        var courses = (res.data.courses || []).slice().sort(function (a, b) { return String(a.code).localeCompare(String(b.code)); });
        courseSel.appendChild(option('', courses.length ? 'เลือกรายวิชา' : 'ยังไม่มีรายวิชา'));
        courses.forEach(function (c) { courseSel.appendChild(option(c.id_courses, c.code + ' ' + c.name)); });
    });

    courseSel.addEventListener('change', function () {
        setFieldError(courseSel, '');
        var id = courseSel.value;
        chapterSel.textContent = '';
        chapterSel.disabled = true;
        if (!id) {
            chapterSel.appendChild(option('', 'เลือกรายวิชาก่อน'));
            return;
        }
        chapterSel.appendChild(option('', 'กำลังโหลด…'));
        Api.get('/courses/' + encodeURIComponent(id) + '/chapters').then(function (res) {
            if (courseSel.value !== id) {
                return;
            }
            chapterSel.textContent = '';
            var chapters = res.ok ? (res.data.chapters || []).filter(function (c) { return c.status === 'active'; }) : [];
            chapterSel.appendChild(option('', chapters.length ? 'ไม่ระบุบทเรียน' : 'รายวิชานี้ยังไม่มีบทเรียน'));
            chapters.forEach(function (c) { chapterSel.appendChild(option(c.id_chapters, 'บทที่ ' + c.chapters_no + ' ' + c.name)); });
            chapterSel.disabled = chapters.length === 0;
        });
    });

    // ---------- ตัวเลือก ----------
    function relabel() {
        Array.prototype.forEach.call(list.children, function (li, i) {
            var label = labels.charAt(i) || String(i + 1);
            li.querySelector('.option-row__label').textContent = label;
            li.querySelector('input[type="text"]').setAttribute('aria-label', 'ตัวเลือก ' + label);
            var mark = li.querySelector('.option-row__mark input');
            mark.setAttribute('aria-label', 'ตัวเลือก ' + label + ' เป็นคำตอบที่ถูก');
            li.querySelector('.option-row__remove').setAttribute('aria-label', 'ลบตัวเลือก ' + label);
        });
        var type = currentType();
        var fixed = type === 'true_false';
        list.querySelectorAll('.option-row__remove').forEach(function (b) { b.hidden = fixed || list.children.length <= 2; });
        $('qOptionAdd').hidden = fixed || list.children.length >= labels.length;
    }

    function addOption(text, correct) {
        var type = currentType();
        var li   = el('li', 'option-row');
        var mark = el('label', 'option-row__mark');
        var box  = el('input');
        box.type = type === 'multiple_choice' ? 'checkbox' : 'radio';
        box.name = 'correct';
        box.checked = !!correct;
        mark.appendChild(box);
        mark.hidden = type === 'matching';
        li.appendChild(mark);
        li.appendChild(el('span', 'option-row__label'));
        var input = el('input', 'input');
        input.type = 'text';
        input.value = text || '';
        input.readOnly = type === 'true_false';
        input.placeholder = type === 'matching' ? 'เช่น หัวใจ → สูบฉีดเลือด' : 'ข้อความตัวเลือก';
        li.appendChild(input);
        var remove = el('button', 'link-btn link-btn--danger option-row__remove', 'ลบ');
        remove.type = 'button';
        li.appendChild(remove);
        list.appendChild(li);
        relabel();
        return input;
    }

    function resetOptions() {
        var type = currentType();
        list.textContent = '';
        setAlert($('qOptionsError'), '');
        if (type === 'true_false') {
            addOption('ถูก', true);
            addOption('ผิด', false);
        } else if (withOptions[type]) {
            for (var i = 0; i < 4; i++) {
                addOption('', false);
            }
        }
        var hints = {
            multiple_choice: 'ทำเครื่องหมายที่คำตอบที่ถูก เลือกได้มากกว่า 1 ข้อ',
            true_false: 'เลือกว่าข้อความในโจทย์ถูกหรือผิด',
            matching: 'พิมพ์คู่ที่ถูกต้องในแต่ละบรรทัด ระบบสลับคู่ให้ตอนสอบ'
        };
        $('qOptionsHint').textContent = hints[type] || '';
        $('qOptionsCard').hidden = !withOptions[type];
        $('qManualCard').hidden = !!withOptions[type];
        $('qScoringField').hidden = type !== 'multiple_choice';
        $('qShuffleField').hidden = !withOptions[type] || type === 'true_false';
    }

    list.addEventListener('click', function (e) {
        var btn = e.target.closest('.option-row__remove');
        if (btn) {
            btn.closest('li').remove();
            relabel();
            var first = list.querySelector('input[type="text"]');
            if (first) {
                first.focus();
            }
        }
    });

    $('qOptionAdd').addEventListener('click', function () {
        addOption('', false).focus();
    });

    form.addEventListener('change', function (e) {
        if (e.target.name === 'type') {
            resetOptions();
        } else if (e.target.name === 'bloom') {
            setFieldError($('qBloom'), '');
        }
    });

    resetOptions();

    // ---------- บันทึก ----------
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        form.querySelectorAll('[aria-invalid="true"]').forEach(function (n) { setFieldError(n, ''); });
        setAlert($('questionFormError'), '');
        setAlert($('qOptionsError'), '');

        var type  = currentType();
        var bloom = form.querySelector('input[name="bloom"]:checked');
        var rows  = Array.prototype.map.call(list.children, function (li, i) {
            return {
                label: li.querySelector('.option-row__label').textContent,
                text: li.querySelector('input[type="text"]').value.trim(),
                is_correct: type === 'matching' ? 1 : (li.querySelector('.option-row__mark input').checked ? 1 : 0),
                sort_order: i + 1
            };
        });
        var invalid = [];

        if (!courseSel.value) {
            setFieldError(courseSel, 'กรุณาเลือกรายวิชา');
            invalid.push(courseSel);
        }
        if (!bloom) {
            setFieldError($('qBloom'), 'กรุณาเลือกระดับ Bloom');
            invalid.push(form.querySelector('input[name="bloom"]'));
        }
        if ($('qText').value.trim() === '') {
            setFieldError($('qText'), 'กรุณาพิมพ์โจทย์');
            invalid.push($('qText'));
        }
        if (withOptions[type]) {
            var filled = rows.filter(function (r) { return r.text !== ''; });
            var problem = '';
            if (filled.length < 2) {
                problem = 'ต้องมีตัวเลือกที่กรอกข้อความอย่างน้อย 2 ตัวเลือก';
            } else if (filled.length !== rows.length) {
                problem = 'มีตัวเลือกที่ยังว่าง กรอกข้อความหรือลบตัวเลือกนั้นออก';
            } else if (type !== 'matching' && !rows.some(function (r) { return r.is_correct; })) {
                problem = 'กรุณาทำเครื่องหมายคำตอบที่ถูกอย่างน้อย 1 ข้อ';
            }
            if (problem) {
                setAlert($('qOptionsError'), problem);
                invalid.push(list.querySelector('input') || $('qOptionAdd'));
            }
        }
        var maxScore = $('qMaxScore').value.trim();
        if (!withOptions[type] && maxScore !== '' && !(Number(maxScore) >= 0)) {
            setFieldError($('qMaxScore'), 'คะแนนเต็มต้องเป็นตัวเลขตั้งแต่ 0');
            invalid.push($('qMaxScore'));
        }
        if (invalid.length) {
            invalid[0].focus();
            return;
        }

        var values = {
            courses_id: courseSel.value,
            chapters_id: chapterSel.value || null,
            bloom_levels_code: bloom.value,
            type: type,
            text: $('qText').value.trim(),
            explanation: $('qExplain').value.trim(),
            options: withOptions[type] ? rows : [],
            manual_max_score: withOptions[type] ? null : (maxScore === '' ? null : Number(maxScore)),
            scoring_logic: type === 'essay' ? 'manual' : (type === 'multiple_choice' ? $('qScoring').value : 'all_or_nothing'),
            shuffle_options: withOptions[type] && type !== 'true_false' && $('qShuffle').checked ? 1 : 0,
            student_visible: $('qVisible').checked ? 1 : 0,
            status: form.querySelector('input[name="status"]:checked').value
        };

        var btn = form.querySelector('button[type="submit"]');
        setBusy(btn, true);
        Api.post('/questions', values).then(function (res) {
            setBusy(btn, false);
            if (!res.ok) {
                setAlert($('questionFormError'), res.error.message);
                $('questionFormError').scrollIntoView({ block: 'center' });
                return;
            }
            var keepCourse  = courseSel.value;
            var keepChapter = chapterSel.value;
            form.reset();
            courseSel.value  = keepCourse;
            chapterSel.value = keepChapter;
            resetOptions();
            var done = $('questionFormDone');
            if (!done) {
                done = el('div', 'alert alert--success');
                done.id = 'questionFormDone';
                done.setAttribute('role', 'status');
                done.appendChild(el('b'));
                form.insertBefore(done, form.firstChild);
            }
            done.querySelector('b').textContent = 'บันทึกคำถามแล้ว เขียนข้อต่อไปได้เลย';
            done.hidden = false;
            done.scrollIntoView({ block: 'center' });
            $('qText').focus({ preventScroll: true });
        });
    });
})();
